<?php
/**
 * Migración: Purgado seguro de tablas huérfanas / obsoletas.
 * Tablas eliminadas:
 *  - otp_codes (reemplazada por auth_otp_tokens)
 *  - movimientos (reemplazada por movimientos_cuenta)
 *  - solicitudes_cambio (reemplazada por solicitudes_cambio_datos)
 *  - comunicados (módulo de Comunicados eliminado por decisión del usuario, 29-09)
 * 
 * Ejecutar: php scripts/migrate_purgar_tablas_huerfanas.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: Purgado Seguro de Tablas Huérfanas ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    $tablasHuerfanas = [
        'otp_codes' => 'Reemplazada por auth_otp_tokens',
        'movimientos' => 'Reemplazada por movimientos_cuenta',
        'solicitudes_cambio' => 'Reemplazada por solicitudes_cambio_datos',
        'comunicados' => 'Módulo de Comunicados eliminado por decisión del usuario (29-09)',
    ];

    foreach ($tablasHuerfanas as $tabla => $motivo) {
        // Verificar si la tabla existe en el esquema actual
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() AND table_name = :tabla
        ");
        $stmt->execute(['tabla' => $tabla]);
        $existe = (int)$stmt->fetchColumn() > 0;

        if ($existe) {
            // Contabilizar registros antes de eliminar para trazabilidad
            $countStmt = $db->query("SELECT COUNT(*) FROM `{$tabla}`");
            $totalRegistros = (int)$countStmt->fetchColumn();

            // Eliminar tabla
            $db->exec("DROP TABLE IF EXISTS `{$tabla}`");
            echo "  ✔ Tabla '{$tabla}' eliminada ({$totalRegistros} registros purgados). Motivo: {$motivo}." . PHP_EOL;
        } else {
            echo "  ℹ Tabla '{$tabla}' no existe en la base de datos (ya purgada o inexistente)." . PHP_EOL;
        }
    }

    echo PHP_EOL . "✅ Migración de purgado completada con éxito." . PHP_EOL;
    exit(0);
} catch (\Exception $e) {
    echo PHP_EOL . "❌ Error durante la migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
