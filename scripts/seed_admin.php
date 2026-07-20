<?php
/**
 * Script de Seed: Inserta o actualiza el usuario administrador semilla.
 * Credenciales: admin@conjunto.com / admin123 (hasheado con bcrypt).
 *
 * Uso: C:\xampp\php\php.exe scripts/seed_admin.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Seed de Administrador ===" . PHP_EOL . PHP_EOL;

$db = Database::getConnection();

$email    = 'admin@conjunto.com';
$usuario  = 'admin';
$password = password_hash('admin123', PASSWORD_BCRYPT);
$nombre   = 'Administrador General';

// Verificar si la columna email existe en la tabla usuarios
try {
    $db->query("SELECT email FROM usuarios LIMIT 1");
} catch (\PDOException $e) {
    // La columna no existe, agregarla
    echo "Agregando columna 'email' a tabla 'usuarios'..." . PHP_EOL;
    $db->exec("ALTER TABLE usuarios ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER usuario");
    $db->exec("ALTER TABLE usuarios ADD UNIQUE KEY uk_email (email)");
    echo "  ✔ Columna 'email' agregada." . PHP_EOL . PHP_EOL;
}

// Verificar si el admin ya existe
$stmt = $db->prepare("SELECT id FROM usuarios WHERE usuario = :usuario");
$stmt->execute(['usuario' => $usuario]);
$existing = $stmt->fetch();

if ($existing) {
    // Actualizar
    $update = $db->prepare("UPDATE usuarios SET email = :email, password = :password, nombre_completo = :nombre WHERE id = :id");
    $update->execute([
        'email'    => $email,
        'password' => $password,
        'nombre'   => $nombre,
        'id'       => $existing['id']
    ]);
    echo "✔ Administrador actualizado (ID: {$existing['id']})." . PHP_EOL;
} else {
    // Insertar
    $insert = $db->prepare("INSERT INTO usuarios (usuario, email, password, nombre_completo, rol, estado) VALUES (:usuario, :email, :password, :nombre, 'admin', 1)");
    $insert->execute([
        'usuario'  => $usuario,
        'email'    => $email,
        'password' => $password,
        'nombre'   => $nombre
    ]);
    echo "✔ Administrador creado." . PHP_EOL;
}

echo PHP_EOL . "Credenciales:" . PHP_EOL;
echo "  Email:    {$email}" . PHP_EOL;
echo "  Password: admin123" . PHP_EOL;
echo PHP_EOL . "✅ Seed completado." . PHP_EOL;
