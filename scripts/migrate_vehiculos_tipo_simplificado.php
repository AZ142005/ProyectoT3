<?php
/**
 * Simplifica el registro de vehículos:
 *  - Agrega tipo_vehiculo (pequeno | camioneta | camion_f350).
 *  - Hace nullable la placa (ya no es obligatoria).
 *
 * Idempotente. Uso: php scripts/migrate_vehiculos_tipo_simplificado.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== MIGRACIÓN: VEHÍCULOS CON TIPO Y PLACA OPCIONAL ===" . PHP_EOL;
$db = Database::getConnection();

$stmt = $db->query("
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehiculos'
      AND COLUMN_NAME = 'tipo_vehiculo'
");
if ((int)$stmt->fetchColumn() === 0) {
    $db->exec("ALTER TABLE vehiculos ADD COLUMN tipo_vehiculo ENUM('pequeno','camioneta','camion_f350') NULL AFTER estacionamiento_id");
    echo "OK: columna tipo_vehiculo agregada." . PHP_EOL;
} else {
    echo "OK: tipo_vehiculo ya existe." . PHP_EOL;
}

$stmt = $db->query("
    SELECT IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehiculos'
      AND COLUMN_NAME = 'placa'
");
$nullable = $stmt->fetchColumn();
if ($nullable === false) {
    echo "ERROR: no existe la columna vehiculos.placa." . PHP_EOL;
    exit(1);
}
if (strtoupper((string)$nullable) !== 'YES') {
    $db->exec("ALTER TABLE vehiculos MODIFY COLUMN placa VARCHAR(20) NULL");
    echo "OK: placa ahora acepta NULL." . PHP_EOL;
} else {
    echo "OK: placa ya acepta NULL." . PHP_EOL;
}

echo "=== Fin ===" . PHP_EOL;
