-- ============================================================================
-- Migración Fase 13: Cuentas Bancarias Autorizadas y Relación con Pagos
-- ============================================================================

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

-- Columna cuenta_bancaria_id en tabla pagos
SET @col_pago_cb = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pagos' AND column_name = 'cuenta_bancaria_id');
SET @sql_pago_cb = IF(@col_pago_cb = 0, 'ALTER TABLE pagos ADD COLUMN cuenta_bancaria_id INT NULL AFTER banco_receptor', 'SELECT "columna cuenta_bancaria_id ya existe"');
PREPARE stmt_pago_cb FROM @sql_pago_cb;
EXECUTE stmt_pago_cb;
DEALLOCATE PREPARE stmt_pago_cb;

-- Cuenta semilla inicial: Banco de Venezuela (Cuenta oficial para cobranzas)
INSERT IGNORE INTO `cuentas_bancarias` 
(`id`, `banco`, `tipo_cuenta`, `numero_cuenta`, `titular`, `tipo_identificacion`, `identificacion`, `telefono_pago_movil`, `permite_transferencia`, `permite_pago_movil`, `activa`)
VALUES 
(1, 'Banco de Venezuela', 'corriente', '01020000000000007558', 'Condominio Digital', 'J', 'J-12345678-0', '04121234567', 1, 1, 1);
