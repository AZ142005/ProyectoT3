<?php
/**
 * Migración Idempotente: Registro de Cartas de Deuda Enviadas
 * Ejecución: php scripts/migrate_cartas_deuda_envios.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: Registro de Cartas de Deuda Enviadas ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    // 1. Crear tabla cartas_deuda_envios (clave de cooldown: propietario o unidad)
    $db->exec("
        CREATE TABLE IF NOT EXISTS `cartas_deuda_envios` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `unidad_id` INT NOT NULL,
          `propietario_id` INT NULL,
          `canal` VARCHAR(20) NOT NULL DEFAULT 'email',
          `usuario_id` INT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          KEY `idx_carta_envio_unidad` (`unidad_id`, `created_at`),
          KEY `idx_carta_envio_propietario` (`propietario_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "  ✔ Tabla 'cartas_deuda_envios' lista." . PHP_EOL;

    echo PHP_EOL . "✅ Migración de Cartas de Deuda Enviadas completada con éxito." . PHP_EOL;
} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
