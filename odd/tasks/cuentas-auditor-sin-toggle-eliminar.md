# Cuentas Bancarias: el auditor no puede desactivar (toggle) ni eliminar cuentas

## Objetivo (instrucción del usuario)
- "Quítale al auditor la opción de eliminar cuenta y desactivarla para residentes" (y ocultarle los botones respectivos).
- Contexto: módulo **Cuentas Bancarias**. Es la poda prometida de la iteración anterior (el auditor podía toggle/eliminar cuentas). Tras esto, el auditor queda en **solo lectura** de ese módulo: ya no ve "Nueva Cuenta" ni "Editar" (iteraciones previas), y ahora tampoco el toggle ni "Eliminar".

## Cambios
1. `public/index.php`:
   - `POST /admin/cuentas-bancarias/toggle` → de `[UserRole::ADMIN, UserRole::AUDITOR]` a `[UserRole::ADMIN]`.
   - `POST /admin/cuentas-bancarias/eliminar` → de `[UserRole::ADMIN, UserRole::AUDITOR]` a `[UserRole::ADMIN]`.
   - (`guardar` ya era solo-admin.)
2. `app/controllers/CuentaBancariaController.php`: guard de `toggle` y de `eliminar` → `Auth::requireRole('admin');` (el archivo usa estilo string; `guardar` ya es así).
3. `app/views/admin/cuentas_bancarias/index.php` (celda de acciones por fila, ~L186-208): envolver en UN solo condicional `<?php if (\App\Core\Auth::role() !== 'auditor'): ?> ... <?php endif; ?>` el bloque que va desde el comentario `<!-- Alternar Estado -->` (form de toggle con sus dos variantes) hasta el cierre del form de `<!-- Eliminar / Desactivar -->` (inclusive). El botón Editar (ya oculto para auditor) y la columna de estado (badge Activa/Inactiva) quedan como están.
4. Tests:
   - `tests/RbacAuthorizationTest.php` — en `testAdminRoutesAllowAuditorExceptDocumentedExclusions`, agregar a `$exclusiones`: `'/admin/cuentas-bancarias/toggle'` y `'/admin/cuentas-bancarias/eliminar'`.
   - `tests/CuentasBancariasTest.php` — nuevo test `testAuditorSinToggleNiEliminarDeCuentas`:
     - Controller: extraer el cuerpo de `toggle` y de `eliminar` (regex `/public function toggle\(\)(.*?)(?=public function|\Z)/s` y análogo para `eliminar`) y asertar que contienen `Auth::requireRole('admin');` y que NO contienen `'auditor'`.
     - Vista: asertar con regex que el bloque de acciones está envuelto para auditor: `/role\(\) !== 'auditor'\): \?>\s*<!-- Alternar Estado -->.*?<!-- Eliminar \/ Desactivar -->.*?<\?php endif; \?>/s` sobre el contenido de `app/views/admin/cuentas_bancarias/index.php`.

## Restricciones
- Conservar literales/rutas existentes (tests de presencia de rutas siguen verdes); no tocar `guardar` ni `index` (el listado sigue visible para auditor).
- `php -l` limpio; suites verdes.

## Criterios de aceptación
1. Auditor recibe 403 en `/admin/cuentas-bancarias/toggle` y `/admin/cuentas-bancarias/eliminar`; no ve esos botones (ni el de editar/nueva) en la lista.
2. El administrador conserva toggle y eliminar sin cambios.
3. Suites verdes: CuentasBancariasTest, RbacAuthorizationTest, BehaviorTest, AuthTest.

## Ruta de implementación
- Writer único (delegado). Verificación del writer + readback/spot check del padre. Commit del orquestador directo en `main`, sin push.
- Skills: `work-unit-commits`. TDD no configurado; runner `php tests/run.php --filter=<Clase>`.

## Verificación
- Writer (delegado): `php -l` 5/5 sin errores; suites exit 0: CuentasBancariasTest 5/36 ✅ (incluye `testAuditorSinToggleNiEliminarDeCuentas`), RbacAuthorizationTest 9/231, BehaviorTest 108/252, AuthTest 20/37. El writer endureció el regex del test de la vista tras detectar un falso positivo en la propuesta (validado por mutación: condicional ausente → NO MATCH; form eliminar fuera del condicional → NO MATCH; estado real → MATCH).
- Spot check del padre: CuentasBancariasTest 5/36 ✅ exit 0; probes runtime con sesión auditor llamando directamente `toggle` y `eliminar` del controller (bypass de rutas) → ambos renderizan 403 y no continúan; readback del wrap en la vista (L186-L210: un condicional encierra toggle + eliminar; Editar ya oculto; badge de estado visible).
- RDD: modo on (global); preflight nativo inoperable en OpenCode. Ruta off aplicada (auto-verificación del writer + spot check del padre).
- Residual honesto: el auditor sigue viendo el listado de cuentas (incluido el badge Activa/Inactiva) pero sin acciones; sin verificación en navegador.

## Entrega
- Commit `ca31fd1` (feature: 2 rutas + 2 guards + wrap de vista + 2 tests; 5 archivos, +32/−4) + commit de cierre `docs(odd)`. Directo en `main` (sin push, decisión del usuario).
