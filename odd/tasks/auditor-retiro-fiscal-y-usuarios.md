# Retiro del módulo fiscal del rol auditor + ajustes de Usuarios para auditor

## Objetivo (instrucción del usuario)
1. "Quita Dashboard Fiscal y Log Auditoría" (del alcance del auditor).
2. "En el apartado de usuarios el auditor no pueda actualizar datos" (y que no se le muestre la pestaña de Cambios de Datos).

## Decisiones de diseño
- Las páginas fiscales (`/auditor/dashboard`, `/auditor/log-transacciones`, `/auditor/exportar-log`) se **retiran del rol auditor**: salen del menú, el auditor queda bloqueado (rutas y guards pasan a solo-admin) y su aterrizaje post-login cambia a `/admin/dashboard`. Las vistas/controlador quedan conservados y accesibles SOLO por administrador vía URL (sin menú), por si se re-ubican después.
- Usuarios para auditor: pierde la acción "Actualizar datos" (ruta + guard solo-admin + opción oculta en el menú por fila) y la pestaña "Cambios de Datos" deja de mostrarse (botón + panel ocultos; si llega con `?tab=cambios` se fuerza `usuarios`). El procesamiento de cambios ya estaba y sigue solo-admin.

## Cambios
1. `app/views/layouts/auditor_sidebar.php` — `$navItems` queda SOLO con los 10 ítems del admin (se eliminan `fiscal` y `logs`):
```php
$navItems = [
    ['route' => 'dashboard',         'url' => '/admin/dashboard',           'icon' => 'dashboard',      'label' => 'Dashboard'],
    ['route' => 'comprobantes',      'url' => '/admin/comprobantes',        'icon' => 'history',        'label' => 'Historial de Pagos'],
    ['route' => 'conciliacion',      'url' => '/admin/conciliacion',        'icon' => 'sync_alt',       'label' => 'Conciliación'],
    ['route' => 'cuentas_bancarias', 'url' => '/admin/cuentas-bancarias',   'icon' => 'account_balance', 'label' => 'Cuentas Bancarias'],
    ['route' => 'gastos',            'url' => '/admin/gastos',              'icon' => 'receipt_long',   'label' => 'Gastos y Facturación'],
    ['route' => 'estructura',        'url' => '/admin/estructura',          'icon' => 'domain',         'label' => 'Estructura'],
    ['route' => 'estacionamientos',  'url' => '/admin/estacionamientos',    'icon' => 'directions_car', 'label' => 'Estacionamientos'],
    ['route' => 'comunicados',       'url' => '/admin/comunicados',         'icon' => 'campaign',       'label' => 'Comunicados'],
    ['route' => 'usuarios',          'url' => '/admin/usuarios',            'icon' => 'group',          'label' => 'Usuarios'],
    ['route' => 'morosidad',         'url' => '/admin/reportes/morosidad',  'icon' => 'account_balance_wallet', 'label' => 'Carta de Deuda'],
];
```
2. `public/index.php` — las 3 rutas `/auditor/*` pasan a `[UserRole::ADMIN]` (de `[UserRole::AUDITOR, UserRole::ADMIN]`). Además L190 `/admin/usuarios/actualizar-datos` pasa a `[UserRole::ADMIN]`.
3. `app/controllers/AuditorController.php` — los 3 guards pasan a `Auth::requireRole(UserRole::ADMIN);` y actualizar los docblocks (módulo retirado del rol auditor, acceso exclusivo de administradores).
4. `app/controllers/AuthController.php` — aterrizaje del auditor a `/admin/dashboard` en los 3 puntos: login exitoso (~L71), finalización 2FA (~L240) y `redirectByRole()` (~L445). Conservar `loginAsAuditor`.
5. `app/views/admin/usuarios/index.php`:
   - Pestaña Cambios: envolver el `<li>` del botón (el que contiene `id="tab-cambios-btn"`, ~L84-99) y el pane `id="tab-cambios"` (~L434-436) en `<?php if (\App\Core\Auth::role() !== 'auditor'): ?> ... <?php endif; ?>`.
   - Opción "Actualizar datos" del dropdown (~L343-368): envolver ese `<li>` completo en el mismo condicional de auditor. El contenido interno (condición `$esAdminCuenta || tipo_entidad === 'usuario'`) no se toca.
6. `app/controllers/UsuarioAdminController.php`:
   - `index()`: tras calcular `$tabActual`, agregar: si el rol es auditor y `$tabActual === 'cambios'` → `$tabActual = 'usuarios'`.
   - `actualizarDatos()`: guard → `Auth::requireRole(UserRole::ADMIN);`.
7. Tests:
   - `tests/RbacAuthorizationTest.php`: agregar `'/admin/usuarios/actualizar-datos'` a la lista de exclusiones del test `testAdminRoutesAllowAuditorExceptDocumentedExclusions`; y agregar un test nuevo `testAuditorModuleRoutesAreAdminOnly`: las 3 rutas `/auditor/*` deben contener `UserRole::ADMIN` y NO `UserRole::AUDITOR` (contador = 3).
   - `tests/AuditorDashboardTest.php`: pasa a renderizar con sesión ADMIN (módulo ahora admin-only), conservando la regresión de la consulta de `gastos_comunes`; ajustar docblock.

## Restricciones
- No borrar las vistas/controlador fiscal (quedan solo-admin); no tocar rutas del portal residente.
- Conservar literales con tests: `id="tab-cambios-btn"` y `id="tab-cambios"` siguen presentes en el código fuente de la vista (envueltos condicionalmente).
- `php -l` limpio; suites verdes.

## Criterios de aceptación
1. El auditor no ve Dashboard Fiscal ni Log Auditoría en su menú; al iniciar sesión aterriza en `/admin/dashboard`; las rutas fiscales le responden 403 (solo admin).
2. En Usuarios, el auditor no ve la opción "Actualizar datos" ni la pestaña Cambios de Datos; el backend rechaza actualizar datos para auditor.
3. Suites verdes: AuditorDashboardTest, RbacAuthorizationTest, UsuariosSolicitudesTabsTest, SolicitudesCambioDatosTest, UsuarioAdminAccionesTest, BehaviorTest, AuthTest.

## Ruta de implementación
- Writer único (delegado). Verificación del writer + readback/spot check del padre. Commit del orquestador directo en `main`, sin push.
- Skills: `work-unit-commits`. TDD no configurado; runner `php tests/run.php --filter=<Clase>`.

## Verificación
- Writer (delegado): `php -l` 8/8 sin errores; suites exit 0: AuditorDashboardTest 2/7 ✅, RbacAuthorizationTest 9/229 ✅ (con `testAuditorModuleRoutesAreAdminOnly`), UsuariosSolicitudesTabsTest 8/34, SolicitudesCambioDatosTest 7/63, UsuarioAdminAccionesTest 8/37, BehaviorTest 108/252, AuthTest 20/37 + extras por labels (GastosFacturacionTabs 5/30, GastosTipologia 5/24, BalanceAgrupado 9/47). Harness runtime: auditor → 403 en módulo fiscal; render real de Usuarios `?tab=cambios` como auditor → sin botón tab-cambios, sin pane, sin "Actualizar datos", pane usuarios activo; como admin → todo visible.
- Spot check del padre: E2E login auditor (cuenta temporal creada y eliminada) → `REDIRECT:/admin/dashboard` ✅; auditor bloqueado en `/auditor/dashboard` (403 render + exit de requireRole) ✅; readback del diff (sidebar sin 2 ítems fiscales, 3 aterrizajes del auditor cambiados, 3 rutas fiscales + actualizar-datos solo-admin, 3 condicionales nuevos en la vista de Usuarios).
- RDD: modo on (global); preflight nativo inoperable en OpenCode. Ruta off aplicada (auto-verificación del writer + spot check del padre).
- Residuales honestos: las páginas fiscales quedan **conservadas y accesibles solo por administrador vía URL directa** (sin menú) — si se quieren eliminar del todo, es un paso adicional; `index()` de Usuarios aún calcula el listado de cambios sin renderizarlo para auditor (costo mínimo); la vista fiscal conserva `$activeRoute = 'fiscal'` que ya no coincide con ningún ítem del menú (sin resaltado).

## Entrega
- Commit (feature: 8 archivos, +64/−24) + commit de cierre `docs(odd)`. Directo en `main` (sin push, decisión del usuario).
