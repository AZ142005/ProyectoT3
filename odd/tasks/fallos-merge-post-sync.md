# Fallos preexistentes del merge — resolución de los 5 fallos de la suite

Fecha: 2026-10-13
Origen: opción (b) elegida por el usuario tras cerrar el despliegue (T1–T4, T6, T7). Los 5 fallos estaban anotados como preexistentes del merge en `odd/tasks/envio-real-smtp.md`.
Ruta: directa — diagnóstico por evidencia (git/historia, docs de decisiones) + fixes mínimos; verificación por suites filtradas + suite completa. TDD: off — runner custom `php tests/run.php`.

## Objetivo

Dejar la suite completa sin fallos, resolviendo cada regresión del merge según la decisión o la intención documentada (no "hacer pasar" tests a ciegas).

## Contexto verificado (diagnóstico por fallo)

1. `comprobantesModelHasSaldoAFavorLogic` (BehaviorTest #36): el merge movió la liquidación de `ComprobantesModel::aprobar()` a `LiquidacionPagoService::aplicarPagoAUnidad()` (delegación en L547-549); `siguienteFactura` ya no existe en el código. → Test desactualizado: apuntar a la delegación y al servicio.
2. `aprobarLoteHasCascadeLoop` (BehaviorTest #2.2a): `PagoModel::aprobarLote()` delega la cascada en `LiquidacionPagoService` (L833-851); ya no hay `while` propio. → Test desactualizado: extracción robusta + aserciones de delegación y de la cascada en el servicio.
3. `pagoDirectoAtribuidoApareceParaElResidenteDeLaUnidad` (PagoDirectoTest): `PersonasModel` (introducido en `e5ae382`) selecciona `fecha_ingreso`, columna que NO existe en el esquema (ni dump ni BD local) y sin consumidor. → Quitar la columna fantasma de las 2 consultas.
4. `pagoControllerEliminaComprobanteHuerfanoEnRamaDeFallo` (PagoUploadHuerfanoTest): el merge añadió en el catch un `unlink` con ruta incorrecta (`UPLOADS_PATH . '/'`, sin `/comprobantes/`) ubicado antes de la ruta del else; rompe el invariante (construir ruta antes de borrar) y no limpiaba nada. → Helper privado con la ruta correcta; el else conserva el patrón verificado por el test.
5. `auditorModuleRoutesAreAdminOnly` (RbacAuthorizationTest #2): `466b128` ("logger", rama desactualizada del merge) revirtió las 3 rutas `/auditor/*` a `[ADMIN, AUDITOR]`, contra la decisión deliberada de `84de294` (retiro del módulo fiscal del rol: doc propio, test de contrato y sidebar vigente sin ítems fiscales). → Restaurar `[UserRole::ADMIN]`.

## Checklist

- [x] T1 `PersonasModel`: quitar `fecha_ingreso` fantasma (2 consultas).
- [x] T2 `PagoController`: cleanup del catch con helper `eliminarComprobanteSiExiste()` (ruta correcta).
- [x] T3 `public/index.php`: `/auditor/*` de vuelta a solo-admin (decisión `84de294`).
- [x] T4 `BehaviorTest` #36 y #2.2a: aserciones al diseño actual (delegación + servicio).
- [x] T5 Verificación: filtros verdes + suite completa **596 ✅ / 0 ❌**.

## Aceptación

- Los 5 fallos desaparecen; suite completa sin ❌ (el aborto conocido de SecurityTest no es un fallo reportado).
- Sin regresiones nuevas: ningún test adicional falla.

## Decisiones

- Fix 5 restaura la decisión de usuario de `84de294` (evidencia: doc de retiro, test de contrato, sidebar actual sin ítems fiscales) contra el artefacto del merge.
- Fixes 1-2 actualizan los tests al refactor intencional del merge; la lógica existe y está cubierta por tests de comportamiento (`LiquidacionPagoTest`, `SaldoFavorTest`).
- Fix 3 no agrega columna: nada la consume; si luego se necesita "fecha de ingreso", migración + UI juntas.
- Fix 4 corrige además la ruta del unlink (antes apuntaba a `uploads/<archivo>`, donde nunca hubo archivo).

## Evidencia

Cierre 2026-10-13 — commits `4fce93e`, `75f2227`, `930ab83`, `0bd3d5a` + este commit de cierre:

- Filtros verdes: `--filter=Behavior` 112 tests / 274 ✅ / 0 ❌; `--filter=PagoDirecto` 11 / 53 ✅ / 0 ❌; `--filter=PagoUploadHuerfano` 3 / 17 ✅ / 0 ❌; `--filter=RbacAuthorization` 9 / 234 ✅ / 0 ❌.
- Suite completa: **596 ✅ / 0 ❌** (antes: 591 ✅ / 5 ❌, los mismos 5 fallos); aborto conocido de `SecurityTest` igual que siempre (no es un fallo reportado). `php -l` 4/4 sin errores.
- Fix 5 restaura la decisión de `84de294` revertida por `466b128`; fixes 1-2 alinean los tests al refactor de liquidación (cubierto por `LiquidacionPagoTest` y `SaldoFavorTest`).
