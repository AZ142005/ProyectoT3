<?php
/**
 * Migracion: estacionamientos.unidad_id pasa a ser NULL-able.
 *
 * Motivo: el modelo libera un puesto asignando unidad_id = NULL y el soft delete
 * hace lo mismo. La columna era NOT NULL, por lo que MySQL guardaba 0 y la clave
 * unica uk_estacionamiento_unidad permitia una sola fila con ese valor. Eso
 * bloqueaba liberar puestos y reasignarlos.
 *
 * La migracion es idempotente: si la columna ya es NULL, no la altera; y
 * normaliza cualquier fila con unidad_id = 0 a NULL.
 *
 * Uso: php scripts/migrate_estacionamientos_unidad_nullable.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== MIGRACION: estacionamientos.unidad_id nullable ===" . PHP_EOL;

$db = Database::getConnection();

$stmt = $db->query("
    SELECT IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'estacionamientos'
      AND COLUMN_NAME = 'unidad_id'
");
$nullable = $stmt->fetchColumn();

if ($nullable === false) {
    echo "ERROR: no existe la columna estacionamientos.unidad_id." . PHP_EOL;
    exit(1);
}

if (strtoupper((string)$nullable) !== 'YES') {
    $db->exec("ALTER TABLE estacionamientos MODIFY COLUMN unidad_id INT(11) NULL");
    echo "OK: unidad_id ahora acepta NULL." . PHP_EOL;
} else {
    echo "OK: unidad_id ya acepta NULL." . PHP_EOL;
}

$stmt = $db->prepare("UPDATE estacionamientos SET unidad_id = NULL WHERE unidad_id = 0");
$stmt->execute();
echo "OK: filas con unidad_id=0 normalizadas a NULL: " . $stmt->rowCount() . PHP_EOL;

$idx = $db->query("
    SELECT INDEX_NAME
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'estacionamientos'
      AND INDEX_NAME = 'uk_estacionamiento_unidad'
    LIMIT 1
")->fetchColumn();

echo ($idx !== false)
    ? "OK: indice uk_estacionamiento_unidad presente." . PHP_EOL
    : "AVISO: no se encontro el indice uk_estacionamiento_unidad." . PHP_EOL;

echo "=== Fin ===" . PHP_EOL;
