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
- [ ] **T1** — Rama `chore/eliminar-comunicados` (desde HEAD) + backup de BD con `php scripts/backup_database.php`.
- [ ] **T2** — Borrar código/UI (5 archivos) y quitar las 4 rutas + 2 menús del sidebar.
- [ ] **T3** — Ajustar tests: quitar 3 métodos de `BehaviorTest` y la entrada de `RbacAuthorizationTest`.
- [ ] **T4** — BD: extender `scripts/migrate_purgar_tablas_huerfanas.php` con `comunicados` (motivo) y ejecutarlo; actualizar `database/condominio_cobranzas.sql` (quitar sección de la tabla).
- [ ] **T5** — Verificación: lint, tests por clase, smoke (404 en `/admin/comunicados`), purity/security, limpieza de referencias residuales.

## Chequeos aplicables
- `php tests/run.php --filter=BehaviorTest` — línea base antes: 110 tests/250 asserts/1F/1E/1S (htaccess preexistente); tras quitar 3 tests bajan tests/asserts, los fallos preexistentes deben mantenerse iguales.
- `php tests/run.php --filter=RbacAuthorizationTest` y `--filter=RouterTest` — verdes.
- `php scripts/check_purity.php`, `php scripts/audit_security.php` — 0.
- Smoke: servidor `php -S` → `GET /admin/comunicados` = 404; `GET /auth/login` = 200.
- BD: `SHOW TABLES` sin `comunicados`; `conciliacion_lotes` intacta; purga re-ejecutable (idempotente).

## Entrega
- Rama única `chore/eliminar-comunicados`. Commits por unidad: (1) código+tests, (2) migración de purga+dump.

## Progreso
- Análisis completo y alcance confirmado por el usuario. Ejecución en curso.
