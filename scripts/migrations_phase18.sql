-- =====================================================================
-- Migración Fase 18: Endurecimiento anti-duplicados (extractos + gastos)
-- =====================================================================
-- 1. extractos_bancarios: UNIQUE KEY uk_extracto_identidad
--    (referencia_bancaria, fecha_movimiento, monto)
-- 2. gastos_comunes: columna generada dup_guard UNIFICADA (identidad F con
--    N° de factura y S sin factura) + UNIQUE KEY uk_gasto_dup_guard.
--    Las filas soft-deleted liberan la identidad (NULL).
-- 3. gastos_comunes: se ELIMINA el índice plano uk_gasto_factura_periodo si
--    existe (retenía la identidad de filas borradas y bloqueaba el re-registro;
--    la unicidad activa con factura queda cubierta por uk_gasto_dup_guard).
-- 4. gastos_comunes: columna soporte_hash CHAR(64) NULL + idx_gastos_soporte_hash
--
-- ANÁLISIS PREVIO: cada bloque cuenta grupos duplicados antes de crear su
-- índice. Si hay conflictos, OMITE la creación y emite un reporte.
-- NOTA: el gemelo ejecutable `scripts/migrate_fase18_duplicados.php` ABORTA
-- (exit 1) con el reporte de grupos. Este SQL no puede abortar dentro de SQL
-- dinámico (SIGNAL no está soportado en el protocolo preparado de MariaDB),
-- por eso degrada a "omitir + reportar"; use el script PHP en despliegues.
--
-- ROLLBACK MANUAL
--   ALTER TABLE extractos_bancarios DROP INDEX uk_extracto_identidad;
--   ALTER TABLE gastos_comunes DROP INDEX uk_gasto_dup_guard;
--   ALTER TABLE gastos_comunes DROP COLUMN dup_guard;
--   ALTER TABLE gastos_comunes DROP INDEX idx_gastos_soporte_hash;
--   ALTER TABLE gastos_comunes DROP COLUMN soporte_hash;
--   -- El índice plano uk_gasto_factura_periodo NO se recrea: fue reemplazado
--   -- a propósito por la guardia consciente de soft-delete.
--
-- IDEMPOTENTE: puede re-ejecutarse sin efectos secundarios.
-- =====================================================================

SET @db = DATABASE();

-- ---------------------------------------------------------------------
-- 1. extractos_bancarios: identidad única (ref, fecha, monto)
-- ---------------------------------------------------------------------
SET @dups_extracto = (SELECT COUNT(*) FROM (
    SELECT 1 FROM extractos_bancarios
    GROUP BY referencia_bancaria, fecha_movimiento, monto
    HAVING COUNT(*) > 1
) d);

SET @idx_extracto = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'extractos_bancarios' AND INDEX_NAME = 'uk_extracto_identidad');

SET @sql_extracto = IF(@idx_extracto > 0,
    'SELECT "uk_extracto_identidad ya existe"',
    IF(@dups_extracto > 0,
        CONCAT('SELECT "ABORTADO: ', @dups_extracto, ' grupo(s) duplicado(s) en extractos_bancarios; NO se creó uk_extracto_identidad"'),
        'ALTER TABLE extractos_bancarios ADD UNIQUE KEY uk_extracto_identidad (referencia_bancaria, fecha_movimiento, monto)'
    )
);
PREPARE stmt_extracto FROM @sql_extracto;
EXECUTE stmt_extracto;
DEALLOCATE PREPARE stmt_extracto;

-- ---------------------------------------------------------------------
-- 2. gastos_comunes: guardia unificada dup_guard (F y S) + uk_gasto_dup_guard
-- ---------------------------------------------------------------------
SET @expr_dup_gasto = "IF(`deleted_at` IS NOT NULL, NULL, IF(`nro_factura_proveedor` IS NULL OR `nro_factura_proveedor` = '', CONCAT('S|', IFNULL(`mes`,0), '|', IFNULL(`anio`,0), '|', IFNULL(`categoria_id`,0), '|', IFNULL(`monto_total`,0), '|', IFNULL(`fecha_gasto`,'0000-00-00'), '|', LEFT(IFNULL(`proveedor`,''),45), '|', LEFT(`descripcion`,60), '|', `tipo_gasto`, '|', IFNULL(`edificio_id`,0)), CONCAT('F|', IFNULL(`mes`,0), '|', IFNULL(`anio`,0), '|', LEFT(`nro_factura_proveedor`,50), '|', LEFT(IFNULL(`proveedor`,''),45))))";

SET @dups_gasto_guard = (SELECT COUNT(*) FROM (
    SELECT 1 FROM gastos_comunes
    WHERE deleted_at IS NULL
    GROUP BY IF(`deleted_at` IS NOT NULL, NULL, IF(`nro_factura_proveedor` IS NULL OR `nro_factura_proveedor` = '', CONCAT('S|', IFNULL(`mes`,0), '|', IFNULL(`anio`,0), '|', IFNULL(`categoria_id`,0), '|', IFNULL(`monto_total`,0), '|', IFNULL(`fecha_gasto`,'0000-00-00'), '|', LEFT(IFNULL(`proveedor`,''),45), '|', LEFT(`descripcion`,60), '|', `tipo_gasto`, '|', IFNULL(`edificio_id`,0)), CONCAT('F|', IFNULL(`mes`,0), '|', IFNULL(`anio`,0), '|', LEFT(`nro_factura_proveedor`,50), '|', LEFT(IFNULL(`proveedor`,''),45))))
    HAVING COUNT(*) > 1
) d);

SET @col_dup_gasto = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gastos_comunes' AND COLUMN_NAME = 'dup_guard');

SET @sql_dup_gasto = IF(@dups_gasto_guard > 0,
    CONCAT('SELECT "ABORTADO: ', @dups_gasto_guard, ' grupo(s) duplicado(s) activos (F/S) en gastos_comunes; NO se creó/modificó dup_guard"'),
    IF(@col_dup_gasto = 0,
        CONCAT('ALTER TABLE gastos_comunes ADD COLUMN dup_guard VARCHAR(191) GENERATED ALWAYS AS (', @expr_dup_gasto, ') STORED'),
        CONCAT('ALTER TABLE gastos_comunes MODIFY COLUMN dup_guard VARCHAR(191) GENERATED ALWAYS AS (', @expr_dup_gasto, ') STORED')
    )
);
PREPARE stmt_dup_gasto FROM @sql_dup_gasto;
EXECUTE stmt_dup_gasto;
DEALLOCATE PREPARE stmt_dup_gasto;

SET @idx_dup_gasto = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gastos_comunes' AND INDEX_NAME = 'uk_gasto_dup_guard');

SET @sql_idx_dup_gasto = IF(@idx_dup_gasto > 0,
    'SELECT "uk_gasto_dup_guard ya existe"',
    IF(@dups_gasto_guard > 0,
        CONCAT('SELECT "ABORTADO: ', @dups_gasto_guard, ' grupo(s) duplicado(s) activos; NO se creó uk_gasto_dup_guard"'),
        'ALTER TABLE gastos_comunes ADD UNIQUE KEY uk_gasto_dup_guard (dup_guard)'
    )
);
PREPARE stmt_idx_dup_gasto FROM @sql_idx_dup_gasto;
EXECUTE stmt_idx_dup_gasto;
DEALLOCATE PREPARE stmt_idx_dup_gasto;

-- ---------------------------------------------------------------------
-- 3. gastos_comunes: eliminar el índice plano uk_gasto_factura_periodo si existe
-- ---------------------------------------------------------------------
SET @idx_gasto_f = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gastos_comunes' AND INDEX_NAME = 'uk_gasto_factura_periodo');

SET @sql_gasto_f = IF(@idx_gasto_f > 0,
    'ALTER TABLE gastos_comunes DROP INDEX uk_gasto_factura_periodo',
    'SELECT "uk_gasto_factura_periodo no existe (correcto)"');
PREPARE stmt_gasto_f FROM @sql_gasto_f;
EXECUTE stmt_gasto_f;
DEALLOCATE PREPARE stmt_gasto_f;

-- ---------------------------------------------------------------------
-- 4. gastos_comunes: soporte_hash + idx_gastos_soporte_hash
-- ---------------------------------------------------------------------
SET @col_soporte_hash = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gastos_comunes' AND COLUMN_NAME = 'soporte_hash');
SET @sql_soporte_hash = IF(@col_soporte_hash = 0,
    'ALTER TABLE gastos_comunes ADD COLUMN soporte_hash CHAR(64) NULL AFTER soporte_digital',
    'SELECT "gastos_comunes.soporte_hash ya existe"');
PREPARE stmt_soporte_hash FROM @sql_soporte_hash;
EXECUTE stmt_soporte_hash;
DEALLOCATE PREPARE stmt_soporte_hash;

SET @idx_soporte_hash = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gastos_comunes' AND INDEX_NAME = 'idx_gastos_soporte_hash');
SET @sql_idx_soporte_hash = IF(@idx_soporte_hash = 0,
    'ALTER TABLE gastos_comunes ADD INDEX idx_gastos_soporte_hash (soporte_hash)',
    'SELECT "idx_gastos_soporte_hash ya existe"');
PREPARE stmt_idx_soporte_hash FROM @sql_idx_soporte_hash;
EXECUTE stmt_idx_soporte_hash;
DEALLOCATE PREPARE stmt_idx_soporte_hash;

SELECT 'Migración Fase 18 (SQL) aplicada o ya aplicada' AS resultado;
