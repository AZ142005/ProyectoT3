<?php
/**
 * Limpieza de uploads huérfanos de comprobantes (sin referencia en la base de datos).
 *
 * Uso:
 *   php scripts/cleanup_uploads.php [--dry-run]
 *
 * - uploads/comprobantes: elimina los archivos sin referencia en pagos.archivo,
 *   comprobantes_pago.archivo ni gastos_comunes.soporte_digital, con más de 7 días
 *   de antigüedad (guarda para no borrar uploads en vuelo).
 * - uploads/soportes: SOLO se reporta, nunca se elimina (puede contener PDFs de
 *   cartelera sin referencia en BD); el borrado se decide en una fase futura.
 *
 * Un archivo se considera "referenciado" si su basename aparece en cualquier fila
 * de la BD, incluidas las filas con borrado lógico.
 *
 * Windows: la tarea programada "Condominio-Uploads" (creada por scripts/setup_scheduled_tasks.ps1)
 * ejecuta este script a diario a las 02:30.
 */
set_time_limit(300);
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

use App\Core\Database;

$dryRun = in_array('--dry-run', $argv ?? [], true);

echo "[" . date('Y-m-d H:i:s') . "] Iniciando limpieza de uploads" . ($dryRun ? " (dry-run)" : "") . "...\n";

try {
    $db = Database::getConnection();

    // 1. Conjunto de basenames referenciados en BD
    $referencias = [];
    $consultas = [
        "SELECT archivo FROM pagos WHERE archivo IS NOT NULL AND archivo <> ''",
        "SELECT archivo FROM comprobantes_pago WHERE archivo IS NOT NULL AND archivo <> ''",
        "SELECT soporte_digital FROM gastos_comunes WHERE soporte_digital IS NOT NULL AND soporte_digital <> ''",
    ];

    foreach ($consultas as $sql) {
        foreach ($db->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $valor) {
            $referencias[basename(trim((string)$valor))] = true;
        }
    }

    echo "  ℹ Referencias en BD: " . count($referencias) . " basename(s).\n";

    // 2. uploads/comprobantes: eliminar huérfanos con más de 7 días
    $dirComprobantes = BASE_PATH . '/uploads/comprobantes';
    $limiteSegundos = 7 * 86400; // 7 días de guarda para uploads en vuelo
    $ahora = time();
    $eliminados = 0;
    $candidatos = 0;

    if (is_dir($dirComprobantes)) {
        foreach (scandir($dirComprobantes) as $nombre) {
            if ($nombre === '' || $nombre[0] === '.') {
                continue; // Omite entradas ocultas y . / ..
            }

            $ruta = $dirComprobantes . '/' . $nombre;
            if (!is_file($ruta)) {
                continue; // Subdirectorios
            }
            if (($ahora - filemtime($ruta)) < $limiteSegundos) {
                continue; // Reciente: puede ser un upload en vuelo
            }
            if (isset($referencias[$nombre])) {
                continue; // Referenciado en BD (incluye borrado lógico)
            }

            if ($dryRun) {
                echo "  🔍 Candidato (dry-run): {$nombre}\n";
                $candidatos++;
            } elseif (@unlink($ruta)) {
                echo "  🗑️ Eliminado: {$nombre}\n";
                $eliminados++;
            }
        }
    } else {
        echo "  ⚠ uploads/comprobantes no existe — omitido.\n";
    }

    // 3. uploads/soportes: misma detección, SOLO reporte (nunca se elimina).
    //    Puede contener PDFs de cartelera sin referencia en BD; el borrado
    //    se decide en una fase futura.
    $dirSoportes = BASE_PATH . '/uploads/soportes';
    $candidatosSoportes = 0;

    if (is_dir($dirSoportes)) {
        foreach (scandir($dirSoportes) as $nombre) {
            if ($nombre === '' || $nombre[0] === '.') {
                continue;
            }

            $ruta = $dirSoportes . '/' . $nombre;
            if (!is_file($ruta)) {
                continue;
            }
            if (($ahora - filemtime($ruta)) < $limiteSegundos) {
                continue;
            }
            if (isset($referencias[$nombre])) {
                continue;
            }

            echo "  📋 Candidato (solo reporte, no eliminado): {$nombre}\n";
            $candidatosSoportes++;
        }
    } else {
        echo "  ⚠ uploads/soportes no existe — omitido.\n";
    }

    // 4. Resumen: consola + registro en storage/logs
    $detalleComprobantes = $dryRun
        ? "{$candidatos} candidato(s) (dry-run, sin eliminar)"
        : "{$eliminados} archivo(s) eliminado(s)";
    $resumen = "Comprobantes: {$detalleComprobantes} | Soportes: {$candidatosSoportes} candidato(s) (solo reporte)";

    echo "[" . date('Y-m-d H:i:s') . "] Limpieza de uploads completada. {$resumen}\n";

    $logDir = BASE_PATH . '/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true); // Crear el directorio de logs si no existe (igual que backup_database.php)
    }
    $logMsg = "[" . date('Y-m-d H:i:s') . "] " . ($dryRun ? "[DRY-RUN] " : "") . $resumen . "\n";
    @file_put_contents($logDir . '/cleanup_uploads.log', $logMsg, FILE_APPEND | LOCK_EX);

    exit(0);
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
