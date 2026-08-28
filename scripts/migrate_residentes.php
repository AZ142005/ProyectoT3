<?php
/**
 * Migración Idempotente: Estandarización de roles de residentes (propietario, inquilino, ambos)
 * e indexación de personas por unidad y estado.
 *
 * Uso: php scripts/migrate_residentes.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: Estandarización de Roles de Residentes e Indexación ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    // 1. Verificar definición actual de la columna 'tipo'
    $stmt = $db->query("
        SELECT COLUMN_TYPE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personas' AND COLUMN_NAME = 'tipo'
    ");
    $currentType = $stmt->fetchColumn();

    if (!$currentType) {
        echo "  ➕ Columna 'tipo' no encontrada. Agregando con ENUM('propietario','inquilino','ambos')..." . PHP_EOL;
        $db->exec("ALTER TABLE personas ADD COLUMN tipo ENUM('propietario','inquilino','ambos') NOT NULL DEFAULT 'propietario'");
        echo "  ✔ Columna 'tipo' agregada exitosamente." . PHP_EOL;
    } elseif ($currentType !== "enum('propietario','inquilino','ambos')") {
        echo "  🔄 Normalizando columna 'tipo' (actual: {$currentType})..." . PHP_EOL;
        $db->exec("ALTER TABLE personas MODIFY COLUMN tipo ENUM('propietario','inquilino','residente','ambos') NOT NULL DEFAULT 'propietario'");
        $db->exec("UPDATE personas SET tipo = 'inquilino' WHERE tipo = 'residente'");
        $db->exec("ALTER TABLE personas MODIFY COLUMN tipo ENUM('propietario','inquilino','ambos') NOT NULL DEFAULT 'propietario'");
        echo "  ✔ Columna 'tipo' normalizada a ENUM('propietario','inquilino','ambos')." . PHP_EOL;
    } else {
        echo "  ℹ La columna 'tipo' ya se encuentra en su formato final ENUM('propietario','inquilino','ambos')." . PHP_EOL;
    }

    // 2. Verificar e instalar índice para optimización
    $stmtIdx = $db->query("
        SELECT COUNT(1) 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personas' AND INDEX_NAME = 'idx_personas_unidad_estado'
    ");
    $hasIndex = (bool)$stmtIdx->fetchColumn();

    if (!$hasIndex) {
        echo "  ➕ Creando índice 'idx_personas_unidad_estado'..." . PHP_EOL;
        $db->exec("CREATE INDEX idx_personas_unidad_estado ON personas (unidad_id, estado)");
        echo "  ✔ Índice creado exitosamente." . PHP_EOL;
    } else {
        echo "  ℹ Índice 'idx_personas_unidad_estado' ya existe." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración de residentes completada con éxito." . PHP_EOL;
} catch (\Throwable $e) {
    echo PHP_EOL . "❌ Error durante la migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
