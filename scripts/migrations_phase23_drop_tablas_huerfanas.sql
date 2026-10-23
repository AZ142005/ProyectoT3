-- =====================================================================
-- Migración Fase 23: retiro de tablas sin uso
-- =====================================================================
-- Retira dos tablas que dejaron de tener función en el sistema:
--
--   conciliacion_lotes
--       Nunca fue usada por el código. El lote se guarda como texto en
--       extractos_bancarios.lote_importacion y obtenerLotes() arma el
--       listado agrupando esa columna.
--
--   movimientos_bancarios
--       Copia de extractos_bancarios creada por el backfill de la fase 16.
--       El importador nunca volvió a escribir en ella; la bandeja de
--       conciliación lee extractos_bancarios. Antes de borrarla esta
--       migración verifica que no existan filas que solo vivan ahí.
--
-- SEGURIDAD
--   * No borra movimientos_bancarios si encuentra filas sin equivalente
--     en extractos_bancarios: en ese caso informa y no hace nada.
--   * No borra vínculos de conciliacion_abono_pago: esos registros
--     apuntan a extractos_bancarios.id.
--
-- ROLLBACK MANUAL
--   No aplica: las tablas se retiran del modelo. Para restaurar los datos
--   usar el respaldo previo (storage/backups/retiro-tablas-*.sql).
--
-- IDEMPOTENTE: puede re-ejecutarse sin efectos secundarios.
-- =====================================================================

SET @db = DATABASE();

-- ---------------------------------------------------------------------
-- 1. conciliacion_lotes
-- ---------------------------------------------------------------------
SET @existe_lotes = (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'conciliacion_lotes'
);

SET @sql_lotes = IF(@existe_lotes = 0,
    'SELECT ''conciliacion_lotes: ya no existe, nada que hacer'' AS resultado',
    'DROP TABLE `conciliacion_lotes`'
);
PREPARE stmt_lotes FROM @sql_lotes;
EXECUTE stmt_lotes;
DEALLOCATE PREPARE stmt_lotes;

-- ---------------------------------------------------------------------
-- 2. movimientos_bancarios (con verificación de datos únicos)
-- ---------------------------------------------------------------------
SET @existe_mov = (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'movimientos_bancarios'
);

SET @huerfanos_mov = 0;
SET @sql_conteo = IF(@existe_mov = 0,
    'SET @huerfanos_mov = 0',
    'SELECT COUNT(*) INTO @huerfanos_mov
       FROM movimientos_bancarios m
       LEFT JOIN extractos_bancarios e ON e.id = m.id
      WHERE e.id IS NULL'
);
PREPARE stmt_conteo FROM @sql_conteo;
EXECUTE stmt_conteo;
DEALLOCATE PREPARE stmt_conteo;

SET @sql_mov = IF(@existe_mov = 0,
    'SELECT ''movimientos_bancarios: ya no existe, nada que hacer'' AS resultado',
    IF(@huerfanos_mov > 0,
        CONCAT('SELECT ''ABORTADO: ', @huerfanos_mov,
               ' filas de movimientos_bancarios no existen en extractos_bancarios. No se borra nada.'' AS resultado'),
        'DROP TABLE `movimientos_bancarios`'
    )
);
PREPARE stmt_mov FROM @sql_mov;
EXECUTE stmt_mov;
DEALLOCATE PREPARE stmt_mov;

-- ---------------------------------------------------------------------
-- 3. Verificación final
-- ---------------------------------------------------------------------
SELECT
    SUM(TABLE_NAME = 'conciliacion_lotes')    AS quedan_lotes,
    SUM(TABLE_NAME = 'movimientos_bancarios') AS quedan_movimientos
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @db
  AND TABLE_NAME IN ('conciliacion_lotes', 'movimientos_bancarios');
