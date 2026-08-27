-- Fase 9: Migraciones para G3 — Estructura y Activos Físicos
SET @db = DATABASE();

-- 1. Agregar deleted_at a vehiculos para soft delete
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vehiculos' AND COLUMN_NAME='deleted_at');
SET @sql = IF(@x=0,
    'ALTER TABLE vehiculos ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER observaciones',
    'SELECT "vehiculos.deleted_at ya existe" AS status');
PREPARE s1 FROM @sql; EXECUTE s1; DEALLOCATE PREPARE s1;

SELECT '✅ Migración Fase 9 completada' AS resultado;
