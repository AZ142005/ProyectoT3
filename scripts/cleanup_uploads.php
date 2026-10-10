<?php
/**
 * Limpieza de uploads de comprobantes: huérfanos y retención de 6 meses.
 *
 * Uso:
 *   php scripts/cleanup_uploads.php [--dry-run]
 *
 * - uploads/comprobantes:
 *   · Huérfanos: elimina los archivos sin referencia en pagos.archivo,
 *     comprobantes_pago.archivo ni gastos_comunes.soporte_digital, con más de
 *     7 días de antigüedad (guarda para no borrar uploads en vuelo).
 *   · Retención 6 meses: elimina los archivos referenciados en pagos o
 *     comprobantes_pago cuya fecha de referencia más reciente sea anterior al
 *     límite (hoy - 6 meses). La fila y el archivo_hash permanecen en la BD
 *     como evidencia; solo se elimina el archivo físico. Las referencias de
 *     gastos_comunes.soporte_digital no registran fecha: si un basename solo
 *     aparece ahí, se conserva siempre.
 * - uploads/soportes: SOLO se reporta, nunca se elimina (puede contener PDFs de
 *   cartelera sin referencia en BD); el borrado se decide en una fase futura.
 *
 * Un archivo se considera "referenciado" si su basename aparece en cualquier fila
 * de la BD, incluidas las filas con borrado lógico. Si un basename tiene varias
 * filas con fecha, gana la más reciente (cualquier referencia reciente conserva
 * el archivo).
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

    // 1. Referencias en BD por basename:
    //    - $referencias: cualquier fila (pagos, comprobantes_pago, gastos_comunes)
    //    - $fechasReferencia: fecha de referencia más reciente (pagos/comprobantes_pago)
    $referencias = [];
    $fechasReferencia = [];

    // 1a. Gastos comunes: referencia permanente sin fecha
    foreach ($db->query("SELECT soporte_digital FROM gastos_comunes WHERE soporte_digital IS NOT NULL AND soporte_digital <> ''")->fetchAll(PDO::FETCH_COLUMN) as $valor) {
        $referencias[basename(trim((string)$valor))] = true;
    }

    // 1b. Referencias con fecha (pagos y comprobantes_pago)
    $consultasConFecha = [
        'fecha_registro' => "SELECT archivo, fecha_registro FROM pagos WHERE archivo IS NOT NULL AND archivo <> ''",
        'fecha_envio'    => "SELECT archivo, fecha_envio FROM comprobantes_pago WHERE archivo IS NOT NULL AND archivo <> ''",
    ];

    foreach ($consultasConFecha as $campoFecha => $sql) {
        foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $nombre = basename(trim((string)$fila['archivo']));
            if ($nombre === '') {
                continue;
            }

            $referencias[$nombre] = true;

            $valorFecha = trim((string)($fila[$campoFecha] ?? ''));
            if ($valorFecha === '') {
                continue; // Sin fecha utilizable: se conserva como referencia sin fecha
            }

            $fecha = date_create($valorFecha);
            if ($fecha === false) {
                continue; // Fecha ilegible: se conserva por seguridad
            }

            if (!isset($fechasReferencia[$nombre]) || $fecha > $fechasReferencia[$nombre]) {
                $fechasReferencia[$nombre] = $fecha; // Varias filas: gana la más reciente
            }
        }
    }

    echo "  ℹ Referencias en BD: " . count($referencias) . " basename(s) ("
        . count($fechasReferencia) . " con fecha).\n";

    // 2. uploads/comprobantes: huérfanos (>7 días) y retención de 6 meses
    $dirComprobantes = BASE_PATH . '/uploads/comprobantes';
    $limiteSegundos = 7 * 86400; // 7 días de guarda para uploads en vuelo
    $limiteRetencion = (new DateTime())->modify('-6 months'); // hoy - 6 meses
    $ahora = time();
    $eliminados = 0;
    $candidatos = 0;
    $eliminadosRetencion = 0;
    $candidatosRetencion = 0;

    if (is_dir($dirComprobantes)) {
        foreach (scandir($dirComprobantes) as $nombre) {
            if ($nombre === '' || $nombre[0] === '.') {
                continue; // Omite entradas ocultas y . / ..
            }

            $ruta = $dirComprobantes . '/' . $nombre;
            if (!is_file($ruta)) {
                continue; // Subdirectorios
            }

            // 2a. Retención 6 meses: el archivo está referenciado con fecha y la
            //     referencia más reciente ya superó el límite. La fila y el
            //     archivo_hash quedan en la BD como evidencia; solo se borra el archivo.
            if (isset($fechasReferencia[$nombre]) && $fechasReferencia[$nombre] < $limiteRetencion) {
                if ($dryRun) {
                    echo "  🔍 Candidato (dry-run, retención 6 meses): {$nombre}\n";
                    $candidatosRetencion++;
                } elseif (@unlink($ruta)) {
                    echo "  🗑️ Eliminado (retención 6 meses): {$nombre}\n";
                    $eliminadosRetencion++;
                }
                continue;
            }

            if (isset($referencias[$nombre])) {
                continue; // Referencia reciente o gasto sin fecha: se conserva
            }

            // 2b. Huérfano: eliminar solo con más de 7 días de antigüedad
            if (($ahora - filemtime($ruta)) < $limiteSegundos) {
                continue; // Reciente: puede ser un upload en vuelo
            }

            if ($dryRun) {
                echo "  🔍 Candidato (dry-run, huérfano >7 días): {$nombre}\n";
                $candidatos++;
            } elseif (@unlink($ruta)) {
                echo "  🗑️ Eliminado (huérfano >7 días): {$nombre}\n";
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
        ? "{$candidatos} candidato(s) huérfano(s) + {$candidatosRetencion} candidato(s) por retención 6 meses (dry-run, sin eliminar)"
        : "{$eliminados} huérfano(s) eliminado(s) + {$eliminadosRetencion} eliminado(s) por retención 6 meses";
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
