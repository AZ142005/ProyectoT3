<?php
/**
 * Migración: Tipología de Gastos (Globales vs Por Edificio) y Deprecación de Cuota Manual.
 * Ejecutar: php scripts/migrate_gastos_tipologia.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

try {
    $db = Database::getConnection();

    // 1. Verificar y agregar columna 'tipo_gasto' en gastos_comunes
    $checkTipo = $db->query("SHOW COLUMNS FROM gastos_comunes LIKE 'tipo_gasto'");
    if (!$checkTipo->fetch()) {
        $db->exec("ALTER TABLE gastos_comunes ADD COLUMN tipo_gasto ENUM('comun', 'individual') NOT NULL DEFAULT 'comun' AFTER descripcion");
        echo "[OK] Columna 'tipo_gasto' agregada a gastos_comunes.\n";
    } else {
        echo "[INFO] La columna 'tipo_gasto' ya existe en gastos_comunes.\n";
    }

    // 2. Verificar y agregar columna 'edificio_id' en gastos_comunes
    $checkEdificio = $db->query("SHOW COLUMNS FROM gastos_comunes LIKE 'edificio_id'");
    if (!$checkEdificio->fetch()) {
        $db->exec("ALTER TABLE gastos_comunes ADD COLUMN edificio_id INT(11) NULL DEFAULT NULL AFTER tipo_gasto");
        
        // Agregar índice y llave foránea opcional
        try {
            $db->exec("ALTER TABLE gastos_comunes ADD CONSTRAINT fk_gastos_edificio FOREIGN KEY (edificio_id) REFERENCES edificios(id) ON DELETE SET NULL");
        } catch (\Exception $e) {
            // Si ya existe índice o restricción, continuar
        }
        echo "[OK] Columna 'edificio_id' agregada a gastos_comunes.\n";
    } else {
        echo "[INFO] La columna 'edificio_id' ya existe en gastos_comunes.\n";
    }

    // 3. Verificar y agregar/sincronizar columna 'fecha_gasto' para compatibilidad
    $checkFechaGasto = $db->query("SHOW COLUMNS FROM gastos_comunes LIKE 'fecha_gasto'");
    if (!$checkFechaGasto->fetch()) {
        $db->exec("ALTER TABLE gastos_comunes ADD COLUMN fecha_gasto DATE NULL AFTER fecha");
        $db->exec("UPDATE gastos_comunes SET fecha_gasto = fecha WHERE fecha_gasto IS NULL");
        echo "[OK] Columna 'fecha_gasto' agregada y sincronizada en gastos_comunes.\n";
    } else {
        echo "[INFO] La columna 'fecha_gasto' ya existe en gastos_comunes.\n";
    }

    // 4. Modificar unidades para que cuota_mensual tenga valor por defecto 0.00
    $db->exec("ALTER TABLE unidades MODIFY COLUMN cuota_mensual DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    echo "[OK] Columna 'cuota_mensual' en unidades modificada con DEFAULT 0.00.\n";

    echo "[COMPLETADO] Migración de tipología de gastos ejecutada con éxito.\n";
    exit(0);
} catch (\Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
