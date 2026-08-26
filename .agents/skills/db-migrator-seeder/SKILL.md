---
name: db-migrator-seeder
description: >-
  Playbook para diseñar, migrar y poblar la base de datos MySQL mediante scripts PHP idempotentes con PDO.
  Úsalo cuando el usuario pida modificar esquemas, agregar tablas, crear relaciones o generar datos de prueba.
---

# Skill: Gestor de Migraciones y Seeders de Base de Datos

Esta habilidad guía la creación de migraciones de esquema y datos de prueba manteniendo la integridad referencial y evitando errores en re-ejecuciones.

---

## 1. Plantilla de Migración Idempotente (`scripts/migrate_<nombre>.php`)

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: Nombre de la Migración ===" . PHP_EOL . PHP_EOL;
$db = Database::getConnection();

try {
    // 1. Crear tabla si no existe
    $db->exec("
        CREATE TABLE IF NOT EXISTS mi_tabla (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            estado TINYINT DEFAULT 1,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "  ✔ Tabla 'mi_tabla' lista." . PHP_EOL;

    // 2. Agregar columna con verificación previa
    $stmt = $db->query("SHOW COLUMNS FROM mi_tabla LIKE 'nuevo_campo'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE mi_tabla ADD COLUMN nuevo_campo VARCHAR(100) DEFAULT NULL AFTER nombre");
        echo "  ✔ Columna 'nuevo_campo' añadida." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración completada con éxito." . PHP_EOL;
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
```

---

## 2. Plantilla de Seeder (`scripts/seed_<nombre>.php`)

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Seeder: Datos de Prueba ===" . PHP_EOL . PHP_EOL;
$db = Database::getConnection();

$datos = [
    ['codigo' => '001', 'nombre' => 'Ejemplo 1'],
    ['codigo' => '002', 'nombre' => 'Ejemplo 2'],
];

foreach ($datos as $d) {
    $stmt = $db->prepare("SELECT id FROM mi_tabla WHERE codigo = :codigo");
    $stmt->execute(['codigo' => $d['codigo']]);
    
    if (!$stmt->fetch()) {
        $ins = $db->prepare("INSERT INTO mi_tabla (codigo, nombre, estado) VALUES (:codigo, :nombre, 1)");
        $ins->execute($d);
        echo "  ✔ Creado: {$d['nombre']}" . PHP_EOL;
    } else {
        echo "  ℹ Ya existente: {$d['nombre']}" . PHP_EOL;
    }
}
echo PHP_EOL . "✅ Seeder completado." . PHP_EOL;
```
