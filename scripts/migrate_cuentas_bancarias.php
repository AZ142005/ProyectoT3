<?php
/**
 * Migración Idempotente: Cuentas Bancarias Autorizadas
 * Ejecución: php scripts/migrate_cuentas_bancarias.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: Cuentas Bancarias Autorizadas (Fase 13) ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    // 1. Crear tabla cuentas_bancarias
    $db->exec("
        CREATE TABLE IF NOT EXISTS `cuentas_bancarias` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `banco` VARCHAR(100) NOT NULL,
          `tipo_cuenta` ENUM('corriente', 'ahorro') NOT NULL DEFAULT 'corriente',
          `numero_cuenta` VARCHAR(20) NOT NULL,
          `titular` VARCHAR(150) NOT NULL,
          `tipo_identificacion` ENUM('V', 'J', 'E', 'G') NOT NULL DEFAULT 'J',
          `identificacion` VARCHAR(20) NOT NULL,
          `telefono_pago_movil` VARCHAR(20) DEFAULT NULL,
          `permite_transferencia` TINYINT(1) NOT NULL DEFAULT 1,
          `permite_pago_movil` TINYINT(1) NOT NULL DEFAULT 1,
          `activa` TINYINT(1) NOT NULL DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY `uk_cuenta` (`numero_cuenta`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "  ✔ Tabla 'cuentas_bancarias' lista." . PHP_EOL;

    // 2. Agregar columna cuenta_bancaria_id en pagos si no existe
    $stmtCol = $db->query("SHOW COLUMNS FROM pagos LIKE 'cuenta_bancaria_id'");
    if (!$stmtCol->fetch()) {
        $db->exec("ALTER TABLE pagos ADD COLUMN cuenta_bancaria_id INT NULL AFTER banco_receptor");
        echo "  ✔ Columna 'cuenta_bancaria_id' añadida a 'pagos'." . PHP_EOL;
    } else {
        echo "  ℹ Columna 'cuenta_bancaria_id' ya existía en 'pagos'." . PHP_EOL;
    }

    // 3. Insertar cuenta semilla Banco de Venezuela
    $stmtSeed = $db->prepare("SELECT id FROM cuentas_bancarias WHERE numero_cuenta = :num LIMIT 1");
    $stmtSeed->execute(['num' => '01020000000000007558']);
    if (!$stmtSeed->fetch()) {
        $stmtInsert = $db->prepare("
            INSERT INTO cuentas_bancarias 
            (banco, tipo_cuenta, numero_cuenta, titular, tipo_identificacion, identificacion, telefono_pago_movil, permite_transferencia, permite_pago_movil, activa)
            VALUES 
            (:banco, :tipo_cuenta, :numero_cuenta, :titular, :tipo_id, :identificacion, :telefono, 1, 1, 1)
        ");
        $stmtInsert->execute([
            'banco'         => 'Banco de Venezuela',
            'tipo_cuenta'   => 'corriente',
            'numero_cuenta' => '01020000000000007558',
            'titular'       => 'Condominio Digital',
            'tipo_id'       => 'J',
            'identificacion'=> 'J-12345678-0',
            'telefono'      => '04121234567'
        ]);
        echo "  ✔ Cuenta semilla Banco de Venezuela insertada." . PHP_EOL;
    } else {
        echo "  ℹ Cuenta semilla ya registrada." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración de Cuentas Bancarias completada con éxito." . PHP_EOL;
} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
