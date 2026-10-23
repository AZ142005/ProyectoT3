-- ============================================================================
--  Verificación posterior al retiro de tablas (fase 23)
--  Base de datos: condominio_cobranzas (MariaDB 10.4)
--
--  Solo lectura. Ejecución:
--      cmd /c "mysql -u <usuario> -p condominio_cobranzas < database\verificar_tablas_sin_uso.sql"
--
--  Sirve para confirmar dos cosas: que las tablas retiradas ya no existen y
--  que la información que contenían sigue disponible en extractos_bancarios.
-- ============================================================================

SET @schema := DATABASE();

SELECT '=== 1. TABLAS RETIRADAS ===' AS bloque;

-- Ambas filas deben decir 0.
SELECT
    SUM(TABLE_NAME = 'conciliacion_lotes')    AS conciliacion_lotes,
    SUM(TABLE_NAME = 'movimientos_bancarios') AS movimientos_bancarios
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @schema
  AND TABLE_NAME IN ('conciliacion_lotes', 'movimientos_bancarios');


SELECT '=== 2. LA INFORMACIÓN SIGUE EN extractos_bancarios ===' AS bloque;

SELECT
    COUNT(*)               AS filas,
    SUM(monto)             AS monto_total,
    MIN(fecha_movimiento)  AS primer_movimiento,
    MAX(fecha_movimiento)  AS ultimo_movimiento
FROM extractos_bancarios;


SELECT '=== 3. VÍNCULOS DE CONCILIACIÓN ÍNTEGROS ===' AS bloque;

-- Ningún vínculo debe quedar apuntando a un abono inexistente.
SELECT COUNT(*) AS vinculos_sin_extracto
FROM conciliacion_abono_pago c
LEFT JOIN extractos_bancarios e ON e.id = c.movimiento_id
WHERE e.id IS NULL;


SELECT '=== 4. RESTOS DE LOS NOMBRES RETIRADOS ===' AS bloque;

-- Vistas, triggers y rutinas no deben mencionar las tablas retiradas.
SELECT 'VIEW' AS tipo, TABLE_NAME AS nombre
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = @schema
  AND (VIEW_DEFINITION LIKE '%conciliacion_lotes%'
    OR VIEW_DEFINITION LIKE '%movimientos_bancarios%')
UNION ALL
SELECT 'TRIGGER', TRIGGER_NAME
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = @schema
  AND (ACTION_STATEMENT LIKE '%conciliacion_lotes%'
    OR ACTION_STATEMENT LIKE '%movimientos_bancarios%')
UNION ALL
SELECT 'ROUTINE', ROUTINE_NAME
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = @schema
  AND (ROUTINE_DEFINITION LIKE '%conciliacion_lotes%'
    OR ROUTINE_DEFINITION LIKE '%movimientos_bancarios%');


SELECT '=== 5. VEREDICTO ===' AS bloque;

-- Lectura: si (1) devuelve 0 y 0, (3) devuelve 0 y (4) no devuelve filas,
-- el retiro está completo y no quedaron referencias en la base.
SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @schema
        AND TABLE_NAME IN ('conciliacion_lotes', 'movimientos_bancarios')) AS tablas_retiradas_presentes,
    (SELECT COUNT(*) FROM conciliacion_abono_pago c
       LEFT JOIN extractos_bancarios e ON e.id = c.movimiento_id
      WHERE e.id IS NULL) AS vinculos_huerfanos,
    (SELECT COUNT(*) FROM extractos_bancarios) AS extractos_disponibles;
