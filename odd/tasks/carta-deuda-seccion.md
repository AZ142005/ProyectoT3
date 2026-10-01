# Sección "Carta de Deuda" (antes "Balance")

## Objetivo
- Renombrar el apartado "Balance" a "Carta de Deuda" en navegación, títulos y referencias visibles.
- Quitar el filtro por estado financiero (moroso/solvente) de la sección y de su cadena de datos.
- Ordenar el consolidado por edificio de mayor a menor deuda (más crítico primero) por defecto.

## Contexto
- Página y flujos: `/admin/reportes/morosidad` (ruta y URLs sin cambios), impresión y exportación CSV de la misma sección.
- Decisión: edificios/unidades solventes siguen listándose; al no existir filtro, quedan al final (deuda 0).
- Decisión: los IDs internos (`tablaBalanceEdificios`, `buscadorBalance`, `filtrarBalance`, `sinResultadosBalance`, etc.) NO se renombran: no son visibles y evita romper JS y tests.
- Decisión: `obtenerReporteMorosidad` (paginado, sin uso actual) queda fuera de alcance.
- TDD: no configurado en el proyecto (no hay sdd-init/testing capabilities). Verificación funcional ordinaria con la suite existente.

## Tareas
- [x] T1 — Modelo + controlador: quitar soporte de `estado` en `obtenerReporteBalanceAgrupadoPorEdificio` y `obtenerReporteMorosidadCompleto`; quitar `estado` de los filtros de las 3 acciones de `ReporteController`; ordenar el resultado por `balance_total` DESC (desempate: nombre ASC); renombrar filename CSV `balance_unidades_` → `deuda_unidades_`; actualizar tests del modelo. Commit `0117cb4`.
- [x] T2 — Vistas: renombrar "Balance" → "Carta de Deuda" (h1, h4, tarjeta, sidebars admin/auditor, enlaces del dashboard, títulos de impresión) y etiquetas "Balance Total (Bs)"/"Balance del Edificio" → "Deuda Total (Bs)"/"Deuda del Edificio"; quitar el select "Estado Financiero" (reajustar columnas del filtro a col-md-4); actualizar tests de vista. Commit `8b665fc`.
- [x] T3 — Verificación: suite por clases + `check_purity.php` + `audit_security.php`; resultados abajo.

## Criterios de aceptación
1. La sección muestra "Carta de Deuda" como título de página, en ambos sidebars y en los enlaces del dashboard; no queda texto visible "Balance" referido a la sección (IDs internos no cuentan).
2. No existe el filtro por estado financiero (sin `name="estado"` en la vista); enviar `estado` por URL no altera el resultado.
3. El consolidado de edificios se entrega ordenado por `balance_total` DESC por defecto (empate: nombre ASC); las unidades dentro de cada edificio siguen ordenadas por deuda DESC.
4. `php tests/run.php --filter=BalanceAgrupadoEdificiosTest` y la suite completa en verde (mismos fallos preexistentes, si los hubiera); `check_purity.php` y `audit_security.php` sin violaciones.

## Ruta de implementación
- Delegada a un único writer (disparador: 2+ archivos no triviales). Verificación del writer en primer plano; spot check del padre.

## Entrega
- Rama `feat/carta-deuda-seccion` (desde `main` @ `f636ddd`); commits por unidad:
  - `0117cb4` — feat(reportes): ordenar consolidado por deuda desc y eliminar filtro moroso/solvente.
  - `8b665fc` — feat(ui): renombrar sección Balance a Carta de Deuda.
- Diff final: 8 archivos, +63/−64 (< 400): sin encadenado de PRs. Push y merge: decisión del usuario.

## Verificación

Ejecutada por el writer (comandos en primer plano) y re-verificada por un verificador independiente en solo lectura sobre `f636ddd...HEAD`:

| Comando | Resultado observado |
|---|---|
| `git diff f636ddd...HEAD --stat` | 8 archivos relacionados, +63/−64 |
| `php -l` (8 archivos) | Sin errores de sintaxis |
| `php tests/run.php --filter=BalanceAgrupadoEdificiosTest` | 8 tests / ✅ 38 passed / 0 failed / 0 errors (baseline 37) |
| `php tests/run.php --filter=DashboardFinancieroTest` | 3 / 31 / 0 / 0 |
| `php tests/run.php --filter=RbacAuthorizationTest` | 7 / 154 / 0 / 0 |
| `php tests/run.php --filter=BehaviorTest` | 111 / 253 passed / 1 failed / 1 error / 1 skipped — preexistentes por falta de `public/uploads/.htaccess` + skip de `.env`; sin fallos nuevos |
| `php scripts/check_purity.php` | ✅ Éxito |
| `php scripts/audit_security.php` | 1 advertencia preexistente (XSS en `app/views/pagos/residente/lista.php`); sin aumento |
| Prueba directa a BD | `['estado']` ya no filtra: 6 edificios idénticos en los 4 casos (sin filtro/deudor/solvente/moroso); primero = Torre A (300), orden DESC confirmado |

Review nativo (RDD): `gentle-ai review assess` → `high_risk` / `unassessable` y preflight STATUS → `immutable_review_transport_unsupported` (OpenCode no es runtime elegible para review inmutable). Se aplicó la vía RDD-off: verificación del writer + verificador independiente (tabla de arriba). La frontera revisada queda en `f636ddd`.
