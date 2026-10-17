# ODD task: Respaldo único descargable con borrado tras descarga

**Estado**: IMPLEMENTADO — pendiente verificación independiente (2026-10-17)
**Autorizado por**: usuario («unifica todo a un solo respaldo… una vez descargado se borre del sistema en una semana para evitar acumulación» + decisión «Todo al ciclo único»)
**Ruta**: ODD delegated direct — writer único + verificador independiente

## Objetivo

Unificar el sistema de respaldos a UN solo respaldo vigente (todos los datos = dump completo de la BD), descargable con un botón, con ciclo único de limpieza anti-acumulación: los archivos descargados se eliminan del servidor a los 7 días; los exportes de purga que nunca se descargan, a los 30 días. El respaldo se regenera a diario y reemplaza al anterior: nunca hay más de un archivo de respaldo general.

## Problema / por qué

- El sistema actual acumula: rotación de 7 días de respaldos diarios (hasta 7 archivos), exportes de purga guardados 366 días y un panel con varias listas/historiales.
- Decisión del usuario (2026-10-17): un solo respaldo + botón de descarga + borrado una semana después de la descarga «para evitar acumulación»; los exportes de purga siguen el mismo ciclo («Todo al ciclo único»).

## Alcance

- En: un solo respaldo vigente (reemplazo en cada generación); botón único de descarga; `fecha_descarga` en `backups_log` y `purgas_log` (migración); reglas nuevas de limpieza (7 d post-descarga / 30 d sin descarga) para exportes de purga; backstop de descarga vencida para el respaldo general; panel simplificado (1 tarjeta de respaldo + botón); copys actualizados; tests; este documento.
- Fuera: motor de dump (mysqldump/fallback intactos); flujo de purga con respaldo previo (intacto — solo cambian SUS ventanas de limpieza); rutas nuevas (no hay ninguna); tareas programadas nuevas (no se agregan; el barrido diario va en la tarea existente `Condominio-Retencion`).

## Decisiones de diseño (fijadas)

- **Un solo archivo de respaldo general**: `backup_database.php` genera y, tras éxito (archivo + hash + registro), elimina TODOS los archivos `backup_*` anteriores (`.sql.gz` + `.sha256`). Nunca se borra el anterior antes de que el nuevo esté completo.
- **`fecha_descarga TIMESTAMP NULL`** en `backups_log` y `purgas_log`; se setea a `NOW()` al descargar (rutas `descargar` y `descargarPurga`, tras las validaciones y antes de transmitir).
- **Reglas de limpieza (ciclo único)**:
  - Exportes de purga (`purgas_log`): eliminar el archivo si `fecha_descarga` + 7 días < ahora; si nunca se descargó (NULL), eliminar cuando `fecha_ejecucion` + 30 días < ahora. Constantes configurables en el servicio. Reemplaza la retención de 366 días.
  - Respaldo general: el reemplazo diario lo cubre en el uso normal; backstop: si `fecha_descarga` + 7 días < ahora → eliminar el archivo (con nota en la fila). El barrido diario vive en `cleanup_retencion.php` (tarea `Condominio-Retencion`, 02:45); `cleanup_backups.php` se actualiza a las MISMAS reglas y queda standalone (tests).
  - Las filas de las bitácoras NUNCA se borran por estas reglas: quedan como historial con nota («archivo eliminado…»). Descargar un archivo ya eliminado falla de forma amable (patrón existente «ya no existe»).
  - Vencimientos calculados con `NOW()` de MySQL (consistente con el corte de retención; evita el desfase TZ conocido PHP/MySQL).
- **Panel (`/admin/respaldos`)**: reemplazar la tabla «Historial de Respaldos» por UNA tarjeta «Respaldo del sistema (vigente)» — fecha, tamaño, tablas, SHA-256 (truncado) — con UN botón «Descargar respaldo» (visible para admin y auditor; ambos ya podían descargar respaldos). Estado vacío si aún no hay ninguno. Banner y copys actualizados al ciclo nuevo (sin «rotación 7 días» ni «366 días»). Las tarjetas de retención y «Purgas y exportaciones» se conservan; su copy de ventanas se actualiza (7 d / 30 d).
- Sin rutas nuevas → `RbacAuthorizationTest` no requiere cambios (verificarlo).
- Estilo: español en scripts/UI; guardas y patrones existentes; CSRF intacto; sin push; commits directos a `main` (stacked-to-main ya elegido por el usuario en la sesión).

## Entrega (slices)

- Forecast de líneas autoradas: ~550–700 (excede 400) → estrategia `ask-on-risk`; `chain_strategy` **stacked-to-main** (ya elegido, se reutiliza).
- **Slice A — Núcleo del ciclo**: T1 migración + T2 servicio + T3 motor + T4 wiring (con sus tests).
- **Slice B — Panel y cierre**: T5 controlador/vista + T6 tests + T7 verificación funcional + doc.

Commits sugeridos (work units, Conventional Commits):
1. `feat(db): migración fecha_descarga en backups_log y purgas_log`
2. `feat(respaldo): ciclo único de limpieza (7/30) y barrido del respaldo general` [servicio + cleanup_retencion + tests]
3. `feat(respaldo): reemplazo del respaldo anterior en cada generación` [backup_database + cleanup_backups + BackupTest]
4. `feat(respaldo): panel de respaldo único con botón y marcado de descarga` [controller + vista + tests]
5. `docs(odd): respaldo único descargable — evidencia final`

## Tareas

- [x] T1 — `scripts/migrate_descarga_respaldo_schema.php` idempotente (guard `SHOW COLUMNS`): `ALTER TABLE backups_log ADD fecha_descarga TIMESTAMP NULL` + igual en `purgas_log`.
- [x] T2 — `app/Services/DataRetentionService.php`: reemplazar `limpiarExportesAntiguos(366)` por reglas 7/30 basadas en `fecha_descarga` + `fecha_ejecucion` (constantes configurables) + método de limpieza del respaldo general descargado-vencido (lee `backups_log`, borra archivo y anota). Actualizar tests del servicio.
- [x] T3 — Motor de respaldo: `scripts/backup_database.php` → política de reemplazo (borrar todos los `backup_*` anteriores tras éxito, `.sql.gz` + `.sha256`); `scripts/cleanup_backups.php` → mismas reglas nuevas, standalone; actualizar `tests/BackupTest.php` a la semántica nueva.
- [x] T4 — `scripts/cleanup_retencion.php`: wiring del barrido diario (exportes con reglas nuevas + backstop del respaldo general); salida estilo actual; `--dry-run` respeta ambas reglas.
- [x] T5 — Controlador y vista: `RespaldoController::index()` (solo el vigente, `LIMIT 1`), `descargar()` y `descargarPurga()` setean `fecha_descarga = NOW()` tras validar; tarjeta única con botón; copys; render verificado en admin y auditor.
- [x] T6 — Tests: nuevo `tests/RespaldoUnicoTest.php` (migración, reglas, estática de vista/controlador; DB con patrón skip) + ajustes a `RetencionDatosTest`/`BackupTest`. `php -l` a todo lo tocado.
- [x] T7 — Verificación funcional: migración ×2 (idempotencia); `backup_database.php` real → exactamente 1 par de archivos en `storage/backups/`; sintéticos para: exporte descargado vencido (7 d) → borrado, exporte nunca descargado (>30 d) → borrado, exporte reciente → intacto, respaldo general descargado vencido → borrado por backstop (dry-run primero; sintéticos limpiados al final); suites verdes; smoke 302; `schtasks /Run /TN "Condominio-Retencion"` → Último resultado 0.
- [ ] V — Verificación independiente (read-only) — patrón de la tarea anterior (diff, código, re-ejecución, evidencia).
- [x] D — Doc: checkboxes + evidencia (commits, comandos, archivos); espejo Engram lo sincroniza el orquestador.

## Criterios de aceptación

- El panel muestra SOLO el respaldo vigente con botón de descarga (sin tabla/historial de respaldos).
- Tras cada generación: exactamente 1 archivo de respaldo general (`.sql.gz` + `.sha256`) en `storage/backups/`.
- Descargar marca `fecha_descarga`; a los 7 días el archivo se elimina (demostrable); exportes nunca descargados se limpian a los 30 días.
- El flujo de purga con respaldo previo queda intacto (solo cambian las ventanas de limpieza).
- Suites `--filter=Backup`, `--filter=Retencion`, `--filter=RespaldoUnico` y `--filter=RbacAuthorization` verdes; `php -l` OK; suite general sin fallos nuevos; sin tareas programadas nuevas.

## Verificación (comandos)

- `php -l <archivos tocados>`
- `php scripts/migrate_descarga_respaldo_schema.php` (×2 → idempotencia)
- `php scripts/backup_database.php` (→ 1 solo par de archivos)
- `php scripts/cleanup_retencion.php --dry-run` y corrida real sobre sintéticos
- `php tests/run.php --filter=Backup` / `--filter=Retencion` / `--filter=RespaldoUnico` / `--filter=RbacAuthorization`
- `php tests/run.php` (completa; comparar contra baseline del día)
- `schtasks /Run /TN "Condominio-Retencion"` (Último resultado 0)
- Smoke: `php -S localhost:<puerto>` + `GET /admin/respaldos` sin sesión → 302 a login.

## Registro de evidencia

**Estado de implementación**: T1–T7 completadas y verificadas (2026-10-17, writer único). V (verificación independiente) pendiente.

**Commits (main, locales; sin push)**

| # | Commit | Mensaje |
|---|--------|---------|
| 1 | `abca162` | `feat(db): migración fecha_descarga en backups_log y purgas_log` |
| 2 | `249c955` | `feat(respaldo): ciclo único de limpieza (7/30) y barrido del respaldo general` |
| 3 | `727a4f1` | `feat(respaldo): reemplazo del respaldo anterior en cada generación` |
| 4 | `00a6383` | `feat(respaldo): panel de respaldo único con botón y marcado de descarga` |
| 5 | (este commit) | `docs(odd): respaldo único descargable — evidencia final` |

El commit 3 incluye además la eliminación de los 6 archivos `storage/backups/backup_2026-09-29_*` que quedaron rastreados en git (el directorio ya está en `.gitignore`; el reemplazo real los borró del disco y se destrackearon del índice).

**Archivos tocados**

- `scripts/migrate_descarga_respaldo_schema.php` (nuevo) — migración idempotente.
- `app/Services/DataRetentionService.php` — constantes `LIMPIEZA_DIAS_TRAS_DESCARGA=7` / `LIMPIEZA_DIAS_SIN_DESCARGAR=30`, helpers puros `vencidoTrasDescarga()`/`vencidoSinDescargar()`, `limpiarExportesPurgas()`, `limpiarRespaldoGeneralVencido()` (reemplaza `limpiarExportesAntiguos(366)`).
- `scripts/cleanup_retencion.php` — barrido diario cableado (dry-run propagado).
- `scripts/backup_database.php` — reemplazo del par anterior solo tras éxito completo; sin limpieza preventiva por antigüedad.
- `scripts/cleanup_backups.php` — standalone, mismas reglas (backstop 7 d, fila anotada, sin BD no borra).
- `app/Controllers/RespaldoController.php` — `index()` solo vigente (`LIMIT 1`); `descargar()`/`descargarPurga()` marcan `fecha_descarga = NOW()` tras validar y antes de transmitir; el mensaje amable de archivo ausente ahora es alcanzable (la comprobación de existencia va antes que la de contención de ruta).
- `app/views/admin/respaldos/index.php` — tarjeta única «Respaldo del sistema (vigente)» + botón «Descargar respaldo»; sin historial; copys 7/30.
- `tests/RespaldoUnicoTest.php` (nuevo, 12 tests), `tests/BackupTest.php` (reescrito), `tests/RetencionDatosTest.php` (+4 asserts).

**Comandos y resultados exactos**

1. `php -l` sobre los 10 archivos tocados → `No syntax errors detected` en todos.
2. `php scripts/migrate_descarga_respaldo_schema.php` ×2 → 1.ª corrida: `✔ Columna 'fecha_descarga' agregada a 'backups_log'.` + `✔ Columna 'fecha_descarga' agregada a 'purgas_log'.`; 2.ª: `ℹ 'backups_log.fecha_descarga' ya existe.` + `ℹ 'purgas_log.fecha_descarga' ya existe.` → idempotente.
3. `php scripts/backup_database.php` → `✔ Respaldo único vigente: 2 archivo(s) anterior(es) eliminado(s).`; `storage/backups/` queda con exactamente 1 par (`backup_2026-10-17_19-56-28.sql.gz` + `.sha256`) + `.htaccess`; fila nueva registrada en `backups_log` (id 11).
4. Sintéticos (marca `zzverify`, creados y eliminados al final): `purgas_log` ids 14 (descarga `NOW()-8d`), 15 (sin descarga `NOW()-31d`), 16 (reciente) + `backups_log` id 12 (`NOW()-8d`) con sus archivos.
   - `php scripts/cleanup_retencion.php --dry-run` → `ℹ [exporte] rate_limits_exportacion_zzverify_descargado8d.sql.gz → se eliminaría (descargado + 7 días).`, `ℹ [exporte] rate_limits_exportacion_zzverify_sin_descarga31d.sql.gz → se eliminaría (sin descargar + 30 días).`, `✔ Exportes de purga (7d tras descarga / 30d sin descarga): 2 archivo(s) por eliminar.`, `ℹ [respaldo] backup_zzverify_descargado8d.sql.gz → se eliminaría (descargado + 7 días).`, `✔ Respaldo general descargado-vencido (7d): 1 archivo(s) por eliminar.` — sin borrar nada (verificado en disco).
   - `php scripts/cleanup_retencion.php` → los 3 archivos vencidos eliminados; el reciente intacto; filas anotadas `Archivo eliminado tras descarga (ciclo único: 7 días).` / `Archivo eliminado sin descarga (ciclo único: 30 días).`; sintéticos (filas + archivos) eliminados después; `storage/backups` final = 1 par real.
5. `php tests/run.php --filter=Backup` → `4 tests | ✅ 13 passed | ❌ 0 failed`; `--filter=Retencion` → `20 tests | ✅ 77 passed | ❌ 0 failed`; `--filter=RespaldoUnico` → `12 tests | ✅ 59 passed | ❌ 0 failed`; `--filter=RbacAuthorization` → `9 tests | ✅ 248 passed | ❌ 0 failed` (sin cambios de rutas; contrato RBAC intacto).
6. `php tests/run.php` (completa) → aborta en `SecurityTest::testCsrfRejectsInvalidToken` (fallo ambiental conocido) tras 69 clases sin ningún `❌`/EXCEPTION (0 fallos nuevos; 2 skips de permisos de directorio, ambientales). Mismo comportamiento que el baseline del día.
7. `schtasks /Run /TN "Condominio-Retencion"` → `CORRECTO: se ha intentado ejecutar...`; `/Query`: `Último tiempo de ejecución: 17/10/2026 01:58:10 p.m.`, `Último resultado: 0`.
8. Smoke: `php -S localhost:8098 -t public` → `GET /admin/respaldos` = `302 http://localhost:8098/auth/login`; `GET /admin/respaldos/descargar/8` = `302 http://localhost:8098/auth/login`; servidor detenido (post-kill `http_code: 000`).
9. Render CLI del panel en ambos roles (patrón `AuditorDashboardTest`): tarjeta vigente + botón + copys 7/30, sin historial, sin «366», sin excepciones (admin 39.584 bytes; auditor 30.722 bytes).
10. Descarga real vía harness CLI: `descargar(11)` y `descargarPurga(1)` marcaron `fecha_descarga = 2026-10-17 13:59:01` (NOW() de MySQL, TZ America/Caracas — no la de PHP); artefactos de verificación reseteados a NULL después. Descarga de archivo ya eliminado (id 8, reemplazado) → mensaje amable: `El archivo físico de respaldo ya no existe en el servidor (purgado por política de retención).`

**Decisiones no obvias / gotchas**

- El guard `!$resolvedPath` (realpath de un archivo ausente devuelve `false`) hacía inalcanzable el mensaje amable de «ya no existe»; se reordenó existencia → contención de ruta (la contención sigue vigente como defensa en profundidad).
- El runner de tests convierte en excepción incluso los warnings suprimidos con `@`: todo `unlink` de acompañantes `.sha256` se guarda con `is_file()` (servicio, script standalone y tests).
- `backups_log`/`purgas_log` conservan todas las filas; la limpieza solo toca archivos y anota (`notas`) — descargar una fila cuyo archivo ya no existe falla de forma amable.

**Forecast de líneas autoradas**: 834 adiciones + 135 eliminaciones ≈ 969 líneas cambiadas (excede 400; `chain_strategy` stacked-to-main ya elegido; 4 commits de trabajo + 1 de docs).

### Corrección post-verificación (F1/F2)

La verificación independiente de `a4f3350..65dd91e` devolvió «VERIFIED CON OBSERVACIONES» con dos hallazgos ALTOS; corrección acotada en un solo commit (F3–F9 quedan como notas, sin tocar):

- **F1 — SHA-256 muerto en la descarga del respaldo general** (`app/Controllers/RespaldoController.php`): `descargar()` verificaba solo `checksum_sha256`, columna NULL en las filas reales (`backup_database.php` inserta `hash_sha256`), así que la comprobación de integridad nunca se ejecutaba. **Fix**: resolver `$hashEsperado` con `checksum_sha256` como alias legado (solo si no es NULL) y `hash_sha256` como hash real; usarlo en la comparación y en el header `X-Checksum-SHA256`. El orden se mantiene: existencia → contención → integridad → marca `fecha_descarga = NOW()` → transmisión; el mensaje de alerta de integridad queda intacto. `descargarPurga()` no cambió (ya usaba `hash_sha256`).
- **F2 — El reemplazo no validaba el nuevo archivo** (`scripts/backup_database.php`): el borrado de los `backup_*` anteriores podía ejecutarse con un archivo nuevo incompleto. **Fix**: guardas ANTES del bloque de borrado — `file_put_contents` del `.sql.gz` distinto de `false`, archivo existente con tamaño > 0 y `hash_file('sha256', ...)` distinto de `false`/`''`. Ante fallo, `fallarRespaldo()` registra `[ERROR]` en `storage/logs/backups.log`, imprime el error y `exit(1)` sin borrar el respaldo anterior.

**Comandos y resultados de la corrección**

- `php -l app/Controllers/RespaldoController.php` y `php -l scripts/backup_database.php` (más los tests tocados) → `No syntax errors detected`.
- `php tests/run.php --filter=Backup` → `4 tests | ✅ 22 passed | ❌ 0 failed | ⚠ 0 errors | ⏭ 0 skipped`.
- `php tests/run.php --filter=RespaldoUnico` → `13 tests | ✅ 70 passed | ❌ 0 failed | ⚠ 0 errors | ⏭ 0 skipped`.
- `php tests/run.php --filter=RbacAuthorization` → `9 tests | ✅ 248 passed | ❌ 0 failed | ⚠ 0 errors | ⏭ 0 skipped`.
- F1 empírico (harness CLI, fila id 31 del respaldo vigente): con `hash_sha256` corrupto en BD → `¡ALERTA DE INTEGRIDAD! El archivo de respaldo ha sido modificado. El checksum SHA-256 no coincide.` y `fecha_descarga` permanece NULL; con el hash real → transmite y marca `fecha_descarga = 2026-10-17 14:16:27` (NOW() de MySQL). Hash restaurado y `fecha_descarga` reseteado a NULL tras la prueba.
- F2 empírico: ruta del nuevo `.sql.gz` colisionada con un directorio → `❌ ERROR EN GENERACIÓN DE RESPALDO: No se pudo escribir el archivo de respaldo: backup_2026-10-17_20-16-55.sql.gz`, `exit=1`, línea `[2026-10-17 20:16:55] [ERROR] No se pudo escribir el archivo de respaldo: backup_2026-10-17_20-16-55.sql.gz` en `storage/logs/backups.log` y el par anterior (`backup_2026-10-17_20-16-43.sql.gz` + `.sha256`) intacto; directorios de colisión eliminados después (estado final: 1 par vigente).
- Asserts nuevos (estáticos, con orden verificado): `tests/RespaldoUnicoTest.php::testDescargarResuelveElHashDesdeHashSha256` (hash real desde `hash_sha256`, alias legado, orden resolución→comparación→marca→transmisión, header y alerta intactos) y guardas en `tests/BackupTest.php::testBackupDatabaseReemplazaSoloTrasRegistrarElNuevo` (escritura/tamaño/firma SHA-256 antes del bloque de borrado).

## Notas

- Contexto: continúa `odd/tasks/respaldo-y-retencion-datos.md` (cierre VERIFIED con observaciones, 2026-10-17). Cambia su ventana de 366 días → 7/30 y simplifica la presentación a UN respaldo único.
- MySQL local levantado (XAMPP). TZ PHP (Europe/Berlin) vs MySQL (America/Caracas): los vencimientos nuevos usan `NOW()` de MySQL.
- Working tree con ruido ajeno (imágenes, `vendor/`, `.atl/`, zips): NO tocar; commits solo con rutas exactas; sin push.
- RDD: activado (global); runtime sin revisión nativa (assess unassessable/high) → verificación funcional + verificador independiente.
- Espejo Engram: `odd/respaldo-unico-descarga/tasks`.
