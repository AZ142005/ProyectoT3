<?php
/**
 * FASE 11 — Agregar columnas pagina_soporte y extracto_texto a gastos_comunes (RF 31, RF 34)
 * Ejecutar: php scripts/migrate_gastos_maestro.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

try {
    $db = Database::getConnection();

    // 1. Verificar y agregar columna 'pagina_soporte'
    $checkPagina = $db->query("SHOW COLUMNS FROM gastos_comunes LIKE 'pagina_soporte'");
    if (!$checkPagina->fetch()) {
        $db->exec("ALTER TABLE gastos_comunes ADD COLUMN pagina_soporte INT NULL DEFAULT 1 AFTER soporte_digital");
        echo "[OK] Columna 'pagina_soporte' agregada a gastos_comunes.\n";
    } else {
        echo "[INFO] La columna 'pagina_soporte' ya existe en gastos_comunes.\n";
    }

    // 2. Verificar y agregar columna 'extracto_texto'
    $checkExtracto = $db->query("SHOW COLUMNS FROM gastos_comunes LIKE 'extracto_texto'");
    if (!$checkExtracto->fetch()) {
        $db->exec("ALTER TABLE gastos_comunes ADD COLUMN extracto_texto TEXT NULL DEFAULT NULL AFTER pagina_soporte");
        echo "[OK] Columna 'extracto_texto' agregada a gastos_comunes.\n";
    } else {
        echo "[INFO] La columna 'extracto_texto' ya existe en gastos_comunes.\n";
    }

    echo "[COMPLETADO] Migración de gastos_comunes ejecutada con éxito.\n";
    exit(0);
} catch (\Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
