<?php
/**
 * Cron job diario: aplica la política de retención a las tablas que se
 * acumulan sin límite (log_auditoria 365 d, historial_estado_abono 365 d,
 * rate_limits 30 d). Cada purga va precedida de un export verificado
 * (.sql.gz + SHA-256) en storage/backups/purgas/; si el respaldo falla,
 * no se borra nada.
 *
 * Sin registros elegibles la corrida es un no-op limpio (no genera archivo
 * ni fila de bitácora).
 *
 * Ciclo único de archivos: los exportes de purga descargados se eliminan 7
 * días después de su descarga (30 días si nunca se descargan) y el respaldo
 * general descargado-vencido se elimina con el backstop de 7 días. Las filas
 * de backups_log/purgas_log NUNCA se borran: quedan con nota.
 *
 * Configurar en crontab:
 *   crontab -e
 *   45 2 * * * /usr/bin/php /ruta/proyecto/scripts/cleanup_retencion.php >> /ruta/proyecto/logs/cleanup.log 2>&1
 *
 * Windows: la tarea programada "Condominio-Retencion" (creada por
 * scripts/setup_scheduled_tasks.ps1) ejecuta este script a diario a las 02:45.
 *
 * Uso CLI:
 *   php scripts/cleanup_retencion.php               # purga las 3 tablas
 *   php scripts/cleanup_retencion.php --dry-run     # solo reporta, no escribe ni borra
 *   php scripts/cleanup_retencion.php --tabla=rate_limits
 *
 * Exit codes: 0 = todo bien (incluye "sin elegibles"); 1 = error o fallo de purga.
 */
set_time_limit(300);
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Services\DataRetentionService;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$tablaFiltro = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--tabla=')) {
        $tablaFiltro = substr($arg, strlen('--tabla='));
    }
}

echo '[' . date('Y-m-d H:i:s') . '] Iniciando retención de datos acumulados' . ($dryRun ? ' (dry-run)' : '') . "...\n";

try {
    if ($tablaFiltro !== null && !DataRetentionService::esTablaPermitida($tablaFiltro)) {
        echo "  ❌ Tabla no permitida: '{$tablaFiltro}'. Permitidas: " . implode(', ', DataRetentionService::tablasPermitidas()) . "\n";
        exit(1);
    }

    $servicio = new DataRetentionService();
    $stats = $servicio->estadisticas($tablaFiltro);

    $procesadas = 0;
    $exportados = 0;
    $eliminados = 0;
    $fallidas = 0;

    foreach ($stats as $tabla => $s) {
        if (!$s['existe']) {
            echo "  ⚠ {$tabla} no existe — omitida.\n";
            continue;
        }

        printf(
            "  ℹ %s: %d fila(s), %.3f MB, más antiguo %s, elegibles %d (corte %s, retención %dd).\n",
            $tabla,
            $s['filas'],
            $s['tamano_mb'],
            $s['mas_antiguo'] ?? '—',
            $s['elegibles'],
            $s['fecha_corte'] ?? '—',
            $s['dias']
        );

        if ($s['elegibles'] === 0) {
            echo "  ✔ {$tabla}: sin registros elegibles (0 borrados).\n";
            continue;
        }

        if ($dryRun) {
            echo "  ℹ [dry-run] {$tabla}: se exportarían y purgarían {$s['elegibles']} registro(s).\n";
            $procesadas++;
            continue;
        }

        $resultado = $servicio->purgar($tabla, 'programado');
        if ($resultado['exito'] && in_array($resultado['estado'], ['exitoso', 'parcial'], true)) {
            echo "  ✔ {$tabla}: {$resultado['registros_eliminados']} registro(s) eliminado(s) tras exportar {$resultado['registros_exportados']} ({$resultado['archivo']}, SHA-256 {$resultado['hash_sha256']}).\n";
            if ($resultado['estado'] === 'parcial') {
                echo "  ⚠ {$tabla}: {$resultado['mensaje']}\n";
            }
            $procesadas++;
            $exportados += $resultado['registros_exportados'];
            $eliminados += $resultado['registros_eliminados'];
        } else {
            echo "  ❌ {$tabla}: {$resultado['mensaje']}\n";
            $fallidas++;
        }
    }

    // Ciclo único de archivos: exportes de purga (7 d tras descarga / 30 d
    // sin descarga) y backstop del respaldo general descargado-vencido (7 d).
    $exportesLimpiados = $servicio->limpiarExportesPurgas($dryRun);
    foreach ($exportesLimpiados['detalles'] as $d) {
        echo "  ℹ [exporte] {$d['archivo']} → " . ($dryRun ? 'se eliminaría' : 'eliminado') . " ({$d['regla']}).\n";
    }
    echo "  ✔ Exportes de purga (7d tras descarga / 30d sin descarga): {$exportesLimpiados['eliminados']} archivo(s) " . ($dryRun ? 'por eliminar' : 'eliminado(s)') . ".\n";

    $respaldoLimpiado = $servicio->limpiarRespaldoGeneralVencido($dryRun);
    foreach ($respaldoLimpiado['detalles'] as $d) {
        echo "  ℹ [respaldo] {$d['archivo']} → " . ($dryRun ? 'se eliminaría' : 'eliminado') . " ({$d['regla']}).\n";
    }
    echo "  ✔ Respaldo general descargado-vencido (7d): {$respaldoLimpiado['eliminados']} archivo(s) " . ($dryRun ? 'por eliminar' : 'eliminado(s)') . ".\n";

    echo '[' . date('Y-m-d H:i:s') . "] Retención completada. {$procesadas} tabla(s) procesada(s), {$exportados} exportado(s), {$eliminados} eliminado(s), {$fallidas} fallida(s).\n";
    exit($fallidas > 0 ? 1 : 0);
} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
