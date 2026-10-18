# Despliegue InfinityFree (plan gratuito) — correcciones de compatibilidad

Fecha: 2026-10-13
Origen: auditoría de compatibilidad con InfinityFree (plan gratuito) y elección del usuario de la opción B para el punto 1 (autoload case-sensitive) tras la re-auditoría post-merge.
Ruta: directa mecánica (renames `git mv` + sustitución case-sensitive de rutas; sin decisiones de diseño). El trigger de escritor no aplica: edición mecánica ya entendida (la misma sustitución repetida).
TDD: off — runner custom `php tests/run.php`.

## Objetivo

Dejar el proyecto desplegable en InfinityFree (plan gratuito), corrigiendo los 8 puntos de la auditoría; el bloqueante T1 (PSR-4 vs carpetas en minúscula) se resuelve en esta tarea.

## Contexto verificado

- InfinityFree = Linux case-sensitive; docroot fijo en `htdocs`; sin cron/SSH; `mail()` capado; 10 MB máx por archivo.
- PSR-4: `App\` → `app/`; carpetas `core|controllers|models|services` en minúscula → en Linux fatal al primer request (en Windows resuelve por insensibilidad del filesystem).
- Referencias afectadas: 12 archivos en `scripts/`, 31 en `tests/`, `composer.json` (entry `files`). El autoloader de `vendor/` SÍ está versionado en este repo; `autoload_files.php` se regenera con `dump-autoload` (no se edita a mano).
- Línea base de tests ANTES del cambio (HEAD `1242140`): suite completa 588 ✅ / 5 ❌ (aborto conocido en SecurityTest); `--filter=SolicitudesCambioDatos` 49 passed / 3 errors; `--filter=UsuariosSolicitudesTabs` 34 passed / 0 errores. Fallos preexistentes del merge reciente, ya anotados por el equipo en `odd/tasks/envio-real-smtp.md`.

## Checklist

- [x] T1 Autoload PSR-4 case-safe (cerrada 2026-10-13, commit `9a654ba`).
- [x] T2 Layout de subida — documentado en `docs/despliegue-infinityfree.md`; **corregido 2026-10-13**: InfinityFree prohíbe archivos fuera de htdocs (open_basedir + borrado automático); layout definitivo = todo dentro de htdocs + `.htaccess` rewrite hacia `/public/` (receta oficial). Ajuste de código: en `index.php` el prefijo del script se descuenta solo si está presente en la URI.
- [x] T3 Proxy seguro de soportes — `public/soporte-proxy.php` (sesión obligatoria, basename, allowlist, MIME real) + URLs actualizadas en `residente/gastos.php` y `admin/gastos/index.php`; `BehaviorTest` #26 migrado al proxy.
- [x] T4 Resuelto por diseño: `public/uploads` es local e ignorado por git (no se sube); la protección legacy quedó reemplazada por el proxy de T3.
- [ ] T5 `.env` de producción (DB `sqlXXX`, claves nuevas, `MAIL_MODE=smtp`) + activar SSL antes de `ENVIRONMENT=production`.
- [x] T6 Web-cron de correos — `public/cron-notificaciones.php` (token `CRON_TOKEN`, hash_equals, limit 20) + worker compartido `app/Services/NotificationQueueWorker.php` para CLI y web; el disparo del respaldo queda como subitem opcional (la generación manual web está deshabilitada por diseño).
- [x] T7 Exclusiones de subida definidas y auditadas — reglas en la guía (§3) + tabla de impacto verificada (`docs/despliegue-infinityfree.md`); la ejecución física ocurre en el despliegue.
- [ ] T8 Verificación post-subida: import dump por phpMyAdmin, upload real de comprobante, login HTTPS, correo de prueba.

## Aceptación (T1)

- Cero referencias en minúscula a las 4 carpetas en `app/`, `public/`, `scripts/`, `tests/` y `composer.json`. ✔
- Chequeo clase→archivo case-exacto para todas las clases con namespace de `app/`. ✔ (70 OK / 0 problemas)
- `php -l` sin errores nuevos; suite con resultados idénticos a la línea base. ✔ (1 fallo de parseo ajeno a este cambio, ver hallazgo)
- `--filter=SolicitudesCambioDatos` y `--filter=UsuariosSolicitudesTabs` idénticos a línea base. ✔

## Decisiones

- Opción B (renombrar carpetas) elegida por el usuario sobre `dump-autoload -o`: elimina la clase de problema; el classmap habría exigido regenerar ante cada clase nueva.
- El autoloader de `vendor/` está versionado: el commit incluye `autoload_files.php` y `autoload_static.php` regenerados (apuntan a `app/Core/helpers.php`). Si se agregan clases nuevas, correr `php composer.phar dump-autoload` (solo impacta a Linux; en Windows resuelve por insensibilidad).
- T2–T8 quedan pendientes de autorización del usuario; no se tocan en esta tarea.

## Evidencia

Cierre T1 (2026-10-13) — rama `deploy/infinityfree-fixes`, commit `9a654ba` (sin push). `main` intacto en `1242140`.

- Renombres: 71 archivos movidos con `git mv` en dos pasos (core→Core, controllers→Controllers, models→Models, services→Services); git los detecta como renames 100% puros; contenido byte-idéntico verificado por hash (`git hash-object` vs `HEAD`).
- Referencias: 43 archivos actualizados (12 `scripts/`, 31 `tests/`) + `composer.json`; 188 líneas, solo case. `grep` case-sensitive de rutas viejas = 0 en `app/`, `public/`, `scripts/`, `tests/`, `composer.json` y `vendor/composer/`.
- Autoloader: `dump-autoload` regeneró solo `autoload_files.php` y `autoload_static.php` (el resto quedó byte-idéntico); ambos apuntan a `app/Core/helpers.php`.
- Verificación estática: PSR-4 case-check 70 clases OK / 0 problemas / 0 mismatch nombre-vs-clase; smoke de autoload por `class_exists` sobre las clases de `app/`: 70/70 OK (tras corregir el fallo de sintaxis, ver hallazgo); `php -l`: 116 archivos sin errores tras el fix.
- Suites: completa 588 ✅ / 5 ❌ idéntica a la base (mismos 5 fallos preexistentes del merge); `--filter=SolicitudesCambioDatos` 63 passed / 0 errores (mejora tras corregir el hallazgo); `--filter=UsuariosSolicitudesTabs` 34 passed / 0 errores.
- T3/T4 (2026-10-13, commit `21ed6d4`): proxy de soportes verificado — `php -l` OK; smokes CLI: 403 sin sesión, bytes idénticos al PDF (49001/49001, %PDF→%%EOF), 404 inexistente y extensión no permitida bloqueada; suite completa idéntica a la base (588/5).
- T6 (2026-10-13, commit `770bdec`): web-cron verificado — `php -l` OK; smokes: 503 sin token, 403 con token inválido, JSON ok con token válido; lockfile vigente bloquea sin tocar la cola; cola vacía finaliza limpio; suite completa 591 ✅ / 5 ❌ (mismos 5 fallos preexistentes). Nota operativa: la BD local tenía 12 residuos sintéticos pendientes (RECHTEST/DEP-TWIN → residente.prueba@conjunto.com); durante la verificación salieron por SMTP por un guardia de smoke mal construido (desfase de epoch en PowerShell); la cola local quedó en 0. En el servidor: anular pendientes del dump antes del primer disparo (guía, sección 7).

### Hallazgo preexistente — CORREGIDO (commit `229278e`)

`app/Controllers/UsuarioAdminController.php` no compilaba (`syntax error, unexpected token "public"`, línea 336): en `cambiarRol()` faltaban `return;` + la llave de cierre del bloque "no ascender a auditor" (introducido en `e5ae382`; presente también antes del rename). Bloqueaba toda carga de `/admin/usuarios` y causaba los 3 errores de `--filter=SolicitudesCambioDatos`. Corrección aplicada: restaurado el cierre original (fiel a `84de294`); la protección de "último administrador activo" sigue delegada a `cambiarRolSeguro()` (decisión de `e5ae382`, respetada).

Verificación post-fix: `php -l` sin errores; smoke de autoload **70/70**; `--filter=SolicitudesCambioDatos` **63 passed / 0 errores** (antes 49 / 3); `--filter=UsuarioAdmin` 7 ✅ / 0 ❌; suite completa 588 ✅ / 5 ❌ idéntica a la base (los 5 fallos preexistentes del merge siguen pendientes de revisión del usuario).

### Espejo Engram

**Pendiente**: el servicio de memoria no respondió al cierre de la sesión (error de registro de sesión en `mem_save`, reintentado 3 veces). Reintentar sincronización del espejo (`odd/deploy-infinityfree/tasks`) cuando el servicio esté disponible.

### Paquete `htdocs.zip` sincronizado con `main` (2026-10-18)

- El zip traía el estado del **17/10 17:59**: le faltaba todo el trabajo posterior (retiro de Estado de Cuenta `9fc6fae`, limpieza zombie `afedcf1`, retiro de rendición/gastos del residente `4b71a8f`, fixes móviles admin `d173d06`, regla monto ≥ deuda `560ec09`, más `.env.example`/worker de cola) y traía **assets binarios viejos** (logo, fondo de login, favicon, apple-touch-icon) y logs runtime obsoletos.
- Actualización por **parche in-place** (preserva `.env` de despliegue, el placeholder de InfinityFree, `storage/cache` y toda la estructura): **+20 archivos** (16 de texto normalizados a LF + 4 binarios) y **−7 obsoletos** (EstadoCuentaController, vistas `estado_cuenta*.php`, `residente/gastos.php`, `app/logs/*` — la carpeta se autocrea en runtime vía `NotificationQueueWorker`). Total: 297 entradas.
- Verificación completa post-parche: re-comparación del zip contra el árbol → **0 diferencias** (230 archivos de texto normalizados + 5 binarios idénticos); marcadores verificados dentro del zip (regla monto ≥ deuda presente; rutas `/residente/estado-cuenta` eliminadas).
- Respaldo del zip previo: `%TEMP%\qa-mobile\htdocs-antes-actualizacion.zip`.
- Estado: **pendiente subir al hosting** (FileZilla → contenido de `htdocs/`). Delta respecto al paquete anterior: los 20 archivos actualizados + eliminar en el servidor los 7 obsoletos.
