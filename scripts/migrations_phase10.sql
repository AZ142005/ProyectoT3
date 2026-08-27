-- Fase 10: G4 — Finanzas y Contabilidad
-- Recommended indexes (idempotent)
-- Note: idx_movimientos_tipo_desc helps filter by tipo='cargo_factura' but the
-- LIKE '%gasto#ID%' on descripcion will still scan matching rows. This is acceptable
-- for the expected volume of cargo_factura movements. If volume grows significantly,
-- consider adding a gasto_id column to movimientos_cuenta for exact lookups.

CREATE INDEX IF NOT EXISTS idx_movimientos_tipo_desc
    ON movimientos_cuenta (tipo, descripcion(100));

CREATE INDEX IF NOT EXISTS idx_movimientos_unidad_tipo_fecha
    ON movimientos_cuenta (unidad_id, tipo, fecha_movimiento);
