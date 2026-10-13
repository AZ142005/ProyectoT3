# Guía de despliegue — InfinityFree (plan gratuito)

Complementa el seguimiento interno en `odd/tasks/deploy-infinityfree.md`.
Estado: T1–T4 cerradas; T5–T8 en curso (ver `odd/tasks/deploy-infinityfree.md`).

## 1. Por qué este layout

- InfinityFree sirve **solo** la carpeta `htdocs` del dominio; no se puede cambiar el webroot.
- El proyecto asume que `public/` es la raíz web, con el resto del código fuera de ella.
- En el servidor eso se traduce así: el **contenido** de `public/` va dentro de `htdocs/`, y todo lo demás queda al lado de `htdocs/` (raíz de la cuenta), inaccesible desde el navegador.
- Las rutas relativas del código ya soportan este layout (`public/index.php` usa `../vendor` y `../app`; `config.php` calcula `BASE_PATH = dirname(__DIR__, 2)`), por lo que **no requiere cambios de código**.
- Beneficio de seguridad: `.env` (credenciales), `app/`, `vendor/` y los datos subidos quedan fuera del alcance web.

## 2. Mapa de subida

| En la PC | En el servidor | Contenido |
|---|---|---|
| `public/` (su contenido) | `htdocs/` | `index.php`, `.htaccess`, `comprobante-proxy.php`, `img/`, `js/`, favicons |
| `app/` | raíz de la cuenta | todo el código (Core, Controllers, Models, Services, views, …) |
| `vendor/` | raíz de la cuenta | autoloader (ya está versionado en el repo) |
| `uploads/` | raíz de la cuenta | crear `uploads/comprobantes` y `uploads/soportes` |
| `logs/` | raíz de la cuenta | crear vacía (la app escribe aquí en producción) |
| `storage/` | raíz de la cuenta | crear `storage/backups`, `storage/logs`, `storage/cache` |
| `scripts/` | raíz de la cuenta | opcional; herramientas CLI (no accesibles por web por estar fuera de `htdocs`) |
| `.env` | raíz de la cuenta | NO se sube desde la PC: se crea en el servidor con los valores de producción (T5) |
| `database/*.sql` | — | no se suben; el dump se importa por phpMyAdmin |

Notas:

- Subir el **contenido** de `public/`, no la carpeta `public` (la URL debe quedar `https://tudominio/`, no `https://tudominio/public/`).
- `public/uploads/` es una carpeta local de desarrollo (ignorada por git) y **no se sube**: los soportes se guardan y sirven desde `uploads/soportes/` mediante el proxy seguro `/soporte-proxy.php`.

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
3. **Importar**: en phpMyAdmin, importar `database/condominio_cobranzas (5).sql` (~0.44 MB; sin triggers ni `DEFINER`, importa limpio). El dump trae los usuarios y personas del entorno local: probar el primer acceso con esas credenciales. Si hiciera falta resetear una contraseña, se hace con un `UPDATE` por phpMyAdmin (el plan gratuito no tiene CLI/SSH, no se pueden correr los scripts de `scripts/`).
4. **FTP**: con FileZilla, conectarse con el host y credenciales que indique el panel (la raíz FTP es la raíz de la cuenta: ahí se ve `htdocs`).
   - Subir el contenido de `public/` → dentro de `htdocs/`.
   - Subir `app/`, `vendor/`, `scripts/` → raíz de la cuenta.
   - Crear las carpetas de la tabla del punto 2 (`uploads/comprobantes`, `uploads/soportes`, `logs`, `storage/backups`, `storage/logs`, `storage/cache`).
5. **`.env` de producción**: crear el archivo en la raíz (fuera de `htdocs`) con los valores reales — ver punto T5 del seguimiento (host `sqlXXX`, claves de seguridad nuevas, `MAIL_MODE=smtp`).
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
| T6 | Web-cron con token (worker de correos/respaldo) | sin él los correos quedan encolados |
