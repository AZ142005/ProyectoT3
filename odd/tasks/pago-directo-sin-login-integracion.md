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
- [ ] **T2 — Feature base**: cherry-pick `0227815..e2b8b09` (portal público completo: capa de datos + migración, controlador y rutas, vistas, login, tests, fix de rate limit, validaciones, autocompletado del comprobante).
- [ ] **T3 — Refinamientos**: cherry-pick `d133131` + `bb6fdc3` (atribución al residente principal + EN REVISIÓN; badge y timeline).
- [ ] **T4 — Verificación**: tests por clase (línea base vs. final), `check_purity.php`, `audit_security.php`, migración idempotente.
- [ ] **T5 — Cierre**: actualizar este doc (verificación y progreso) con commit final.

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
_(pendiente — se completa en T4/T5)_

## Progreso
- Rama y doc creados; pendiente cherry-picks y verificación.
