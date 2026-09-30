<?php
/**
 * Migración: Añade columna 'telefono' a la tabla 'usuarios' si no existe.
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

try {
    $db = Database::getConnection();
    echo "=== Migrando tabla 'usuarios' para soporte de teléfono ===" . PHP_EOL;

    // 1. Verificar si la columna 'telefono' ya existe
    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'telefono'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$col) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN telefono VARCHAR(20) NULL DEFAULT NULL AFTER cedula");
        echo "✔ Columna 'telefono' agregada exitosamente a la tabla 'usuarios'." . PHP_EOL;
    } else {
        echo "ℹ Columna 'telefono' ya existía en la tabla 'usuarios'." . PHP_EOL;
    }

    echo "=== Migración completada exitosamente ===" . PHP_EOL;
} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
