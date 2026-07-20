<?php
/**
 * Script de Migración SQL: Crea la tabla `edificios` y actualiza la tabla `unidades`.
 * Migra los datos existentes de la columna `torre` hacia la tabla `edificios`.
 *
 * Uso: C:\xampp\php\php.exe scripts/migrate_estructura.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración de Estructura (Edificios y Unidades) ===" . PHP_EOL . PHP_EOL;

$db = Database::getConnection();

try {
    // 1. Crear tabla `edificios` si no existe
    echo "1. Creando tabla 'edificios'..." . PHP_EOL;
    $sqlEdificios = "
        CREATE TABLE IF NOT EXISTS edificios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            descripcion VARCHAR(255) DEFAULT NULL,
            estado TINYINT DEFAULT 1,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    $db->exec($sqlEdificios);
    echo "  ✔ Tabla 'edificios' lista." . PHP_EOL . PHP_EOL;

    // 2. Verificar si `unidades` ya posee la columna `edificio_id`
    $stmt = $db->query("SHOW COLUMNS FROM unidades LIKE 'edificio_id'");
    $hasEdificioId = $stmt->fetch();

    if (!$hasEdificioId) {
        echo "2. Agregando columna 'edificio_id' a tabla 'unidades'..." . PHP_EOL;
        $db->exec("ALTER TABLE unidades ADD COLUMN edificio_id INT DEFAULT NULL AFTER id");
        echo "  ✔ Columna 'edificio_id' agregada." . PHP_EOL . PHP_EOL;
    }

    // 3. Migrar datos existentes de `torre` si la columna `torre` existe en `unidades`
    $stmtTorre = $db->query("SHOW COLUMNS FROM unidades LIKE 'torre'");
    $hasTorre = $stmtTorre->fetch();

    if ($hasTorre) {
        echo "3. Migrando torres existentes desde 'unidades.torre' hacia 'edificios'..." . PHP_EOL;
        
        // Obtener valores únicos de `torre`
        $torresQuery = $db->query("SELECT DISTINCT torre FROM unidades WHERE torre IS NOT NULL AND TRIM(torre) != ''");
        $torres = $torresQuery->fetchAll(PDO::FETCH_COLUMN);

        foreach ($torres as $torreNombre) {
            $torreNombre = trim($torreNombre);
            // Insertar edificio si no existe
            $insStmt = $db->prepare("INSERT IGNORE INTO edificios (nombre) VALUES (:nombre)");
            $insStmt->execute(['nombre' => $torreNombre]);

            // Obtener el ID del edificio
            $idStmt = $db->prepare("SELECT id FROM edificios WHERE nombre = :nombre");
            $idStmt->execute(['nombre' => $torreNombre]);
            $edificioId = $idStmt->fetchColumn();

            if ($edificioId) {
                // Actualizar unidades
                $updStmt = $db->prepare("UPDATE unidades SET edificio_id = :edificio_id WHERE torre = :torre");
                $updStmt->execute(['edificio_id' => $edificioId, 'torre' => $torreNombre]);
                echo "  ✔ Migrada torre '{$torreNombre}' (Edificio ID: {$edificioId})" . PHP_EOL;
            }
        }

        // Eliminar columna legacy `torre`
        echo "  Eliminando columna legacy 'torre' de la tabla 'unidades'..." . PHP_EOL;
        $db->exec("ALTER TABLE unidades DROP COLUMN torre");
        echo "  ✔ Columna 'torre' eliminada." . PHP_EOL . PHP_EOL;
    }

    // 4. Agregar Foreign Key si no existe
    echo "4. Verificando clave foránea FK en 'unidades.edificio_id'..." . PHP_EOL;
    try {
        $db->exec("ALTER TABLE unidades ADD CONSTRAINT fk_unidades_edificio FOREIGN KEY (edificio_id) REFERENCES edificios(id) ON DELETE SET NULL");
        echo "  ✔ Clave foránea `fk_unidades_edificio` agregada." . PHP_EOL;
    } catch (\PDOException $e) {
        // Puede fallar si la FK ya existe, lo cual es normal e ignoramos
        echo "  ℹ Clave foránea ya existente o configurada." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración de estructura completada con éxito." . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ Error en la migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
