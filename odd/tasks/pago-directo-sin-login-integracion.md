# Integración: Pago directo sin iniciar sesión sobre la main actual

## Objetivo
Integrar en la línea actual de `main` los cambios de la feature **pago directo sin iniciar sesión (portal público)** —desarrollados en la copia aparte `Cambios aparte/`— re-aplicados sobre la `main` divergente actual (refactor de historial de pagos, estructura por edificios, conciliación centralizada, etc.).

## Contexto
- Base común de ambas líneas: `2d00326` (feat: users, saldo a favor and receipts).
- La feature original vive en la rama local `feat/pago-directo-sin-login` (tip `e2b8b09`; commits `7a0b47f..e2b8b09`) con refinamientos posteriores de la misma feature en `feat/pago-directo-en-revision`: `d133131` (atribuir el pago al residente principal de la unidad y crearlo EN REVISIÓN) y `bb6fdc3` (badge azul de EN REVISIÓN y timeline coherente).
- `main` actual: `46a3899` (= `origin/main` al momento de esta integración).
- Fuera de alcance (existen en la copia aparte pero NO se integran aquí): eliminación del módulo de comunicados, fix del esquema `backups_log`, carta de deuda / WhatsApp, nombre del conjunto en el login, fix de navegación del detalle de pago (`0227815`).

## Tareas
- [x] **T1 — Rama + doc**: rama `feat/pago-directo-integracion` desde `main` + este documento.
- [x] **T2 — Feature base**: cherry-pick `0227815..e2b8b09` (portal público completo: capa de datos + migración, controlador y rutas, vistas, login, tests, fix de rate limit, validaciones, autocompletado del comprobante). Commit local del rango: `abae3c2..56fac74`.
- [x] **T3 — Refinamientos**: cherry-pick `d133131` + `bb6fdc3` (atribución al residente principal + EN REVISIÓN; badge y timeline). Commits locales: `976dffa`, `03c412d`.
- [x] **T4 — Verificación**: tests por clase (línea base vs. final), `check_purity.php`, `audit_security.php`, migración idempotente. Ver resultados abajo.
- [x] **T5 — Cierre**: actualizar este doc (verificación y progreso) con commit final.

## Política de resolución de conflictos
- `app/models/PagoModel.php` — conservar la estructura actual de `main` (transacción, dedup por identidad económica con `referencia_norm`, `deleted_at`) y aplicar la semántica de la feature:
  - `crearPago`: aceptar `$residenteId` NULL (docblock/comentario; el binding NULL es válido) y estado inicial configurable por `$datos['estado']` con whitelist `EstadoPago::all()` (por defecto `PENDIENTE`); INSERT con `:estado` en lugar del literal `'PENDIENTE'`.
  - `obtenerTodosPagos` (rama `pagos` del UNION): `LEFT JOIN personas` + `COALESCE(..., 'Pago directo (sin usuario)')` para que los pagos sin usuario no desaparezcan del listado ni del conteo. No tocar la rama de `comprobantes_pago`.
  - `obtenerPagoPorId` (consulta de `pagos`): mismo cambio; conservar intacto el fallback de `comprobantes_pago`.
- `public/index.php`: conservar todas las rutas actuales de `main` y agregar las 5 rutas públicas `/pago-directo*` (`index`, `deuda`, `extraer`, `reportar`, `exito`) más el `use PagoDirectoController`.
- `app/views/pagos/detalle.php`: aplicar el estado inicial del timeline (dot/título según `$estadoInicial`) y cambiar la condición del evento "Procesado" a `in_array($estado, ['APROBADO', 'RECHAZADO'], true)`.
- `app/models/PersonasModel.php`: agregar `getPrincipalByUnidadId()` reutilizando `getByUnidadId()` de `main`.
- Resto de archivos: tomar la versión de la feature (main no los modificó). Mantener diffs mínimos; no reformatear código ajeno.

## Criterios de aceptación
1. `php tests/run.php --filter=PagoDirectoTest` en verde sobre la main actual.
2. Sin regresiones en clases afectadas (`PagoDuplicadosTest`, `ModelTest`, `HelperTest`, `HistorialPagosTest`, `PagoDetalleNavegacionTest`) comparado contra la línea base.
3. `check_purity.php` y `audit_security.php` con 0 violaciones.
4. Migración `migrate_pago_directo.php` idempotente (aplicada o "ya aplicada").
5. Pago invitado: atribuido al residente principal si existe; si no, `residente_id` NULL; nace EN REVISIÓN; visible y aprobable en pantallas admin.

## Ruta de implementación
- T2–T4: **delegada** a un único writer (disparador: 2+ archivos no triviales y resolución de conflictos de merge). Verificación: writer con comandos en primer plano + spot check del padre.

## Entrega
- Rama `feat/pago-directo-integracion` desde `main` (`46a3899`); commits por unidad (los cherry-picks preservan los mensajes originales). Push / merge a main: decisión del usuario.

## Verificación

### Tests (línea base → final; mismas clases, runner con filtro)
| Clase | Línea base (tests/passed/failed/errors) | Final (tests/passed/failed/errors) |
|---|---|---|
| `PagoDuplicadosTest` | 10 / 30 / 19 / 1 | 10 / 30 / 19 / 1 (fallos preexistentes: falta `.env` con `APP_KEY`/`NOTIFICATION_ENCRYPT_KEY`; idénticos antes y después) |
| `ModelTest` | 23 / 130 / 0 / 0 | 23 / 130 / 0 / 0 |
| `HelperTest` | 58 / 90 / 0 / 0 | 59 / 93 / 0 / 0 (+1 test / +3 aserciones del badge de `bb6fdc3`, sin fallos) |
| `HistorialPagosTest` | 4 / 20 / 0 / 0 | 4 / 20 / 0 / 0 |
| `PagoDetalleNavegacionTest` | 4 / 31 / 0 / 0 | 4 / 31 / 0 / 0 |
| `PagoDirectoTest` | — | 11 / 53 / 0 / 0 |

Sin regresiones: los únicos fallos (19+1 en `PagoDuplicadosTest`) son los mismos de la línea base y responden a la ausencia de `.env`.

### Gates
- `php scripts/check_purity.php` → `✅ ÉXITO: Todos los Controladores y Modelos cumplen con la pureza arquitectónica MVC.` (exit 0)
- `php scripts/audit_security.php` → `✅ AUDITORÍA EXITOSA: Cero vulnerabilidades estáticas detectadas.` (exit 0)
- `php scripts/migrate_pago_directo.php` → `Migración ya aplicada: pagos.residente_id ya permite NULL.` (idempotente; exit 0)
- `git grep -n "INNER JOIN personas per ON p.residente_id" -- app/models/PagoModel.php` → sin resultados (las consultas de pagos usan `LEFT JOIN`)

### Criterio 5 (pago invitado)
Cubierto por `PagoDirectoTest`: registro con `residente_id NULL` (`pagoInvitadoConResidenteNuloSeRegistraYSeLimpia`), atribución al residente principal (`pagoDirectoAtribuidoApareceParaElResidenteDeLaUnidad`), estado `EN REVISIÓN` con whitelist (`modeloPagoAceptaEstadoEnRevisionConWhitelist`) y visibilidad en el listado administrativo con la etiqueta `Pago directo (sin usuario)`.

## Progreso
- T2: 21 cherry-picks `0227815..e2b8b09` (`abae3c2` → `56fac74`), mensajes originales preservados. Único conflicto real: `app/models/PagoModel.php` en `7a0b47f` (2 hunks) — resuelto conservando la estructura de main (UNION `pagos` + `comprobantes_pago`, `referencia_norm`, `deleted_at`) y aplicando `LEFT JOIN personas` + `COALESCE(..., 'Pago directo (sin usuario)')` solo en la rama `pagos` de `obtenerTodosPagos` y en `obtenerPagoPorId`. `public/index.php` auto-mergeó; verificado: +8 líneas con las 5 rutas públicas y todas las rutas de main intactas.
- Decisión no cubierta literalmente por la política: en `PagoModel::notificarCambioEstadoPago` el JOIN también pasó de `INNER` a `LEFT` para cumplir el gate T4 (que exige cero coincidencias de `INNER JOIN personas per ON p.residente_id`). Es neutro en conducta: un pago sin persona asociada retorna temprano por `email` vacío, igual que antes por fila ausente.
- T3: `d133131` (`976dffa`) y `bb6fdc3` (`03c412d`). Conflicto en `PagoModel.php` (hunks de docblock y del INSERT): resultado con una única implementación de `$estado` + whitelist `EstadoPago::all()` y el INSERT conservando `referencia_norm`. `PersonasModel.php`, `app/views/pagos/detalle.php` y `app/core/helpers.php` auto-mergearon; verificados a mano: `getPrincipalByUnidadId()` reutiliza `getByUnidadId()` de main, y el timeline usa `$estadoInicial` con la condición `in_array($estado, ['APROBADO', 'RECHAZADO'], true)`.
- Nota: el hunk de estado (`:estado`/whitelist) provino de `d133131` (T3), no de `7a0b47f` (T2) como anticipaba el plan; se resolvió en T3 evitando duplicación.
- T4: resultados de tests y gates arriba. `PagoDirectoTest` en verde.
- T5: este commit de cierre. Push / merge a main: decisión del usuario.
