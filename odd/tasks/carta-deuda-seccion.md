# Sección "Carta de Deuda" (antes "Balance")

## Objetivo
- Renombrar el apartado "Balance" a "Carta de Deuda" en navegación, títulos y referencias visibles.
- Quitar el filtro por estado financiero (moroso/solvente) solo de la pantalla de la sección; impresión y CSV conservan su soporte de `estado` (ajuste de alcance en T4).
- Ordenar el consolidado por edificio de mayor a menor deuda (más crítico primero) por defecto.

## Contexto
- Página y flujos: `/admin/reportes/morosidad` (ruta y URLs sin cambios), impresión y exportación CSV de la misma sección.
- Decisión (T4): el filtro `estado` se conserva en Imprimir PDF y Exportar CSV (alcanzable por URL; la UI de la pantalla no lo envía); la pantalla del apartado queda sin filtro.
- Decisión: edificios/unidades solventes siguen listándose; al no existir filtro, quedan al final (deuda 0).
- Decisión: los IDs internos (`tablaBalanceEdificios`, `buscadorBalance`, `filtrarBalance`, `sinResultadosBalance`, etc.) NO se renombran: no son visibles y evita romper JS y tests.
- Decisión: `obtenerReporteMorosidad` (paginado, sin uso actual) queda fuera de alcance.
- TDD: no configurado en el proyecto (no hay sdd-init/testing capabilities). Verificación funcional ordinaria con la suite existente.

## Tareas
- [x] T1 — Modelo + controlador (commit `0117cb4`): quitar `estado` de la ruta de la pantalla (`morosidad()` + `obtenerReporteBalanceAgrupadoPorEdificio`); ordenar el resultado por `balance_total` DESC (desempate: nombre ASC); renombrar filename CSV `balance_unidades_` → `deuda_unidades_`; actualizar tests del modelo. (El soporte de `estado` en impresión/CSV se restaura en T4.)
- [x] T2 — Vistas: renombrar "Balance" → "Carta de Deuda" (h1, h4, tarjeta, sidebars admin/auditor, enlaces del dashboard, títulos de impresión) y etiquetas "Balance Total (Bs)"/"Balance del Edificio" → "Deuda Total (Bs)"/"Deuda del Edificio"; quitar el select "Estado Financiero" (reajustar columnas del filtro a col-md-4); actualizar tests de vista. Commit `8b665fc`.
- [x] T3 — Verificación: suite por clases + `check_purity.php` + `audit_security.php`; resultados abajo.
- [x] T4 — Corrección de alcance (commit `afca0f8`): restaurar el soporte de `estado` en `imprimirMorosidad()`, `exportarCsv()` y `obtenerReporteMorosidadCompleto()`; solo la pantalla del apartado queda sin filtro; test `testFiltroPorEstadoSigueDisponibleEnElReporteCompleto` añadido.

## Criterios de aceptación
1. La sección muestra "Carta de Deuda" como título de página, en ambos sidebars y en los enlaces del dashboard; no queda texto visible "Balance" referido a la sección (IDs internos no cuentan).
2. La pantalla del apartado no ofrece el filtro por estado financiero (sin `name="estado"` en la vista) y enviar `estado` por URL no altera el consolidado de edificios; impresión y CSV sí responden a `estado`.
3. El consolidado de edificios se entrega ordenado por `balance_total` DESC por defecto (empate: nombre ASC); las unidades dentro de cada edificio siguen ordenadas por deuda DESC.
4. `php tests/run.php --filter=BalanceAgrupadoEdificiosTest` y la suite completa en verde (mismos fallos preexistentes, si los hubiera); `check_purity.php` y `audit_security.php` sin violaciones.

## Ruta de implementación
- Delegada a un único writer (disparador: 2+ archivos no triviales). Verificación del writer en primer plano; spot check del padre.
- T4: corrección directa del padre (ediciones mecánicas ya entendidas, sin trigger de writer) + verificador independiente.

## Entrega
- Rama `feat/carta-deuda-seccion` (desde `main` @ `f636ddd`); commits por unidad:
  - `0117cb4` — feat(reportes): ordenar consolidado por deuda desc y eliminar filtro moroso/solvente.
  - `8b665fc` — feat(ui): renombrar sección Balance a Carta de Deuda.
  - `d3e92f0` — docs(carta-deuda): cierre del doc ODD.
  - `afca0f8` — fix(reportes): conservar el filtro moroso/solvente en impresión y CSV (fuera del apartado).
  - `docs(carta-deuda)` — alcance del filtro (solo el apartado) y verificación del fix (este commit).
- Diff final vs `main`: 9 archivos, +134/−53 (< 400): sin encadenado de PRs. Push y merge: decisión del usuario.

## Verificación

Ejecutada por el writer (comandos en primer plano) y re-verificada por un verificador independiente en solo lectura sobre `f636ddd...HEAD`:

| Comando | Resultado observado |
|---|---|
| `git diff f636ddd...HEAD --stat` | 8 archivos relacionados, +63/−64 |
| `php -l` (8 archivos) | Sin errores de sintaxis |
| `php tests/run.php --filter=BalanceAgrupadoEdificiosTest` | 8 tests / ✅ 38 passed / 0 failed / 0 errors (baseline 37; estado final tras T4: 9 / 45 / 0 / 0) |
| `php tests/run.php --filter=DashboardFinancieroTest` | 3 / 31 / 0 / 0 |
| `php tests/run.php --filter=RbacAuthorizationTest` | 7 / 154 / 0 / 0 |
| `php tests/run.php --filter=BehaviorTest` | 111 / 253 passed / 1 failed / 1 error / 1 skipped — preexistentes por falta de `public/uploads/.htaccess` + skip de `.env`; sin fallos nuevos |
| `php scripts/check_purity.php` | ✅ Éxito |
| `php scripts/audit_security.php` | 1 advertencia preexistente (XSS en `app/views/pagos/residente/lista.php`); sin aumento |
| Prueba directa a BD | `['estado']` ya no filtra: 6 edificios idénticos en los 4 casos (sin filtro/deudor/solvente/moroso); primero = Torre A (300), orden DESC confirmado |
| Prueba directa a BD (T4) | `obtenerReporteMorosidadCompleto`: deudor → 2 filas (todas > 0); solvente → 4 filas (todas ≤ 0); partición 6 = 2 + 4; la pantalla sigue con 6 edificios idénticos con/sin `estado` |
| Verificación independiente del fix `afca0f8` | ✅ Sin desviaciones (diff + pruebas vivas + suites) |

Review nativo (RDD): `gentle-ai review assess` → `high_risk` / `unassessable` y preflight STATUS → `immutable_review_transport_unsupported` (OpenCode no es runtime elegible para review inmutable). Se aplicó la vía RDD-off: verificación del writer + verificador independiente (tabla de arriba). El fix `afca0f8` siguió el mismo esquema (edición del padre + verificador independiente). La frontera revisada queda en `f636ddd`.
