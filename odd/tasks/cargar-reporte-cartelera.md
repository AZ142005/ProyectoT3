# ODD task: Cargar Reporte de Cartelera (Dotwood) — importación de deuda por unidades

**Estado**: COMPLETADO (2026-10-10) — implementación T1–T7 + verificación E2E; commits locales en `main`, sin push.
**Fecha**: 2026-10-10
**Ruta**: delegated-direct — un writer para toda la feature (trigger: writer rule, 5+ archivos no triviales). Commit por unidad de trabajo.

## Objetivo

1. Renombrar el botón "Cargar PDF Maestro" (vista `admin/gastos/index.php`) a **"Cargar Reporte de Cartelera"** y repuntarlo al nuevo flujo.
2. Implementar la carga real del **Reporte de Cartelera** (PDF de Dotwood PC Condominios): extraer deudas por unidad (Saldo Anterior + meses + Deuda Total), mostrar preview y **importarlas al sistema** (edificios/unidades faltantes + facturas), idempotente y auditado.
3. Analizar qué áreas de la interfaz admin quedan **sin rellenar** con los datos del reporte (informe; tarea de exploración aparte).

## Contexto y decisiones ya tomadas

- PDF real de prueba: `rpueba para reporte estudiantes.pdf` (raíz del repo, untracked). Emitido 09/10/2026; **216 unidades con deuda**; totales por columna: S.Anterior 488.257,99 / Ago 889.225,85 / Sep 2.057.568,00 / Oct 5.456.080,67 / Nov 0,00; **Deuda Total 8.891.132,51 Bs** (verificado al centavo con prototipo).
- Prototipo extractor funcional (referencia de algoritmo): `C:/Users/Amadeo/AppData/Local/Temp/opencode/pdf-extract/final.php` + `decode.php`.
- Quirks del PDF (Crystal Reports): `/Filter [ /FlateDecode ]` en array; CMaps `/ToUnicode` por fuente; Td relativos sin avance implícito; valores partidos en piezas contiguas ("19.5"+"11,94"); columnas detectables por borde derecho alineado.
- Los parsers actuales del sistema NO pueden leer este PDF (probado: ruido binario / 0 renglones / campos null). El nuevo parser es propio del módulo.
- Modelo de datos: `edificios` → `unidades` (numero UNIQUE global → usar etiqueta completa "N-NN") → `facturas` (numero_factura UNIQUE, saldo, mes/anio, fechas). Para que la deuda aparezca en morosidad admin: saldo > 0 y fecha_vencimiento < hoy.
- Decisiones de importación: edificios "Torre 1".."Torre 6"; unidades numero = etiqueta del reporte ("1-01".."6-66"), propietario NULL, cuota 0; facturas `CART-{etiqueta}-{AAAAMM}` (Saldo Anterior → mes 7/2026 "Saldo anterior"; meses → "Cuota {Mes}-{anio}"), monto_total = saldo = valor, monto_pagado = 0, fecha_emision = fecha_vencimiento = fecha de emisión del reporte, estado 'vencida', observaciones "Migración Reporte de Cartelera (Dotwood)". Reimportación omite duplicados por numero_factura.
- Patrón a seguir: flujo PDF Maestro de Gastos (`GastoController::cargarMaestro/parsearMaestro/importarMaestro`, vista `admin/gastos/cargar_maestro.php`, rutas `public/index.php` L212-214, `GastosModel::importarGastosMaestro` con transacción + dedupe + auditoría).
- Repo: commits locales en `main`, **sin push** (patrón del repo). Commits convencionales en español, sin atribución IA.

## Tareas

- [x] **T1 — Botón**: renombrar "Cargar PDF Maestro" → "Cargar Reporte de Cartelera" en `app/views/admin/gastos/index.php` y repuntar href a `/admin/cartelera`. No borrar el flujo del PDF Maestro de Gastos (queda intacto; solo se repunta el botón).
- [x] **T2 — `app/services/CarteleraParserService.php`**: parser propio del PDF de cartelera (generalizar del prototipo: fuentes/CMaps dinámicos, meses por etiquetas de cabecera, columnas por clustering, validación de sumas por fila y contra la fila "Totales" impresa). Salida estructurada (filas, totales, fecha emisión, conteo por edificio, errores). Sin dependencias nuevas.
- [x] **T3 — `app/controllers/CarteleraController.php` + rutas**: GET `/admin/cartelera` (vista), POST `/admin/cartelera/parsear` (subida + parseo + preview en sesión, JSON en AJAX), POST `/admin/cartelera/importar` (confirmar). Roles [ADMIN, AUDITOR]. FileUploader a `UPLOADS_PATH.'/soportes'`, PDF ≤10MB.
- [x] **T4 — `app/models/CarteleraModel.php`**: `importarLote(...)` transaccional: asegura edificios/unidades, crea facturas con dedupe por numero_factura, registra auditoría, devuelve conteos.
- [x] **T5 — Vista `app/views/admin/cartelera/cargar.php`**: subida + preview (tabla por unidad, totales, validación) + confirmar; estilos y CSRF como el resto del módulo.
- [x] **T6 — Tests `tests/CarteleraParserTest.php`**: fixture copiado a `tests/fixtures/cartelera_dotwood.pdf` (skip si falta); 216 filas, fecha 2026-10-09, totales exactos, filas muestra (1-04, 5-63, 6-66). Runner: `php tests/run.php --filter=Cartelera`.
- [x] **T7 — Verificación E2E** (script temporal fuera del repo): importar el PDF real a la BD local → `SUM(saldo)` de facturas vencidas = **8.891.132,51** (morosidad admin); re-import → 0 creadas (idempotente). Reportar conteos reales.

## Criterios de aceptación

- Botón renombrado y funcional → página de carga del reporte.
- Parser: PDF real → 216 filas, totales exactos al centavo, validación interna OK.
- Import: crea estructura faltante + facturas; morosidad admin suma 8.891.132,51; reimportar no duplica.
- Tests nuevos pasan; flujo existente de gastos maestro intacto.

## Checks

- **TDD estricto**: no configurado para el proyecto (sin registro `sdd-init`); se ejecutan checks funcionales ordinarios (suite del repo).
- `C:/xampp/php/php.exe tests/run.php --filter=Cartelera` (nuevos).
- `php tests/run.php` completo: **falla conocida preexistente** — aborta en `SecurityTest::testCsrfRejectsInvalidToken` (exit 1, sin resumen); no es regresión de esta feature.
- E2E en BD local (ver T7).

## Progreso y evidencia

- Mirror Engram: **omitido por instrucción explícita de la tarea** (no usar Engram en esta ejecución).

### T1 — Botón ✅ (2026-10-10)

- Cambio en `app/views/admin/gastos/index.php` (L104-107): href `/admin/gastos/maestro?mes=…&anio=…` → `/admin/cartelera`; texto "Cargar PDF Maestro" → "Cargar Reporte de Cartelera". Icono sin cambios.
- Commit: `3f23218` — `feat(cartelera): repuntar boton de carga al Reporte de Cartelera` (1 archivo, 2 insert/2 delete; flujo maestro intacto).
- Verificación: `git diff --cached --stat` → solo el archivo de la vista.

### T2 + T6 — Parser y tests ✅ (2026-10-10)

- `app/services/CarteleraParserService.php` (nuevo, ~945 líneas): indexa objetos por firma `N 0 obj`, descomprime FlateDecode (`gzuncompress`/`gzinflate`), resuelve `/Resources /Font` → fuentes → `/ToUnicode` (CMaps bfrange/bfchar) y `/Widths`/`/FirstChar`; decodifica `Tf/Tm/Td/TD/Tj/TJ` con Td acumulados sin avance implícito; fusiona piezas (misma posición / contiguas); detecta fecha de emisión (`dd/mm/aaaa` junto a "Fecha emisión:"); agrupa columnas monetarias por clustering de bordes derechos (gap 500, asignación ±220); asigna meses por etiquetas de cabecera (Ene..Dic con año `2.026`); valida suma por fila y contra la fila "Totales" impresa; `ok=false` con detalle si algo no cuadra.
- `tests/CarteleraParserTest.php` (nuevo): 3 tests — clases/métodos, parseo del fixture real (skip si falta), archivo inválido. Fixture copiado a `tests/fixtures/cartelera_dotwood.pdf` (NO versionado).
- Commit: `14197e5` — `feat(cartelera): parser del reporte de deudas por unidad con tests`.
- Comandos y resultados:
  - `C:/xampp/php/php.exe -l app/services/CarteleraParserService.php` → sin errores de sintaxis.
  - Harness temporal contra el PDF real → `ok=true`, **216 filas**, fecha `2026-10-09`, meses `[8,9,10,11]`, totales 488.257,99 / 889.225,85 / 2.057.568,00 / 5.456.080,67 / 0,00 / **8.891.132,51**, conteo 30/39/33/38/38/38; muestras 1-04, 5-63 y 6-66 exactas.
  - `C:/xampp/php/php.exe tests/run.php --filter=Cartelera` → `3 tests | 44 passed | 0 failed | 0 errors | 0 skipped`.
- Desviaciones del prototipo (documentadas):
  - El prototipo fijaba a mano las columnas (bordes 3866/5186/6506/7826/9146 y ancla 10200–10700) y los objetos de fuente/CMaps (27/33, s5/s7.bin). El servicio los resuelve dinámicamente desde el PDF.
  - No se descartan clusters pequeños: una columna inesperada hace fallar la validación (fail closed) en lugar de ignorarse. Esto permitió detectar la columna Nov (0,00) que solo existe en la fila "Totales".
  - La banda horizontal (±25) de la última fila de cada página choca con la cabecera de la tabla ("Inmueble", "S. Anterior Bs"); se excluye con corte de etiqueta (`primera columna − 1500`) y limpieza explícita de esos residuos.

### T4 — Modelo de importación ✅ (2026-10-10)

- `app/models/CarteleraModel.php` (nuevo, 209 líneas): `importarLote(array $filas, string $fechaEmision, int $adminId, string $archivo, ?string $hash): array` — transacción PDO; asegura edificios "Torre N" (descripción "Importado de Reporte de Cartelera") y unidades "N-NN" (propietario NULL, cuota 0) reutilizando existentes por nombre/numero; crea facturas `CART-{unidad}-{AAAAMM}` (saldo anterior → mes 7 "Saldo anterior"; cada mes > 0 → "Cuota {Mes}-{año}"), `monto_total = saldo = valor`, `monto_pagado=0`, fechas = emisión del reporte, `estado='vencida'`, observaciones "Migración Reporte de Cartelera (Dotwood)"; omite duplicados por `numero_factura` (incluye carrera 1062); audita cada factura en `log_auditoria` (`accion='importar_cartelera'`, incluye archivo y hash del PDF). `total_importado` = facturas creadas (definido en docblock).
- Commit: `2fedeac` — `feat(cartelera): importacion transaccional con dedupe y auditoria`.
- E2E (script temporal `C:/Users/Amadeo/AppData/Local/Temp/opencode/cartelera-e2e/e2e.php`, BD local `condominio_cobranzas`):
  - 1ª ejecución: `{"edificios_creados":6,"unidades_creadas":216,"facturas_creadas":362,"facturas_omitidas":0}`.
  - `SELECT ROUND(SUM(saldo),2) FROM facturas WHERE numero_factura LIKE 'CART-%' AND deleted_at IS NULL` → **8.891.132,51**.
  - Desglose: 2026-07 → 18 facturas / 488.257,99; 2026-08 → 40 / 889.225,85; 2026-09 → 88 / 2.057.568,00; 2026-10 → 216 / 5.456.080,67.
  - 2ª ejecución (idempotencia): `{"edificios_creados":0,"unidades_creadas":0,"facturas_creadas":0,"facturas_omitidas":362}`; el conteo CART sigue en 362; `log_auditoria` con `accion='importar_cartelera'` = 362 registros (uno por factura creada, sin duplicados en la 2ª corrida).
  - Morosidad general tras importar: 367 facturas / 218 unidades / **8.892.022,51** (los CART aportan 8.891.132,51; el resto es deuda preexistente: 890,00).

### T3 + T5 — Controlador, rutas y vista ✅ (2026-10-10)

- `app/controllers/CarteleraController.php` (nuevo, 179 líneas): `index()` (render con preview de sesión; parámetro `?descartar=1` limpia la previsualización), `parsear()` (FileUploader a `UPLOADS_PATH/soportes`, PDF ≤10MB, parser; guarda `cartelera_preview`/`cartelera_meta` en sesión; JSON en AJAX con el `csrf_token` rotado; flash + redirect en formulario normal), `importar()` (lee la sesión, llama a `CarteleraModel::importarLote`, limpia sesión y deja flash con conteos). Roles admin/auditor en los tres métodos.
- `public/index.php` L216-219: rutas GET `/admin/cartelera`, POST `/admin/cartelera/parsear`, POST `/admin/cartelera/importar` con roles `[UserRole::ADMIN, UserRole::AUDITOR]`, junto al bloque de gastos.
- `app/views/admin/cartelera/cargar.php` (nuevo, 235 líneas): patrón de `admin/gastos/cargar_maestro.php` (sidebar admin/auditor, flash, CSRF); dropzone PDF con envío AJAX por `fetch` (fallback POST normal sin JS); previsualización con encabezado (archivo, emisión, páginas, validación ✓, conteo por edificio), tabla Inmueble / S. Anterior / meses / Deuda Total con pie de Totales, y confirmación POST `/admin/cartelera/importar`. El JS actualiza el token CSRF con el valor devuelto en cada intento.
- Commit: `8526e8c` — `feat(cartelera): flujo web de carga, preview e importacion`.
- Comandos y resultados:
  - `php -l` de controlador, vista y `public/index.php` → sin errores de sintaxis.
  - Smoke test CLI (`.../cartelera-e2e/smoke_controller.php`): render con preview → 484.894 bytes; presentes "Carga del Reporte de Cartelera", "Previsualización del Reporte", "Confirmar e Importar", "S. Anterior", "Agosto", "Noviembre", "8.891.132,51", "77.860,89", "1-04" y los ids de ambos formularios; sin preview no se renderiza la tabla.
  - Regresión: `tests/run.php --filter=Router` (23 tests/44 asserts), `--filter=Gastos` (27/122), `--filter=Behavior` (108/252), `--filter=Cartelera` (3/44) → todo en verde.
- Desviación: la subida AJAX valida en el servidor y recarga la página para que la previsualización se renderice desde la sesión (única fuente de verdad del HTML); el formulario POST normal queda como fallback sin JS.

### T7 — Verificación E2E final ✅ (2026-10-10)

- Script temporal (fuera del repo): `C:/Users/Amadeo/AppData/Local/Temp/opencode/cartelera-e2e/e2e.php` (parseo + importación + consultas de verificación).
- Re-ejecución de la importación (idempotencia): `{"edificios_creados":0,"unidades_creadas":0,"facturas_creadas":0,"facturas_omitidas":362}`.
- `SELECT ROUND(SUM(saldo),2) AS s FROM facturas WHERE numero_factura LIKE 'CART-%' AND deleted_at IS NULL` → **8.891.132,51** (sin cambios tras reimportar).
- Conteos reales de la primera importación: **6 edificios creados** ("Torre 1".."Torre 6"), **216 unidades creadas**, **362 facturas creadas**, **0 omitidas**; desglose: 2026-07 → 18 / 488.257,99 · 2026-08 → 40 / 889.225,85 · 2026-09 → 88 / 2.057.568,00 · 2026-10 → 216 / 5.456.080,67.
- Morosidad general tras importar (criterio de `ReportesModel`: `saldo > 0 AND fecha_vencimiento < CURDATE() AND deleted_at IS NULL`): **367 facturas / 218 unidades / 8.892.022,51 Bs** (los CART aportan 8.891.132,51; el resto es deuda preexistente de 890,00).
- `log_auditoria`: 362 registros `importar_cartelera` (uno por factura creada; la reimportación no generó registros nuevos).
- Estado final de la BD local: datos del reporte cargados (el usuario quería los datos en la BD).

## Cierre

- Commits (locales en `main`, sin push, sin atribución IA):

  | Hash | Mensaje |
  |------|---------|
  | `3f23218` | feat(cartelera): repuntar boton de carga al Reporte de Cartelera |
  | `14197e5` | feat(cartelera): parser del reporte de deudas por unidad con tests |
  | `2fedeac` | feat(cartelera): importacion transaccional con dedupe y auditoria |
  | `8526e8c` | feat(cartelera): flujo web de carga, preview e importacion |
  | `(cierre documental)` | docs(odd): registra progreso y evidencia de la carga de cartelera |

- Criterios de aceptación: botón renombrado y funcional ✓; parser 216 filas y totales exactos ✓; import crea estructura + facturas, morosidad suma 8.891.132,51 y reimportar no duplica ✓; tests nuevos en verde y flujo del PDF Maestro de Gastos intacto (rutas y vista sin cambios; solo se repuntó el botón) ✓.
- Desviaciones del diseño original (documentadas arriba): fuentes/CMaps/Widths dinámicos en vez de objetos fijos; sin descarte de clusters (fail closed); la vista recarga para renderizar la preview tras el AJAX; `total_importado` = facturas creadas; el hash del PDF solo se usa en auditoría (la idempotencia es por `numero_factura`).
- Pendientes: el objetivo 3 ("analizar qué áreas de la interfaz admin quedan sin rellenar con los datos del reporte") queda como informe/exploración aparte; no forma parte de T1–T7. El fixture real no se versiona: en clones sin `tests/fixtures/cartelera_dotwood.pdf` el test principal hace skip.
- Riesgos: el parser está calibrado al formato Crystal/Dotwood (ante variantes de layout fallará cerrado con errores y no importará nada); el PDF subido queda en `uploads/soportes` (directorio gitignored).
