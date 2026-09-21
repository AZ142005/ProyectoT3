-- Fase 11: Ingesta y Justificación de Gastos con PDF Maestro (RF 30, RF 31, RF 34)
-- Idempotente: crea columnas solo si no existen

SET @db = DATABASE();

-- 1. pagina_soporte en gastos_comunes
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='gastos_comunes' AND COLUMN_NAME='pagina_soporte');
SET @sql = IF(@x=0,
    'ALTER TABLE gastos_comunes ADD COLUMN pagina_soporte INT NULL DEFAULT 1 AFTER soporte_digital',
    'SELECT "pagina_soporte ya existe en gastos_comunes" AS status');
PREPARE s1 FROM @sql; EXECUTE s1; DEALLOCATE PREPARE s1;

-- 2. extracto_texto en gastos_comunes
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='gastos_comunes' AND COLUMN_NAME='extracto_texto');
SET @sql = IF(@x=0,
    'ALTER TABLE gastos_comunes ADD COLUMN extracto_texto TEXT NULL DEFAULT NULL AFTER pagina_soporte',
    'SELECT "extracto_texto ya existe en gastos_comunes" AS status');
PREPARE s2 FROM @sql; EXECUTE s2; DEALLOCATE PREPARE s2;
