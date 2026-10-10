# ODD task: Limpieza del importador PDF Maestro + fallback "Sin Propietario"

**Estado**: COMPLETADO Y VERIFICADO (2026-10-10) — verificación independiente APROBADA CON OBSERVACIONES; commits locales en `main`, sin push.
**Fecha**: 2026-10-10
**Ruta**: delegated-direct — un writer (borrado multi-archivo + fix SQL + tests). Commit por unidad de trabajo.

## Objetivo

1. **Eliminar por completo el flujo viejo del "PDF Maestro de Gastos"** (rutas, métodos de controlador, vista, parser, import del modelo, tests), verificando TODAS las referencias para no romper otras áreas (pedido explícito del usuario).
2. **Pulir el fallback de propietario en Morosidad**: las unidades sin propietario deben mostrar "Sin Propietario" (hoy la celda queda en blanco porque `CONCAT(COALESCE(p.nombre,''), ' ', COALESCE(p.apellido,''))` devuelve `' '` — truthy en PHP — y el `?:` de la vista no dispara).

## Contexto

- El botón de `app/views/admin/gastos/index.php` YA apunta a `/admin/cartelera` (feature nueva, verificada). El flujo maestro quedó sin entrada UI.
- Referencias conocidas al flujo maestro: `public/index.php` L212-214 (3 rutas); `GastoController` (`cargarMaestro`, `parsearMaestro`, `importarMaestro`, helper `periodoDesdePost`); vista `admin/gastos/cargar_maestro.php`; `app/services/GastoParserService.php`; `GastosModel::importarGastosMaestro`; `tests/GastosMaestroTest.php`; `tests/ArreglosAuditoriaTest.php` (lee el fuente de esos métodos: ajustar SOLO esos bloques).
- NO tocar artefactos históricos: `.sdd/*`, `odd/tasks/*` (docs), `docs/*`, `scripts/migrate_*` (p.ej. `migrate_gastos_maestro.php` es migración de esquema).
- Fallback propietario: `ReportesModel.php` L39/L115/L249 (`CONCAT(COALESCE(...))`) y L520 (`CONCAT(p.nombre,' ',p.apellido)`; era L519 antes del fix del CSV); consumidores: `morosidad.php` L206 (fallback ya presente — funcionará con NULL), `imprimir.php` L106 (sin fallback → agregarlo), CSV `exportarCsvStreaming` (~L492, decidir y documentar).
- Repo: commits locales en `main`, **sin push**; convencionales en español; sin atribución IA.

## Tareas

- [x] **T1 — Borrar flujo maestro**: rutas, métodos del controlador, vista, `GastoParserService` (huérfano), `GastosModel::importarGastosMaestro` (sin callers de producción), `tests/GastosMaestroTest.php`; ajustar `tests/ArreglosAuditoriaTest.php`. `periodoDesdePost` CONSERVADO (lo usa `guardar`). Barrido de referencias documentado abajo.
- [x] **T2 — Fallback propietario**: `NULLIF(TRIM(...), '')` en los 4 puntos de `ReportesModel.php`; fallback en `imprimir.php` L106; decisión en CSV (documentada). Consumidores verificados.
- [x] **T3 — Verificación**: `php -l` de tocados; `tests/run.php --filter=Arreglos`, `--filter=Gastos`, `--filter=Router`, `--filter=Cartelera`; evidencia SQL del fallback (NULL para unidades importadas sin propietario).

## Checks

- Suite completa: falla conocida preexistente (`SecurityTest::testCsrfRejectsInvalidToken`) — no intentar arreglarla. Confirmado: la suite completa se detiene en ese test (render 403 + corte del runner); todo lo anterior quedó en verde y no se tocó.
- Evidencia: greps de referencias + salidas de tests + SQL.

## Progreso y evidencia

### T1 — Eliminación del flujo PDF Maestro

Borrados/ajustes aplicados:
- `public/index.php`: eliminadas las 3 rutas `/admin/gastos/maestro`, `/admin/gastos/parsear-maestro`, `/admin/gastos/importar-maestro`. La ruta `/admin/gastos` y las 3 de `/admin/cartelera` (L214-216) quedan intactas.
- `app/controllers/GastoController.php`: eliminados `cargarMaestro` (L296-317), `parsearMaestro` (L322-404) e `importarMaestro` (L409-509). **`periodoDesdePost` se conserva**: lo usa `guardar` (L94-95) → no quedó huérfano.
- `app/views/admin/gastos/cargar_maestro.php`: archivo eliminado.
- `app/services/GastoParserService.php`: eliminado (huérfano — único caller de producción: `parsearMaestro`; único caller de test: `GastosMaestroTest`, ambos eliminados; `CarteleraParserService` es otro archivo y no se tocó).
- `app/models/GastosModel.php`: eliminado `importarGastosMaestro` (L422-572). Nada más del modelo fue tocado.
- `tests/GastosMaestroTest.php`: archivo eliminado (testeaba el flujo).
- `tests/ArreglosAuditoriaTest.php`: se eliminaron SOLO los bloques del flujo maestro — `testGastoControllerSaneaArchivoMaestroConBasename`, `testGastosModelImportarGastosMaestroSaneaAntesDeGuardarSoporte`, `testImportarMaestroProtegeCamposAntesDeTrimYFloatval` y `testImportarMaestroValidaRangoDePeriodo`; `testGastoControllerNormalizaPeriodoScalar` se redujo a `guardar` (regresión vigente de `periodoDesdePost`). Resto intacto.
- `tests/GastosDuplicadosTest.php`: **desviación justificada** (ver abajo): eliminados solo los 2 tests maestro (`testImportarMaestroIdempotenciaPorHash`, `testImportarMaestroOmiteFilasDuplicadasEnCarrera`) y las líneas de docblock/section-comment referidas; esquema Fase 18 + `crearGasto` + mensaje duplicado intactos.

**DESVIACIÓN (justificada)**: `tests/GastosDuplicadosTest.php` también invocaba `importarGastosMaestro` (no listado en la spec). Al ser un caller solo-de-test, conservar el método lo dejaba como código muerto de producción (contradiciendo "eliminar por completo ... import del modelo, tests"); se aplicó el mismo patrón indicado para `ArreglosAuditoriaTest` ("elimina SOLO esos bloques"). Los guards de BD (esquema) y el dedupe de `crearGasto` siguen cubiertos por los tests restantes.

**Barrido de referencias** (inicial: 91 coincidencias del patrón compuesto; final: 0 pendientes en `app/`, `public/`, `tests/`, `resources/`; `public/js/` sin coincidencias de `maestro` case-insensitive):

| Referencia | Ubicación | Resolución |
|---|---|---|
| `gastos/maestro`, `parsear-maestro`, `importar-maestro` | `public/index.php` L212-214 | Rutas eliminadas |
| `cargarMaestro`, `parsearMaestro`, `importarMaestro` | `GastoController.php` L293-509 | Métodos eliminados |
| `periodoDesdePost` | `GastoController.php` L515-521 | **Conservado** (usado por `guardar` L94-95) |
| `cargar_maestro` (vista) | `app/views/admin/gastos/cargar_maestro.php` | Archivo eliminado |
| `GastoParserService` | `app/services/GastoParserService.php` | Archivo eliminado (huérfano) |
| `importarGastosMaestro` | `GastosModel.php` L422-572 | Método eliminado (sin callers de producción) |
| `GastosMaestroTest` | `tests/GastosMaestroTest.php` | Archivo eliminado |
| `importarMaestro`, `parsearMaestro`, `importarGastosMaestro` | `tests/ArreglosAuditoriaTest.php` L57-66/79-88/155-160/182-198 | Bloques maestro eliminados; `guardar` conservado |
| `importarGastosMaestro`, `maestro` | `tests/GastosDuplicadosTest.php` L14-15/332-435 | 2 tests maestro eliminados (desviación justificada) |
| `maestro` (texto UI "PDF Maestro"/"documento maestro") | `app/views/residente/gastos.php` L164/L213 | **NO tocado**: visor de extractos históricos (RF 33/34); no referencia símbolos eliminados; datos ya importados siguen visibles |
| `GastoParserService`, `cargarMaestro/parsearMaestro/importarMaestro`, `cargar_maestro`, `gastos/maestro` | `.sdd/specs|designs|tasks|proposals/*` | Histórico: NO tocado (solo reporte) |
| idem | `odd/tasks/*` (arreglos-auditoria, auditor-funcional, auditor-visibilidad, cargar-reporte-cartelera, cumplimiento-rf, endurecimiento-fase18, estetica-listas, botones-superior-derecha) | Histórico: NO tocado (solo reporte) |
| `GastoParserService` | `scripts/report_code.py` L97/L137, `scripts/report_sprints.py` L236, `scripts/report_data.py` L30, `scripts/build_academic_report.py` L451 | Generadores de informes históricos: NO tocados (solo reporte, según regla para `scripts/`) |
| `PDF Maestro` (comentarios) | `scripts/migrate_gastos_maestro.php`, `scripts/migrate_fase18_duplicados.php` L23/L172 | Migraciones históricas: NO tocadas (solo reporte) |
| `maestro` | `public/js/`, `resources/` | 0 hallazgos |

Verificación: `grep -n "maestro" public/index.php` → 0 coincidencias; las 3 rutas de cartelera (L214-216) intactas. `git diff --stat` T1: 8 archivos, +4/-1285.

### T2 — Fallback propietario

Cambios:
- `app/models/ReportesModel.php` L39 (`obtenerReporteMorosidad`), L115 (`obtenerReporteBalanceAgrupadoPorEdificio`) y L249 (`obtenerReporteMorosidadCompleto`): `CONCAT(COALESCE(...))` → `NULLIF(TRIM(CONCAT(COALESCE(...))), '')`.
- `app/models/ReportesModel.php` L519 (`obtenerDetalleDeudaUnidad`): `CONCAT(p.nombre,' ',p.apellido)` → `NULLIF(TRIM(CONCAT(p.nombre,' ',p.apellido)), '')`.
- `app/views/admin/reportes/imprimir.php` L106: `e($m['propietario_nombre'] ?: 'Sin Propietario')`.
- `app/models/ReportesModel.php` `exportarCsvStreaming` (~L492): **Decisión**: mismo fallback que la UI — `sanitizeCsvField($row['propietario_nombre'] ?: 'Sin Propietario')` (consistencia UI/CSV; el vacío se propaga como NULL desde el modelo). Comentario inline incluido.
- `app/controllers/ReporteController.php` L206 (email `enviarAvisoCobro`): `?: 'Propietario'` (fallback mínimo, alinea con L131). La plantilla `aviso_cobro.php` L46 ya tenía `?? 'Propietario'`, pero `?:` también cubre cadena vacía.

Consumidores verificados (`grep propietario_nombre app/`):

| Consumidor | Estado | Acción |
|---|---|---|
| `morosidad.php` L206 | ya `?: 'Sin Propietario'` | Sin cambios (funciona con NULL) |
| `morosidad.php` L188 (buscador) | `?? ''` | Sin cambios |
| `carta_deuda.php` L83 | ya `?: 'Propietario / Residente'` | Sin cambios |
| `estado_cuenta_imprimir.php` L59 | ya `?: 'Propietario'` | Sin cambios (fuente `EstadoCuentaController`, no tocado) |
| `ReporteController` L131 | ya `?: 'Propietario'` | Sin cambios |
| `ReporteController` L206 (email) | pasaba crudo | Fallback agregado |
| `VehiculosModel`, `EstadoCuentaController` | ajenos | NO tocados |

**Evidencia SQL** (unidad importada sin propietario, `1-01`, `propietario_id = NULL`):

```
numero  propietario_id  antes  despues
1-01    NULL                     NULL

SELECT u.numero, u.propietario_id,
       CONCAT(COALESCE(p.nombre,''), ' ', COALESCE(p.apellido,'')) AS antes,
       NULLIF(TRIM(CONCAT(COALESCE(p.nombre,''), ' ', COALESCE(p.apellido,''))), '') AS despues
FROM unidades u LEFT JOIN personas p ON u.propietario_id = p.id
WHERE u.numero = '1-01';
```

`antes` devuelve un espacio `' '` (truthy → el `?:` no disparaba); `despues` devuelve `NULL` (falsy → dispara el fallback). Segunda forma L519 (`CONCAT(p.nombre,' ',p.apellido)`) también NULL.

### T3 — Verificación

- `php -l`: sin errores en `public/index.php`, `GastoController.php`, `GastosModel.php`, `ReportesModel.php`, `ReporteController.php`, `imprimir.php`, `ArreglosAuditoriaTest.php`, `GastosDuplicadosTest.php`.
- Tests: `--filter=Arreglos` 13/13 ✅ (33 aserciones); `--filter=Gastos` 18/18 ✅ (83); `--filter=Router` 23/23 ✅ (44); `--filter=Cartelera` 3/3 ✅ (44); extras de regresión T2: `--filter=Balance` 9/9 ✅ (479); `--filter=Reporte` 0 tests.
- Suite completa: se detiene en la falla conocida preexistente `SecurityTest::testCsrfRejectsInvalidToken` (render 403 y corte del runner); todo lo ejecutado antes quedó ✅. No se intentó arreglar.
- Rutas: `maestro` ausente de `public/index.php`; `/admin/cartelera*` intactas.

### Commits

- `ca46f46` — `refactor(gastos): elimina el importador obsoleto de PDF Maestro` (T1 + ajuste de tests)
- `cd567da` — `fix(reportes): muestra 'Sin Propietario' cuando la unidad no tiene propietario` (T2)

### Desviaciones y notas

1. `tests/GastosDuplicadosTest.php` referenciaba `importarGastosMaestro` (hallazgo del barrido no listado en la spec): se eliminaron SOLO sus 2 tests maestro, conservando esquema y dedupe de `crearGasto`. Alternativa rechazada: conservar el método solo para sus tests (código muerto).
2. `--filter=Reporte` no coincide con ninguna clase; se corrió `--filter=Balance` (ejercita el SQL de L115 contra BD viva) como regresión adicional de T2.
3. `app/views/residente/gastos.php` conserva textos "PDF Maestro" (visor histórico de extractos); sin dependencia del código eliminado.

## Verificación independiente (orquestador, 2026-10-10)

- **Spot-check del orquestador**: commits `ca46f46`/`cd567da`/`d9d9f83` (solo archivos esperados); `tests/run.php --filter=Arreglos|Gastos|Cartelera|Balance|Router` → todos exit 0; **HTTP en vivo** (server localhost:8000, sesión admin real): `/admin/reportes/morosidad` → 200 con **219× "Sin Propietario"** (antes: celda en blanco), `/admin/reportes/morosidad/exportar-csv` → contiene "Sin Propietario", `/admin/gastos` → 200 con botón "Cargar Reporte de Cartelera", `/admin/gastos/maestro` → **404** (ruta eliminada).
- **Riesgo del diff** (`gentle-ai review assess --base-ref b9b8466 --committed-only`): `risk: high` / `unassessable` (OpenCode no elegible para revisión inmutable; esperado) → verificador independiente por el gate.
- **Verificador independiente (agente fresco, read-only): APROBADO CON OBSERVACIONES — 6/6 PASS**:
  1. Diff íntegro: solo funciones `cargarMaestro/parsearMaestro/importarMaestro/importarGastosMaestro` eliminadas; `guardar/eliminar/index/rendicionResidente`, CRUD de `GastosModel` y `AdminController::generarFacturas` intactos; `periodoDesdePost` conservado con uso válido.
  2. Cero referencias colgantes en runtime (`app/`, `public/`, `resources/`, `tests/`); solo históricos esperados (`.sdd/`, `odd/`, `docs/`, `scripts/migrate_*`).
  3. Lint OK; suites: Arreglos 13/13, Gastos 18/18, Router 23/23, Cartelera 3/3, Balance 9/9 (todas exit 0).
  4. Fallback: 4× `NULLIF` en `ReportesModel`; SQL en vivo `1-01`: antes `' '` (truthy) → ahora `NULL`; consumidores toleran NULL (cableado verificado).
  5. Rutas: viejas ausentes (404 real); `/admin/cartelera*` presentes [ADMIN, AUDITOR].
  6. Tests ajustados: solo bloques maestro eliminados, sin sobre-borrado.
- **Observaciones (menores, no bloqueantes)**: (a) L519→L520 cosmético (corregido arriba); (b) `obtenerReporteMorosidad` (L39) no tiene callers — código muerto preexistente, candidato a limpieza futura; (c) atribución cosmética en el barrido sobre `scripts/report_*.py` (prosa histórica).
