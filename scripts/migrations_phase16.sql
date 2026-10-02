-- =====================================================================
-- Migración Fase 16: Concurrencia Estricta y Cruce Inteligente 1:1
-- Control de concurrencia atómica, tabla de vínculo y auditoría append-only
-- =====================================================================

-- 1. Tabla canonical de Movimientos Bancarios (Abonos)
CREATE TABLE IF NOT EXISTS `movimientos_bancarios` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `banco` VARCHAR(100) NOT NULL,
  `fecha` DATE NOT NULL,
  `fecha_movimiento` DATE NULL,
  `referencia` VARCHAR(100) NOT NULL,
  `referencia_bancaria` VARCHAR(100) NULL,
  `descripcion` TEXT NULL,
  `descripcion_banco` TEXT NULL,
  `importe` DECIMAL(12,2) NOT NULL,
  `monto` DECIMAL(12,2) NULL,
  `tipo` ENUM('credito', 'debito') NOT NULL DEFAULT 'credito',
  `tipo_movimiento` ENUM('credito', 'debito') NOT NULL DEFAULT 'credito',
  `estado` ENUM('disponible', 'conciliado', 'anulado') NOT NULL DEFAULT 'disponible',
  `lote_importacion` VARCHAR(50) NULL,
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_mov_estado` (`estado`),
  INDEX `idx_mov_estado_fecha` (`estado`, `fecha`, `importe`),
  INDEX `idx_mov_referencia` (`referencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Asegurar columnas de estado e índices en tabla legacy 'extractos_bancarios'
ALTER TABLE `extractos_bancarios`
  ADD COLUMN IF NOT EXISTS `estado` ENUM('disponible', 'conciliado', 'anulado') NOT NULL DEFAULT 'disponible' AFTER `estado_conciliacion`,
  ADD COLUMN IF NOT EXISTS `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS `idx_extractos_estado` ON `extractos_bancarios` (`estado`);
CREATE INDEX IF NOT EXISTS `idx_extractos_estado_fecha` ON `extractos_bancarios` (`estado`, `fecha_movimiento`, `monto`);

-- 3. Tabla de Vínculo de Conciliación 1:1 Estricto (Relación anti-carreras)
CREATE TABLE IF NOT EXISTS `conciliacion_abono_pago` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `movimiento_id` BIGINT NOT NULL,
  `pago_id` BIGINT NOT NULL,
  `conciliado_por` BIGINT NOT NULL,
  `conciliado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `origen_tipo` VARCHAR(20) NOT NULL DEFAULT 'pago',
  `idempotency_key` VARCHAR(64) NULL,
  CONSTRAINT `uq_movimiento` UNIQUE (`movimiento_id`),
  CONSTRAINT `uq_pago` UNIQUE (`pago_id`),
  INDEX `idx_cap_idempotency` (`idempotency_key`),
  INDEX `idx_cap_movimiento` (`movimiento_id`),
  INDEX `idx_cap_pago` (`pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Tabla de Auditoría Append-Only de Transiciones de Abonos
CREATE TABLE IF NOT EXISTS `historial_estado_abono` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `movimiento_id` BIGINT NOT NULL,
  `pago_id` BIGINT NULL,
  `estado_anterior` VARCHAR(30) NULL,
  `estado_nuevo` VARCHAR(30) NOT NULL,
  `usuario_id` BIGINT NOT NULL,
  `resultado` ENUM('exito', 'rechazado', 'error') NOT NULL DEFAULT 'exito',
  `codigo_negocio` VARCHAR(50) NULL,
  `detalles` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_hist_mov` (`movimiento_id`),
  INDEX `idx_hist_res` (`resultado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
