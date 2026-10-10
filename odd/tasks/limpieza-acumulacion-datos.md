# ODD task: Limpieza de acumulación de datos — opciones 1, 2, 4 y 5

**Estado**: COMPLETADO (2026-10-11)
**Autorizado por**: usuario («haz las opciones excepto 3 y 6»)
**Ruta**: ODD delegated direct — writer scripts + writer flujo de pagos + verificador independiente

## Objetivo

Reducir y prevenir la acumulación de datos: purga de la cola de notificaciones, rotación de logs, limpieza de comprobantes huérfanos, blindaje del flujo de uploads y programación de las rutinas existentes en Windows Task Scheduler.

## Problema / por qué

Auditoría 2026-10-10: `notificaciones_cola` con 218 filas (196 fallidas) y ~1.5 MB sin purga alguna; logs append sin rotación (`mail_mock.log`, `storage/logs`); uploads con huérfanos posibles cuando falla el registro del pago; y ningún script de limpieza programado (los ejemplos cron son plantillas Linux). Backups de 7 días retenidos desde el 29-sep por falta de ejecución.

## Alcance

- En: opciones 1, 2, 4 y 5 del informe de auditoría.
- Fuera: opción 3 (podar `rate_limits`), opción 6 (retención de `log_auditoria`/`historial_estado_abono`), opción 7 (archivado a escala — no aplica al volumen actual).

## Restricciones

- Sin cambios de esquema en la BD. Solo: scripts, 2 controladores (fix de flujo), tests y tareas programadas.
- Commits locales en `main`, sin push (patrón del repo). Conventional commits.
- Respetar estilo existente (comentarios y mensajes en español dentro de los scripts).
- El fix en controladores de pago NO altera rutas de éxito; solo elimina el archivo subido cuando el registro del pago falla definitivamente.

## Tareas

- [x] T1 — Purga de `notificaciones_cola` (estado `enviado`/`fallido` con antigüedad > 30 días) en `scripts/cleanup_tokens.php` + docblock. (implementado y probado; incluye fix del error fatal pre-existente `rowCount()` sobre la conexión PDO)
- [x] T2 — Rotación de logs append (`mail_mock.log`, `storage/logs/backups.log`, `storage/logs/cron_backup.log` > 2 MB → `.1`) en `scripts/cleanup_logs.php`. (implementado; rotación probada empíricamente con archivo de 2.2 MB)
- [x] T3 — Nuevo `scripts/cleanup_uploads.php`: borra huérfanos (sin referencia en `pagos.archivo` / `comprobantes_pago.archivo` / `gastos_comunes.soporte_digital`, con más de 7 días) en `uploads/comprobantes`; `uploads/soportes` solo se reporta. (implementado; dry-run verificado: 1 candidato `4885cce1….jpeg`, nada eliminado)
- [x] T4 — Fix de flujo: `PagoController` y `PagoDirectoController` eliminan el archivo subido si el registro del pago falla. (implementado en `434f27e`; test estático `PagoUploadHuerfanoTest`; `--filter=Pago`: 35 tests / 192 aserciones, 0 fallos)
- [x] T5 — Tareas reales creadas y probadas: `Condominio-Backup` (23:30), `Condominio-Tokens` (02:00), `Condominio-Logs` (02:15), `Condominio-Uploads` (02:30). (`847effe` + `b5df72f` + `9c5eaa8`; Último resultado 0 en las 4; `StartWhenAvailable=True`; `cron_backup.ps1` corregido con BOM UTF-8)
- [x] T6 — Saneamiento inicial: backup fresco → purga de las 196 fallidas actuales → ejecución de limpiezas → aplicación de uploads. (cola 218 → 22 filas; huérfano `4885cce1….jpeg` eliminado; dry-run posterior: 0 candidatos)
- [x] V — Verificación independiente (diff + pruebas + comandos). (sin blockers; F1 corregido; F2–F6 documentados como notas asumidas)

## Criterios de aceptación

- `php -l` correcto en todos los archivos tocados; scripts ejecutan sin error.
- Cola sin fallidas ni registros > 30 días tras el saneamiento.
- Huérfanos de `uploads/comprobantes` eliminados (dry-run revisado antes de aplicar).
- Tareas visibles en `schtasks` y ejecutables.
- Suite relevante sin fallas nuevas (falla conocida pre-existente: `SecurityTest::testCsrfRejectsInvalidToken` aborta la suite completa).

## Verificación (comandos)

- `php -l <archivos>` / `php scripts/cleanup_tokens.php` / `php scripts/cleanup_logs.php` / `php scripts/cleanup_uploads.php --dry-run`
- `php tests/run.php` (subconjunto; ver falla conocida)
- `schtasks /Query /TN "Condominio-*"`

## Registro de evidencia

- Commits (main local, sin push): `34798e5` (T1), `cf2546c` (T2), `0c14be9` (T3), `434f27e` (T4), `847effe` (T5 script), `b5df72f` + `9c5eaa8` (settings de tareas), y el commit de este documento.
- Limpiezas (2026-10-11 00:55): `cleanup_tokens.php` → 5 tablas / 0 registros (incluye el bloque nuevo de cola); `cleanup_logs.php` → 0 purgados / 0 rotados; `cleanup_uploads.php` → 1 huérfano eliminado (`4885cce1….jpeg`); dry-run posterior → 0 candidatos.
- Purga de saneamiento: `notificaciones_cola` 218 → 22 filas (196 fallidas eliminadas; quedan solo pendientes).
- Backup: `backup_2026-10-11_00-36-26.sql.gz` (~640 KB) + fila en `backups_log`. Los 3 respaldos del 29-sep conservan mtime del 10-oct y se purgarán al superar 7 días.
- Tareas: probadas con `schtasks /Run` → Último resultado 0 en las 4; comando almacenado con comillas correctas; `StartWhenAvailable=True`.
- Verificación independiente: sin blockers; `--filter=Behavior` 110/254, `--filter=Pago` 35/192, `--filter=Backup` 3/6 — 0 fallos.

## Notas

- RDD: activado (global), pero runtime OpenCode no elegible (assess `unassessable/high`, sin `next_transition`) → revisión nativa no disponible; se aplica verificación funcional + verificador independiente.
- TDD: off (sin configuración explícita); pruebas del repo + checks funcionales.
- Espejo Engram: `odd/limpieza-acumulacion-datos/tasks` — PENDIENTE de subida: Engram rechazó los 4 intentos de guardado con «could not confirm Engram session registration» (problema de registro de sesión del servidor, no del contenido). Reintentar en otra sesión.
