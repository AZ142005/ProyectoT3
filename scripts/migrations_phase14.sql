-- =====================================================================
-- Migración Fase 14: Soporte para Nomenclatura Automática de Puestos
-- =====================================================================

ALTER TABLE `estacionamientos` MODIFY COLUMN `numero` VARCHAR(50) NOT NULL;
