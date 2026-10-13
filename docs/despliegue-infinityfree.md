# Guía de despliegue — InfinityFree (plan gratuito)

Complementa el seguimiento interno en `odd/tasks/deploy-infinityfree.md`.
Estado: T1–T4 y T6 cerradas; T5/T7/T8 corresponden al momento del despliegue (secciones 3–5 de esta guía).

> **Revisión 2026-10-13 (layout definitivo):** InfinityFree **no permite archivos ni carpetas fuera de `htdocs`** (la creación se rechaza en el gestor web; `open_basedir` restringe PHP a `htdocs` y lo subido fuera se elimina). El layout correcto es **todo dentro de `htdocs/`** con `public/` como subcarpeta y un `.htaccess` de rewrite — receta oficial del hosting. Reemplaza al layout anterior (código fuera de htdocs), que no es aplicable aquí.

## 1. Por qué este layout (revisado 2026-10-13)

- InfinityFree sirve **solo** `htdocs` como webroot (no configurable) y además **PHP está restringido a `htdocs`** (`open_basedir`): no puede leer ni escribir fuera de esa carpeta, y lo que se sube fuera se elimina. El layout "código fuera de htdocs" **no es posible** en este hosting; el gestor web directamente rechaza crear carpetas fuera ("Directory creation is only allowed in htdocs folders").
- El proyecto asume que `public/` es la raíz web, con `app/`, `vendor/`, etc. como hermanos de `public/`. Eso se preserva si **todo el proyecto vive dentro de `htdocs/`**:
  - `htdocs/public/` ← la carpeta `public/` completa.
  - `htdocs/app/`, `htdocs/vendor/`, `htdocs/uploads/`, `htdocs/logs/`, `htdocs/storage/`, `htdocs/.env` ← dentro de htdocs.
- Un **`.htaccess` nuevo en la raíz de `htdocs`** reescribe toda petición hacia `/public/` (receta oficial de InfinityFree para estructuras con carpeta pública):

  ```apache
  RewriteEngine On
  RewriteRule (.*) /public/$1 [L]
  ```

- **Protección por rewrite**: una URL sensible (`/.env`, `/app/...`, `/storage/...`) se reescribe a `/public/...` (que no existe) → 404. El `.env` queda dentro de htdocs pero inaccesible desde el navegador.
- Las rutas relativas siguen funcionando igual (`public/index.php` usa `../app` y `../vendor`, ahora dentro de htdocs; `BASE_PATH = dirname(__DIR__, 2)` = `htdocs`). Único ajuste de código ya aplicado: `index.php` descuenta el prefijo del script solo si está presente en la URI (bajo este rewrite la URL pública no lleva `/public`).

## 2. Mapa de subida (todo dentro de htdocs)

El paquete `deploy-infinityfree-htdocs.zip` ya trae esta estructura lista: se sube **el contenido completo dentro de `htdocs/`**.

| Dentro del paquete | Destino en el servidor |
|---|---|
| `.htaccess` (nuevo) | `htdocs/.htaccess` — rewrite hacia `/public/` |
| `public/` (carpeta completa) | `htdocs/public/` — index.php, .htaccess, proxies, img/, js/, favicons |
| `app/` | `htdocs/app/` |
| `vendor/` | `htdocs/vendor/` |
| `uploads/` | `htdocs/uploads/` (crear `comprobantes` y `soportes`) |
| `logs/` | `htdocs/logs/` |
| `storage/` | `htdocs/storage/` (`backups`, `logs`, `cache`) |
| `scripts/` | `htdocs/scripts/` (opcional; herramientas CLI) |
| `.env.example` | referencia; el `.env` real se crea en `htdocs/.env` (T5) |
| `database/*.sql` | no se suben; el dump se importa por phpMyAdmin |

Notas:

- Las URLs del navegador **no cambian**: el rewrite hace que `https://tudominio/loquesea` se sirva desde `htdocs/public/loquesea`.
- `public/uploads/` no existe en el paquete (era local): los soportes se guardan y sirven desde `htdocs/uploads/soportes/` mediante `/soporte-proxy.php`.

## 3. Qué NO subir

- `php.ini` (InfinityFree no lo lee), `composer.phar`, `.codegraph/` (supera el límite de 10 MB), `.git/`
- `tests/`, `odd/`, `.sdd/`, `.atl/`, `.config/`, `Estilos de stitch/`, `resources/`, `docs/`
- Documentos e imágenes sueltas de la raíz (`*.docx`, `Captura de pantalla *.png`, `*.jpeg`, `*.pdf`, etc.)
- Datos locales: contenido de `uploads/` (hay archivos de prueba), `storage/cache/*`, `logs/*`

(El pase de limpieza definitivo es el punto T7 del seguimiento.)

### ¿Qué pasa si NO subes cada cosa? (verificado)

Ninguno de los excluidos rompe el sistema: la app no los lee en runtime (0 referencias en código de producción) y las carpetas de datos se autogestionan.

| Excluido | Efecto real |
|---|---|
| `php.ini` | Ninguno: el servidor usa su propia configuración; el archivo solo aplica al PHP local. |
| `composer.phar` | Ninguno en el servidor (solo se usa en la PC para regenerar el autoloader). |
| `.git/`, `.codegraph/`, `.atl/`, `.sdd/`, `.config/`, `odd/`, `tests/` | Ninguno; herramientas de desarrollo (el namespace `Tests` del autoloader queda sin uso). |
| `docs/`, documentos e imágenes sueltas de la raíz | Ninguno; ninguna vista los enlaza. |
| `resources/` | Ninguno; el estilo corre por CDN (Tailwind/Bootstrap) y los estilos propios van inline en el layout. |
| `Estilos de stitch/` | Ninguno; material de diseño. |
| Contenido de `storage/cache/`, `storage/logs/` y `logs/` | Ninguno: la caché de estructura se recrea sola (crea sus subcarpetas) y los logs nacen al primer uso. |
| Contenido de `uploads/` (datos de prueba) | Ninguno: `uploads/comprobantes` y `uploads/soportes` se autocrean al primer upload. |
| `database/*.sql` | Ninguno en runtime; solo se usan para importar una vez por phpMyAdmin.

Lo único que **debe** existir: `vendor/` (autoloader), el `.env` de producción y las carpetas escribibles del paso 4.

## 4. Procedimiento

1. **Cuenta y dominio**: registrarse en InfinityFree y crear el dominio (sirve el subdominio gratuito).
2. **Base de datos**: en el panel, crear la base MySQL y anotar host (`sqlXXX.infinityfree.com`), nombre, usuario y contraseña.
3. **Importar**: en phpMyAdmin, importar **`database/condominio_cobranzas.sql`** (dump canónico regenerado 2026-10-13, ya compatible con MariaDB ≥10.5 — importable tal cual; incluye `DROP TABLE` + FK checks apagados, así que sirve incluso sobre un estado de importación parcial). Si en cambio exportás un dump nuevo desde otro entorno 10.4, revisá **§8**. El dump trae los usuarios y personas del entorno local: probar el primer acceso con esas credenciales. Si hiciera falta resetear una contraseña, se hace con un `UPDATE` por phpMyAdmin (el plan gratuito no tiene CLI/SSH, no se pueden correr los scripts de `scripts/`).
4. **FTP**: extraer `deploy-infinityfree-htdocs.zip` y subir **el contenido completo DENTRO de `htdocs/`** con FileZilla (host y credenciales del panel; la raíz FTP es la raíz de la cuenta, entrar a `htdocs/`). Se recomienda **vaciar antes `htdocs`** para no mezclar estados. El paquete incluye `public/` como subcarpeta, el `.htaccess` raíz nuevo, `app/`, `vendor/`, `uploads/`, `logs/`, `storage/` y `scripts/`.
5. **`.env` de producción**: crear el archivo en `htdocs/.env` con los valores reales — ver T5 y la sección 7 (host `sqlXXX`, claves de seguridad nuevas, `MAIL_MODE=smtp`). Queda protegido por el rewrite (404 desde el navegador).
6. **PHP**: en el panel, seleccionar PHP 8.3/8.4 (el código es compatible; no usa sintaxis más nueva).
7. **SSL**: activar el certificado gratuito y esperar a que propague **antes** de poner `ENVIRONMENT=production` (la cookie de sesión es `secure` y sin HTTPS el login no funciona).
8. **Pruebas rápidas**: cargar home y login; subir un comprobante real (valida el límite de 10 MB por archivo); abrir un soporte de gasto (valida el proxy de T3); revisar que se escriba `logs/`.

## 5. Límites del plan a tener presentes

- 10 MB máximo por archivo (duro; aplica también a FTP).
- ~5 GB de disco y 50 000 hits/día. Suficiente para la entrega y las pruebas.
- Sin cron ni SSH: los procesos programados necesitan web-cron externo (pendiente T6: worker de correos y respaldos).
- `mail()` está capado: los correos salen por SMTP externo (`MAIL_MODE=smtp`).

## 6. Pendientes relacionados (del seguimiento)

| Punto | Qué falta | Impacto en esta guía |
|---|---|---|
| T3 | ✅ Resuelto: proxy `/soporte-proxy.php` (sesión obligatoria; sirve desde `uploads/soportes/`) | `public/uploads` no se sube |
| T4 | ✅ Resuelto por diseño: `public/uploads` es local (ignorado por git) y no se sube | — |
| T5 | `.env` de producción y orden SSL → `production` | pasos 5 y 7 |
| T6 | ✅ Resuelto: `/cron-notificaciones.php` + cron externo (ver sección 7) | sin él los correos quedan encolados |

## 7. Correo en producción — web-cron (T6)

InfinityFree no tiene cron: la cola de correos (OTP de 2FA, cartas de deuda, avisos) se despacha con un **cron externo** que llama a un endpoint protegido por token.

### Qué subir

| Archivo | Destino | Para qué |
|---|---|---|
| `public/cron-notificaciones.php` | `htdocs/public/cron-notificaciones.php` | Disparador web del worker (la URL pública no cambia: el rewrite lo sirve igual) |
| `app/Services/NotificationQueueWorker.php` | `htdocs/app/Services/` | Worker compartido (viaja con la carpeta `app/`) |
| `app/Services/EmailService.php` + `SmtpMailer.php` | `htdocs/app/Services/` | Transporte SMTP real (viajan con `app/`) |
| `.env` del servidor | `htdocs/.env` | `MAIL_*` idénticas a tu entorno local + `CRON_TOKEN` nuevo |

El CLI `scripts/process_notifications.php` no es necesario en el servidor (solo se usa en local).

La configuración de correo que ya funciona en local (SMTP Gmail con contraseña de aplicación) se copia **tal cual** al `.env` del servidor: `MAIL_MODE=smtp`, `MAIL_HOST`, `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`, `MAIL_USER`, `MAIL_PASS`, `MAIL_VERIFY_PEER=1`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_TIMEOUT`.

### Configurar el cron externo (cron-job.org u otro)

1. Crear un job con URL: `https://TU-DOMINIO/cron-notificaciones.php?token=TU_CRON_TOKEN`
2. Frecuencia: cada 5 minutos.
3. Verificación manual: abrir la URL en el navegador → debe responder JSON `{"ok":true,...}`.

Con token incorrecto responde 403; si el `.env` no tiene `CRON_TOKEN`, responde 503 con aviso. El worker tiene lockfile: dos llamadas simultáneas no se pisan.

### Antes del primer disparo

- **El dump actual trae 12 correos en `pendiente`** (residuos sintéticos de pruebas, `RECHTEST…`/`DEP-TWIN…`). Antes del primer disparo del cron, anularlos tras importar:
  `UPDATE notificaciones_cola SET estado='fallido', error_mensaje='Anulado pre-despliegue' WHERE estado='pendiente';`
- Copiar `NOTIFICATION_ENCRYPT_KEY` idéntica a la local (los correos nuevos se cifran con la clave del servidor; la copia evita sorpresas con datos del dump).
- Prueba de humo real: pedir un OTP desde el login del servidor → abrir la URL del cron a mano → verificar que llegó el correo y que la fila pasó a `enviado`.

## 8. Gotchas de importación (MariaDB ≥10.5)

**Error `#1901 – Function or expression 'edificio_id' cannot be used in the GENERATED ALWAYS AS clause of dup_guard`** (u otro nombre con el mismo patrón).

Causa: desde MariaDB 10.5, una columna generada **STORED/PERSISTENT no puede depender de una columna que sea modificable por una acción de FK en cascada** (`ON DELETE SET NULL`, `ON UPDATE CASCADE`) — el valor almacenado quedaría desincronizado (MDEV-18114; validado también en MDEV-32030). MariaDB 10.4 (local) solo lo avisa; el servidor (10.5+) lo rechaza con error.

En este proyecto el único caso es `gastos_comunes.dup_guard` (STORED) ↔ FK `fk_gastos_edificio` (`edificio_id`, `ON DELETE SET NULL`) — **ya corregido en local y en el dump canónico del repo (2026-10-13)**. El reemplazo siguiente solo hace falta para exports generados en OTROS entornos sin el cambio: editar el archivo `.sql`, **una línea** (buscar y reemplazar):

```
buscar:     ADD CONSTRAINT `fk_gastos_edificio` FOREIGN KEY (`edificio_id`) REFERENCES `edificios` (`id`) ON DELETE SET NULL;
reemplazar: ADD CONSTRAINT `fk_gastos_edificio` FOREIGN KEY (`edificio_id`) REFERENCES `edificios` (`id`);
```

*(si el export la trae inline dentro del CREATE TABLE, quitar solo ` ON DELETE SET NULL` de esa línea).*

La app no cambia su comportamiento: `EdificiosModel::eliminarSiLimpio()` ya bloquea eliminar edificios con gastos (la FK queda como red de seguridad que falla fuerte en lugar de desvincular en silencio, que era justo lo que el guard evitaba).

**Importación fallida a mitad** — limpiar antes de reintentar (el dump no incluye `DROP TABLE IF EXISTS`, así que las tablas ya creadas romperían el reimport):

```sql
SET FOREIGN_KEY_CHECKS=0;   -- luego, en la pestaña Estructura: seleccionar TODAS las tablas → Drop
SET FOREIGN_KEY_CHECKS=1;
```

**Paridad local — ✅ aplicada (2026-10-13)** al MariaDB local y reflejada en el dump canónico `database/condominio_cobranzas.sql` (regenerado con `mysqldump`):

```sql
ALTER TABLE gastos_comunes DROP FOREIGN KEY fk_gastos_edificio;
ALTER TABLE gastos_comunes ADD CONSTRAINT fk_gastos_edificio FOREIGN KEY (edificio_id) REFERENCES edificios (id) ON DELETE RESTRICT;
```
