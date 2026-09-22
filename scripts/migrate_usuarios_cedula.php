<?php
/**
 * Migración: Añade columna 'cedula' a la tabla 'usuarios' si no existe,
 * y asigna la cédula semilla 'V00000000' al usuario administrador inicial.
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

try {
    $db = Database::getConnection();
    echo "=== Migrando tabla 'usuarios' para soporte de cédula ===" . PHP_EOL;

    // 1. Verificar si la columna 'cedula' ya existe
    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'cedula'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$col) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN cedula VARCHAR(20) NULL UNIQUE AFTER email");
        echo "✔ Columna 'cedula' agregada exitosamente a la tabla 'usuarios'." . PHP_EOL;
    } else {
        echo "ℹ Columna 'cedula' ya existía en la tabla 'usuarios'." . PHP_EOL;
    }

    // 2. Asignar cédula por defecto al administrador semilla (ID 1 / usuario 'admin')
    $stmtAdmin = $db->query("SELECT id, usuario, cedula FROM usuarios WHERE id = 1 OR usuario = 'admin' LIMIT 1");
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        if (empty($admin['cedula'])) {
            $upd = $db->prepare("UPDATE usuarios SET cedula = 'V00000000' WHERE id = :id");
            $upd->execute(['id' => $admin['id']]);
            echo "✔ Cédula 'V00000000' asignada al administrador (ID: {$admin['id']})." . PHP_EOL;
        } else {
            echo "ℹ El administrador ya posee la cédula '{$admin['cedula']}'." . PHP_EOL;
        }
    }

    echo "=== Migración completada exitosamente ===" . PHP_EOL;
} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
