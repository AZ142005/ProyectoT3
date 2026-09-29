# Pago directo sin iniciar sesión (portal público)

## Objetivo
Agregar en el index (página de login) una opción para **reportar un pago sin iniciar sesión**, seleccionando el edificio y el número de apartamento (unidad). El pago queda registrado contra la unidad en la tabla `pagos` y sigue el flujo administrativo existente (revisión, conciliación, aprobación).

## Problema / Por qué
Hoy solo un residente autenticado puede reportar/registrar pagos. Los copropietarios o terceros que pagan por una unidad (sin cuenta, sin acceso) no tienen forma de reportar su comprobante por el sistema.

## Alcance autorizado
- Bloque de acceso público en `app/views/auth/login.php` ("Pagar sin iniciar sesión").
- Página pública `/pago-directo` (sin auth): seleccionar edificio + apartamento (unidad) y reportar el pago con comprobante.
- Consulta pública de deuda por unidad (solicitado por el usuario): al seleccionar la unidad se muestra el monto pendiente (total + desglose de facturas) para que quien paga sepa cuánto debe.
- Controlador nuevo `PagoDirectoController` + rutas públicas en `public/index.php`.
- Migración para permitir `pagos.residente_id = NULL` (el pago pertenece a la unidad; el residente es opcional).
- Ajustes mínimos en `PagoModel` para mostrar pagos sin usuario en las pantallas admin (`LEFT JOIN` + etiqueta).
- Tests nuevos + corrida de verificación del proyecto.

## Fuera de alcance (v1)
- Pasarela de pago real (el sistema es de reporte de pagos con comprobante).
- Exponer datos personales de residentes; solo se muestra la deuda de la unidad seleccionada (total y desglose de facturas, sin nombres ni cédulas).
- Autocompletado OCR para invitados (el endpoint `/pagos/extraer` requiere sesión).
- Notificaciones al pagador sin cuenta (no hay contacto asociado).

## Decisiones de diseño
- **Flujo reutilizado**: `pagos` (pago a nivel de unidad, sin factura) + `PagoModel::crearPago`, igual que "Registrar Pago" del residente. La aprobación ya liquida en cascada (`LiquidacionPagoService`).
- **`residente_id` nullable**: necesario para unidades sin personas registradas (caso real en datos: unidades 1, 3, 4 sin residentes). Migración idempotente.
- **Etiqueta admin**: `COALESCE(..., 'Pago directo (sin usuario)')` en `PagoModel::obtenerTodosPagos` y `obtenerPagoPorId`.
- **Conciliación**: `ConciliacionBancariaService` ya usa `LEFT JOIN` al propietario de la unidad; no requiere cambios.
- **Observaciones**: prefijo `"Pago directo sin sesión (portal público). "`.
- **Deuda visible al pagador**: `GET /pago-directo/deuda?unidad_id=X` (JSON, solo con unidad activa) devuelve `total_deuda`, `saldo_favor` y el desglose de facturas pendientes (periodo, vencimiento, saldo). Botón "usar monto total" autocompleta el campo monto. Sin datos personales.
- **Anti-abuso**: rate limit por IP (5/hora) en el reporte y ~60/hora en la consulta de deuda; CSRF global; dedup existente de `PagoModel` (unidad+referencia+fecha+monto).

## Tareas (IDs estables)
- [x] **T1 — Capa de datos**: `scripts/migrate_pago_directo.php` (idempotente, `pagos.residente_id` NULL) + ejecutarlo localmente + ajustes `PagoModel` (`obtenerTodosPagos`, `obtenerPagoPorId`: LEFT JOIN + COALESCE; doc de null).
- [x] **T2 — Backend**: `app/controllers/PagoDirectoController.php` (`index()`, `deuda()`, `reportar()`, `exito()`) + rutas `GET /pago-directo`, `GET /pago-directo/deuda`, `POST /pago-directo/reportar`, `GET /pago-directo/exito` en `public/index.php`.
- [x] **T3 — UI**: `app/views/pago_directo/index.php` (selección dependiente edificio→unidad + panel de deuda con fetch + formulario de pago + cuentas bancarias oficiales), `app/views/pago_directo/exito.php`, y bloque de acceso en `auth/login.php`.
- [x] **T4 — Pruebas y verificación**: `tests/PagoDirectoTest.php` + corrida por clase de la suite + `scripts/check_purity.php` + `scripts/audit_security.php` + smoke test.

## Criterios de aceptación
1. El index muestra una opción visible "Pagar sin iniciar sesión" que lleva a `/pago-directo`.
2. Sin sesión: seleccionar edificio → apartamento (unidad), completar datos y subir comprobante crea una fila en `pagos` con `residente_id=NULL`, `estado='PENDIENTE'` y la `unidad_id` correcta.
3. Al seleccionar la unidad se muestra el monto pendiente (total + desglose de facturas) y se puede autocompletar el monto del pago con ese total; si no hay deuda, se informa y se permite reportar un abono.
4. Validaciones server-side: unidad activa; cuenta destino activa; monto > 0 (≤ 999.999,99); método válido; referencia obligatoria; fecha válida; archivo por `FileUploader` (JPG/PNG/PDF ≤ 5MB); rate limit por IP; CSRF.
5. Admin: `/pagos` y `/pagos/detalle/{id}` muestran el pago con etiqueta "Pago directo (sin usuario)"; conciliación/aprobación funcionan (liquidación en cascada).
6. La página pública muestra únicamente la deuda de la unidad consultada; jamás datos personales de residentes.
7. Sin regresiones en tests (comparar contra línea base) y auditorías del proyecto en 0 violaciones.

## Chequeos aplicables
- `php tests/run.php --filter=PagoDirectoTest`
- Suite por clase (ver nota de línea base); `SecurityTest` aborta el runner por un test preexistente con `exit` (`testCsrfRejectsInvalidToken`) — correr aparte las 2 clases finales.
- `php scripts/check_purity.php`
- `php scripts/audit_security.php`
- Migración local ejecutada + smoke test de la página pública.

## TDD
Desactivado (sin configuración explícita en el proyecto/sesión). Runner del proyecto: `php tests/run.php`.

## Línea base de tests (antes del cambio)
- BehaviorTest 1❌+1⚠ (falta `public/uploads/.htaccess`), ConciliacionTest 2❌, ConfigTest 3❌+2⏭ (entorno), JwtTest 3⚠ (falta `.env`), NotificationTest 1⚠, SecurityTest aborta el runner (preexistente). El resto verde.

## Verificación final (post-implementación)
- **Migración**: `php scripts/migrate_pago_directo.php` aplicada; 2ª corrida idempotente ("ya aplicada"); `IS_NULLABLE='YES'`; FK `pagos_ibfk_1` intacta.
- **Tests nuevos**: `php tests/run.php --filter=PagoDirectoTest` → 5 tests / 23 asserts / 0 fallos (re-corrido por el padre como spot check ✅).
- **Regresión (clases clave)**: ModelTest 130✅; RouterTest 45✅; ComprobanteFlujoAprobacionTest 27✅; ConciliacionTest 71✅/2❌ (fallos de línea base, no tocados).
- **Gates del proyecto**: `check_purity.php` 0 violaciones; `audit_security.php` 0 vulnerabilidades.
- **Smoke E2E (servidor embebido + curl)**: `GET /pago-directo` → 200 (contiene "Pagar"); `GET /pago-directo/deuda?unidad_id=1` → JSON `success:true` (total 450, 3 facturas); unidad inválida → 404; `POST /pago-directo/reportar` multipart (cookie+CSRF+PNG) → 302 a `/pago-directo/exito`; fila creada con `residente_id NULL`/`PENDIENTE`, verficada y eliminada (BD restaurada, `pagos` = 0).
- **Diff**: 9 archivos, +1018/−6 (incluye doc ODD y tests; excluye artefactos de runtime `.atl/` y `storage/`).
- **RDD / revisión nativa**: assess → `review_due: true`, `high / unassessable`; STATUS → `immutable_review_transport_unsupported` (`next_action: stop`, `retry_safe: false`, `mutation_outcome: not_started`). El runtime activo (OpenCode) no es elegible para revisión inmutable (soportados: claude-code, codex). Resultado tipado preservado; **no** se ejecutó revisión nativa. Decisión de cierre presentada al usuario.
- **Nota**: artefactos de runtime sucios (`.atl/*`, `storage/cache/estructura/data_0.json`) quedaron fuera de todos los commits.

## Ruta de implementación por tarea
- T1–T4: **delegada** a un único writer (disparador: 2+ archivos no triviales y preparación de escritura). Verificación: writer con comandos en primer plano + revisión del padre.

## Entrega (delivery)
- Pronóstico: **~750 líneas añadidas** (sin archivos generados) — supera el presupuesto de ~400.
- Decisión del usuario: **rama única con excepción de tamaño** (`size:exception`), sin PRs encadenados.
- Rama: `feat/pago-directo-sin-login` (desde HEAD actual). Commits por unidad de trabajo; push/PR quedan a decisión del usuario.
- Ajuste de alcance pedido por el usuario: mostrar la deuda de la unidad al pagador (incluido arriba).

## Progreso
- Exploración completa; decisión de entrega: rama única + deuda visible al pagador.
- T1–T4 implementadas y verificadas. Commits: `7a0b47f` (datos+migración), `ea9f9b7` (controlador+rutas), `ddc863c` (vistas+login), `bf2d84f` (tests) + commit de docs con este registro.
- Verificación padre: spot check `--filter=PagoDirectoTest` ✅; diff estructural revisado (controlador null-safe, validaciones completas, rate limit).
- RDD: revisión nativa no ejecutable en OpenCode (`immutable_review_transport_unsupported`); resultado preservado y decisión ofrecida al usuario.
- Siguiente paso: decisión del usuario sobre el cierre de la revisión (disable clone / mantener / verificación independiente).
