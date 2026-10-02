<?php
/**
 * Job de Auditoría y Reconciliación Periódica:
 * 
 * Verifica que todos los abonos en estado 'conciliado' tengan exactamente
 * un registro en 'conciliacion_abono_pago' (relación estricta 1:1),
 * y que no existan duplicados ni registros huérfanos.
 * 
 * Uso: php scripts/reconciliacion_auditoria_job.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Services\ConciliacionBancariaService;

echo "==========================================================" . PHP_EOL;
echo "  AUDITORÍA DE RECONCILIACIÓN BANCARIA - CONDOMINIO DIGITAL" . PHP_EOL;
echo "==========================================================" . PHP_EOL . PHP_EOL;

try {
    $service = new ConciliacionBancariaService();
    $reporte = $service->ejecutarAuditoriaReconciliacion();

    echo "Fecha de ejecución : " . $reporte['fecha_auditoria'] . PHP_EOL;
    echo "Abonos conciliados : " . $reporte['total_abonos_conciliados'] . PHP_EOL;
    echo "Vínculos activos   : " . $reporte['total_vinculos_activos'] . PHP_EOL;
    echo "Divergencias       : " . $reporte['conteo_divergencias'] . PHP_EOL . PHP_EOL;

    if ($reporte['ok']) {
        echo "✔ INVARIANTE 1:1 CUMPLIDA: Todos los abonos y pagos están perfectamente balanceados." . PHP_EOL;
        echo "✔ Sin movimientos duplicados ni pagos con doble conciliación." . PHP_EOL;
        echo PHP_EOL . "✅ Auditoría finalizada con ÉXITO." . PHP_EOL;
        exit(0);
    } else {
        echo "❌ ATENCIÓN: Se detectaron {$reporte['conteo_divergencias']} discrepancia(s) de conciliación:" . PHP_EOL . PHP_EOL;
        foreach ($reporte['divergencias'] as $idx => $div) {
            $num = $idx + 1;
            echo "  [{$num}] [{$div['tipo']}] {$div['descripcion']}" . PHP_EOL;
        }
        echo PHP_EOL . "❌ Estado inconsistente detectado. Revise los registros señalados." . PHP_EOL;
        exit(1);
    }
} catch (\Throwable $e) {
    echo "❌ Error al ejecutar el job de auditoría: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
