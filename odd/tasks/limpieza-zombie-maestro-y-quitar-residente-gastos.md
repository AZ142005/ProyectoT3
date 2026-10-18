# ODD task: Limpieza zombie maestro + retiro de «Rendición de Cuentas» del residente

**Estado**: COMPLETADO Y VERIFICADO (2026-10-18)
**Autorizado por**: usuario («haz la limpieza, luego quita residente gastos (no hay informacion para rellenarlo, ni boton de acceso, y el sistema tampoco puede agregar datos a esta area por su cuenta), y dame sugerencias para reemplazarlo»)
**Ruta**: ODD — 1 writer delegado (2 unidades de trabajo, 2 commits) + spot check del orquestador + sugerencias de reemplazo (análisis)

## Objetivo

1. **U1 — Limpieza zombie**: eliminar de `GastoController` los métodos `cargarMaestro/parsearMaestro/importarMaestro` — restos del importador PDF Maestro eliminado por diseño el 10/10 (`ca46f46`) que el commit de seguridad `e5ae382` (13/10) reintrodujo accidentalmente desde una copia vieja del archivo. Sin rutas; referencian `GastoParserService` (ya inexistente) → Fatal si se reactivara una ruta.
2. **U2 — Retiro de la sección del residente**: eliminar `/residente/gastos` («Rendición de Cuentas y Justificación de Gastos»): sin botón de acceso en ninguna vista, sin vía de datos (el maestro fue el único que llenaba extracto/página; hoy 0 gastos con soporte) y sin auto-alimentación posible.

## Contexto verificado (2026-10-18)

- Rutas: `public/index.php:235` (`/residente/gastos` → `rendicionResidente` [RESIDENTE]); referencias de enlace en vistas: **ninguna**.
- Controlador: `GastoController::rendicionResidente` (~L384-440) usa modelos COMPARTIDOS con el admin (`obtenerGastosPorPeriodo`, `obtenerTotalesPorCategoria`, `calcularDistribucionCuotas` — siguen usándose en `index()`) y el helper de base `getAuthenticatedResidente()` (usado por 4 controladores más — no se toca).
- Vista: `app/views/residente/gastos.php` (con modal «Ver Justificación» + visor por `/soporte-proxy.php`).
- Tests: `tests/BehaviorTest.php` sección 4.5 (3 métodos `testRendicionResidente*`, ~L1369-1399).
- Manuales/JSON: sin referencias a la sección (nada que actualizar allí).
- El servido de soportes (`soporte-proxy.php`) y el flujo admin de soportes (carga manual + «Ver PDF» del historial) NO se tocan.

## Alcance

- **En**: los 3 métodos zombie; la ruta, el método `rendicionResidente`, la vista `residente/gastos.php` y los 3 tests 4.5.
- **Fuera**: `GastosModel` y sus métodos, esquema/columnas, `soporte-proxy.php`, módulo admin de gastos, manuales, `.sdd/`, `odd/` históricos, `/residente/historial` (redirige a /pagos — fuera de alcance).

## Tareas

- [x] U1 — Writer: métodos zombie eliminados; 0 referencias; commit `afedcf1` (−218 líneas).
- [x] U2 — Writer: ruta + método + vista + tests 4.5 eliminados; 0 referencias y 404 HTTP; commit `4b71a8f` (−321 líneas).
- [x] Spot check del orquestador (greps 0, vista eliminada, HTTP 404, `--filter=Behavior` → TODAS LAS PRUEBAS PASARON).
- [x] Sugerencias de reemplazo para el área retirada (sección final).

## Verificación

- `php -l` en archivos tocados; greps → 0: `cargarMaestro|parsearMaestro|importarMaestro|GastoParserService` y `rendicionResidente|residente/gastos`.
- Tests con filtro: `--filter=Behavior`, `--filter=Gastos`, `--filter=Router` (exit 0; suite completa detiene en SecurityTest — defecto conocido, no tocar).
- HTTP: `http://localhost:8000/residente/gastos` → 404 tras el retiro.
- Commits locales en `main`, sin push.

## Evidencia final (2026-10-18)

- `afedcf1` — `refactor(gastos): elimina metodos zombie del importador maestro (restaurados por e5ae382)`: solo `GastoController.php`, −218 líneas (los 3 métodos + docblocks).
- `4b71a8f` — `refactor(residente): retira la rendicion de cuentas sin acceso ni datos de gastos`: `GastoController.php` (−58), `app/views/residente/gastos.php` eliminado (−230), `public/index.php` (−1 ruta), `tests/BehaviorTest.php` (−32, sección 4.5).
- Verificación: `php -l` OK; grep `cargarMaestro|parsearMaestro|importarMaestro|rendicionResidente|residente/gastos` en app/public/tests → **0**; `http://localhost:8000/residente/gastos` → **404**; `--filter=Behavior` (109 tests, 267 ✅), `--filter=Gastos` (20/90 ✅), `--filter=Router` (23/44 ✅) — exit 0. Suite completa: detiene en SecurityTest (defecto preexistente, no tocado).
- `getAuthenticatedResidente()` (helper base) y `GastosModel` (métodos compartidos con admin) intactos; `soporte-proxy.php` y módulo admin sin cambios; manuales sin referencias a la sección (nada que actualizar).

## Sugerencias de reemplazo (análisis)

**Hallazgo**: `/residente/estado-cuenta` («Mi Estado de Deuda y Libro Mayor») es un módulo completo (saldo consolidado + libro mayor por movimiento + impresión + API v1) que **también está huérfano de botón de acceso** — pero con datos reales (a diferencia del retirado).

- Recomendada (chica): agregar acceso destacado al Estado de Cuenta en el panel del residente (y/o en Mis Pagos). Recupera transparencia con datos reales.
- Alternativas residente: filtros por tipo en el libro mayor; descarga del estado de cuenta; recordatorio mensual.
- SRS (slot del RF 34 si se reemplaza): rol auditor (solo lectura + log exportable), bloqueo/rate limiting, exportaciones; o reformular RF 19/20 en torno al Estado de Cuenta.
