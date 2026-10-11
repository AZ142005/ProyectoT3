# ODD task: Historial de Reportes (reemplaza al historial de gastos; ventana de un mes)

**Estado**: COMPLETADO Y VERIFICADO (2026-10-11) — verificador independiente PASS (5/5); commits locales en `main`, sin push.
**Fecha**: 2026-10-11
**Ruta**: delegated-direct — un writer (trigger: writer rule, 3+ archivos no triviales: modelo, vista, migración y test nuevo).

## Objetivo (pedido del usuario)

1. El "Historial de Gastos del Período" de `/admin/gastos` pasa a ser el **"Historial de Reportes"**: lista las cargas (importaciones) del Reporte de Cartelera en lugar de los gastos manuales.
2. Las entradas del historial **dejan de mostrarse un mes después de su carga** (los datos permanecen en BD; solo se filtran de la vista): ventana `fecha_carga >= NOW() - INTERVAL 1 MONTH`.

## Diseño y decisiones

- **Nueva tabla `reportes_cartelera`** (InnoDB/utf8mb4_general_ci, como el resto): una fila por reporte cargado — archivo (nombre almacenado), nombre_original (nombre real del PDF subido), hash (sha256, UNIQUE para dedupe), fecha_emision, fecha_carga, conteos de la importación (creadas/actualizadas/omitidas/edificios/unidades) y usuario_id.
- **Upsert por hash**: recargar el mismo PDF actualiza su fila (fecha_carga = ahora, conteos y archivo al día) en lugar de duplicarla; coherente con el upsert de deudas de la reimportación. El registro ocurre **dentro de la transacción** de `importarLote` (si la importación falla, no queda entrada).
- **Vista**: columnas Reporte (+ enlace "Ver PDF" solo si el archivo existe en disco), Fecha de Carga, Fecha del Reporte, Resultado (facturas creadas/actualizadas/omitidas + edificios/unidades nuevas) y Cargado por (JOIN usuarios). Nota "Se muestran las cargas del último mes". Estado vacío propio.
- **Se retira la barra de filtros de gastos** (mes/año/categoría/tipo + Filtrar/Limpiar): ya no filtraban nada visible tras el cambio. La sección de Emisión de Deuda mantiene su mes/año actual por defecto (el controlador conserva la lectura de `?mes=&anio=` por compatibilidad).
- **`GastosModel::obtenerGastosAdmin` se elimina** (queda sin caller tras el cambio; patrón del repo: limpieza de código muerto).
- **Migración `scripts/migrate_fase21_reportes_cartelera.php`**: idempotente; crea la tabla y hace backfill de las importaciones históricas registradas en `log_auditoria` (accion='importar_cartelera', agrupadas por archivo/día; hash NULL).
- El backfill en la BD local del usuario reconstruirá la carga del 2026-10-10 (e2e, 362 facturas).
- Commit local en `main`, sin push (patrón del repo).

## Tareas

- [x] **T1 — Modelo**: `CarteleraModel::registrarCarga` (upsert por hash, en transacción) + `obtenerHistorialUltimoMes($pagina, $porPagina)` + nuevo parámetro `$nombreOriginal` en `importarLote`.
- [x] **T2 — Controladores**: `CarteleraController` guarda/pasa el nombre original; `GastoController::index` consulta el historial (25/página), enriquece `archivo_existe` y deja de consultar el listado de gastos. `GastosModel`: eliminar `obtenerGastosAdmin`.
- [x] **T3 — Vista**: reemplazar filtros + tabla por el "Historial de Reportes".
- [x] **T4 — Migración fase21** (tabla + backfill) y ejecución en la BD local.
- [x] **T5 — Tests**: `CarteleraHistorialTest` (nuevo; vista/controlador + ventana de un mes + upsert en BD) y ajustes en `FiltrosCompactosTest` y `PaginacionEstandarTest`.
- [x] **T6 — Verificación del orquestador**: render HTTP real (historial visible con la carga backfilleada; sin filtros; nota del último mes), filtros de tests, assess RDD y verificador independiente.

## Criterios de aceptación

- `/admin/gastos` muestra "Historial de Reportes" con la carga del 2026-10-10 backfilleada; sin "Historial de Gastos del Período" ni barra de filtros de gastos.
- La consulta del historial excluye filas con `fecha_carga` de más de un mes; el upsert no duplica filas al recargar el mismo PDF.
- Tests en verde; sin regresiones en los filtros ejecutados.

## Checks

- `C:/xampp/php/php.exe -l` de los archivos tocados; `C:/xampp/php/php.exe scripts/migrate_fase21_reportes_cartelera.php`.
- `C:/xampp/php/php.exe tests/run.php --filter=Cartelera` / `--filter=Gastos` / `--filter=Filtros` / `--filter=Paginacion` / `--filter=Router`.
- Render HTTP con sesión admin (server local).
- Suite completa: falla preexistente conocida (`SecurityTest`) — no tocar.

## Progreso y evidencia

### T1–T5 — Implementación (COMPLETADO)

- `CarteleraModel`: `registrarCarga()` (upsert por hash, `ON DUPLICATE KEY UPDATE` refresca fecha_carga/conteos, dentro de la transacción), `obtenerHistorialUltimoMes()` (ventana `fecha_carga >= NOW() - INTERVAL 1 MONTH`, orden desc, JOIN usuarios, paginado estándar) y `importarLote(..., ?string $nombreOriginal = null)`.
- `CarteleraController`: sanitiza y guarda el nombre original del PDF en la sesión y lo pasa a la importación.
- `GastoController::index`: consulta `obtenerHistorialUltimoMes($pagina, 25)` y enriquece `archivo_existe` (`is_file`); ya no consulta el listado de gastos. `GastosModel::obtenerGastosAdmin` eliminado (43 líneas, sin callers).
- Vista: tabla "Historial de Reportes" (Reporte —con enlace "Ver PDF" solo si el archivo existe—, Fecha de Carga, Fecha del Reporte, Resultado, Cargado por) + nota "Se muestran las cargas del último mes" + estado vacío; se retiró la barra de filtros de gastos; se conservan acciones, modal, sección de deuda y paginación.
- Migración `scripts/migrate_fase21_reportes_cartelera.php` creada y ejecutada: tabla (InnoDB/utf8mb4) + backfill de 1 fila (`e2e_reporte_cartelera.pdf`, 2026-10-10 15:30:35, 362 facturas); re-ejecución idempotente verificada.
- Fuga detectada y corregida en revisión: `CarteleraUpsertTest` ahora limpia sus filas del historial (el upsert sin hash insertaba una fila por corrida); se purgaron 2 filas residuales y la BD quedó con 1 sola fila real.
- Diff del commit: 10 archivos, +337/−198.

### T6 — Verificación del orquestador (COMPLETADO)

- Tests: Cartelera 8/71, Gastos 18/84, Filtros 5/30, Paginacion 10/30, Router 23/44 — todo verde; tras `--filter=Cartelera` la BD sigue con 1 sola fila (limpieza de los tests OK).
- Render HTTP real: `/admin/gastos` y `?tab=facturacion` → 200 e idénticos (36.587 bytes); 18/18 comprobaciones: presentes "Historial de Reportes", nota del último mes, fila backfilleada (e2e…pdf, 362 facturas, "Administrador General"), secciones y acciones; ausentes el historial de gastos, la barra de filtros y "Ver PDF" (el PDF e2e no está en disco → sin enlace; degradación esperada).
- Riesgo RDD: `assess --base-ref 097d928 --committed-only` → `risk: high` (`process_boundary` por el script de migración), `review_due: true`. En OpenCode la revisión inmutable v2 no está habilitada (referencia staged, no autorizada) → se aplicó el gate: verificador independiente.
- **Verificador independiente (agente fresco, read-only): PASS — 5/5**: commit íntegro (10 archivos, padre exacto, sin atribución IA); código (upsert transaccional, ventana de un mes en query y count, `obtenerGastosAdmin` sin rastro, vista conserva lo requerido); BD (esquema correcto, 1 fila, idempotencia con fingerprint intacto); tests (64 tests / 0 fallos y sin residuos tras la corrida); coherencia (lint 10/10, greps limpios).
- Observaciones (no bloqueantes): (a) el PDF físico del e2e no está en disco (fila sin enlace, degradación correcta); (b) variables muertas preexistentes en el controlador (`$totalesPorCategoria`, `$unidades`, `$distribucion`, `$totalGastosMes`, `$tabActual`) — candidatas a limpieza futura; (c) `AUTO_INCREMENT` residual cosmético; (d) el backfill agrupa por archivo/día (esperado).

## Cierre

- Commits (locales en `main`, sin push, sin atribución IA):

  | Hash | Mensaje |
  |------|---------|
  | `778b39e` | feat(gastos): historial de reportes cargados en lugar del listado de gastos |
  | `(cierre documental)` | docs(odd): cierra el historial de reportes de cartelera |

- La ventana de "un mes" usa la fecha de carga; los datos NO se borran (solo dejan de listarse).
- Pendiente/decisiones del usuario: push/PR.
