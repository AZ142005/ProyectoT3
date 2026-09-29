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
- [x] **T5 — Pulido visual (pedido del usuario, 29-09)**: botón del login integrado (mismo estilo del enlace de registro, sin ámbar); `/pago-directo` y su pantalla de éxito alineadas a la UI de residentes (banner verde con acento institucional, cards `rounded-2xl shadow-sm`, formularios, dropzone visual con preview). Solo vistas; lógica intacta. Commit `35b9870`.
- [x] **T6 — Orden del portal igual al flujo residente (29-09)**: comprobante en "Paso 1" y datos del pago en "Paso 2" (como `residente/enviar_pago.php`). Verificado por render (orden identifica→paso1→paso2→submit, un solo dropzone) + tests. Commit `4fcfacd`.
- [x] **T7 — Autocompletado desde el comprobante + Paso 2 idéntico a residentes (pedido del usuario, 29-09)**: endpoint público `POST /pago-directo/extraer` (rate limit por IP 20/h, reusa `ComprobanteParserService`, mismo shape que `/pagos/extraer`) + port del JS de extracción (Tesseract.js cliente, autocompletado solo de vacíos, badges y avisos de inconsistencia, resumen de extracción) + reestructura del Paso 2 con las cajas "Detalles de la Transacción" y "Entidades Bancarias y Cuentas". Commits `6bad8cb` (endpoint+ruta+tests), `2d2c7c2` (vista) y `35bd31c` (fix: token CSRF rotado devuelto en la respuesta para permitir análisis repetidos). Verificado: PagoDirectoTest 8/40 ✅; purity/security 0; smoke e2e con extracción real (BDV, ref 0591395041816, monto 3250.00, fecha 22/09/2026) y POST encadenado con token rotado ✅ (token viejo → 403).
- [x] **T8 — Cuenta destino más visible y etiqueta clara (pedido del usuario, 29-09)**: aplicado en las 3 vistas que comparten la sección (`residente/enviar_pago.php`, `pagos/residente/subir.php`, `pago_directo/index.php`): etiqueta → "Cuenta Oficial para el Pago", placeholder → "Seleccione la cuenta oficial del condominio", y los valores del recuadro (número de cuenta, titular, RIF, teléfono) de `text-xs` → `text-base`. Verificado por render stub en las 3 (nuevos textos presentes, frase vieja ausente, 4 valores en text-base, divs balanceados) + PagoDirectoTest 8/40. Commit `1cae447`.
- [x] **T9 — Simplificación de cuadros anidados (pedido del usuario, 29-09)**: el portal público tenía 3 tarjetas blancas separadas (unidad / deuda / datos) + caja de nota; consolidado en UNA tarjeta con secciones internas (header "Datos del pago" → "Identifica tu unidad" → estado de cuenta inline → Paso 1 → Paso 2 → botones), igual a la estructura de residentes; la nota dejó de ser caja. Verificado: 1 tarjeta blanca, balance divs 78/78 (fuente) y 77/77 (render), orden interno intacto, dropzone único, PagoDirectoTest 8/40. Commit `580092f`.
- [x] **T8 — Cuenta destino más visible y etiqueta clara (pedido del usuario, 29-09)**: aplicado en las 3 vistas que comparten la sección (`residente/enviar_pago.php`, `pagos/residente/subir.php`, `pago_directo/index.php`): etiqueta → "Cuenta Oficial para el Pago", placeholder → "Seleccione la cuenta oficial del condominio", y los valores del recuadro (número de cuenta, titular, RIF, teléfono) de `text-xs` → `text-base`. Verificado por render stub en las 3 (nuevos textos presentes, frase vieja ausente, 4 valores en text-base, divs balanceados) + PagoDirectoTest 8/40. Commit `1cae447`.

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
- **Diff**: 10 archivos, +1120/−29 con las correcciones incluidas (excluye artefactos de runtime `.atl/` y `storage/`).
- **RDD / revisión nativa**: assess → `review_due: true`, `high / unassessable`; STATUS → `immutable_review_transport_unsupported` (`next_action: stop`, `retry_safe: false`, `mutation_outcome: not_started`). El runtime activo (OpenCode) no es elegible para revisión inmutable (soportados: claude-code, codex). Resultado tipado preservado; **no** se ejecutó revisión nativa. Decisión de cierre presentada al usuario.
- **Nota**: artefactos de runtime sucios (`.atl/*`, `storage/cache/estructura/data_0.json`) quedaron fuera de todos los commits.

### Hallazgos de la verificación independiente (29-09)
- **[HIGH] Rate limiter inefectivo por desfase de zona horaria PHP↔MySQL** (`app/core/RateLimiter.php`): PHP usa `Europe/Berlin` (php.ini de XAMPP) y MySQL `America/Caracas`; el corte calculado por PHP (`date(...time()-ventana)`) nunca encuentra la fila escrita con `NOW()` de MySQL, así que cada intento inserta una fila nueva y `attempts` nunca incrementa. Evidencia propia: `rate_limits` = 117 filas, `MAX(attempts)=1` histórico; SELECT de `attempt()` con corte PHP → NULL; con corte MySQL-side → encuentra fila. Afecta a **todo el sistema** (login/OTP/registro) y a los endpoints nuevos. **Criterio 4 (rate limit) queda NO cumplido en este entorno hasta corregirlo.**
- **[LOW] L1**: `monto` 0,001–0,004 supera la validación y se guarda `0,00`. **[LOW] L2**: POST con campos array (`trim(array)`) → `TypeError`/HTTP 500 en endpoint público (en dev, handler global con traza). **[LOW] L3**: banco "OTRO" vacío se guarda literal `"OTRO"`; sin `maxlength` en `referencia` (varchar(100), truncado silencioso por `sql_mode` sin `STRICT_TRANS_TABLES`). **[LOW] L4**: la conciliación muestra al propietario de la unidad (o vacío) en vez de "Pago directo (sin usuario)" — visible/aprobable, pero inconsistente con las demás pantallas admin.
- **Sugerencias documentadas**: S1 fecha futura aceptada (`9999-12-31`; formatos inválidos sí rechazados); S2 "Usar este monto" usa deuda bruta sin descontar saldo a favor; S3 no se valida cuenta receptora contra método (preexistente); S4 mensaje de duplicado engañoso (dedup real por unidad+fecha+monto; `referencia_norm` NULL, sin triggers); S5 cobertura del test (no cubre `reportar()`/`deuda()`/upload/duplicados); S7 residuo de 6 filas en `rate_limits` del smoke.
- **Refutaciones confirmadas (núcleo sano)**: pago invitado visible/aprobable por caminos admin reales; residente no puede ver pagos ajenos; CSRF/upload correctos; privacidad del JSON de deuda; migración idempotente con FK intacta; no-regresión del residente.
- **Límites del chequeo**: sin HTTP real/navegador/escrituras de BD; no es autoridad nativa ni emite recibo.
- **Correcciones aplicadas (decisión del usuario: HIGH + menores)**:
  - H1 en `84b59ec` (`RateLimiter` con ventanas en hora de MySQL + docblock actualizado) y tests `rateLimiterBloqueaTrasMaxIntentos` / `rateLimiterComparaVentanasEnHoraSql` (RED→GREEN demostrado: el test conductual fallaba antes del fix).
  - L1/L2/L3/S4 en `4a945fe` (monto redondeado ≥ 0,01; guards `postString/postInt/postFloat`; OTRO vacío → error; `maxlength="100"`; mensaje de duplicado corregido).
  - Re-verificación: `--filter=PagoDirectoTest` 7 tests / 33 asserts ✅ (spot check del padre); BehaviorTest idéntico a línea base; AuthTest y ModelTest ✅; purity/security 0; smoke manual: 6.º intento bloqueado y `secondsUntilAvailable` = 60; residuos `pago_directo*` eliminados de `rate_limits`.
  - Pendientes documentados (sin urgencia): S1 (fecha futura), S2 (monto vs saldo a favor), S3 (cuenta vs método), S5 (cobertura de `reportar()`/`deuda()`/upload en tests), S8 (hallado en T5: la vista del flujo residente `residente/enviar_pago.php` no renderiza los errores de validación del controlador `$error` — preexistente), S9 (la rotación CSRF rompe el segundo análisis/reanalizar también en el flujo residente `/pagos/extraer` — en el portal público ya se corrigió en `35bd31c` devolviendo el token rotado; falta aplicar el mismo fix a residentes).

## Nota de rendimiento del autocompletado (29-09)
- Descarga real de la primera vez (navegador moderno, brotli medido en CDN): loader 10 KB + worker 33 KB + core SIMD-LSTM 1.31 MB + modelo español `4.0.0_best_int` 2.0 MB ≈ **3.4 MB** (sin brotli ≈ 6 MB). El modelo de idioma se cachea en IndexedDB y el core en caché HTTP.
- Tiempos estimados primera vez: ~3 s (10 Mbps), ~7 s (4 Mbps), ~18 s (1.5 Mbps), ~67 s (400 kbps), ~3 min (150 kbps) + cómputo local del OCR (2–5 s escritorio / 5–12 s móvil medio / 15–30 s gama baja).
- Después de la primera vez: sin descargas; solo cómputo local + POST pequeño (KB) → la mala conexión deja de pesar.
- PDF: sube hasta 5 MB al servidor (≈100 s a 50 KB/s; ≈4.4 min a 19 KB/s).
- Feedback actual: spinner + textos por fase + % real durante el reconocimiento; la fase de descarga del modelo de idioma (la más larga en mala conexión) NO actualiza mensaje ni % (`loading language traineddata` sin manejar en el logger) → parece colgado. Recomendación: mostrar esa fase/progreso y aviso de "solo la primera vez" (~15–20 líneas, en ambos flujos). Pendiente de decisión del usuario.

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
- RDD: revisión nativa no ejecutable en OpenCode (`immutable_review_transport_unsupported`); resultado preservado. Verificación independiente completada y correcciones aplicadas (`84b59ec`, `4a945fe`).
- Estado: feature cerrado y verificado; push/PR quedan a decisión del usuario (rama `feat/pago-directo-sin-login`). Nota: el switch RDD global sigue activo; este clone puede apagarse con `gentle-ai review mode disable --scope clone` si se desea.
- 29-09 (post-cierre): T5 completado (`35b9870`): botón del login natural + portal alineado a la UI de residentes. Verificado: PagoDirectoTest 7/33 ✅, purity/security 0, render HTTP 200 en `/pago-directo`, `/auth/login` y `/pago-directo/exito` con marcadores esperados (captura de navegador no disponible: sin navegador de escritorio conectado a la sesión).
- 29-09: T6 completado (`4fcfacd`): orden Paso 1/Paso 2 igual al flujo residente (render: identifica→paso1→paso2→submit ✅). Consulta de factibilidad de autocompletado desde capturas respondida al usuario: **ALTA** (la maquinaria ya existe en el flujo residente: Tesseract.js cliente + `PagoController::extraer` + `ComprobanteParserService` + regla "solo completa vacíos y avisa inconsistencias"). Para el portal público faltaría: endpoint público con rate limit por IP (~50 líneas), portar el JS de extracción (~200-300), tests; ~350-450 líneas en total. Pendiente de decisión del usuario (T7).
- 29-09: T7 completado (`6bad8cb`, `2d2c7c2`, fix `35bd31c`): autocompletado implementado en el portal público (Tesseract.js cliente para imágenes + PDF server-side vía `/pago-directo/extraer` con rate limit 20/h por IP) y Paso 2 espejo de residentes ("Detalles de la Transacción" / "Entidades Bancarias y Cuentas" con badges "Auto-completado" y avisos de inconsistencia). Verificado: test 8/40 ✅, smoke con extracción real y análisis encadenado tras rotación CSRF ✅, purity/security 0.
- 29-09: T8 completado (`1cae447`): cuenta destino con datos más grandes (`text-base`) y etiqueta más clara ("Cuenta Oficial para el Pago") en las 3 vistas del flujo de pagos (incluida `pagos/residente/subir.php`, detectada como tercera vista compartida).
- 29-09: T9 completado (`580092f`): portal público consolidado en una sola tarjeta con secciones internas (como la vista de residentes); nota sin caja. Verificado por balance de divs + render stub + tests.
