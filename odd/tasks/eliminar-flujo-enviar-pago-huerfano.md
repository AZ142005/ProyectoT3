# Eliminación del flujo huérfano /residente/enviar-pago (comprobantes legado)

## Objetivo (instrucción del usuario)
- Verificar si quitar la vista sin enlace (`/residente/enviar-pago`) causaría algún fallo o si es visible en otra parte del sistema; si no, **eliminarla**.
- Luego, **prueba de funcionalidad** en los otros dos apartados de pago (`/pago-directo` y `/pagos/nuevo`) para asegurar que todo esté bien.

## Resultado de la verificación previa (read-only, hecha)
- **Referencias en código**: ruta `public/index.php:134` (`any '/residente/enviar-pago'`), `ResidenteController::enviarPago()` (L46-197; render L187) y la vista `residente/enviar_pago.php`. No hay más usos de `enviarPago`/`enviar_pago` en `app/`, `public/`, `scripts/`.
- **Enlaces de UI**: NINGUNO (el único match es el `action` del propio formulario).
- **Notificaciones (BD viva)**: 102 enlaces, **todos** a `/pagos/subir`; **0** a `enviar-pago`.
- **Datos**: `comprobantes_pago` = 5 filas (último envío 2026-09-28). El propio código lo trata como "sistema anterior" (PagoModel L348/L353) con compatibilidad total; el flujo vigente es `/pagos/nuevo` (tabla `pagos`). El historial legado seguirá visible en admin (Conciliación / "Historial de Pagos") y en Mis Pagos; **no se borran datos**.
- **Tests que lo referencian (se actualizan aquí)**: `BehaviorTest` 4.4 (3 tests de tope) y 4.7 (form banco); `ComprobantesDuplicadosTest` (bloque de rate limiting); `RouterTest` (lista de rutas).
- **Conclusión**: quitarlo **no rompe runtime** (solo referencias de tests, que se limpian) y **no es visible en otra parte**. → Se elimina.

## Cambios
1. `public/index.php`: eliminar la línea 134 (ruta).
2. `app/controllers/ResidenteController.php`: eliminar el docblock + método `enviarPago()` (L43-197) y el import `use App\Models\ComprobantesModel;` (queda sin uso). **Mantener** `FacturasModel` (se usa en dashboard), `ComunicadosModel`, `Auth`, etc.
3. Eliminar `app/views/residente/enviar_pago.php` (`git rm`).
4. `tests/BehaviorTest.php`:
   - Eliminar los 3 tests de la sección 4.4 (`testEnviarPagoHasUpperBoundValidation`, `testEnviarPagoUpperBoundMessageIsSpanish`, `testEnviarPagoUpperBoundCheckOrder`) + su comentario de sección; su validación vivía SOLO en el flujo eliminado (`PagoController::subir` no tiene tope 999999 — verificado).
   - Repurposear `testEnviarPagoFormHasBancoPagadorField` → `testPagoSubirFormHasBancoPagadorField` leyendo `app/views/pagos/residente/subir.php` con las MISMAS aserciones (`name="banco_pagador"`, `data.banco_pagador`).
5. `tests/ComprobantesDuplicadosTest.php`: en `testControladoresTieneRateLimitingAdecuado`, quitar el bloque de ResidenteController (L365-375) y conservar el de PagoController::subir.
6. `tests/RouterTest.php`: quitar `'/residente/enviar-pago',` de `$residenteRoutes`.

## Fuera de alcance (no tocar)
- Purgar métodos de modelo que queden sin uso (p. ej. `FacturasModel::getByIdAndUnidadGeneral`) — evaluar aparte.
- Agregar tope 999999.99 a `PagoController::subir` — decisión aparte.
- Datos históricos de `comprobantes_pago`.

## Verificación
- Writer: `php -l` (controlador, index, 3 tests); grep repo-wide sin referencias residuales (`app,public,tests,scripts`); suite completa exit 0; filtros RouterTest / BehaviorTest / ComprobantesDuplicadosTest.
- Prueba de funcionalidad post-eliminación (orquestador): Pago directo por HTTP real (GET `/pago-directo` con marcadores + GET `/pago-directo/deuda?unidad_id=1` JSON success); `/pagos/nuevo` sin sesión → redirect a login (ruta viva); render smoke de `subir.php` (sin fatales, marcadores); `node --check`; y GET `/residente/enviar-pago` → 404.
- Verificador independiente: diff acotado, tests actualizados correctos, sin referencias residuales.

## Resultado (cierre)
- [x] Verificación previa (read-only): sin enlaces de UI; notificaciones en BD todas a `/pagos/subir` (0 a enviar-pago); `comprobantes_pago` = "sistema anterior" (5 filas, historial intacto en admin/Mis Pagos); referencias de tests identificadas.
- [x] Eliminación: ruta (`public/index.php`), método + docblock + import `ComprobantesModel` (`ResidenteController`), vista borrada; tests actualizados (4.4 eliminados; 4.7 repurpuesto a `subir.php`; ComprobantesDuplicados sin bloque Residente ni código muerto; RouterTest sin la ruta).
- Verificación del writer: `php -l` 5/5; grep repo-wide 0 referencias residuales; suite completa **EXIT=0**; filtros RouterTest 23/23 ✅, BehaviorTest 108/252 ✅, ComprobantesDuplicados 6/27 ✅; CRLF preservado.
- Verificador independiente: **VERIFIED** — hunk único −157 exacto (docblock+método, `historial()`/`dashboard()` intactos), imports usados conservados, sin referencias muertas ni URLs dinámicas; observaciones INFO preexistentes (tope 999999 sin test en `PagoDirectoController`; `PagoController::subir` sin tope; docs históricas mencionan la ruta).
- **Prueba de funcionalidad (la pedida):**
  - Render smoke `subir.php`: `RENDER_SUBIR_OK` (47 KB, sin warnings/fatales, marcadores del Paquete A presentes).
  - HTTP real (dev server local): `/pago-directo` **200** + 5 marcadores (dropzone, Cuenta Oficial, copiarDatoCuenta, btnReanalizar, título).
  - `/pago-directo/deuda?unidad_id=1` → `{"success":true,...}` (A-101, deuda 450) — flujo de datos público OK.
  - `/pagos/nuevo` sin sesión → **302 → /auth/login** (ruta viva + guard de rol).
  - `/residente/enviar-pago` → **404** (eliminación efectiva). `node --check` del JS de subir: OK.
- RDD: `mode status` → on (global). `assess` (base `4195775`) → `risk: high`, `unassessable` (untracked preexistentes; OpenCode no elegible), `review_due: high_risk`, sin `next_transition`. Ruta RDD-off aplicada. Modo NO desactivado.
- Residuales honestos: sin navegador real (HTTP + render estáticos); follow-ups opcionales NO ejecutados: tope 999999.99 en `PagoController::subir` + test del tope existente en `PagoDirectoController`; purgar `FacturasModel::getByIdAndUnidadGeneral` si se confirma sin uso.

## Entrega
- Commit `2ea5a7e` directo en `main` (6 archivos; −1209/+4) + este doc en `docs(odd)`. Push: decisión del usuario.
