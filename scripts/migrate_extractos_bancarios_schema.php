<?php
/**
 * Migración Idempotente: Alineación de Esquema para extractos_bancarios
 * Ejecución: php scripts/migrate_extractos_bancarios_schema.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: Esquema de Extractos Bancarios ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    // 1. Verificar y agregar columna referencia_bancaria
    $stmtCol = $db->query("SHOW COLUMNS FROM extractos_bancarios LIKE 'referencia_bancaria'");
    if (!$stmtCol->fetch()) {
        $db->exec("ALTER TABLE extractos_bancarios ADD COLUMN referencia_bancaria VARCHAR(100) NOT NULL DEFAULT '' AFTER fecha_movimiento");
        echo "  ✔ Columna 'referencia_bancaria' agregada." . PHP_EOL;
    } else {
        echo "  ℹ Columna 'referencia_bancaria' ya existe." . PHP_EOL;
    }

    // 2. Verificar y agregar columna descripcion_banco
    $stmtCol = $db->query("SHOW COLUMNS FROM extractos_bancarios LIKE 'descripcion_banco'");
    if (!$stmtCol->fetch()) {
        $db->exec("ALTER TABLE extractos_bancarios ADD COLUMN descripcion_banco TEXT DEFAULT NULL AFTER referencia_bancaria");
        echo "  ✔ Columna 'descripcion_banco' agregada." . PHP_EOL;
    } else {
        echo "  ℹ Columna 'descripcion_banco' ya existe." . PHP_EOL;
    }

    // 3. Verificar y agregar columna admin_id
    $stmtCol = $db->query("SHOW COLUMNS FROM extractos_bancarios LIKE 'admin_id'");
    if (!$stmtCol->fetch()) {
        $db->exec("ALTER TABLE extractos_bancarios ADD COLUMN admin_id INT NULL AFTER pago_id");
        echo "  ✔ Columna 'admin_id' agregada." . PHP_EOL;
    } else {
        echo "  ℹ Columna 'admin_id' ya existe." . PHP_EOL;
    }

    // 4. Copiar datos legados si existen
    $stmtCheckRef = $db->query("SHOW COLUMNS FROM extractos_bancarios LIKE 'referencia'");
    if ($stmtCheckRef->fetch()) {
        $db->exec("UPDATE extractos_bancarios SET referencia_bancaria = referencia WHERE (referencia_bancaria IS NULL OR referencia_bancaria = '') AND referencia IS NOT NULL");
    }
    $stmtCheckDesc = $db->query("SHOW COLUMNS FROM extractos_bancarios LIKE 'descripcion'");
    if ($stmtCheckDesc->fetch()) {
        $db->exec("UPDATE extractos_bancarios SET descripcion_banco = descripcion WHERE descripcion_banco IS NULL AND descripcion IS NOT NULL");
    }

    // 5. Verificar índice compuesto idx_extracto_busqueda
    $stmtIdx = $db->query("SHOW INDEX FROM extractos_bancarios WHERE Key_name = 'idx_extracto_busqueda'");
    if (!$stmtIdx->fetch()) {
        $db->exec("ALTER TABLE extractos_bancarios ADD INDEX idx_extracto_busqueda (referencia_bancaria, monto, fecha_movimiento)");
        echo "  ✔ Índice 'idx_extracto_busqueda' creado." . PHP_EOL;
    } else {
        echo "  ℹ Índice 'idx_extracto_busqueda' ya existe." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración de extractos bancarios completada exitosamente." . PHP_EOL;
} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
