# ODD task: Respaldo y retención de datos acumulados

**Estado**: COMPLETADO (2026-10-17) — writer cerrado; pendiente la verificación independiente (V)
**Autorizado por**: usuario («Completo: respaldar y purgar»)
**Ruta**: ODD delegated direct — writer único + verificador independiente

## Objetivo

Hacer visible el apartado de Respaldos en los menús (admin/auditor) y convertirlo en el centro de control de la retención de las 3 tablas que hoy se acumulan sin limpieza (`log_auditoria`, `historial_estado_abono`, `rate_limits`): ver acumulación, exportar (respaldo dirigido con SHA-256) y purgar con respaldo previo obligatorio — manual desde el panel y programado en el servidor.

## Problema / por qué

- Auditoría 2026-10-10: las opciones 3 y 6 quedaron fuera del plan de limpieza → esas 3 tablas crecen sin límite y el sistema no las limpia.
- `/admin/respaldos` existe y funciona (historial + descarga con verificación SHA-256) pero NO tiene enlace en ningún menú: es inalcanzable sin escribir la URL. Lo mismo aplica a los sidebars del auditor.
- Producción (InfinityFree): sin cron; el disparo del respaldo quedó como pendiente del despliegue (fuera de alcance aquí; se registra como nota).

## Alcance

- En: ítem de menú en ambos roles; panel de acumulación + exportación + purga con respaldo previo; servicio compartido entre panel y CLI; migración (`purgas_log` + índice `idx_hist_creado`); script CLI; tarea programada `Condominio-Retencion`; tests; este documento.
- Fuera: motor de backup general (intacto); uploads/comprobantes; notificaciones y tokens (ya purgados por `cleanup_tokens.php`); despliegue InfinityFree.

## Decisiones de diseño (fijadas)

- Tablas y columnas de retención (constantes configurables en el servicio):
  - `log_auditoria` → `created_at` (365 d; usa el índice existente `idx_created_at`; NO usar `fecha_registro`, no es indexable por rango).
  - `historial_estado_abono` → `creado_en` (365 d; requiere índice nuevo `idx_hist_creado`: hoy haría full scan).
  - `rate_limits` → `window_start` (30 d; la ventana real máxima del rate limiter es 3600 s).
- La purga SIEMPRE va precedida de un export verificado (`.sql.gz` + SHA-256). Si el export falla o no se puede escribir/verificar, NO se borra nada. El corte temporal se calcula una sola vez por corrida y se usa para el SELECT y el DELETE. Se contrastan conteos (exportados vs eliminados): si difieren, `estado='parcial'` + nota.
- Formato de export: INSERTs SQL (mismo espíritu que el fallback PDO de `backup_database.php`), comprimido gzip, en `storage/backups/purgas/` con `.htaccess` que deniega acceso web (patrón de `storage/backups/`). Rotación de exportes a 366 días (configurable).
- `purgas_log` registra exportaciones y purgas: `tipo` (`exportacion`|`purga`), `tabla`, `fecha_corte`, `registros_exportados`, `registros_eliminados`, `archivo`, `tamano_bytes`, `hash_sha256`, `modo` (`manual`|`programado`), `admin_id` (FK→usuarios ON DELETE SET NULL), `estado` (`exitoso`|`parcial`|`fallido`), `notas`, `fecha_ejecucion`.
- Whitelist estricta: solo esas 3 tablas; cualquier otro valor es error (nada de identificadores dinámicos sin validar).
- Roles: ver el panel y descargar exportes → admin + auditor (patrón existente); exportar/purgar → SOLO admin (se añaden a las exclusiones documentadas de `RbacAuthorizationTest`, que cualquier otra ruta `/admin/*` permita AUDITOR).
- Rutas nuevas, una línea cada una, junto a las existentes (`public/index.php` ~259-262):
  - `POST /admin/respaldos/acumulados/exportar` (ADMIN)
  - `POST /admin/respaldos/acumulados/purgar` (ADMIN)
  - `GET /admin/respaldos/purgas/descargar/{id}` (ADMIN + AUDITOR)
- CSRF con `csrf_field()` (la validación global de POST ya existe); confirmación JS en la purga (operación destructiva).
- Estilo: comentarios y mensajes en español dentro de scripts/app (consistente con el repo); UI en español; patrón de guardas `tableExists()`/`INFORMATION_SCHEMA` como `cleanup_tokens.php`.
- Obtener el id del admin con el helper existente de `app/Core/Auth.php` (revisar usos previos de `admin_id`).

## Entrega (slices)

- Forecast de líneas autoradas: ~600–800 (excede 400 por tramo) → estrategia `ask-on-risk`.
- `chain_strategy`: **stacked-to-main** (elección del usuario, 2026-10-17) → cada slice se commitea directamente en `main` (commits locales, sin push). Sin rama feature.
- **Slice A — Núcleo de retención**: migración (T2) + servicio (T3) + CLI (T4) + tests núcleo (parte de T7). Autónomo y verificable por CLI; sin UI.
- **Slice B — Panel y automatización**: sidebars (T1) + controlador/rutas/vista (T5) + tarea programada (T6) + tests RBAC/UI (resto de T7) + este doc (commit `docs(odd)`).

## Tareas

- [x] T1 — Ítem «Respaldos» en `app/views/layouts/admin_sidebar.php` (las DOS listas: base y variante `pagos`) y `app/views/layouts/auditor_sidebar.php` (icono `backup`, label «Respaldos», route `respaldos`). Criterio: la página resalta activa en ambos roles. (`98d06fe`; `--filter=Retencion` → testSidebarsIncluyenItemRespaldos verde)
- [x] T2 — `scripts/migrate_retencion_datos_schema.php` (idempotente, estilo `migrate_backups_log_schema.php`): crea `purgas_log` (columnas e índices de arriba) + `ADD INDEX idx_hist_creado` a `historial_estado_abono` si no existe. (`32b2bea`; 3 corridas: crea → idempotente ×2; esquema verificado por INFORMATION_SCHEMA)
- [x] T3 — `app/Services/DataRetentionService.php` (estilo de `app/Services/`): const `RETENCION`; `estadisticas()` (filas, tamaño MB vía information_schema, registro más antiguo, elegibles según corte, fecha de corte por tabla); `exportar($tabla,$modo,$adminId)`; `purgar($tabla,$modo,$adminId)` (export→verificación→DELETE→registro; fallo previo al DELETE ⇒ `estado='fallido'` y cero borrados); `listarPurgas($limit)`; `limpiarExportesAntiguos($dias=366)`; serialización de filas como método puro testeable; whitelist. (`debad1b`; 16 tests iniciales verdes)
- [x] T4 — `scripts/cleanup_retencion.php`: recorre las 3 tablas; flags `--dry-run` y `--tabla=`; salida estilo `cleanup_tokens.php`; exit codes; usa el servicio; documenta la tarea programada en el docblock. (`1bbba21`; dry-run 0/0/9, `--tabla=rate_limits` E2E 9/9, `--tabla=usuarios` rechazada exit 1)
- [x] T5 — Extensión de `app/Controllers/RespaldoController.php` + `public/index.php` + `app/views/admin/respaldos/index.php`: card «Datos acumulados y retención» (por tabla: filas, tamaño, más antiguo, elegibles, corte; botones «Exportar» y «Respaldar y purgar» visibles solo para admin, con confirm + CSRF) + card «Purgas y exportaciones» (historial de `purgas_log` con descarga verificada SHA-256). (`49d9e02`; render CLI de la vista verificado en ambos roles, incluido el camino con elegibles)
- [x] T6 — Automatización: entrada `Condominio-Retencion` (diaria 02:45) en `scripts/setup_scheduled_tasks.ps1` + registro real con schtasks + `schtasks /Run` con resultado 0. (`a125f75`; /Query OK, /Run → Último resultado 0)
- [x] T7 — Tests: `tests/RetencionDatosTest.php` (serialización pura, whitelist, cálculo de cortes, rutas/sidebars/vista/controlador estáticos, stats con `try/catch → skip` si no hay BD) + actualización de `tests/RbacAuthorizationTest.php` (exclusiones admin-only para las 2 rutas POST; las GET siguen el contrato AUDITOR). `php -l` 10/10 OK. (`debad1b` + `49d9e02`; `--filter=RbacAuthorization` 248 asserts verdes, `--filter=Retencion` 73 asserts verdes)
- [x] T8 — Verificación funcional: `--dry-run` real de las 3 tablas (0/0/9); E2E real sobre `rate_limits` (214→205 filas; export→purga→archivo + .sha256 + fila en `purgas_log` con conteos 9/9; ids purgados 18,19,20,21,22,23,24,25,27); smoke HTTP (GET /admin/respaldos sin sesión → 302 a login, puerto 8123). (evidencia completa abajo)
- [ ] V — Verificación independiente (read-only): diff, re-ejecución de comandos clave, RBAC, y demostración del criterio «export fallido ⇒ nada borrado». (La demostración del criterio ya quedó adjunta abajo como evidencia del writer; V queda pendiente de ejecución por el verificador.)
- [x] D — Doc: checkboxes + evidencia (commits, resultados por comando, exportes generados). Espejo Engram lo sincroniza el orquestador.

## Criterios de aceptación

- Sin elegibles → 0 borrados y mensaje claro; export fallido → nada se borra (demostrable).
- `purgas_log` refleja conteos, archivo y SHA-256; la descarga de un export verifica integridad.
- Suites `--filter=RbacAuthorization` y `--filter=Retencion` verdes; `php -l` OK; suite general sin fallos NUEVOS (5 pre-existentes conocidos + `SecurityTest::testCsrfRejectsInvalidToken` aborta la suite).
- Tarea `Condominio-Retencion` creada y ejecutable («Último resultado 0»).

## Verificación (comandos)

- `php -l <archivos tocados>`
- `php scripts/migrate_retencion_datos_schema.php`
- `php scripts/cleanup_retencion.php --dry-run` y `php scripts/cleanup_retencion.php --tabla=rate_limits`
- `php tests/run.php --filter=RbacAuthorization` y `php tests/run.php --filter=Retencion`
- `php tests/run.php` (comparar contra baseline del mismo día)
- `schtasks /Query /TN "Condominio-Retencion"` y `schtasks /Run /TN "Condominio-Retencion"`
- Smoke: `php -S localhost:8000` + `GET /admin/respaldos` sin sesión → 302 a login.

## Registro de evidencia

### Commits (main local, sin push; orden de aplicación)

| # | Hash | Mensaje |
|---|------|---------|
| A1 | `32b2bea` | feat(db): migración de purgas_log e índice de historial de estados |
| A2 | `debad1b` | feat(retencion): servicio de exportación y purga con respaldo previo |
| A3 | `1bbba21` | feat(retencion): CLI cleanup_retencion con dry-run y por tabla |
| B1 | `98d06fe` | feat(ui): ítem Respaldos en sidebars de admin y auditor |
| B2 | `49d9e02` | feat(respaldos): panel de datos acumulados, exportación y purgas |
| B3 | `a125f75` | feat(retencion): tarea programada Condominio-Retencion |
| B4 | (este commit) | docs(odd): respaldo y retención de datos — evidencia final |

Estrategia: `stacked-to-main` (commits directos en `main`, sin rama feature ni push). Líneas autoradas por slice: A1 81, A2 831, A3 108, B1 3, B2 363 (+3 eliminadas), B3 13 (+9 eliminadas). Total ≈ 1.399 inserciones.

### Resultados exactos por comando (2026-10-17)

1. **`php -l`** — 10/10 archivos PHP tocados sin errores de sintaxis:
   `scripts/migrate_retencion_datos_schema.php`, `app/Services/DataRetentionService.php`, `tests/RetencionDatosTest.php`, `scripts/cleanup_retencion.php`, `app/views/layouts/admin_sidebar.php`, `app/views/layouts/auditor_sidebar.php`, `app/Controllers/RespaldoController.php`, `public/index.php`, `app/views/admin/respaldos/index.php`, `tests/RbacAuthorizationTest.php`.

2. **`php scripts/migrate_retencion_datos_schema.php`** — corrida 1: `✔ Tabla 'purgas_log' creada.` + `✔ Índice 'idx_hist_creado' agregado...` (exit 0); corridas 2 y 3: `ℹ La tabla 'purgas_log' ya existe.` + `ℹ El índice 'idx_hist_creado' ya existe.` (exit 0, idempotente). Esquema verificado: 14 columnas, índices `idx_purgas_fecha`, `idx_purgas_tabla(tabla, fecha_ejecucion)`, FK `admin_id → usuarios.id ON DELETE SET NULL`; índice `idx_hist_creado (creado_en)` visible.

3. **`php scripts/cleanup_retencion.php --dry-run`** (pre-purga, estado inicial): `log_auditoria` 374 filas / 0 elegibles; `historial_estado_abono` 128 / 0; `rate_limits` 214 / **9 elegibles** (corte 2026-09-17 13:06:43, retención 30d) → dry-run: «se exportarían y purgarían 9 registro(s)», exit 0.

4. **`php scripts/cleanup_retencion.php --tabla=rate_limits`** (E2E real): `✔ rate_limits: 9 registro(s) eliminado(s) tras exportar 9 (rate_limits_purga_2026-10-17_19-06-51.sql.gz, SHA-256 2ce5372a9dcf0566ba10b2d0c4209615981038aa7db401e80c77bcabe3d3b3d1)`, exit 0. Verificado después: `rate_limits` 214 → **205 filas, 0 elegibles**; archivo `storage/backups/purgas/rate_limits_purga_2026-10-17_19-06-51.sql.gz` (418 B) + `.sha256` coincidente con `hash_file`; gzdecode relee 9 INSERTs con ids `18,19,20,21,22,23,24,25,27`; fila `#2` en `purgas_log` (tipo=purga, modo=programado, admin_id=NULL, exp=9, elim=9, estado=exitoso). `.htaccess` de denegación presente en `storage/backups/purgas/`.
   Extra: `php scripts/cleanup_retencion.php --tabla=usuarios` → `❌ Tabla no permitida: 'usuarios'. Permitidas: log_auditoria, historial_estado_abono, rate_limits`, exit 1 (whitelist).

5. **Criterio «export fallido ⇒ nada borrado» (demostrado)**: con 2 filas sintéticas antiguas en `rate_limits` y el directorio de exportes bloqueado (archivo ocupando el nombre), `purgar('rate_limits')` devolvió `exito=false / estado=fallido / "Fallo el respaldo previo; no se eliminó nada: No se pudo crear el directorio de exportes"`; las 2 filas siguieron intactas (`sinteticas_despues=2`) y `purgas_log` quedó con fila `#3` estado=fallido. Restaurado el directorio, las 2 sintéticas se purgaron con el flujo normal (fila `#4`: exp=2, elim=2) y la tabla quedó en 0 elegibles.

6. **`php tests/run.php --filter=RbacAuthorization`** — 9 tests / **248 passed / 0 failed** (exit 0).
   **`php tests/run.php --filter=Retencion`** — 20 tests / **73 passed / 0 failed** (exit 0).

7. **`php tests/run.php` (completa)** — antes del aborto conocido en `SecurityTest::testCsrfRejectsInvalidToken` (preexistente, ver abajo): **619 ✅ / 0 ❌ / 0 ⚠ / 2 ⏭**; incluye los 20 tests de retención y los 9 de RBAC. Clases posteriores al corte, verificadas aparte: `--filter=Solicitudes` 22 tests / 144 passed / 0 failed; `--filter=UsuarioAdminTest` 10 / 36 / 0; `--filter=UsuariosSolicitudesTabs` 8 / 34 / 0.
   **Línea base medida** (worktree temporal en `44e5ce4`, commit previo a esta tarea): misma suite completa 596 ✅ / 0 ❌ relevantes, mismo aborto en `SecurityTest`, mismo `--filter=Usuario` abortando con render 403 y mismo error preexistente `UsuarioCrearRolTest::cambiarRolBloqueaDegradarAlUltimoAdminActivo` («There is already an active transaction»). Diferencia 596→619 (+23 ✅) = 20 tests nuevos + 3 ambiente (`.htaccess` y fixture de cartelera no versionados, presentes solo en el checkout principal). **Cero fallos nuevos.**
   Nota: los «5 fallos preexistentes» citados en el pedido ya no existen (fueron resueltos el 2026-10-13, ver `odd/tasks/fallos-merge-post-sync.md`); la línea base real de hoy es 0 ❌.

8. **`schtasks /Query /TN "Condominio-Retencion"`** — tarea visible, próxima ejecución 18/10/2026 02:45. **`schtasks /Run /TN "Condominio-Retencion"`** — «se ha intentado ejecutar» y tras la corrida: **Último resultado: 0** (0 elegibles post-purga → no-op limpio, sin archivos nuevos ni filas de bitácora).

9. **Smoke HTTP** — `php -S localhost:8123 -t public public/index.php` (puerto 8000 ocupado por otro dev server ajeno): `GET /admin/respaldos` sin sesión → `302 http://localhost:8123/auth/login`; `GET /admin/respaldos/purgas/descargar/1` sin sesión → `302 http://localhost:8123/auth/login`. Servidor detenido tras la prueba.

10. **Render de vista (CLI, ambos roles)**: con datos reales, rol admin mostró forms «Exportar»/«Respaldar y purgar» con `csrf_field()` y `return confirm(...)` cuando hay elegibles, y «Sin registros elegibles» cuando 0; rol auditor: «Solo lectura», sin botones, con descargas visibles. Sin warnings/errores PHP en el HTML generado.

### Exportes generados en `storage/backups/purgas/` (evidencia física)

- `rate_limits_exportacion_2026-10-17_19-05-58.sql.gz` (419 B) + `.sha256` — export manual (fila #1, admin_id=1).
- `rate_limits_purga_2026-10-17_19-06-51.sql.gz` (418 B) + `.sha256` — purga E2E de los 9 reales (fila #2).
- `rate_limits_purga_2026-10-17_19-07-21.sql.gz` (353 B) + `.sha256` — purga de las 2 sintéticas de la demo de fallo (fila #4).
- `purgas_log` final: 4 filas (#1 exportación, #2 y #4 purgas exitosas, #3 fallida de la demo del criterio).

## Notas

- MySQL local: al momento del diseño estaba apagado; se levanta XAMPP (`mysqld`) para la verificación. Si no conecta, las pruebas de BD van en skip y se reporta.
- RDD: activado (global); runtime OpenCode no elegible para revisión nativa → verificación funcional + verificador independiente (patrón del repo).
- Working tree con ruido ajeno (imágenes, `vendor/`, `.atl/`, zips): NO tocar ni commitear; commits solo con rutas exactas de esta tarea; sin push.
- Nota de deuda aparte (no de esta tarea): disparo de respaldo en producción (InfinityFree) pendiente.
- Espejo Engram: `odd/respaldo-y-retencion-datos/tasks` (lo sincroniza el orquestador; el writer actualiza solo el archivo).
