# Panel del residente: simplificar a deuda + comunicados reales (solo visualización)

## Objetivo (instrucción del usuario)
"el usuario solo debería ver el monto que debe pagar; quita facturas pendientes y comprobantes recientes del panel de usuario; en su lugar pon el apartado para ver comunicados y empieza a darle funcionalidad real (para el usuario es solo visualización)."

## Contexto (mapeo read-only 2026-10-03)
- Ruta: `/residente/dashboard` → `ResidenteController::dashboard` (public/index.php:133). Vista: `app/views/residente/dashboard.php` (228 líneas).
- Secciones actuales: header residente (L2-21), tarjetas "Deuda Total" (L25-34) y "Saldo a Favor" (L36-45), "Facturas Pendientes" (L48-127), "Comprobantes Recientes" (L129-192), modal `#modalComprobante` (L195-199) + script (L201-228).
- Cartelera existente: `/residente/cartelera` → `ComunicadoController::carteleraResidente`; vista `cartelera.php` con lenguaje visual a reutilizar (badges urgencia L35-41, fecha `d/m/Y H:i`, `edificio_nombre`, saneo de contenido).
- Bug de resolución relevante: `carteleraResidente` resuelve la unidad vía `unidades.propietario_id` (ComunicadoController.php:148-168) — excluye inquilinos y no coincide con el resto del portal, que usa `personas.unidad_id` (vía `getAuthenticatedResidente`/`PersonasModel::getResidenteDetails`). La cartelera puede estar vacía para residentes reales → hay que unificar la resolución para que la "funcionalidad real" funcione.
- Decisión de alcance tomada por el orquestador: se conservan las tarjetas "Deuda Total" y "Saldo a Favor" (son montos, no listas); solo se quitan las dos secciones nombradas por el usuario.
- TDD: no configurado → verificación funcional por suites (`php tests/run.php`). Baseline verde en `main` dc15d49.
- Hallazgo TZ (detectado por el writer, confirmado por el orquestador): PHP `Europe/Berlin` vs MySQL `SYSTEM` = ~6h de desfase. `obtenerPorResidente` filtraba `fecha_publicacion <= NOW()` (reloj MySQL) mientras `crearComunicado` escribe con reloj PHP → un comunicado recién publicado quedaba invisible ~6h. Fix aplicado: `:ahora` con reloj PHP (consistente con la escritura y con `existeDuplicadoReciente`) + test de regresión (falsificación verificada: RED con el código viejo, GREEN con el fix).
- RDD: on (global); runtime OpenCode no elegible para revisión inmutable → ruta writer + verificador independiente + spot check (igual que tareas previas del repo).

## Alcance (6 tareas)
- T1. Vista dashboard: eliminar "Facturas Pendientes" (L48-127) y "Comprobantes Recientes" (L129-192) completas (headers, empty states, tablas, paginaciones), el modal `#modalComprobante` (L195-199) y el script asociado (L201-228, solo lo usaba ese modal — verificado). Conservar header, tarjetas y cierre.
- T2. Vista dashboard: nueva sección "Comunicados" en el lugar de las anteriores: card blanca con header (icono `campaign`, título "Comunicados", badge de total, link "Ver todos" → `/residente/cartelera` con el patrón del viejo "Ver todos" de comprobantes), empty state calcado de `cartelera.php:23-28`, y hasta 4 comunicados: badge de urgencia EXACTO de cartelera (`bg-red-100 text-red-700` / `bg-amber-100 text-amber-800` / `bg-slate-100 text-slate-700`, `text-xs font-bold px-3 py-1 rounded-full uppercase`), fecha `d/m/Y H:i`, `e(edificio_nombre)` en badge `text-primary bg-primary/10`, título `e(...)` y extracto en texto plano (strip_tags + colapso de espacios + `mb_substr(..., 0, 180)` + elipsis + `e()`). Contenido nunca crudo. Sin tracking de leído (solo visualización).
- T3. `ResidenteController::dashboard()`: quitar `$porPagina`, `$pageFacturas`, `$pageComprobantes`, cargas de facturas paginadas, comprobantes y sus vars de render (conservar `getTotalDeudaByUnidad` y `getSaldoFavorByUnidad`). Agregar `use App\Models\ComunicadosModel;` + carga `(new ComunicadosModel())->obtenerPorResidente($edificioId, $unidadId, 1, 4)` y pasar `comunicados` + `totalComunicados` al render. NO tocar `enviarPago()` ni `historial()` ni los `use` que ellos necesitan.
- T4. Resolución unificada residente→unidad/edificio: `PersonasModel::getResidenteDetails` agrega `u.edificio_id AS edificio_id` al SELECT (ya hace el JOIN a `unidades`); `ComunicadoController::carteleraResidente` elimina el SQL propio por `unidades.propietario_id` y usa la resolución del portal (`getAuthenticatedResidente()`), manteniendo paginación y render actuales. Sin fallback a propietario (fuente canónica: `personas.unidad_id`; el INNER JOIN ya garantiza unidad asignada).
- T5. Tests: (a) actualizar `tests/PaginacionEstandarTest.php` — su test de paginación del dashboard residente (L25-39) queda obsoleto; reescribirlo acorde al nuevo contrato conservando razonablemente la cobertura de `method_exists` de los modelos; (b) nuevo `tests/ComunicadosResidenteTest.php` (clase `Tests\ComunicadosResidenteTest`, auto-descubierto por `tests/run.php`): visibilidad de `obtenerPorResidente` (global + edificio + unidad visibles; excluye otros destinos, `deleted_at` y futuros; orden DESC; total/paginación) siguiendo el patrón DB de `ComunicadosDuplicadosTest`; contrato de vista (dashboard contiene "Comunicados" y `/residente/cartelera`; NO contiene "Facturas Pendientes"/"Comprobantes Recientes"); `ResidenteController.php` contiene `obtenerPorResidente`; `ComunicadoController.php` contiene `getAuthenticatedResidente`.
- T6. Verificación: `php -l` de todos los archivos tocados; suite completa exit 0; verificador independiente read-only; spot check del padre; commit work-unit.
- T7. Fix TZ del filtro de publicación: `ComunicadosModel::obtenerPorResidente` compara contra `:ahora` (reloj PHP) en vez de `NOW()` (MySQL); test de regresión `testComunicadoRecienPublicadoEsVisibleDeInmediato` en el test nuevo.

## Restricciones
- No romper contratos testeados: `RbacAuthorizationTest` (vistas residente sin links/forms `/admin`), `AuthTest` (requireRole), `RouterTest`, `ComprobantesDuplicadosTest` y `BehaviorTest` (strings de `enviarPago` intactos), `ComunicadosDuplicadosTest` (strings de `guardar`/`encolarComunicadoCorreo` intactos), `SaldoFavorTest`/`ModelTest` (conservar métodos existentes de los modelos).
- Conservar `FacturasModel::getPendientesByUnidad`, `FacturasModel::getTotalDeudaByUnidad`, `FacturasModel::getSaldoFavorByUnidad` y `ComprobantesModel::getRecientesByResidente` en los modelos.
- No tocar: `cartelera.php`, `notificaciones.php`, vistas de pagos, layouts, rutas (la cartelera ya está ruteada).
- Contenido sensible: re-sanear/escapar siempre en la vista (mismo criterio que cartelera).
- SQL: `Database.php` usa `PDO::ATTR_EMULATE_PREPARES => false` (no repetir placeholders).
- Sin endpoints nuevos; solo visualización.

## Checklist
- [x] T1 quitar secciones + modal + script del dashboard
- [x] T2 nueva sección "Comunicados" (datos reales, lenguaje de cartelera, link Ver todos)
- [x] T3 controller dashboard limpio + carga de comunicados
- [x] T4 resolución unificada (PersonasModel + carteleraResidente)
- [x] T5 tests actualizados + nuevo ComunicadosResidenteTest
- [x] T6 php -l + suite completa exit 0 + verificación independiente + commit (498fda0)
- [x] T7 fix TZ + test de regresión (falsificación verificada RED→GREEN)

## Verificación
- Writer: `php -l` de los archivos tocados; `php tests/run.php` → exit 0; filtros `--filter=PaginacionEstandarTest`, `--filter=ComunicadosResidenteTest`, `--filter=RbacAuthorizationTest`, `--filter=AuthTest`, `--filter=RouterTest`, `--filter=ComprobantesDuplicadosTest`, `--filter=ComunicadosDuplicadosTest`, `--filter=BehaviorTest`, `--filter=SaldoFavorTest`. Auto-auditoría del diff (alcance acotado a: dashboard.php, ResidenteController.php, ComunicadoController.php, PersonasModel.php, PaginacionEstandarTest.php, ComunicadosResidenteTest.php).
- Verificador independiente read-only: revisión adversarial del diff completo + suites.
- Spot check del padre: `php -l` + filtradas clave + revisión del diff.
- Cierre: commit work-unit en `feat/residente-panel-comunicados`; push/PR = decisión del usuario.

## Evidencia (cierre 2026-10-03)
- T1-T7 aplicadas 7/7.
- `php -l` 7/7: "No syntax errors detected".
- Suites: `php tests/run.php` completo exit 0; filtros clave exit 0 (ComunicadosResidenteTest 10, PaginacionEstandarTest 10, RbacAuthorizationTest 7, AuthTest 20, RouterTest 23, ComprobantesDuplicadosTest 6, ComunicadosDuplicadosTest 7, BehaviorTest 111, SaldoFavorTest 5).
- NOTA runner (defecto PREEXISTENTE, no de este cambio): `SecurityTest::testCsrfRejectsInvalidToken` provoca el `exit` de `Security::validateCSRF` (Security.php:52) y el run completo muere antes del RESUMEN, saltándose las últimas 4 clases con exit 0. Las 4 clases se verificaron por separado: SolicitudesRegistroTest 7, UsuarioAdminAccionesTest 7, UsuarioAdminTest 10, UsuariosSolicitudesTabsTest 8 — todas ✅ exit 0. Recomendación: ticket separado (runner/seguridad), fuera de alcance.
- Falsificación TZ probada: código viejo → RED (exit 1, 1 failed); con fix → GREEN. El verificador independiente reprodujo empíricamente: predicado viejo 0 visibles / nuevo 1 visible (stored 18:34 vs DB 12:30).
- Verificación independiente read-only: VERIFIED CON OBSERVACIONES. Sin hallazgos que invaliden: pedido cumplido, render saneado (sin echo crudo), `enviarPago`/`historial` intactos, resolución unificada, tests honestos, 0 filas de prueba residuales. Observaciones: runner early-exit (preexistente; arriba) + comentario `dbTime` obsoleto (corregido) + `idsVisibles` con límite 100 (teórico, sin impacto).
- Spot check del padre: lints + 4 filtros de las clases no alcanzadas por el run completo + revisión de alcance del diff.
- Commit work-unit: 498fda0 (8 archivos; 392 ins / 230 del). Push/PR: decisión del usuario.
- RDD: on; assess (working tree y post-commit base dc15d49) → `risk: high`, `unassessable` (untracked + runtime V2 sin elegibilidad inmutable), sin `next_transition` → ruta RDD-off aplicada (writer + verificador independiente + spot check).
