# Conciliación: lote único activo, reportes históricos y cruce automático

## Objetivo (instrucción del usuario)
- Sincronizar e integrar la rama de trabajo hacia `main` con historial lineal (`fetch` + `rebase`), resolviendo conflictos sin pérdida de código.
- Excluir de forma estricta `scratch/` del staging, commits y push (ya está en `.gitignore` y `.git/info/exclude`).
- Refactorizar la lógica del lote bancario en Conciliación **sin modificar el esquema de la base de datos**:
  1. Trabajar exclusivamente con el último lote cargado; al importar uno nuevo, el anterior deja de ser fuente activa. Los `DISPONIBLE` de lotes viejos no deben aparecer en la bandeja ni entrar al cruce. Los `CONCILIADO` de lotes viejos se conservan intactos.
  2. Generar un reporte histórico completo (PDF y CSV) en disco al reemplazar un lote, y habilitar descarga del reporte actual y de los históricos desde la UI, sin tablas nuevas.
  3. Re-ejecutar automáticamente el cruce inteligente al terminar la importación del nuevo lote.

## Contexto y hallazgos
- `extractos_bancarios` no tiene bandera de lote activo ni tabla de lotes: `conciliacion_lotes` fue retirada en `scripts/migrations_phase23_drop_tablas_huerfanas.sql:6-9`. El lote es el texto `lote_importacion`.
- El lote activo sólo puede derivarse: último `MAX(fecha_carga)`, desempate `MAX(id)`.
- `uk_extracto_identidad (referencia_bancaria, fecha_movimiento, monto)` es global, no por lote (`database/condominio_cobranzas.sql:438`). Reimportar el mismo movimiento no inserta fila nueva; el modelo actual sólo cuenta duplicado (`ConciliacionModel.php:21-25,53-56`).
- `obtenerExtractosPendientes(null)` hoy devuelve todos los lotes (`ConciliacionModel.php:106-139`); el controlador lo llama sin lote cuando no hay `?lote` (`ConciliacionController.php:22-25`).
- `ejecutarCruceInteligente()` no consulta extractos: recibe movimientos y consulta pagos/comprobantes pendientes (`ConciliacionBancariaService.php:399-443`). Filtra estado y hoy sólo acepta `disponible` (`:483-486`).
- No existe endpoint de descarga de conciliación. FPDF está embebido en `app/libraries/fpdf/fpdf.php`; patrón de descarga en `RespaldoController.php:124-133`; patrón CSV en `AuditorController.php:107-154` y sanitización en `ReportesModel.php` (`sanitizeCsvField`).
- `storage/backups/` ya está ignorado por `.gitignore:19` y bloqueado por `storage/backups/.htaccess`. Ubicación elegida: `storage/backups/reportes_conciliacion/`.
- RDD está **off** (global), por lo que no aplica el ciclo de review nativa; se usan checks ordinarios.
- MySQL local: servicio `mysql8` detenido/deshabilitado; XAMPP tiene `condominio_cobranzas` (294 extractos). Para verificación se levantó `C:\xampp\mysql\bin\mysqld.exe` con `--skip-grant-tables` de forma temporal. Debe apagarse al cerrar la tarea.

## Alcance
**Dentro:**
- `ConciliacionModel`: lote activo, filtro activo, adopción de duplicados, métricas de lote, queries de conciliados/disponibles.
- `ConciliacionBancariaService`: aceptar `estado = 'pendiente'` como disponible en el cruce (compatibilidad con filas legacy).
- Nuevo `app/Services/ReporteConciliacionService.php`: snapshot, PDF, CSV, guardado en disco, listado y resolución segura de archivos.
- `ConciliacionController`: importación con snapshot + reporte + cruce; descargas actual/histórico; datos para la vista.
- `public/index.php`: dos rutas GET de descarga.
- `app/views/admin/conciliacion/index.php`: botones, modal de históricos, aviso de lote histórico, selector.
- Tests nuevos y ajustes mínimos de tests existentes si el comportamiento nuevo lo exige.

**Fuera:**
- Cualquier migración, `ALTER`, `CREATE TABLE` o cambio de columnas/índices.
- Auto-aprobación de pagos: el cruce se ejecuta y se informa; aprobar sigue siendo acción explícita.
- Conciliación manual de movimientos pertenecientes a lotes históricos (no se exponen en la bandeja).
- Cambios en otros módulos o rediseños ajenos.
- Incluir `scratch/` en el repositorio.

## Reglas de negocio
1. **Lote activo** = `lote_importacion` con `MAX(fecha_carga)`; empate → `MAX(id)`.
2. `obtenerExtractosPendientes(null)` y `obtenerExtractosPendientes(loteActivo)` devuelven sólo el lote activo. Un lote histórico devuelve `[]`.
3. El cruce inteligente sólo recibe movimientos del lote activo (la vista ya no llama con `null` global).
4. **Importación:**
   a. Parsear el archivo (sin cambios).
   b. Determinar el lote saliente (activo actual) y construir el snapshot en memoria: conciliados globales, disponibles del saliente, pagos/comprobantes pendientes globales.
   c. Insertar el nuevo lote. Los duplicados no conciliados se **adoptan** (se actualiza `lote_importacion`, `banco`, descripciones y `fecha_carga` de la fila existente); los conciliados **no se tocan**.
   d. Persistir el reporte histórico del saliente en PDF y CSV (si existía lote saliente). Un fallo de reporte no bloquea la importación.
   e. Ejecutar el cruce inteligente sobre los disponibles del lote activo resultante. Un fallo del cruce no bloquea la importación.
   f. Flash con insertados, adoptados, duplicados, resumen del cruce y archivos de reporte generados.
5. Si el nuevo lote queda sin filas (todo duplicado conciliado), **no se rota**: se mantiene el lote anterior y se informa al usuario.
6. **Reporte:**
   - Sección A: movimientos `CONCILIADO` de todo el historial (unidad, residente, referencia, lote, monto).
   - Sección B: movimientos `DISPONIBLE` del lote consultado/saliente.
   - Sección C: pagos y comprobantes `PENDIENTE` por conciliar.
   - PDF con FPDF (conversión Windows-1252), CSV con BOM UTF-8 y delimitador `;`.
   - Metadatos en sidecar `.json`; sólo se descargan `.pdf`/`.csv`.
   - Reporte actual: se genera bajo demanda y se transmite (no se persiste). Histórico: se lee del disco.
7. **UI:** botones "Reporte actual PDF/CSV" y "Históricos (N)"; modal con fecha de corte, lote, conteos y enlaces. Si el lote seleccionado no es el activo, aviso visible de que es histórico.
8. **Seguridad:** roles `admin`/`auditor`; salida con `e()`; sin SQL en controlador; descargas GET con `basename`, regex de extensión y `realpath` contenido en el directorio de reportes.

## Archivos previstos
- `app/Models/ConciliacionModel.php`
- `app/Services/ConciliacionBancariaService.php`
- `app/Services/ReporteConciliacionService.php` (nuevo)
- `app/Controllers/ConciliacionController.php`
- `app/views/admin/conciliacion/index.php`
- `public/index.php`
- `tests/ConciliacionLoteUnicoTest.php` (nuevo)
- `tests/ReporteConciliacionTest.php` (nuevo)
- `tests/ConciliacionReporteIntegracionTest.php` (nuevo)

## Tareas (checklist con IDs estables)

- [x] **T1 — Lote único activo y adopción de duplicados**  
  Ruta: delegated writer (una sesión acotada). Superficies: `app/Models/ConciliacionModel.php`, `app/Services/ConciliacionBancariaService.php`, `tests/ConciliacionLoteUnicoTest.php`.  
  Incluye: `obtenerLoteActivo()`, filtro activo en `obtenerExtractosPendientes()`, `obtenerDisponiblesLote()` (crudo para reportes), `insertarExtracto()` con `adoptados`, `obtenerLotes()` con `es_activo` y métricas corregidas, cruce aceptando `pendiente`. Test-first: escribir `ConciliacionLoteUnicoTest`, observar RED, implementar, GREEN.

- [x] **T2 — Servicio de reportes históricos PDF/CSV**  
  Ruta: delegated writer. Superficies: `app/Models/ConciliacionModel.php` (queries de reporte), `app/Services/ReporteConciliacionService.php`, `tests/ReporteConciliacionTest.php`.  
  Incluye: `obtenerMovimientosConciliados()`, `construir()`, `generarPdf()`, `generarCsv()`, `guardar()` (PDF+CSV+JSON), `listarHistoricos()`, `resolverArchivo()`, `directorioReportes()`, sanitización CSV anti-inyección. Test-first.

- [x] **T3 — Integración controlador/rutas/vista y cruce automático**  
  Ruta: delegated writer. Superficies: `app/Controllers/ConciliacionController.php`, `app/views/admin/conciliacion/index.php`, `public/index.php`, `tests/ConciliacionReporteIntegracionTest.php`.  
  Incluye: snapshot antes de insertar, reporte al reemplazar, cruce automático, flash, lote activo por defecto, rutas de descarga, botones/modal/aviso, tests de wiring y seguridad de descarga. Test-first.

- [ ] **T4 — Verificación y Git**  
  `php -l` en archivos tocados; `php scripts/check_purity.php`; `php scripts/audit_security.php`; `php tests/run.php` (con MySQL). Auditar `git status --ignored` (scratch intacta). `git fetch origin`; `git rebase origin/main`; resolver conflictos si aparecen; `git checkout main`; `git merge --ff-only feat/conciliacion-lote-unico-reportes`; `git push origin main`; `git log -n 5 --oneline --graph`. Apagar el MySQL temporal.

## Plan de pruebas (TDD)
- `ConciliacionLoteUnicoTest` (BD real, limpieza por prefijo único):
  - lote activo = último por `fecha_carga`/`id`;
  - disponibles de lote viejo no salen en `obtenerExtractosPendientes()` ni en el cruce;
  - conciliado de lote viejo no se modifica;
  - reimportar duplicado no conciliado lo adopta sin duplicar fila;
  - reimportar duplicado conciliado no lo toca;
  - `obtenerLotes()` marca `es_activo` y cuenta pendientes reales.
- `ReporteConciliacionTest` (sin BD):
  - CSV con BOM, secciones, `;` y sanitización de `=`, `+`, `-`, `@`;
  - PDF inicia con `%PDF` y termina con `%%EOF`;
  - `guardar()` crea PDF+CSV+JSON; `listarHistoricos()` los encuentra;
  - `resolverArchivo()` rechaza `../`, rutas absolutas y extensiones no permitidas.
- `ConciliacionReporteIntegracionTest` (estático/wiring + BD acotada):
  - rutas GET registradas y roles;
  - controlador con `obtenerLoteActivo`, `construir`, `guardar`, `obtenerExtractosPendientes`, `ejecutarCruceInteligente`;
  - vista con botones/modal y `e()` en datos de reportes;
  - descarga histórica rechaza archivo inválido.
- Runner: `composer test` o `php tests/run.php --filter=<Clase>`.
- Nota: los tests de BD requieren MySQL activo. Si no está disponible, se reporta como bloqueo de entorno, no como éxito.

## Criterios de aceptación
1. Al importar un segundo lote, la bandeja y el cruce usan sólo el nuevo; los `DISPONIBLE` del anterior no aparecen.
2. Los `CONCILIADO` anteriores siguen visibles en el reporte histórico y en la BD sin cambios.
3. Reimportar el mismo extracto no duplica filas: adopta las no conciliadas y preserva las conciliadas.
4. Al reemplazar un lote se generan en disco un PDF y un CSV con las tres secciones; la UI permite descargar el actual y los históricos.
5. Al terminar la importación se ejecuta el cruce automático y el flash informa el resultado.
6. No existe ninguna migración ni cambio de esquema.
7. `scratch/` no aparece en `git status`, `git diff --cached --stat` ni en el push.
8. `check_purity`, `audit_security` y la suite pasan (salvo fallos de entorno preexistentes, reportados con evidencia).
9. Historial de `main` lineal tras rebase/merge `--ff-only`.

## Estrategia de entrega
- `exception-ok`: el usuario pidió explícitamente integración directa a `main` por rebase, sin PR. Se conservan commits por unidad de trabajo para revisabilidad.
- Pronóstico de líneas autoradas: ~1.100 (modelo ~150, servicio ~380, controlador ~150, vista ~100, rutas ~2, tests ~350). Supera el presupuesto de ~400; se justifica por las tres funcionalidades y su cobertura. Sin recortes cosméticos.
- Commits previstos:
  1. `feat(conciliacion): limita la bandeja al lote activo y adopta duplicados`
  2. `feat(conciliacion): genera reportes historicos en pdf y csv`
  3. `feat(conciliacion): integra reportes y cruce automatico al reemplazar lote`

## Progreso y evidencia
- **T1 completado y verificado.** `obtenerLoteActivo()`, filtro exclusivo del lote activo en `obtenerExtractosPendientes()`, consulta cruda `obtenerDisponiblesLote()`, adopción atómica de duplicados no conciliados (`adoptados`), métricas `obtenerLotes()` con `es_activo`, `conciliarAbono()` aceptando `estado='pendiente'` y cruce con doble estado.
  - `php tests/run.php --filter=ConciliacionLoteUnicoTest`: **9 tests, 100 aserciones, 0 fallos, 0 errores, 0 omitidos**.
  - `php tests/run.php --filter=ExtractosDuplicadosTest`: **4 tests, 9 aserciones, 0 fallos**.
  - `php tests/run.php --filter=ConciliacionTest`: 13 tests, 85 aserciones, 3 fallos preexistentes (`notificaciones_cola.adjuntos` ausente en la BD local; ruta `NotificationService`, ajena a T1).
  - `check_purity`, `audit_security` y `php -l`: OK.
  - Verificación independiente: PASS WITH FINDINGS; se corrigieron el bug NULL de `estado`, la adopción atómica por lote esperado, la precedencia de estados del cruce y la consistencia de métricas. Quedan riesgos LOW aceptados: tabla vacía y desempate por id se validan por inspección; no se agregó un test de concurrencia de adopción.
- **Ruta de delegación:** el agente `gentle-ai-worker` no está disponible en este entorno por restricción geográfica del proveedor; T1 se ejecutó con el agente general bajo las mismas superficies y fue re-verificado por `gentle-ai-verify`.
- Commit T1: `264fdc6 feat(conciliacion): limita la bandeja al lote activo y adopta duplicados`.
- **T2 completado y verificado.** Nuevo `ReporteConciliacionService` con `construir()`, PDF (FPDF/Windows-1252, tablas con corte de página), CSV (BOM UTF-8, `;`, anti-inyección), `guardar()` (PDF+CSV+JSON con limpieza compensatoria) y `listarHistoricos()`/`resolverArchivo()` con validación de metadatos, magic bytes y `realpath`. Se agregaron `obtenerDatosLote()` y `obtenerMovimientosConciliados()` al modelo; se corrigió el doble conteo de residente/propietario en `obtenerTodosPagosPendientes()` con `COALESCE`.
  - `php tests/run.php --filter=ReporteConciliacionTest`: **12 tests, 90 aserciones, 0 fallos**.
  - `php tests/run.php --filter=ReporteConciliacionDbTest`: **5 tests, 14 aserciones, 0 fallos**.
  - Regresiones verdes: `ConciliacionLoteUnicoTest` (9/100), `ExtractosDuplicadosTest` (4/9), `ConciliacionCentralizadaTest` (6/45).
  - Smoke real sobre el lote activo: conciliados=1, disponibles=86, pendientes=2; PDF 7.161 bytes con `%PDF`/`%%EOF`; CSV 6.179 bytes con BOM.
  - Verificación independiente: PASS WITH FINDINGS; se corrigieron el doble conteo, el guard CSV, la validación de sidecars, la limpieza de archivos y la cobertura DB. Riesgos LOW aceptados: contenido PDF no se compara byte a byte más allá de encoding/EOF; fallo de `guardar()` no tiene test ejecutable de path de error.
- **T3 completado y verificado.** `index()` usa el lote activo por defecto, valida `?lote[]`, conserva el filtro de mes y pasa `loteActivo`/`loteExiste`/`loteEsActivo`/`reportesHistoricos`. `importarExtracto()` toma el snapshot del lote saliente antes de insertar, genera lote con sufijo aleatorio, persiste el reporte sólo si hubo filas nuevas/adoptadas, ejecuta el cruce automático sobre el lote activo resultante y conserva el lote anterior en importaciones no-op. Se agregaron descargas actual/histórica con `resolverArchivo`, `readfile` y cabeceras seguras; rutas GET protegidas; botones/modal/avisos en la vista.
  - `php tests/run.php --filter=ConciliacionReporteIntegracionTest`: **8 tests, 73 aserciones, 0 fallos**.
  - Regresiones verdes: FiltroMes (3/13), Simplificada (7/31), DetallePagoVista (3/19), MontoDispar (4/29), Centralizada (6/45), Rbac (9/262), T2 (13/94 + 5/14), T1 (9/100).
  - `check_purity`, `audit_security`, `php -l`: OK. El render de `index()` ya no crea `storage/backups/reportes_conciliacion`; sólo `guardar()` lo hace.
  - Verificación independiente: PASS WITH FINDINGS; se corrigieron el falso mensaje de fallo en no-op, `?lote[]`, la creación de carpetas en GET, el desfase de zona horaria del corte, el flake de referencia y las etiquetas de botones. Riesgos LOW aceptados: el path no-op y las descargas no tienen test HTTP real (se hará smoke en T4); `fecha_corte` es metadato del snapshot síncrono.
- T4 pendiente.

## Próximo paso
Ejecutar T4: verificación completa, smoke de importación real, Git (fetch/rebase/merge ff-only/push) y auditoría de `scratch/`.
