-- ========================================================
-- MIGRACIÓN: Roles de Residentes (Propietario / Inquilino / Ambos)
-- ========================================================

-- 1. Ampliar ENUM temporalmente para compatibilidad
ALTER TABLE `personas` 
  MODIFY COLUMN `tipo` ENUM('propietario', 'inquilino', 'residente', 'ambos') NOT NULL DEFAULT 'propietario';

-- 2. Normalizar registros existentes
UPDATE `personas` SET `tipo` = 'inquilino' WHERE `tipo` = 'residente';

-- 3. Fijar ENUM final estandarizado
ALTER TABLE `personas` 
  MODIFY COLUMN `tipo` ENUM('propietario', 'inquilino', 'ambos') NOT NULL DEFAULT 'propietario';

-- 4. Índice para optimizar búsquedas por unidad y estado
CREATE INDEX `idx_personas_unidad_estado` ON `personas` (`unidad_id`, `estado`);
