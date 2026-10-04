# Arreglos de auditoría admin/auditor

Fecha: 2026-10-04
Origen: auditoría general pedida por el usuario (hallazgos en engram `auditoria/hallazgos-admin-auditor`).
Ruta: implementación DELEGADA — un escritor único (trigger: 2+ archivos no triviales). Verificación: auto-verificación del escritor + verificación independiente del orquestador (revisión nativa RDD no disponible en OpenCode).
TDD: off — sin configuración de TDD en el proyecto; verificación con el runner custom `php tests/run.php`.

## Objetivo

Corregir los hallazgos confirmados de la auditoría manteniendo la suite en verde, sin cambiar rutas, roles ni contratos públicos.

## Checklist (IDs estables)

- [x] T1 `app/views/pagos/admin/lista.php`: eliminar formularios anidados; acciones por fila (`/pagos/cambiar-estado`) y masiva (`/admin/pagos/aprobar-masivo`) funcionan por separado.
      Evidencia: el form masivo envuelve solo `#barraMasiva`; checkboxes con `form="formAprobacionMasiva"`; ArreglosAuditoriaTest (balance + profundidad ≤ 1) ✅.
- [x] T2 Traversal `archivo_maestro`: `GastoController::importarMaestro` sanea con basename (+ backslashes y bytes nulos); `GastosModel::importarGastosMaestro` almacena el nombre saneado; `GastosModel::eliminarGasto` aplica basename antes del `unlink`.
      Evidencia: prueba empírica `..\..\windows\system32\evil.pdf` → `evil.pdf`, nulo → eliminado, nombre válido intacto; tests de fuente ✅.
- [x] T3 `EstacionamientoController::guardarVehiculo`: `persona_id` se resuelve desde la unidad (residente activo → propietario → error flash); no se lee de `$_POST`.
      Evidencia: nuevo `PersonasModel::obtenerPrimerResidenteActivo`; verificado contra BD viva (unidad con residentes → id; sin residentes → NULL); test de fuente sin `$_POST['persona_id']` ✅.
- [x] T4 `EstacionamientoController::eliminarVehiculo`: guard con roles válidos (strings, como el resto del archivo).
      Evidencia: `Auth::requireRole(['admin', 'auditor'])`, sin `UserRole::` en el método ✅.
- [x] T5 `ReporteController::generarCartaDeuda`: parámetro sin tipo + `intval` + 404 para id inválido (no 500).
      Evidencia: firma `generarCartaDeuda($unidadId)` + guard `intval`/`errors/404`; test de fuente ✅.
- [x] T6 Warnings: JSON body en `conciliarPago`; rango mes/año en `generarFacturas` (AdminController); quitar `http_response_code(400)` previo a redirect en `guardar` (Estacionamiento); coerción `intval(array)` (PagoModel::aprobarLote, conciliarPago/conciliarLote, importarMaestro); uploads con error no silenciados + `FileUploader` dentro de try (gastos guardar/parsear); perfil del auditor usa `$usuarioAdmin` (`$isAdmin || $isAuditor`).
      Evidencia: `id[]=1` ya no coacciona a 1 (verificado con closures `is_scalar`+`is_numeric`); filtro de aprobarLote idéntico al anterior para ids válidos; upload fallido bloquea el guardado; tests de fuente ✅.
- [x] T7 Tests de regresión `tests/ArreglosAuditoriaTest.php`.
      Evidencia: `php tests/run.php --filter=Arreglos` → 14 tests, 37 passed, 0 failed.
- [x] T8 Verificación: `php -l` + filtros + suite completa (aborto conocido en SecurityTest).
      Evidencia: 12 archivos con `php -l` sin errores; filtros Gasto/Estacionamiento/Conciliacion/Pago/Rbac/Perfil/Balance/Solicitudes/Usuario en verde; suite completa aborta en SecurityTest con 0 ❌ previas.

## Aceptación

- Sin `<form>` anidados; cada flujo de pagos postea sus campos correctos.
- Ningún POST puede hacer que un `unlink` salga de `uploads/soportes`.
- Vehículo registrado guarda un `personas.id` vinculado a su unidad (nunca `usuarios.id`).
- Sin nuevos 500 en rutas de admin/auditor con entradas inválidas.
- Suite: sin ❌ (SecurityTest aborta el runner: preexistente, ya documentado).

## Decisiones

- `persona_id` del vehículo: residente activo de la unidad → propietario de la unidad → error flash. Sin selector de persona en la UI; se ignora `$_POST['persona_id']`.
- No se modifican rutas ni roles.

## Evidencia

### php -l (12 archivos, sin errores)

lista.php, perfil/index.php, GastoController, GastosModel, EstacionamientoController, ReporteController, ConciliacionController, AdminController, PagoModel, ConciliacionBancariaService, PersonasModel, ArreglosAuditoriaTest → todos "No syntax errors detected".

### Filtros

| Comando | Resultado |
| --- | --- |
| `php tests/run.php --filter=Gasto` | ✅ 27 tests, 122 passed, 0 failed |
| `php tests/run.php --filter=Estacionamiento` | ✅ 13 tests, 41 passed, 0 failed |
| `php tests/run.php --filter=Conciliacion` | ✅ 43 tests, 347 passed, 0 failed |
| `php tests/run.php --filter=Pago` | ✅ 32 tests, 178 passed, 0 failed |
| `php tests/run.php --filter=Rbac` | ✅ 9 tests, 231 passed, 0 failed |
| `php tests/run.php --filter=Perfil` | ✅ 5 tests, 25 passed, 0 failed |
| `php tests/run.php --filter=Balance` | ✅ 9 tests, 47 passed, 0 failed |
| `php tests/run.php --filter=Arreglos` | ✅ 14 tests, 37 passed, 0 failed |
| `php tests/run.php --filter=Solicitudes` | ✅ 22 tests, 144 passed, 0 failed |
| `php tests/run.php --filter=Usuario` | ✅ 33 tests, 133 passed, 0 failed |
| `php tests/run.php` (completa) | aborta en SecurityTest::testCsrfRejectsInvalidToken (403 + exit, preexistente); 0 líneas ❌ antes del aborto |

### Commits (11 desde 6f95e5d, locales sin push)

- `6f95e5d` feat(gastos): renombrar boton a Cargar PDF Maestro
- `6b2ca83` fix(pagos): separar aprobacion por fila del formulario masivo y sanear ids del lote
- `e53db4e` fix(gastos): sanear archivo maestro y robustecer importacion y subidas
- `4f92685` fix(estacionamientos): dueno del vehiculo desde la unidad y guard de eliminacion
- `44feb84` fix(conciliacion): aceptar body JSON y validar ids escalares en lotes
- `3168fdb` fix(admin): rango de periodo, carta de deuda sin 500 y perfil del auditor
- `761a1b1` test(regresion): suite de arreglos de auditoria admin/auditor
- `ec44784` fix(periodos): validar mes/anio escalares en gastos y facturas
- `5573553` fix(conciliacion): devolver token CSRF rotado en respuestas JSON
- `d613ab3` test(regresion): cubrir periodos escalares y token CSRF en JSON
- `99ee6e7` test(regresion): endurecer extraccion de metodos y conteo de token CSRF

### Verificación final (misma batería del informe)

| Comando | Resultado |
| --- | --- |
| Suite completa `php tests/run.php` | 0 aserciones fallidas y 0 excepciones antes del aborto conocido en SecurityTest (preexistente) |
| `--filter=Behavior` (incluye aprobarLoteHasCascadeLoop) | 108 tests / 252 passed |
| `--filter=Gasto` | 27 / 122 |
| `--filter=Conciliacion` | 43 / 347 |
| `--filter=Arreglos` | 17 / 44 |
| `--filter=Solicitudes` | 22 / 144 |
| `--filter=Usuario` | 33 / 133 |
| SecurityTest (harness externo, 21 métodos) | 30 passed / 0 failed (1 excluido: mata el runner por diseño) |

Verificación independiente por subagente de contexto fresco: F1–F5 verificados; F6 y delta (periodos, token CSRF) re-verificados tras las correcciones.

### Notas de implementación

- T6e: `UPLOAD_ERR_NO_FILE` se exceptúa del rechazo porque el soporte digital es opcional en el formulario; cualquier error real de subida (tamaño, parcial, etc.) bloquea el guardado.
- Periodos: helper `periodoDesdePost` (gastos) y closure `$periodo` (facturas): ausente o vacío => default; array o texto inválido => 0 (rechazado por rango). `importarMaestro` valida 1..12 / 2000..2100 antes de procesar.
- Respuestas JSON de `conciliarPago` devuelven el token CSRF rotado (4/4 payloads), igual que `PagoController::extraer`.
- Residuales INFO de la verificación (no bloqueantes, fuera del alcance de esta tanda): el 404 de carta de deuda responde HTTP 200 (patrón preexistente de `render('errors/404')`); un período inválido en `guardar` puede dejar un soporte huérfano en `uploads/soportes` (orden preexistente: sube antes de validar); los filtros GET usan `intval($_GET['mes'])` (vista de solo lectura, preexistente).
- Commits locales, sin push.
