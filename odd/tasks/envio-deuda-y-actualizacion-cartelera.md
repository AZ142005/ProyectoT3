# ODD task: "Enviar deuda" (textos) + actualización de deudas al re-importar cartelera (upsert)

**Estado**: EN PROGRESO
**Fecha**: 2026-10-10
**Ruta**: delegated-direct — un writer (textos + upsert + tests) + verificación E2E del orquestador (PDF fecha +1 día).

## Objetivo

1. **Textos (solo visual)**:
   - Quitar "de facturas" del subtítulo del Registro: L101 de `admin/gastos/index.php`: "Control de facturas, tipología de egresos (comunes/individuales) y soporte digital" → **"Control de egresos (comunes/individuales) y soporte digital"**.
   - Botones: **"Generar Facturas del Mes" → "Enviar Deuda"**; **"Re-generar Facturas" → "Re-enviar Deuda"** (L408).
   - Barrido coherente en la pestaña Emisión (solo textos): L84 badge "N generadas" → "N emitidas"; L346 KPI "Facturas Creadas" → "Deudas Creadas"; L357 "Cálculo Dinámico de Cuotas a Facturar" → "…a Emitir"; L383 "Facturas ya generadas" → "Deudas ya enviadas"; L385 y L395 reescritos con "deuda(s)" (ver detalle abajo); flashes de `AdminController` L203/L208.
   - NO renombrar: rutas, ids (`tab-facturacion`, `'facturacion'`), clases, métodos, ni "facturas" del contexto de GASTOS (facturas de proveedor, campo "N° Factura" del modal, `generar_facturas.php` huérfana).

2. **Import de cartelera = UPSERT**: al re-importar un reporte, las facturas existentes (mismo `numero_factura` `CART-*`) se **actualizan** con los valores del reporte en vez de omitirse. El sistema **no acumula**: el total refleja el reporte. Contador `facturas_actualizadas` + flash + tests.

3. **Verificación E2E (orquestador)**: PDF de prueba con fecha de emisión cambiada al día siguiente (10/10/2026) → cargarlo por el flujo real → esperado: 0 creadas / 362 actualizadas / total sin acumular (8.891.132,51) y fechas actualizadas.

## Contexto

- Modelo actual `CarteleraModel::importarLote` (~L147-154): si `numero_factura` existe → `facturas_omitidas++` y `continue`. Cambiar a upsert:
  - `SELECT id, deleted_at, monto_pagado FROM facturas WHERE numero_factura = :numero`.
  - Si existe y `deleted_at` no es NULL → omitida (respetar soft-delete).
  - Si existe y activa → `UPDATE facturas SET monto_total = :total, saldo = :saldo, fecha_emision = :fe, fecha_vencimiento = :fv, estado = :estado WHERE id = :id` con `saldo = valor del reporte`, `monto_total = valor + monto_pagado` (round 2), `fecha_emision = fecha_vencimiento = fecha del reporte`, `estado = saldo > 0 ? 'vencida' : 'pagada'`. Contar en `facturas_actualizadas`.
  - Las deudas que DESAPARECEN del reporte NO se borran (limitación documentada: proteger historial/pagos).
  - Auditoría: mantener fila por factura creada; agregar UNA fila de resumen por lote si hubo actualizadas (`accion='actualizar_cartelera'`, `tabla_afectada='facturas'`, `estado_nuevo='actualizada'`, detalles con conteo + archivo).
  - Actualizar docblock de `importarLote` (ya no es "solo dedupe").
- `CarteleraController::importar`: flash → "Reporte de Cartelera importado: N creadas, M actualizadas, K omitidas. Edificios creados: X; unidades creadas: Y."
- Tests que tocan textos: `tests/GastosFacturacionTabsTest.php` L63-64 (asserta 'Facturas Creadas' → actualizar a 'Deudas Creadas'); grep `Generar Facturas|Re-generar|Facturas Creadas|facturas generadas` para el resto.
- Repo: commits locales en `main`, sin push; convencionales en español; sin atribución IA. PHP `C:/xampp/php/php.exe`; suite completa con falla preexistente `SecurityTest` (no tocar).

## Tareas

- [x] **T1 — Textos**: aplicar los cambios listados + actualizar aserciones de tests afectadas. Grep de verificación: sin textos viejos en la pestaña Emisión (`Generar Facturas del Mes|Re-generar Facturas|Facturas Creadas`).
- [x] **T2 — Upsert**: modificar `CarteleraModel::importarLote` (resultado con `facturas_actualizadas`), flash del controlador, y **test nuevo `tests/CarteleraUpsertTest.php`** (live-DB, patrón del repo, con restauración defensiva):
  - Parsear el fixture real; modificar el mes 10 de `1-01` (25.152,28 → 25.252,28); `importarLote` → assert `facturas_actualizadas >= 1`, valor en BD actualizado, `SUM(CART)` = 8.891.232,51 (no acumulado).
  - Re-importar las filas originales → assert valor restaurado y `SUM(CART)` = 8.891.132,51.
  - Limpiar filas de auditoría del test (`detalles LIKE '%test-upsert%'`, archivo marcador `test-upsert.pdf`).
- [ ] **T3 — Verificación E2E (orquestador)**: PDF reconstruido con fecha 10/10/2026 → flujo HTTP real → evidencia de 0 creadas / 362 actualizadas / sin acumulación.

## Checks

- `php -l` de tocados; `--filter=Cartelera` (parser intacto), `--filter=Gastos`, `--filter=Filtros`, `--filter=Router`; nuevo test upsert.
- Suite completa: falla preexistente conocida (no tocar).

## Progreso y evidencia

### T1 — Textos (completado)

- `app/views/admin/gastos/index.php`: L84 badge `N emitidas`; L101 subtítulo `Control de egresos (comunes/individuales) y soporte digital`; comentario L317 `Tarjeta de Emisión Masiva`; L346 `Deudas Creadas`; L357 `Cálculo Dinámico de Cuotas a Emitir`; L383 `Deudas ya enviadas`; L385 y L395 reescritos con «deuda(s)» (incluye «Re-enviar Deuda» entre comillas angulares); L408 botones `Re-enviar Deuda` / `Enviar Deuda`. Sin tocar ids/claves (`tab-facturacion`, `'facturacion'`), rutas, `name="generar"`, textos de gastos/proveedor ni `app/views/admin/generar_facturas.php` (huérfana).
- `app/controllers/AdminController.php` (generarFacturas): L203 `Ya existen deudas emitidas para el mes …`; L208 `Se enviaron N deudas calculadas para el mes …`.
- `tests/GastosFacturacionTabsTest.php`: aserciones actualizadas a `Deudas Creadas` y `Cálculo Dinámico de Cuotas a Emitir` (única aserción de texto adicional detectada por grep).
- Verificación: `php -l` OK en los 3 archivos; grep de textos viejos en la vista → sin coincidencias; `php tests/run.php --filter=Gastos` → 18 tests, 83 asserts passed, 0 failed, 0 errors.
- Commit: `fe3af55` — `feat(ui): botones enviar/re-enviar deuda y textos de emision`.

### T2 — Upsert de importación (completado)

- `app/models/CarteleraModel.php` (`importarLote`): el SELECT previo trae `id, deleted_at, monto_pagado`; soft-deleted → `facturas_omitidas++`; activa → `UPDATE` (`saldo` = valor del reporte, `monto_total` = saldo + `monto_pagado`, `fecha_emision` = `fecha_vencimiento` = fecha del reporte, estado `vencida`/`pagada`) y `facturas_actualizadas++` (nueva clave del resultado, default 0). Las deudas que desaparecen del reporte no se borran (limitación documentada en el docblock, junto al modo upsert). Auditoría: fila por factura creada + UNA fila resumen por lote si hubo actualizadas (`accion='actualizar_cartelera'`, `tabla_afectada='facturas'`, `registro_id=NULL`, `estado_nuevo='actualizada'`, detalles con conteo + archivo + hash-ref).
- `app/controllers/CarteleraController.php`: flash → `Reporte de Cartelera importado: {N} creadas, {M} actualizadas, {K} omitidas. Edificios creados: {X}; unidades creadas: {Y}.` El tipo de flash conserva la lógica previa (success solo si `creadas > 0`; una reimportación pura muestra info).
- `tests/CarteleraUpsertTest.php` (nuevo, live-DB, patrón del repo): fixture real → snapshot `CART-1-01-202610` = 25.152,28 y `SUM(CART)` = 8.891.132,51; mes 10 de 1-01 → 25.252,28 → `importarLote` → 362 actualizadas (assert `>= 1`), 0 creadas, BD = 25.252,28, `SUM` = 8.891.232,51 (no acumulado); reimport de filas originales → 25.152,28 / 8.891.132,51; limpieza `DELETE FROM log_auditoria WHERE detalles LIKE '%test-upsert%'` y restauración defensiva en `finally`.
- Verificación: `php -l` OK en modelo/controlador/test; `--filter=CarteleraUpsert` ✅ 1 test / 11 asserts; `--filter=Cartelera` ✅ 4 tests / 55 asserts; `--filter=Gastos` ✅ 18 / 83; `--filter=Filtros` ✅ 6 / 39; `--filter=Router` ✅ 23 / 44. Estado BD post-test comprobado: `CART-1-01-202610` = 25.152,28; `SUM(CART)` = 8.891.132,51; 0 filas de auditoría del test. Fila resumen comprobada en corrida manual de 1 fila (accion/tabla/registro_id/estado_nuevo/detalles con archivo y hash-ref).
- Commit: `76c08ec` — `feat(cartelera): reimportar actualiza deudas existentes sin acumular`.
- Fix de verificación (fechas): `tests/CarteleraUpsertTest.php` toma snapshot del par `(fecha_emision, fecha_vencimiento)` dominante de las CART activas antes de importar (par único esperado; si hay varios, el más frecuente con desempate determinista) y lo restaura en el `finally` junto a montos y auditoría. Evidencia: corrida normal → fechas antes = después = 2026-10-09 (362 filas), `SUM` = 8.891.132,51, 0 auditoría; prueba adversarial con fechas pre-fijadas en 2026-10-08 → tras el test (que importa con 2026-10-09) quedaron 2026-10-08 (el `finally` restauró), y la BD se devolvió manualmente a 2026-10-09. Test ✅ 11 asserts. Commit: `test(cartelera): restaura fechas en el test de upsert`.
