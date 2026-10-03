-- =====================================================================
-- Migración Fase 19: Comunicados con expiración
-- =====================================================================
-- comunicados: columna fecha_expiracion DATETIME NULL DEFAULT NULL.
-- NULL = sin vencimiento. Los comunicados vencidos se ocultan de la
-- cartelera del residente y se auto-eliminan lógicamente (soft delete)
-- al abrir el módulo de administración.
--
-- ROLLBACK MANUAL
--   ALTER TABLE comunicados DROP COLUMN fecha_expiracion;
--
-- IDEMPOTENTE: puede re-ejecutarse sin efectos secundarios.
-- =====================================================================

SET @db = DATABASE();

-- ---------------------------------------------------------------------
-- 1. comunicados: fecha_expiracion (NULL = sin vencimiento)
-- ---------------------------------------------------------------------
SET @col_fecha_expiracion = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'comunicados' AND COLUMN_NAME = 'fecha_expiracion');

SET @sql_fecha_expiracion = IF(@col_fecha_expiracion = 0,
    'ALTER TABLE comunicados ADD COLUMN fecha_expiracion DATETIME NULL DEFAULT NULL',
    'SELECT "comunicados.fecha_expiracion ya existe"');
PREPARE stmt_fecha_expiracion FROM @sql_fecha_expiracion;
EXECUTE stmt_fecha_expiracion;
DEALLOCATE PREPARE stmt_fecha_expiracion;

SELECT 'Migración Fase 19 (SQL) aplicada o ya aplicada' AS resultado;
