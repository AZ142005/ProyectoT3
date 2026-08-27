<?php
/**
 * Migración: Tablas para RateLimiter (DB-based) y JWT Blacklist.
 *
 * Ejecutar: php scripts/migrate_fase1_auth_security.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración FASE 1: Auth & Security ===\n\n";

try {
    $db = Database::getConnection();

    // 1. Tabla rate_limits — sustituye el rate limiter de sesión
    echo "[1/2] Creando tabla 'rate_limits'...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(64) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 1,
            window_start DATETIME NOT NULL,
            INDEX idx_key_ip (`key`, ip),
            INDEX idx_window (window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   OK — tabla 'rate_limits' creada.\n";

    // 2. Tabla jwt_blacklist — invalidación de tokens JWT al logout
    echo "[2/2] Creando tabla 'jwt_blacklist'...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS jwt_blacklist (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            jti VARCHAR(64) NOT NULL,
            user_id INT UNSIGNED NULL,
            expires_at DATETIME NOT NULL,
            blacklisted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE INDEX idx_jti (jti),
            INDEX idx_expires (expires_at),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   OK — tabla 'jwt_blacklist' creada.\n";

    echo "\n✅ Migración completada exitosamente.\n";

} catch (\Throwable $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}