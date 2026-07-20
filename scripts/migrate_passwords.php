<?php
/**
 * Script de migración one-time: Convierte contraseñas en texto plano a bcrypt.
 * Recorre las tablas `personas` y `usuarios` y hashea las contraseñas existentes.
 *
 * Uso: C:\xampp\php\php.exe scripts/migrate_passwords.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración de Contraseñas a Bcrypt ===" . PHP_EOL . PHP_EOL;

$db = Database::getConnection();
$migrated = 0;
$skipped = 0;

// --- Migrar tabla `personas` ---
echo "Procesando tabla 'personas'..." . PHP_EOL;
$stmt = $db->query("SELECT id, password FROM personas WHERE password IS NOT NULL AND password != ''");
$personas = $stmt->fetchAll();

foreach ($personas as $row) {
    // Si ya es un hash bcrypt ($2y$), saltar
    if (str_starts_with($row['password'], '$2y$')) {
        $skipped++;
        continue;
    }

    $hashed = password_hash($row['password'], PASSWORD_BCRYPT);
    $update = $db->prepare("UPDATE personas SET password = :password WHERE id = :id");
    $update->execute(['password' => $hashed, 'id' => $row['id']]);
    $migrated++;
    echo "  ✔ Persona ID {$row['id']} migrada." . PHP_EOL;
}

// --- Migrar tabla `usuarios` ---
echo PHP_EOL . "Procesando tabla 'usuarios'..." . PHP_EOL;
$stmt = $db->query("SELECT id, password FROM usuarios WHERE password IS NOT NULL AND password != ''");
$usuarios = $stmt->fetchAll();

foreach ($usuarios as $row) {
    if (str_starts_with($row['password'], '$2y$')) {
        $skipped++;
        continue;
    }

    $hashed = password_hash($row['password'], PASSWORD_BCRYPT);
    $update = $db->prepare("UPDATE usuarios SET password = :password WHERE id = :id");
    $update->execute(['password' => $hashed, 'id' => $row['id']]);
    $migrated++;
    echo "  ✔ Usuario ID {$row['id']} migrado." . PHP_EOL;
}

echo PHP_EOL . "=== Resultado ===" . PHP_EOL;
echo "Migrados: {$migrated}" . PHP_EOL;
echo "Omitidos (ya hasheados): {$skipped}" . PHP_EOL;
echo "✅ Migración completada." . PHP_EOL;
