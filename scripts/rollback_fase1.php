<?php
/**
 * Script de Rollback para la Fase 1:
 * Deshace los cambios de la migración Fase 1 si es necesario.
 *
 * Uso: C:\xampp\php\php.exe scripts/rollback_fase1.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Rollback de Migración: Fase 1 ===" . PHP_EOL . PHP_EOL;

$db = Database::getConnection();

try {
    echo "Eliminando tablas 'vehiculos' y 'estacionamientos'..." . PHP_EOL;
    $db->exec("DROP TABLE IF EXISTS vehiculos");
    $db->exec("DROP TABLE IF EXISTS estacionamientos");
    echo "  ✔ Tablas eliminadas." . PHP_EOL;

    echo "Eliminando índices creados..." . PHP_EOL;
    $dropIndice = function($tabla, $nombreIndice) use ($db) {
        $stmt = $db->query("SHOW INDEX FROM {$tabla} WHERE Key_name = '{$nombreIndice}'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE {$tabla} DROP INDEX {$nombreIndice}");
            echo "  ✔ Índice '{$nombreIndice}' eliminado de '{$tabla}'." . PHP_EOL;
        }
    };

    $dropIndice('facturas', 'idx_facturas_morosidad');
    $dropIndice('facturas', 'idx_facturas_antiguedad');
    $dropIndice('log_auditoria', 'idx_log_auditoria_pago');

    echo PHP_EOL . "✅ ROLLBACK DE LA FASE 1 COMPLETADO EXITOSAMENTE." . PHP_EOL;
} catch (\Exception $e) {
    echo "❌ ERROR EN EL ROLLBACK: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
