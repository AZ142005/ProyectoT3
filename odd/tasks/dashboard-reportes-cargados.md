# ODD task: Dashboard — "Reportes Cargados" reemplaza a Egresos por Categoría; se quitan las tarjetas Egresos y Balance

**Estado**: COMPLETADO Y VERIFICADO (2026-10-11) — verificador independiente PASS; commits locales en `main`, sin push.
**Fecha**: 2026-10-11
**Ruta**: direct-inline — 1 vista no trivial (`app/views/admin/dashboard.php`) + adiciones mecánicas en el controlador y ajuste de aserciones en 1 test (sin disparador de delegación: un solo archivo no trivial).

## Objetivo (pedido del usuario; alcance confirmado en conversación)

1. El panel **"Egresos por Categoría"** del dashboard pasa a ser **"Reportes Cargados"**: lista las últimas cargas del Reporte de Cartelera del último mes (nombre, fecha de carga, resultado) con botón "Ver Historial" → `/admin/gastos`.
2. **Quitar** las tarjetas KPI **"Egresos (mes)"** y **"Balance Operativo"** del dashboard.

## Diseño y decisiones

- Solo lo visual del dashboard: no se toca `ReportesModel` ni los cálculos de fondo (el resumen financiero se sigue calculando igual; sus claves quedan sin uso en la vista, como el resto de variables muertas preexistentes).
- Se reutiliza `CarteleraModel::obtenerHistorialUltimoMes(1, 5)` (misma ventana de un mes del historial).
- El controlador pasa `reportes_recientes` (datos + total).
- Con 2 tarjetas KPI restantes ("Total por Cobrar", "Ingresos"), se quita `col-xl-3` para que ocupen media fila cada una.
- Test `DashboardFinancieroTest`: se actualizan las aserciones (quitar "Egresos ("/"Balance Operativo"/"Egresos por Categoría"; exigir "Reportes Cargados" + guardas negativas).
- Commit local en `main`, sin push (patrón del repo).

## Tareas

- [x] **T1 — Vista**: quitar tarjetas Egresos y Balance (ajustar grilla KPI); reemplazar el panel de Egresos por Categoría por "Reportes Cargados"; actualizar comentario de la fila 2; default defensivo de `$reportes_recientes`.
- [x] **T2 — Controlador**: `AdminController::dashboard` consulta `obtenerHistorialUltimoMes(1, 5)` y pasa `reportes_recientes` (+ import de `CarteleraModel`).
- [x] **T3 — Tests**: ajustar `DashboardFinancieroTest`.
- [x] **T4 — Verificación**: `php -l`, filtros de tests, render HTTP real del dashboard (presentes/ausentes).
- [x] **T5 — Cierre**: commit, assess RDD, verificador independiente y documentación.

## Criterios de aceptación

- Dashboard sin "Egresos (mes)", sin "Balance Operativo" y sin "Egresos por Categoría"; con "Reportes Cargados" (lista del último mes) y botón "Ver Historial".
- El resto del dashboard intacto (Total por Cobrar, Ingresos, Salud de Cartera, Cuentas Bancarias, Últimos Movimientos, selector de período).
- Tests en verde.

## Checks

- `C:/xampp/php/php.exe -l` de tocados; `tests/run.php --filter=Dashboard` / `--filter=Filtros` / `--filter=Paginacion` / `--filter=Gastos` / `--filter=Cartelera`.
- Render HTTP con sesión admin (server local).
- Suite completa: falla preexistente conocida (`SecurityTest`) — no tocar.

## Progreso y evidencia

### T1–T3 — Implementación (COMPLETADO)

- Vista `app/views/admin/dashboard.php`: fuera las tarjetas "Egresos (mes)" y "Balance Operativo" (la fila KPI queda con "Total por Cobrar" e "Ingresos", media fila cada una); el panel "Egresos por Categoría" se reemplaza por **"Reportes Cargados"** (badge con total del último mes, hasta 5 cargas con nombre/fecha/resultado, botón "Ver Historial" → `/admin/gastos`, estado vacío) + default defensivo de `$reportes_recientes`.
- `AdminController::dashboard`: import de `CarteleraModel`, consulta `obtenerHistorialUltimoMes(1, 5)` y pasa `reportes_recientes`. `ReportesModel` y el resto de cálculos intactos.
- `DashboardFinancieroTest`: aserciones ajustadas (fuera "Egresos ("/"Balance Operativo"/"Egresos por Categoría"; dentro "Reportes Cargados"/"Ver Historial" + guardas negativas).
- Diff del commit: 3 archivos, +47/−88.

### T4–T5 — Verificación y cierre (COMPLETADO)

- `php -l` 3/3 sin errores; 0 restos de "Egresos"/"Balance Operativo" en la vista.
- Tests: Dashboard 5/40, Filtros 5/30, Paginacion 10/30, Gastos 18/84, Router 23/44 — todo verde.
- Render HTTP real: `/admin/dashboard` 200 (42.050 bytes); 16/16 comprobaciones: presentes "Reportes Cargados", badge "en el último mes", fila backfilleada (e2e…pdf, 362 facturas), "Ver Historial", resto del dashboard intacto; ausentes las 2 tarjetas y el panel de egresos.
- Riesgo RDD: `assess --base-ref b46fadb --committed-only` → `risk: medium`, 3 paths / 135 líneas, `review_due: false` (`under_budget`).
- **Verificador independiente (agente fresco, read-only): PASS** — integridad (3 archivos, padre exacto, sin atribución IA), diff hunk a hunk (solo lo descrito; `ReportesModel` intacto), disco coincide (ausencias/presencias y clases verificadas), controlador+lint OK, y 5 filtros de tests en verde (61 tests / 0 fallos). Observaciones no bloqueantes: `gastos_por_categoria` sigue pasándose sin uso (coherente con "cálculos de fondo intactos"); archivos ajenos sucios no tocados.

## Cierre

- Commits (locales en `main`, sin push, sin atribución IA):

  | Hash | Mensaje |
  |------|---------|
  | `fc21c81` | feat(ui): dashboard con Reportes Cargados en lugar de egresos y sin tarjetas egresos/balance |
  | `(cierre documental)` | docs(odd): cierra el dashboard con Reportes Cargados |

- Pendiente/decisiones del usuario: push/PR.
