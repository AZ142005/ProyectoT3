# ODD task: Unificación de "Carga y Emisión" (una sola vista + apartado renombrado)

**Estado**: COMPLETADO Y VERIFICADO (2026-10-11) — verificador independiente PASS (5/5 + smoke render CLI); commits locales en `main`, sin push.
**Fecha**: 2026-10-11
**Ruta**: delegated-direct — un writer (trigger: writer rule, 2+ archivos no triviales: la vista y el test principal; el resto son textos mecánicos en sidebars/controlador/tests secundarios).

## Objetivo (pedido del usuario)

1. Quitar la línea descriptiva bajo "Emisión de Deuda Mensual" ("Cálculo dinámico de cuotas del conjunto y distribución entre las unidades del conjunto").
2. Combinar ambas pestañas de la página ("Registro de Gastos" + "Emisión de Deuda") en una sola vista: sin nav-pills, ambas secciones visibles en secuencia.
3. Renombrar el apartado para quitar "de deuda": "Carga y Emisión de Deuda" → **"Carga y Emisión"** en sidebar admin (2 lugares), sidebar auditor, encabezado de la vista y título del navegador (controlador).

## Alcance y decisiones

- Solo interfaz + textos; lógica de fondo intacta: el controlador conserva `$tabActual` y el redirect de compatibilidad `/admin/facturas/generar` → `/admin/gastos?tab=facturacion` (el parámetro queda ignorado por la vista, sin romper nada).
- Se retiran los restos del mecanismo de pestañas en la vista: estilo `.nav-pills`, bloque de navegación, wrapper `tab-content`, JS de sincronización de URL, input hidden `tab` del formulario de filtros y parámetro `?tab=gastos` del enlace "Limpiar".
- Nuevas secciones: `id="seccion-gastos"` y `id="seccion-emision"` (separador: `mt-5 pt-5 border-top border-background`).
- Se conserva el título interno "Emisión de Deuda Mensual" (no es el apartado; solo se elimina su línea descriptiva).
- Tests actualizados: `GastosFacturacionTabsTest` (3 métodos renombrados/reescritos), `GastosTipologiaCalculoTest` (3 aserciones), `FiltrosCompactosTest` (comentario + quitar aserción `name="tab"`).
- Commit local en `main`, sin push (patrón del repo).

## Tareas

- [x] **T1 — Vista**: quitar línea descriptiva + combinar pestañas (sin nav-pills; secciones en secuencia; limpiar estilo/wrapper/JS/`tab` oculto/Limpiar).
- [x] **T2 — Renombrar apartado**: sidebars admin (2) y auditor (1), encabezado de la vista, título y comentarios del `GastoController`, comentario del `AdminController`.
- [x] **T3 — Tests**: actualizar `GastosFacturacionTabsTest`, `GastosTipologiaCalculoTest`, `FiltrosCompactosTest`.
- [x] **T4 — Verificación del writer**: `php -l` de los 8 archivos + filtros `Gastos`, `Filtros`, `Paginacion`, `Router`.
- [x] **T5 — Verificación del orquestador**: render HTTP real (una sola página muestra ambas secciones; sin nav-pills; URLs con `?tab=` siguen respondiendo 200), evaluación de riesgo RDD y verificador independiente.

## Criterios de aceptación

- La vista muestra ambas secciones en secuencia, sin pestañas; `/admin/gastos` y `/admin/gastos?tab=facturacion` responden 200 con el mismo contenido.
- "de deuda" eliminado del apartado (sidebar admin/auditor, encabezado, título del navegador); sin ocurrencias colgantes de "Carga y Emisión de Deuda" en `app/` ni en `tests/`.
- Tests en verde.

## Checks

- `C:/xampp/php/php.exe -l` de todos los archivos tocados.
- `C:/xampp/php/php.exe tests/run.php --filter=Gastos` / `--filter=Filtros` / `--filter=Paginacion` / `--filter=Router`.
- Render HTTP con sesión admin (server local en `localhost:8000`).
- Suite completa: falla preexistente conocida (`SecurityTest::testCsrfRejectsInvalidToken`) — no tocar.

## Progreso y evidencia

### T1–T3 — Vista, renombres y tests (COMPLETADO)

- Vista `app/views/admin/gastos/index.php`: secciones combinadas en una sola vista (`#seccion-gastos` → `#seccion-emision`, separador `mt-5 pt-5 border-top border-background`); eliminados: estilo `.nav-pills`, bloque `gastosTabs`, wrapper `tab-content`, ambos `tab-pane`, JS de sincronización de URL, input hidden `tab` y `?tab=gastos` del enlace Limpiar; línea "Cálculo dinámico de cuotas…" eliminada (queda el h4 "Emisión de Deuda Mensual"); encabezado → "Carga y Emisión".
- Renombres del apartado: sidebars admin (L10/L25) y auditor (L7), encabezado de la vista y título del navegador (`GastoController`); comentarios ajustados en `GastoController` y `AdminController`. `$tabActual` y el redirect de compatibilidad intactos.
- Tests: `GastosFacturacionTabsTest` (3 métodos renombrados/reescritos + guardas anti-regresión), `GastosTipologiaCalculoTest` (3 aserciones) y `FiltrosCompactosTest` (docblock + aserción `name="tab"` eliminada).
- Diff del commit: 8 archivos, +50/−124.

### T4 — Verificación del writer (COMPLETADO)

- `php -l` 8/8 sin errores. Tests: `--filter=Gastos` 18/84, `--filter=Filtros` 6/38, `--filter=Paginacion` 10/30, `--filter=Router` 23/44 — todo en verde.

### T5 — Verificación del orquestador (COMPLETADO)

- Render HTTP real (sesión admin): `/admin/gastos` y `/admin/gastos?tab=facturacion` → 200 con HTML idéntico (40.576 bytes); sin `gastosTabs`/`nav-pills`/pill/`history.replaceState`/`name="tab"`/`id="tab-`; presentes encabezado "Carga y Emisión", ambas secciones y títulos, modal, tabla, filtros y botón de deuda ("Re-enviar Deuda" por las 216 facturas del período).
- Riesgo RDD (`gentle-ai review assess --base-ref 31c3a81 --committed-only --untracked-scope=exclude --expected-untracked-inventory=sha256:9ceb22f2…`): `risk: medium`, 8 paths / 174 líneas, `review_due: false` (`under_budget`).
- **Verificador independiente (agente fresco, read-only): PASS — 5/5 checks + smoke render CLI 25/25**:
  1. Commit íntegro: los 8 archivos; padre 31c3a81; mensaje convencional; sin atribución IA.
  2. Diff correcto hunk a hunk: `AdminController` solo 1 comentario; `$tabActual` intacto; redirect en contexto sin cambios.
  3. Disco coincide con el commit: sin restos de pestañas; presentes secciones y renombres; 0 ocurrencias de "Carga y Emisión de Deuda" en `app/`; lint 8/8.
  4. Tests: Gastos 18/84, Filtros 6/38, Paginacion 10/30, Router 23/44 (196 aserciones, 0 fallos).
  5. Coherencia: la vista ya no referencia `$tabActual`; rutas y redirect de compatibilidad presentes; modal/tabla/filtros/formulario intactos.
- Observaciones (no bloqueantes): (a) `tests/` conserva 4 literales "Carga y Emisión de Deuda" dentro de las guardas `assertFalse` (anti-regresión, intencional); (b) archivos ajenos sucios/untracked no fueron tocados; (c) smoke CLI con avisos HTML4 cosméticos.

## Cierre

- Commits (locales en `main`, sin push, sin atribución IA):

  | Hash | Mensaje |
  |------|---------|
  | `7d3e76a` | feat(ui): combina las secciones de gastos en una vista y renombra el apartado a 'Carga y Emision' |
  | `(cierre documental)` | docs(odd): cierra la unificacion de las secciones de Carga y Emision |

- Decisión de alcance registrada: "el apartado" se interpretó como "Carga y Emisión de Deuda" (sidebar/encabezado/título del navegador); el título interno "Emisión de Deuda Mensual" se conserva (solo se eliminó su línea descriptiva).
- Sin pendientes funcionales. Push/PR: decisión del usuario.
