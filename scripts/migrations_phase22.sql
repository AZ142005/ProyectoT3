-- =====================================================================
-- Migración Fase 22: Deudas manuales por unidad y período
-- =====================================================================
-- deudas_manuales: monto de deuda elegido manualmente para unidades
-- específicas (o todo el condominio) en un mes/año. Al emitir la deuda
-- del período (upsert por numero_factura FAC-…), el monto manual
-- sustituye la cuota prorrateada de esa unidad; el monto se aplica
-- completo por unidad (no se reparte entre edificios ni unidades).
--
-- ROLLBACK MANUAL
--   DROP TABLE deudas_manuales;
--
-- IDEMPOTENTE: puede re-ejecutarse sin efectos secundarios.
-- =====================================================================

SET @db = DATABASE();

-- ---------------------------------------------------------------------
-- 1. deudas_manuales: upsert por (unidad_id, mes, anio) con baja lógica
-- ---------------------------------------------------------------------
SET @tabla_deudas_manuales = (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'deudas_manuales');

SET @sql_deudas_manuales = IF(@tabla_deudas_manuales = 0,
    'CREATE TABLE deudas_manuales (
        id INT(11) NOT NULL AUTO_INCREMENT,
        unidad_id INT(11) NOT NULL,
        mes TINYINT NOT NULL,
        anio SMALLINT NOT NULL,
        monto DECIMAL(10,2) NOT NULL,
        admin_id INT(11) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_deuda_manual_unidad_periodo (unidad_id, mes, anio),
        KEY idx_deuda_manual_periodo (mes, anio),
        CONSTRAINT fk_deuda_manual_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
    'SELECT "deudas_manuales ya existe"');
PREPARE stmt_deudas_manuales FROM @sql_deudas_manuales;
EXECUTE stmt_deudas_manuales;
DEALLOCATE PREPARE stmt_deudas_manuales;

SELECT 'Migración Fase 22 (SQL) aplicada o ya aplicada' AS resultado;
