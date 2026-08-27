<?php
/**
 * FASE 2 M3 — Agregar columna ip_address a log_auditoria
 * Ejecutar: php scripts/migrate_fase2_ip_auditoria.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

try {
    $db = Database::getConnection();

    $check = $db->query("SHOW COLUMNS FROM log_auditoria LIKE 'ip_address'");
    if ($check->fetch()) {
        echo "[OK] La columna 'ip_address' ya existe en log_auditoria.\n";
        exit(0);
    }

    $db->exec("ALTER TABLE log_auditoria ADD COLUMN ip_address VARCHAR(45) NULL AFTER motivo");
    echo "[OK] Columna 'ip_address' agregada a log_auditoria.\n";
} catch (\Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
