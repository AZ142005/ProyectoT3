-- =====================================================================
-- Migración Fase 20: Destinos múltiples de comunicados (unidades específicas)
-- =====================================================================
-- comunicados_destinos: tabla de unión entre comunicados y unidades.
-- Registra a qué unidades específicas se dirige un comunicado, permitiendo
-- abarcar unidades de uno o varios edificios. Complementa los destinos
-- simples existentes (comunicados.edificio_id / comunicados.unidad_id),
-- que quedan en NULL cuando se publica en modo multi-unidad.
--
-- ROLLBACK MANUAL
--   DROP TABLE comunicados_destinos;
--
-- IDEMPOTENTE: puede re-ejecutarse sin efectos secundarios.
-- =====================================================================

SET @db = DATABASE();

-- ---------------------------------------------------------------------
-- 1. comunicados_destinos: unidades destino por comunicado
-- ---------------------------------------------------------------------
SET @tabla_comunicados_destinos = (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'comunicados_destinos');

SET @sql_comunicados_destinos = IF(@tabla_comunicados_destinos = 0,
    'CREATE TABLE comunicados_destinos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comunicado_id INT NOT NULL,
        unidad_id INT NOT NULL,
        KEY idx_comdest_comunicado (comunicado_id),
        KEY idx_comdest_unidad (unidad_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
    'SELECT "comunicados_destinos ya existe"');
PREPARE stmt_comunicados_destinos FROM @sql_comunicados_destinos;
EXECUTE stmt_comunicados_destinos;
DEALLOCATE PREPARE stmt_comunicados_destinos;

SELECT 'Migración Fase 20 (SQL) aplicada o ya aplicada' AS resultado;
