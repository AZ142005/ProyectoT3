# Eliminar apartado de Comunicados (y soltar la tabla `comunicados`)

## Objetivo
Quitar por completo el módulo de Comunicados/Cartelera (código, UI, rutas, menú, tests) y eliminar la tabla `comunicados` de la base de datos y del dump.

## Decisión del usuario (29-09)
- Alcance elegido: **apartado completo + soltar solo `comunicados`**. `conciliacion_lotes` NO se toca (queda anotada para una revisión futura).
- Advertencia registrada: el proyecto mapea Comunicados → RF 37 en `scripts/migrate_fase2.php`; el usuario decidió eliminarlo igual (los Requisitos.docx no mencionan "comunicados").

## Evidencia previa (análisis 29-09)
- `comunicados`: 0 filas; sin FKs entrantes; sin notificaciones con enlace a la cartelera (0); `notificaciones_cola` no tiene `comunicado_id` en la BD viva.
- Ocupación completa: `ComunicadoController.php`, `ComunicadosModel.php`, `views/admin/comunicados/`, `views/residente/cartelera.php`, `views/emails/comunicado.php`, 4 rutas en `public/index.php`, 2 entradas en `admin_sidebar.php`, 3 tests en `BehaviorTest`, 1 entrada en `RbacAuthorizationTest`.
- `conciliacion_lotes`: 0 filas, 0 referencias en código — en espera por decisión del usuario.

## Tareas
- [x] **T1** — Rama `chore/eliminar-comunicados` (desde HEAD) + backup de BD con `php scripts/backup_database.php` → `storage/backups/backup_2026-09-29_22-20-59.sql.gz` (+sha256). NOTA: el registro en `backups_log` falló por bug de esquema **preexistente** (el script inserta `nombre_archivo/tamano_bytes/hash_sha256/...` y la tabla tiene `archivo/tamano/checksum/admin_id/created_at`); el archivo de respaldo sí quedó.
- [x] **T2** — Borrados: `ComunicadoController.php`, `ComunicadosModel.php`, `views/admin/comunicados/`, `views/residente/cartelera.php`, `views/emails/comunicado.php`; 4 rutas eliminadas en `public/index.php` (+ comentario de sección ahora "Módulo de Notificaciones (RF 35, RF 36)"); 2 entradas del sidebar eliminadas.
- [x] **T3** — `BehaviorTest`: 3 métodos eliminados (secciones 33/41/42). `RbacAuthorizationTest`: entrada de ComunicadoController eliminada.
- [x] **T4** — `scripts/migrate_purgar_tablas_huerfanas.php` extendido con `comunicados`; ejecutado (0 registros) e idempotente (2.ª corrida "no existe"); dump actualizado sin la sección de `comunicados`.
- [x] **T5** — Verificado: lint OK; referencias residuales 0 (app/public/tests); BehaviorTest 107 tests/244 asserts/1F/1E (fallos htaccess preexistentes idénticos); Rbac 7/144 ✅; Router 23/45 ✅; purity/security 0; smoke `/admin/comunicados` 404, `/residente/cartelera` 404, `/auth/login` 200; BD 25 tablas con `comunicados` ausente y `conciliacion_lotes` intacta.

## Resultado
- Commits: `c885598` (código+tests+doc) y `4d4c936` (purga+dump). Ningún dato perdido (la tabla tenía 0 filas).
- Pendientes anotados: `conciliacion_lotes` (no se tocó por decisión del usuario); bug preexistente de columnas en `backups_log`.

## Chequeos aplicables
- `php tests/run.php --filter=BehaviorTest` — línea base antes: 110 tests/250 asserts/1F/1E/1S (htaccess preexistente); tras quitar 3 tests bajan tests/asserts, los fallos preexistentes deben mantenerse iguales.
- `php tests/run.php --filter=RbacAuthorizationTest` y `--filter=RouterTest` — verdes.
- `php scripts/check_purity.php`, `php scripts/audit_security.php` — 0.
- Smoke: servidor `php -S` → `GET /admin/comunicados` = 404; `GET /auth/login` = 200.
- BD: `SHOW TABLES` sin `comunicados`; `conciliacion_lotes` intacta; purga re-ejecutable (idempotente).

## Entrega
- Rama única `chore/eliminar-comunicados`. Commits por unidad: (1) código+tests, (2) migración de purga+dump.

## Progreso
- Análisis completo y alcance confirmado por el usuario. **Ejecución completada y verificada** (ver Resultado arriba).
