-- =====================================================================
-- Migración Fase 15: Autenticación por Correo Electrónico
-- Unicidad de Correo y Desvinculación de Cédula como credencial primaria
-- =====================================================================

-- 1. Desvincular restricción UNIQUE de 'cedula' en 'usuarios' y asegurar NULL
-- (La cédula se mantiene como dato demográfico sin unicidad estricta)
ALTER TABLE `usuarios` MODIFY COLUMN `cedula` VARCHAR(20) NULL;

-- 2. Restricción UNIQUE en el campo 'email' de la tabla 'usuarios'
ALTER TABLE `usuarios` MODIFY COLUMN `email` VARCHAR(150) NOT NULL;
-- En caso de que no exista el índice único sobre email:
-- ALTER TABLE `usuarios` ADD UNIQUE KEY `uq_usuarios_email` (`email`);

-- 3. Restricción UNIQUE en el campo 'email' de la tabla 'personas'
ALTER TABLE `personas` MODIFY COLUMN `email` VARCHAR(150) NOT NULL;
-- En caso de que no exista el índice único sobre email:
-- ALTER TABLE `personas` ADD UNIQUE KEY `uq_personas_email` (`email`);
