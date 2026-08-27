-- Fase 8: Índices y columnas para G2 — Core de Pagos y Facturación
-- Ejecutar: php scripts/migrations_phase8.sql (o via MySQL CLI)
-- Idempotente: crea todo solo si no existe

SET @db = DATABASE();

-- 1. Índice para prevención de duplicados en crearPago()
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagos' AND INDEX_NAME='idx_pago_duplicado');
SET @sql = IF(@x=0, 'CREATE INDEX idx_pago_duplicado ON pagos (unidad_id, referencia, fecha_pago, monto)', 'SELECT "idx_pago_duplicado ya existe" AS status');
PREPARE s1 FROM @sql; EXECUTE s1; DEALLOCATE PREPARE s1;

-- 2. Índice para facturas pendientes por unidad
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='facturas' AND INDEX_NAME='idx_factura_unidad_saldo');
SET @sql = IF(@x=0, 'CREATE INDEX idx_factura_unidad_saldo ON facturas (unidad_id, saldo, deleted_at)', 'SELECT "idx_factura_unidad_saldo ya existe" AS status');
PREPARE s2 FROM @sql; EXECUTE s2; DEALLOCATE PREPARE s2;

-- 3. Índice para reportes de morosidad
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='facturas' AND INDEX_NAME='idx_factura_morosidad');
SET @sql = IF(@x=0, 'CREATE INDEX idx_factura_morosidad ON facturas (saldo, fecha_vencimiento, deleted_at, unidad_id)', 'SELECT "idx_factura_morosidad ya existe" AS status');
PREPARE s3 FROM @sql; EXECUTE s3; DEALLOCATE PREPARE s3;

-- 4. Índice para pagos del residente
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagos' AND INDEX_NAME='idx_pago_residente_fecha');
SET @sql = IF(@x=0, 'CREATE INDEX idx_pago_residente_fecha ON pagos (residente_id, fecha_registro DESC)', 'SELECT "idx_pago_residente_fecha ya existe" AS status');
PREPARE s4 FROM @sql; EXECUTE s4; DEALLOCATE PREPARE s4;

-- 5. Índice para conciliación (estado + fecha)
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagos' AND INDEX_NAME='idx_pago_estado_fecha');
SET @sql = IF(@x=0, 'CREATE INDEX idx_pago_estado_fecha ON pagos (estado, fecha_pago)', 'SELECT "idx_pago_estado_fecha ya existe" AS status');
PREPARE s5 FROM @sql; EXECUTE s5; DEALLOCATE PREPARE s5;

-- 6. Índice para conteo de unidades activas (KPIs)
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='unidades' AND INDEX_NAME='idx_unidad_estado');
SET @sql = IF(@x=0, 'CREATE INDEX idx_unidad_estado ON unidades (estado)', 'SELECT "idx_unidad_estado ya existe" AS status');
PREPARE s6 FROM @sql; EXECUTE s6; DEALLOCATE PREPARE s6;

-- 7. Columnas banco_origen/destino en pagos
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagos' AND COLUMN_NAME='banco_origen');
SET @sql = IF(@x=0, 'ALTER TABLE pagos ADD COLUMN banco_origen VARCHAR(100) NULL AFTER banco_receptor', 'SELECT "pagos.banco_origen ya existe" AS status');
PREPARE s7 FROM @sql; EXECUTE s7; DEALLOCATE PREPARE s7;

SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagos' AND COLUMN_NAME='banco_destino');
SET @sql = IF(@x=0, 'ALTER TABLE pagos ADD COLUMN banco_destino VARCHAR(100) NULL AFTER banco_origen', 'SELECT "pagos.banco_destino ya existe" AS status');
PREPARE s8 FROM @sql; EXECUTE s8; DEALLOCATE PREPARE s8;

SELECT '✅ Migración Fase 8 completada' AS resultado;
