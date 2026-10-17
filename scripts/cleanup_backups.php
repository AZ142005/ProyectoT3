<?php
/**
 * Script independiente de limpieza del respaldo general (ciclo único).
 *
 * Aplica las MISMAS reglas que DataRetentionService::limpiarRespaldoGeneralVencido():
 *  - elimina el archivo físico (.sql.gz + .sha256) de las filas de backups_log
 *    que fueron descargadas hace más de LIMPIEZA_DIAS_TRAS_DESCARGA (7) días,
 *    anotando la fila («archivo eliminado tras descarga…»).
 *
 * Las filas de backups_log NUNCA se borran: quedan como historial con nota.
 * Sin dependencias del autoloader ni del servicio: conexión con el mismo
 * patrón de scripts/backup_database.php (config + Database). Si la BD no está
 * disponible NO se borra nada (jamás por antigüedad de archivo).
 *
 * Uso CLI: php scripts/cleanup_backups.php [--dry-run]
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

use App\Core\Database;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$diasDescarga = 7;

$backupDir = dirname(__DIR__) . '/storage/backups';
if (!is_dir($backupDir)) {
    echo "[" . date('Y-m-d H:i:s') . "] Limpieza de respaldos: directorio inexistente, nada que hacer.\n";
    return;
}

try {
    $db = Database::getConnection();
    $ahora = (string)$db->query('SELECT NOW()')->fetchColumn();
    $corte = (new DateTime($ahora))->sub(new DateInterval('P' . $diasDescarga . 'D'))->format('Y-m-d H:i:s');

    $stmt = $db->prepare("
        SELECT id, nombre_archivo, notas
        FROM backups_log
        WHERE fecha_descarga IS NOT NULL AND fecha_descarga < :corte
        ORDER BY id ASC
    ");
    $stmt->execute(['corte' => $corte]);

    $purgados = 0;
    foreach ($stmt as $fila) {
        $archivo = basename((string)$fila['nombre_archivo']);
        $ruta = $backupDir . '/' . $archivo;
        if (!is_file($ruta)) {
            continue; // ya eliminado (reemplazo diario u otra corrida)
        }

        if (!$dryRun) {
            if (!@unlink($ruta)) {
                continue; // se reintenta en la próxima corrida
            }
            $rutaSha = $ruta . '.sha256';
            if (is_file($rutaSha)) {
                @unlink($rutaSha);
            }
            $nota = trim((string)($fila['notas'] ?? '') . ' Archivo eliminado tras descarga (ciclo único: 7 días).');
            $update = $db->prepare("UPDATE backups_log SET notas = :notas WHERE id = :id");
            $update->execute(['notas' => $nota, 'id' => (int)$fila['id']]);
        }

        $purgados++;
        echo "  - {$archivo} → " . ($dryRun ? 'se eliminaría' : 'eliminado') . " (descargado + {$diasDescarga} días).\n";
    }

    echo "[" . date('Y-m-d H:i:s') . "] Limpieza de respaldos (ciclo único) completada: {$purgados} archivo(s) " . ($dryRun ? 'por eliminar' : 'eliminado(s)') . ".\n";
} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Limpieza de respaldos omitida: no se pudo consultar la bitácora (" . $e->getMessage() . "). No se borró nada.\n";
}
