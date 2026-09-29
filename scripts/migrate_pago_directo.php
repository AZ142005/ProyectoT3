<?php
/**
 * Migración: Pago directo sin iniciar sesión (portal público).
 *
 * Permite que la columna pagos.residente_id sea NULL, ya que los pagos
 * reportados desde el portal público pertenecen a la unidad y no a un
 * usuario registrado.
 *
 * Idempotente: si la columna ya permite NULL, no ejecuta cambios.
 *
 * Uso: php scripts/migrate_pago_directo.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

try {
    $db = Database::getConnection();

    $stmt = $db->prepare("
        SELECT IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'pagos'
          AND COLUMN_NAME = 'residente_id'
        LIMIT 1
    ");
    $stmt->execute();
    $esNullable = $stmt->fetchColumn();

    if ($esNullable === false) {
        echo "Error: no se encontró la columna pagos.residente_id. Verifique el esquema de la base de datos.\n";
        exit(1);
    }

    if (strtoupper((string)$esNullable) === 'YES') {
        echo "Migración ya aplicada: pagos.residente_id ya permite NULL.\n";
        exit(0);
    }

    // La restricción FK existente sigue siendo válida con valores NULL.
    $db->exec("ALTER TABLE `pagos` MODIFY COLUMN `residente_id` int(11) NULL;");

    echo "Migración aplicada exitosamente: pagos.residente_id ahora permite NULL.\n";
    exit(0);
} catch (Exception $e) {
    echo "Error al aplicar la migración: " . $e->getMessage() . "\n";
    exit(1);
}
