# ODD task: Compresión y retención de comprobantes + filtro de conciliación

**Estado**: COMPLETADO (2026-10-11)
**Autorizado por**: usuario («aplica la compresion al guardar el comprobante», «que los comprobantes se borren automaticamente pasados seis meses», «en conciliacion que solo se muestren los pagos realizados este mes»)
**Ruta**: ODD delegated direct — writer A (compresión + retención), writer B (conciliación), verificador independiente

## Objetivo

1. Comprimir las imágenes de comprobantes al guardarlas, sin dejar residuos.
2. Borrar automáticamente los archivos de comprobantes referenciados con más de 6 meses (conservando la fila y el `archivo_hash` en BD como evidencia).
3. En conciliación, mostrar solo los pagos (lista de pendientes) del mes actual.
4. (Análisis) proyección anual de `log_auditoria` e `historial_estado_abono` — se entrega en el informe final, no requiere código.

## Restricciones

- Compresión en servidor con GD (recién habilitado en `C:\xampp\php\php.ini`, backup `php.ini.bak-20261011-gentle`). Fallback silencioso si GD no está: los uploads nunca se rompen. PDFs no se comprimen. El hash SHA-256 se calcula DESPUÉS de comprimir (debe coincidir con el archivo guardado).
- La retención de 6 meses NO borra filas de BD (evidencia = `archivo_hash`); solo archivos de `uploads/comprobantes`. Huérfanos siguen a 7 días. `uploads/soportes` sin cambios (solo reporte).
- Conciliación: filtrar SOLO la lista de pagos pendientes (`$pagosPendientes` y las filas «sin_extracto»); NO tocar cruces, extractos ni lotes.
- Commits locales en `main`, sin push. Estilo español en scripts.
- El server de desarrollo (`php -S localhost:8000`, PID 10036) requiere reinicio para tomar GD — se avisa al usuario.

## Tareas

- [x] T1 — Compresión de imágenes en `app/services/FileUploader.php` (máx. 1600 px, JPEG q82, orientación EXIF, PNG→JPEG; hash tras comprimir; guarda anti-bomba) + tests + verificación de residuos. (`e39fd8d` + fix `5dbe092`; 7 tests / 61 aserciones; sin residuos `*.conv`/`*.bak`)
- [x] T2 — Retención de 6 meses para comprobantes referenciados en `scripts/cleanup_uploads.php` + tests. (`31d236f`; dry-run OK; conserva fila + `archivo_hash`)
- [x] T3 — Filtro de mes actual en `ConciliacionController::index()` (+ vista y tests). (`36997a4`; test funcional real; 46/360 verde)
- [x] V — Verificación independiente (diff + pruebas + comandos). (sin blockers; W1 corregido; notas menores asumidas)

## Verificación (comandos)

- `php -l` en todos los archivos tocados.
- `php tests/run.php --filter=FileUploader` / `--filter=Conciliacion` / `--filter=Behavior` / `--filter=Backup`.
- `php scripts/cleanup_uploads.php --dry-run` (sin eliminar).
- Residuos: buscar `*.conv` y temporales en los directorios de prueba y en `uploads/`.

## Registro de evidencia

- Commits (main local, sin push): `e39fd8d` (T1 compresión), `31d236f` (T2 retención), `36997a4` (T3 conciliación), `5dbe092` (fix W1), + commit de este documento.
- Tests: FileUploader 7/61 (×2 estable), Conciliación 46/360, Behavior 112/269, Backup 3/6 — 0 fallos.
- Residuos: sin `*.conv` ni `*.bak` en el repo; uploads sin cambios (comprobantes 0, soportes 2).
- Dry-run `cleanup_uploads`: 0 huérfanos + 0 por retención (sin eliminar).
- Verificación independiente: sin blockers; W1 (pérdida del original si fallaba el rename final) corregido con respaldo `.bak` transitorio; notas: dedup transitorio por hash (dup_guard sigue primario), test de bomba no discrimina la guarda (cubierta estáticamente), retención sin test funcional local (sin candidatos), espejos EXIF 2/4/5/7 omitidos.
- GD: `extension=gd` habilitado en C:\xampp\php\php.ini (backup `php.ini.bak-20261011-gentle`); CLI verificado. El server `php -S localhost:8000` requiere reinicio para tomar GD.

## Notas

- GD habilitado en `C:\xampp\php\php.ini`; CLI ya lo ve. El server :8000 necesita reinicio (sin él, los uploads siguen funcionando sin comprimir — fallback silencioso).
- TZ: el PHP local corre en `Europe/Berlin` (php.ini) mientras MySQL/app usan `America/Caracas` — el filtro T3 se pineó explícitamente a Caracas; inconsistencia pre-existente anotada para revisión aparte.
- Proyección anual (informe final): `log_auditoria` ~0.9–6.7 MB/año según escenario; `historial_estado_abono` ~0.14–2.2 MB/año.
- Espejo Engram: `odd/compresion-retencion-comprobantes/tasks` — PENDIENTE (Engram rechazó los guardados por registro de sesión durante toda la sesión).
