# Combinar "Usuarios" + "Solicitudes de Registro" en una sección con pestañas (patrón Estructura)

## Objetivo
- Unificar `/admin/usuarios` y `/admin/solicitudes-registro` en una sola sección con pestañas (nav-pills), tomando como ejemplo `/admin/estructura`.
- `/admin/solicitudes-registro` pasa a redirigir a `/admin/usuarios?tab=solicitudes`.

## Contexto (del mapeo)
- Patrón Estructura: tab por `$_GET['tab']` con whitelist y fallback (`EstructuraController.php:115-118`); pills `#estructuraTabs` (`estructura.php:72-102`), panes server-side (`108`, `324`), hidden `tab` en forms GET/POST, JS `history.replaceState` + activación inicial (`estructura.php:911-933`), estilo de pills (`40-70`), modales fuera del `.tab-content`.
- Usuarios: `UsuarioAdminController::index` lee buscar/rol/page, render `admin/usuarios/index`; POSTs `reiniciarPassword`/`actualizarDatos`/`eliminar` redirigen a `/admin/usuarios`.
- Solicitudes: `SolicitudesRegistroController` (index/aprobar/rechazar → `/admin/solicitudes-registro`); vista `admin/solicitudes_registro/index.php` (204 líneas, wrapper propio); `SolicitudesRegistroModel::obtenerListado` / `contarPendientes`.
- Sin colisiones de IDs ni funciones JS entre ambas vistas.
- Decisión: los POST conservan sus URLs actuales; los handlers de solicitudes redirigen a `/admin/usuarios?tab=solicitudes`.
- Decisión: sidebar admin conserva SOLO la entrada Usuarios (se elimina `solicitudes_registro` de ambos arrays); h1 de la página "Usuarios y Solicitudes"; pestañas "Usuarios" y "Solicitudes de Registro"; `$activeRoute='usuarios'`.
- Cuidado: colisión de `$paginacion` y `$filtros` entre panes → el pane de solicitudes usa `paginacionSolicitudes` y construye su propio `$filtros` (con `tab`).

## Tareas
- [x] T1 — Backend (commit `0c720f3`): `UsuarioAdminController::index` lee `tab` (whitelist `['usuarios','solicitudes']`, default `usuarios`), carga ambos datasets (usuarios + solicitudes + `contarPendientes`) y pasa `tabActual`/datos de solicitudes; `SolicitudesRegistroController::index` → redirect a `/admin/usuarios?tab=solicitudes` (conserva `Auth::requireRole` primero); `aprobar`/`rechazar` redirigen a `/admin/usuarios?tab=solicitudes`.
- [x] T2 — UI (commit `91cd4ae`): `admin/usuarios/index.php` pasa a contenedor combinado (un solo wrapper/header/flash; nav-pills patrón estructura; pane `tab-usuarios` = contenido actual con hidden `tab=usuarios` y paginación con `tab`; pane `tab-solicitudes` = `include` de la vista de solicitudes refactorizada); `admin/solicitudes_registro/index.php` se refactoriza como partial (sin wrapper/header/sidebar/flash; links con `tab=solicitudes`; conserva badge de pendientes, tabla, paginación, modal y script); sidebar admin sin la entrada solicitudes (2 arrays); `components/pagination.php` whitelist `+tab`.
- [x] T3 — Tests (commit `91cd4ae`): nuevo `tests/UsuariosSolicitudesTabsTest.php` (vista con nav-pills/`tab-solicitudes`/include; redirect del index viejo; sidebar sin `solicitudes_registro`; whitelist `tab` en controlador y paginación) + literales existentes intactos (UsuarioAdminTest, UsuarioAdminAccionesTest).
- [x] T4 — Verificación: lint + suites `UsuarioAdmin`, `SolicitudesRegistro`, `RbacAuthorization` + smoke render de ambos tabs con sesión simulada; resultados abajo.

## Criterios de aceptación
1. `/admin/usuarios` renderiza UNA página con pestañas "Usuarios" y "Solicitudes de Registro" (patrón estructura); default usuarios; `?tab=solicitudes` abre la segunda.
2. `/admin/solicitudes-registro` redirige a `/admin/usuarios?tab=solicitudes`; aprobar/rechazar siguen funcionando y vuelven a la pestaña de solicitudes.
3. Sidebar admin: una sola entrada (Usuarios); sin `solicitudes_registro`; la sección se marca activa.
4. Filtros, paginación y modales de ambas listas funcionan en su pane; la paginación de solicitudes conserva `tab`.
5. Tests existentes en verde sin cambios de asserts; test nuevo en verde.

## Ruta de implementación
- Delegada a un único writer (2+ archivos no triviales). Verificación del writer en primer plano; smoke del padre.

## Entrega
- Rama `feat/usuarios-solicitudes` desde `main` @ `0170920`; commits por unidad:
  - `0c720f3` — feat(usuarios): cargar la sección combinada con pestaña de solicitudes (backend).
  - `91cd4ae` — feat(ui): usuarios y solicitudes de registro en una sección con pestañas.
- Diff final: 7 archivos, +224/−72 (<400). Push y merge: decisión del usuario.

## Verificación
- `php tests/run.php --filter=UsuarioAdmin`: 17 tests / ✅ 68 passed / 0 failed / 0 errors (baseline exacto).
- `--filter=SolicitudesRegistro`: 7 / 47 / 0 / 0. `--filter=RbacAuthorization`: 7 / 154 / 0 / 0. `--filter=Perfil`: 5 / 25 / 0 / 0.
- `--filter=UsuariosSolicitudesTabs` (nuevo): 8 / 34 / 0 / 0.
- `php -l` de los 7 archivos: sin errores de sintaxis.
- Smoke (sesión admin simulada, sin HTTP ni escrituras): TAB usuarios OK (129784 bytes), TAB solicitudes OK (121038 bytes). Verificación independiente del pane activo: `tab=solicitudes` activa el pane correcto, `tab=usuarios` el inverso, `tab=filtrado` (inválido) cae a usuarios — 3/3.
- Verificador independiente (solo lectura): VERIFICADO, sin desviaciones bloqueantes; diffs y baselines intactos.
- Review nativo (RDD): `gentle-ai review assess` → `high_risk` / `unassessable` (OpenCode no elegible para review inmutable; misma limitación que el candidato anterior). Vía RDD-off aplicada: verificación del writer + verificador independiente.
