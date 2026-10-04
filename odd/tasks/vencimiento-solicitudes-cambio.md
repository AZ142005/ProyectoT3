# Vencimiento de solicitudes de cambio de datos (24 h) + aviso al residente

## Objetivo (instrucción del usuario)
- Las solicitudes de cambio de datos en estado **pendiente** o **rechazado** deben durar máximo **1 día** antes de eliminarse.
- Dejar una **nota al residente** advirtiendo sobre este vencimiento.

## Contexto / hallazgos
- Flujo: residente envía solicitud desde `app/views/perfil/index.php` (tarjeta "Solicitar Actualización de Datos") → admin procesa en `/admin/usuarios?tab=cambios` (`app/controllers/UsuarioAdminController.php`) → historial visible en "Historial de Solicitudes Enviadas" del perfil.
- Ya existe una regla de 24 h en `SolicitudesModel::crearSolicitud` (reintento permitido tras rechazo pasado 24 h desde `fecha_respuesta`); este cambio la extiende con eliminación.
- Semántica elegida (coherente con la regla existente):
  - `pendiente`: se elimina cuando `fecha_solicitud` supera las 24 h (antigüedad desde el envío).
  - `rechazado`: se elimina cuando `fecha_respuesta` supera las 24 h; si `fecha_respuesta` es NULL, se usa `fecha_solicitud`.
  - `aprobado`: se conserva siempre (registro de los cambios aplicados).
- Reloj: aritmética MySQL (`NOW()` / `DATE_SUB(..., INTERVAL 1 DAY)`) porque ambas columnas son escritas por MySQL (`current_timestamp()` y `NOW()`); evita el desfase PHP/MySQL ya documentado en el repo.
- Borrado perezoso (sin cron), siguiendo el patrón existente de `ComunicadosModel::eliminarExpirados`:
  - `PerfilController::verPerfil` → limpieza acotada a la persona antes de listar su historial.
  - `PerfilController::solicitarCambio` → limpieza acotada a la persona antes de crear (evita bloqueos por duplicados vencidos).
  - `UsuarioAdminController::index` → limpieza global antes de listar/contar la bandeja de cambios.
- Fuera de alcance: `solicitudes_registro` (flujo de registro), notificaciones al eliminar y cron/scheduler.

## Cambios
1. `app/models/SolicitudesModel.php` — nuevo método `eliminarExpiradas(?int $personaId = null): int` con las condiciones de la semántica y filtro opcional por persona. Ubicación: tras `obtenerPorPersona()`, antes de `procesarSolicitud()`.
2. `app/controllers/PerfilController.php` — llamadas acotadas en `verPerfil()` (antes de `obtenerPorPersona`) y en `solicitarCambio()` (antes de `crearSolicitud`).
3. `app/controllers/UsuarioAdminController.php` — llamada global en `index()` antes de `obtenerTodasAdmin`/`contarPendientes`.
4. `app/views/perfil/index.php` — nota ámbar en la tarjeta de solicitud del residente, bajo la descripción existente. Texto exacto (una sola línea):
   "Solo se conservan las solicitudes aprobadas: las pendientes se eliminan 24 horas después de su envío y las rechazadas 24 horas después del rechazo."
5. `tests/SolicitudesCambioDatosTest.php` — pruebas nuevas:
   - Funcional (acotada por persona, sin tocar datos reales): pendiente >24 h eliminada; rechazada con respuesta >24 h eliminada; pendiente reciente intacta; rechazada reciente intacta; aprobada antigua intacta; la limpieza acotada no toca filas de otra persona.
   - Estática: la vista del perfil contiene la nota; el modelo expone `eliminarExpiradas` con `INTERVAL 1 DAY`; los dos controladores invocan `eliminarExpiradas`.
   - NO invocar la variante global `eliminarExpiradas()` en tests (borraría datos reales del entorno de desarrollo).

## Restricciones funcionales
- Sin cambios de esquema, rutas ni JS.
- No alterar el cuerpo de `crearSolicitud` (BehaviorTest lo verifica por regex: `86400`, `pendiente`, `rechazado`, `ksort`).
- Conservar intactos: textos, iconos, `href`, formularios, `csrf_field()`, condicionales PHP y variables existentes.
- `php -l` limpio en los archivos tocados.
- Mensajes de UI y comentarios en español neutro; commit en ASCII.

## Criterios de aceptación
1. Solicitudes pendientes o rechazadas con más de 24 h no aparecen en: historial del residente, bandeja admin ni contador de pendientes de cambios.
2. Solicitudes dentro del día y aprobadas permanecen intactas.
3. La nota es visible para el residente y describe exactamente la regla.
4. Suites filtradas verdes: `SolicitudesCambioDatosTest`, `PerfilAdminTest`, `BehaviorTest` (mismos fallos de entorno preexistentes si los hubiera).
5. Diff acotado a los 5 archivos + este doc.

## Ruta de implementación
- T1–T4: writer único (delegado) — modelo + controladores + vista + tests. El writer no commitea.
- T5: verificación del writer (`php -l`, suites filtradas) + verificador independiente (read-only) + spot check del padre; commit del orquestador directo en `main` (convención del repo), sin push.
- Skills: `work-unit-commits` (organización; el writer no commitea).
- TDD: no configurado (sin `sdd-init`); runner propio `php tests/run.php --filter=<Clase>`; se agregan tests de regresión funcional y estáticos.

## Verificación
- Writer (delegado): `php -l` 5/5 sin errores; `php tests/run.php --filter=SolicitudesCambioDatosTest` → 7 tests / 62 asserts ✅; `--filter=PerfilAdminTest` → 5 tests / 25 asserts ✅; `--filter=BehaviorTest` → 108 tests / 252 asserts ✅; exit 0 en todos.
- Spot check del padre: re-ejecución de `SolicitudesCambioDatosTest` → 7/63 ✅ exit 0 + `php -l` limpio sobre modelo y tests.
- Verificador independiente (read-only, adversarial): VERIFIED CON OBSERVACIONES — semántica correcta (pendiente 24 h desde envío; rechazada 24 h desde respuesta con fallback; aprobada intacta), reloj MySQL justificado (columnas escritas por MySQL), 3 puntos de limpieza correctos y sin otros lectores de la tabla sin limpieza, `crearSolicitud` intacto, tests no tautológicos, nota exacta solo en rama residente, scope acotado. Observaciones atendidas antes del commit: H1 (los asserts estáticos ahora exigen las llamadas exactas: `eliminarExpiradas((int)$personaId)`, `eliminarExpiradas($personaId)`, `$solicitudesCambioModel->eliminarExpiradas();`) y H5 (la API ya no interpreta 0/negativo como borrado global: solo `null` limpia global). Residual aceptado: H2 (el fallback `fecha_respuesta NULL` no es alcanzable por el flujo de la app; la frontera exacta de 24 h es inherentemente variable), H4 (race TOCTOU de `procesarSolicitud`: no revalida vencimiento, pero cada carga de `/admin/usuarios` limpia globalmente; fuera de alcance del doc), H6 (sin índice dedicado para la limpieza; tabla pequeña).
- RDD: `mode status` → on (global); preflight nativo `review status --contract gentle-ai.review-integration/v2` → `immutable_review_transport_unsupported` (runtime OpenCode no elegible; `mutation_outcome: not_started`). Ruta RDD-off aplicada: auto-verificación del writer + verificador independiente + spot check del padre. No se desactiva el modo (decisión del usuario).
- Residual honesto: sin verificación renderizada en navegador (nota validada por código y test estático).

## Entrega
- Commit `067c515` (feature: modelo + controladores + vista + tests; 5 archivos, +88/−1) + commit de cierre `docs(odd)`. Directo en `main` (convención del repo). Sin push (decisión del usuario).
