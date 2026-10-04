# Visibilidad total para el Auditor (lectura) — "todo lo que ve el admin"

## Objetivo (instrucción del usuario)
- "Haz que todo lo visible para el admin también sea para el auditor, luego vamos quitando cosas."
- Alcance de esta iteración: **visibilidad (GET/lectura)**. Las **mutaciones (POST) siguen exclusivas de admin**; el `RoleMiddleware` global ya bloquea cualquier método no-GET para auditor (403). La poda fina de menús/botones por módulo vendrá después.

## Decisiones de diseño
- Rutas `GET /admin/*` pasan a `[UserRole::ADMIN, UserRole::AUDITOR]`. Los `POST` (y `any` de mutación) siguen `[UserRole::ADMIN]`.
- Guards internos de controladores (`Auth::requireRole`) de los métodos de vista pasan a `['admin', 'auditor']` / `[UserRole::ADMIN, UserRole::AUDITOR]` según el estilo del archivo. Los métodos de mutación no se tocan.
- `PagoController::listar`: la rama de vista admin incluye también a auditor (`in_array($rol, ['admin', 'auditor'], true)`); `/pagos` sigue con middleware `['auth']`.
- Sidebar del auditor: se amplía al menú completo del admin (10 ítems) manteniendo sus dos ítems fiscales (`Dashboard Fiscal`, `Log Auditoría`). El ítem fiscal se remapea a `activeRoute = 'fiscal'` para no colisionar con el Dashboard admin (`'dashboard'`).
- Vistas admin con sidebar fijo: pasan al patrón por rol ya existente (gastos/cuentas/morosidad): `if (\App\Core\Auth::role() === 'auditor') require auditor_sidebar else admin_sidebar`, preservando el `$activeRoute` actual de cada vista.
- Excepciones (quedan solo admin) y se documentan para la poda posterior:
  - `GET /admin/respaldos/descargar/{id}` → descarga de dump completo; no es "vista".
  - `GET|POST /admin/facturas/generar` → acción de compatibilidad/generación.
  - Todos los POST/mutaciones (aprobaciones, cambios, creación, eliminación, importación, conciliación, envío de avisos, guardar/eliminar de cualquier módulo).
- Los botones/formularios de mutación seguirán visibles dentro de las páginas (403 al usar) hasta la poda por módulo ("luego vamos quitando cosas").

## Cambios
1. `public/index.php` — subir a `[UserRole::ADMIN, UserRole::AUDITOR]` SOLO estas rutas GET:
   `/admin/dashboard`, `/admin/comprobantes`, GET `/admin/comprobante/verificar`, `/admin/estructura`, `/admin/estacionamientos`, `/admin/reportes/morosidad`, `/admin/reportes/morosidad/imprimir`, `/admin/reportes/morosidad/exportar-csv`, `/admin/reportes/balance`, `/admin/reportes/balance/imprimir`, `/admin/reportes/balance/exportar-csv`, `/admin/reportes/carta-deuda/{unidadId}`, `/admin/comunicados`, `/admin/solicitudes-registro`, `/admin/usuarios`, `/admin/conciliacion`, `/admin/cuentas-bancarias`, `/admin/gastos`, `/admin/gastos/maestro`, `/admin/respaldos`.
   NO tocar: todos los POST, `/admin/comprobante/verificar` POST, `/admin/facturas/generar`, `/admin/respaldos/descargar/{id}`, `/admin/respaldos/generar`, APIs.
2. Controladores — guard de los métodos de vista a `['admin','auditor']` (estilo del archivo):
   - `AdminController`: `dashboard`, `listarComprobantes`, `verificarComprobante` (usar `[UserRole::ADMIN, UserRole::AUDITOR]`).
   - `EstructuraController`: `index`. `EstacionamientoController`: `index`.
   - `ReporteController`: `morosidad`, `imprimirMorosidad`, `exportarCsv`, `generarCartaDeuda`.
   - `ComunicadoController`: `index`. `SolicitudesRegistroController`: `index` (usar UserRole). `UsuarioAdminController`: `index` (usar UserRole).
   - `ConciliacionController`: `index`. `CuentaBancariaController`: `index`.
   - `GastoController`: `index`, `cargarMaestro`. `RespaldoController`: `index` (usar UserRole).
   - `PagoController::listar`: rama admin → `in_array($rol, ['admin', 'auditor'], true)`.
3. `app/views/layouts/auditor_sidebar.php` — menú completo: `fiscal` (Dashboard Fiscal, /auditor/dashboard), `logs` (Log Auditoría), y los 10 ítems del admin con sus mismas URLs/labels/keys (`dashboard`, `comprobantes`, `conciliacion`, `cuentas_bancarias`, `gastos`, `estructura`, `estacionamientos`, `comunicados`, `usuarios`, `morosidad`). Conservar literal `'label' => 'Gastos y Facturación'` y `'label' => 'Carta de Deuda'` (tests).
   `app/views/auditor/dashboard.php` — `$activeRoute = 'fiscal';`.
4. Vistas admin con sidebar fijo → patrón por rol (preservando su `$activeRoute`): `admin/comprobantes.php`, `admin/estacionamientos/index.php`, `admin/dashboard.php`, `pagos/admin/lista.php`, `admin/conciliacion/index.php`, `admin/comunicados/index.php`, `admin/usuarios/index.php`, `admin/estructura.php`, `admin/respaldos/index.php`.
5. `tests/RbacAuthorizationTest.php` — nuevo test de contrato: todas las rutas GET `/admin/*` (salvo excepciones documentadas) contienen `UserRole::AUDITOR`; ninguna mutación `/admin/*` lo habilita. Excepciones GET: `/admin/respaldos/descargar/{id}`, `/admin/facturas/generar`, `/admin/login`, `/admin/logout`.

## Restricciones funcionales
- `RbacAuthorizationTest` existente debe seguir verde (las rutas conservan `UserRole::ADMIN`).
- No tocar vistas ni rutas del portal residente.
- No tocar `RoleMiddleware` (el bloqueo de mutaciones para auditor ya es correcto).
- Conservar labels/keys verificados por tests (`Gastos y Facturación`, `Carta de Deuda`, `Duración`, etc.).
- `php -l` limpio en todos los archivos tocados. Commit en ASCII.

## Criterios de aceptación
1. Auditor (rol `auditor`) puede abrir todos los GET admin listados (excepto excepciones documentadas) sin 403.
2. Cualquier mutación (POST) del auditor sigue devolviendo 403 (RoleMiddleware).
3. `php tests/run.php --filter=RbacAuthorizationTest` verde, incluido el nuevo test de visibilidad.
4. Suites relacionadas verdes: `BalanceAgrupadoEdificiosTest`, `GastosFacturacionTabsTest`, `UsuarioCrearRolTest`, `PagoDetalleNavegacionTest`, `BehaviorTest`, `AuthTest`, `RouterTest`.
5. El sidebar del auditor muestra todos los módulos y marca correctamente el activo; las vistas admin muestran el sidebar de auditor cuando el rol es auditor.

## Ruta de implementación
- Writer único (delegado). Verificación del writer (`php -l` + suites filtradas) + verificador independiente (read-only, adversarial: acceso/mutaciones/excepciones) + spot check del padre. Commit del orquestador directo en `main`, sin push.
- Skills: `work-unit-commits`. TDD no configurado; runner: `php tests/run.php --filter=<Clase>`.

## Verificación
- Writer (delegado): `php -l` 25/25 sin errores; suites filtradas exit 0: RbacAuthorizationTest 8/215 ✅ (incluye el test nuevo), BalanceAgrupadoEdificiosTest 9/47 ✅, GastosFacturacionTabsTest 5/30 ✅, UsuarioCrearRolTest 4/18 ✅, PagoDetalleNavegacionTest 4/31 ✅, BehaviorTest 108/252 ✅, AuthTest 20/37 ✅, RouterTest 23/44 ✅. Harness empírico temporal (borrado): `requireRole(['admin','auditor'])` permite auditor; dos argumentos posicionales NO (gotcha detectado y corregido por el writer antes del cierre).
- Spot check del padre: `php -l public/index.php` limpio; RbacAuthorizationTest 8/215 ✅ exit 0.
- Verificador independiente (read-only, adversarial): **VERIFIED CON OBSERVACIONES** — 17/17 guardas en formato array (0 llamadas de dos argumentos); 20 rutas GET /admin/* con `UserRole::AUDITOR` y excepciones coherentes (login/logout/facturas/respaldos-descargar); mutaciones exclusivas en triple capa (ruta + guard + RoleMiddleware); sidebar auditor 12 ítems con labels literales conservados y `fiscal` sin colisión; 9 vistas convertidas preservando `$activeRoute`; test nuevo no tautológico (probado por trazas de falsificación); RoleMiddleware y vistas residente intactos. Observaciones: (a) las limpiezas oportunistas GET (`ComunicadoController::index` soft-delete de vencidos; `UsuarioAdminController::index` DELETE duro de solicitudes vencidas) ahora son alcanzables por auditor — aceptado en esta iteración, anotado como candidato de la poda por módulo; (b) `/pagos` y `/admin/respaldos` no resaltan ítem en el sidebar auditor (su `activeRoute` no existe como ítem; intencional).
- RDD: modo on (global); preflight nativo inoperable en el runtime OpenCode de este equipo (`immutable_review_transport_unsupported`, `mutation_outcome: not_started`, ya registrado en la sesión). Ruta RDD-off aplicada: auto-verificación del writer + verificador independiente + spot check del padre. No se desactiva el modo (decisión del usuario).
- Residual honesto: sin verificación renderizada en navegador; el test de rutas es un parser por línea (una ruta multilínea futura no se auditaría individualmente).

## Entrega
- Commit `7b844be` (feature: 20 rutas + 17 guardas + sidebar + 9 vistas + test de contrato; 25 archivos, +157/−53) + commit de cierre `docs(odd)`. Directo en `main` (sin push, decisión del usuario).
