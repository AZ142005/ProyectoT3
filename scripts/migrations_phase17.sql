-- =====================================================================
-- Migración Fase 17: Protección contra Duplicados en Comprobantes de Pago
-- y Optimización de Índices de Referencia y Hash de Archivos SHA-256
-- =====================================================================

-- 1. Tabla comprobantes_pago: referencia_norm y archivo_hash
SET @db = DATABASE();

SET @col_ref_norm = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'comprobantes_pago' AND COLUMN_NAME = 'referencia_norm');
SET @sql_ref_norm = IF(@col_ref_norm = 0, 'ALTER TABLE comprobantes_pago ADD COLUMN referencia_norm VARCHAR(100) NULL AFTER referencia', 'SELECT "comprobantes_pago.referencia_norm ya existe"');
PREPARE stmt_ref_norm FROM @sql_ref_norm;
EXECUTE stmt_ref_norm;
DEALLOCATE PREPARE stmt_ref_norm;

SET @col_hash_comp = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'comprobantes_pago' AND COLUMN_NAME = 'archivo_hash');
SET @sql_hash_comp = IF(@col_hash_comp = 0, 'ALTER TABLE comprobantes_pago ADD COLUMN archivo_hash CHAR(64) NULL AFTER archivo', 'SELECT "comprobantes_pago.archivo_hash ya existe"');
PREPARE stmt_hash_comp FROM @sql_hash_comp;
EXECUTE stmt_hash_comp;
DEALLOCATE PREPARE stmt_hash_comp;

-- 2. Guardia única y columna generada en comprobantes_pago
SET @col_dup_guard = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'comprobantes_pago' AND COLUMN_NAME = 'dup_guard');
SET @expr_dup_comp = "IF(`estado` = 'rechazado' OR (`deleted_at` IS NOT NULL AND `estado` <> 'aprobado'), NULL, IF(`referencia_norm` IS NULL OR `referencia_norm` = '', CONCAT('S|', `factura_id`, '|', `fecha_pago`, '|', `monto`), CONCAT('R|', `factura_id`, '|', `referencia_norm`)))";

SET @sql_dup_guard = IF(@col_dup_guard = 0, 
    CONCAT('ALTER TABLE comprobantes_pago ADD COLUMN dup_guard VARCHAR(191) GENERATED ALWAYS AS (', @expr_dup_comp, ') STORED'),
    CONCAT('ALTER TABLE comprobantes_pago MODIFY COLUMN dup_guard VARCHAR(191) GENERATED ALWAYS AS (', @expr_dup_comp, ') STORED')
);
PREPARE stmt_dup_guard FROM @sql_dup_guard;
EXECUTE stmt_dup_guard;
DEALLOCATE PREPARE stmt_dup_guard;

SET @idx_dup_guard = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'comprobantes_pago' AND INDEX_NAME = 'uk_comprobante_dup_guard');
SET @sql_idx_dup_guard = IF(@idx_dup_guard = 0, 'ALTER TABLE comprobantes_pago ADD UNIQUE KEY uk_comprobante_dup_guard (dup_guard)', 'SELECT "uk_comprobante_dup_guard ya existe"');
PREPARE stmt_idx_dup_guard FROM @sql_idx_dup_guard;
EXECUTE stmt_idx_dup_guard;
DEALLOCATE PREPARE stmt_idx_dup_guard;

SET @idx_ref_comp = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'comprobantes_pago' AND INDEX_NAME = 'idx_comprobantes_referencia_norm');
SET @sql_idx_ref_comp = IF(@idx_ref_comp = 0, 'ALTER TABLE comprobantes_pago ADD INDEX idx_comprobantes_referencia_norm (referencia_norm)', 'SELECT "idx_comprobantes_referencia_norm ya existe"');
PREPARE stmt_idx_ref_comp FROM @sql_idx_ref_comp;
EXECUTE stmt_idx_ref_comp;
DEALLOCATE PREPARE stmt_idx_ref_comp;

SET @idx_hash_comp = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'comprobantes_pago' AND INDEX_NAME = 'idx_comprobantes_archivo_hash');
SET @sql_idx_hash_comp = IF(@idx_hash_comp = 0, 'ALTER TABLE comprobantes_pago ADD INDEX idx_comprobantes_archivo_hash (archivo_hash)', 'SELECT "idx_comprobantes_archivo_hash ya existe"');
PREPARE stmt_idx_hash_comp FROM @sql_idx_hash_comp;
EXECUTE stmt_idx_hash_comp;
DEALLOCATE PREPARE stmt_idx_hash_comp;

-- 3. Tabla pagos: archivo_hash e índices
SET @col_hash_pagos = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pagos' AND COLUMN_NAME = 'archivo_hash');
SET @sql_hash_pagos = IF(@col_hash_pagos = 0, 'ALTER TABLE pagos ADD COLUMN archivo_hash CHAR(64) NULL AFTER archivo', 'SELECT "pagos.archivo_hash ya existe"');
PREPARE stmt_hash_pagos FROM @sql_hash_pagos;
EXECUTE stmt_hash_pagos;
DEALLOCATE PREPARE stmt_hash_pagos;

SET @idx_ref_pagos = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pagos' AND INDEX_NAME = 'idx_pagos_referencia_norm');
SET @sql_idx_ref_pagos = IF(@idx_ref_pagos = 0, 'ALTER TABLE pagos ADD INDEX idx_pagos_referencia_norm (referencia_norm)', 'SELECT "idx_pagos_referencia_norm ya existe"');
PREPARE stmt_idx_ref_pagos FROM @sql_idx_ref_pagos;
EXECUTE stmt_idx_ref_pagos;
DEALLOCATE PREPARE stmt_idx_ref_pagos;

SET @idx_hash_pagos = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pagos' AND INDEX_NAME = 'idx_pagos_archivo_hash');
SET @sql_idx_hash_pagos = IF(@idx_hash_pagos = 0, 'ALTER TABLE pagos ADD INDEX idx_pagos_archivo_hash (archivo_hash)', 'SELECT "idx_pagos_archivo_hash ya existe"');
PREPARE stmt_idx_hash_pagos FROM @sql_idx_hash_pagos;
EXECUTE stmt_idx_hash_pagos;
DEALLOCATE PREPARE stmt_idx_hash_pagos;
