# Vistas móviles — login, pago sin sesión y área del residente (QA móvil + fixes)

Fecha: 2026-10-13
Origen: Pedido del usuario — «revisa las vistas móviles de login, pago sin iniciar sesión y todo lo que ve el usuario residente, todo al main, dime dónde haces los cambios para subirlos al hoster».
Ruta: QA automatizado propio (Chrome headless + CDP con WebSocket nativo de Node, sin dependencias, fuera del repo) + correcciones de clases ya entendidas aplicadas inline. No es SDD.
TDD: off — runner custom `php tests/run.php`.
Commit: `35e8599` (main). Paquete de deploy regenerado.

## Objetivo

Verificar el comportamiento móvil real (390px) del login, el pago directo sin sesión y todas las vistas del residente; corregir lo que se rompe; dejar los cambios en `main` y el paquete de subida para InfinityFree actualizado, señalando archivo por archivo dónde va cada cambio.

## Metodología (verificable)

- Harness temporal en `%TEMP%\qa-mobile` (nunca en el repo): sesión PHP sembrada para la persona 207 (`residente.prueba@conjunto.com`, unidad A-101), Chrome headless con emulación móvil real (`390×844 CSS`, DPR 2, `mobile:true`) vía CDP directo.
  - Capturas full-page de 15 rutas + captura con scroll interno del login.
  - Sonda de layout por página: `scrollWidth` del documento vs viewport, elementos fuera de viewport, colores computados.
- Rutas cubiertas: `/auth/login`, `/auth/register`, `/auth/registro-exitoso`, `/pago-directo`, `/pago-directo/exito`; `/residente/dashboard`, `/pagos`, `/pagos/nuevo`, `/pagos/detalle/{id}`, `/residente/historial` (redirige a `/pagos`), `/residente/gastos`, `/residente/estado-cuenta`, `/residente/cartelera`, `/residente/notificaciones`, `/perfil`.
- La contraseña del residente de prueba NO se tocó (la sesión se sembró server-side); verificado además el hash restaurado byte a byte.

## Hallazgos y correcciones (commit `35e8599`)

| # | Vista | Hallazgo (verificado en captura + sonda) | Fix |
|---|---|---|---|
| 1 | `/pagos` — `app/views/pagos/residente/lista.php` | Botones del encabezado sin wrap: «Registrar Nuevo Pago» quedaba cortado fuera de pantalla y el viewport se estiraba a 473px | `flex-wrap` en el contenedor de acciones |
| 2 | `/pagos/detalle` — `app/views/pagos/detalle.php` | Título comprimido palabra por palabra y toolbar «Descargar Comprobante / Volver a la lista» desbordado (viewport 456px) | `flex-wrap` + `gap` en el encabezado del residente y en la barra de navegación del admin |
| 3 | `/residente/notificaciones` — `app/views/residente/notificaciones.php` | Tarjeta de 3 columnas comprimía el mensaje a ~2 palabras por línea | `flex-col sm:flex-row`; acciones en fila en móvil; `min-w-0` + `break-words` |
| 4 | `/perfil` — `app/views/perfil/index.php` | «Volver al Panel» se partía en 3 líneas junto al título | `flex-wrap gap-4` + `whitespace-nowrap` en el botón |
| 5 | Global — `app/views/layouts/header.php` | **Paleta**: `bg-primary`/`text-primary` renderizaban el azul Bootstrap `#0d6efd` en toda la app (desktop y móvil), por colisión con las utilidades `!important` de Bootstrap 5.3 que usan `--bs-primary-rgb` (sin definir; solo estaba `--bs-primary`) | `--bs-primary-rgb: 39, 174, 96` (RGB de `#27ae60`) |

**Nota de compatibilidad (5):** el manual y las capturas existentes muestran los acentos en azul; tras el fix los acentos primarios pasan a verde institucional en TODA la app (incluye admin). Es revertible con una línea. El azul del design Stitch (`tertiary #0053db`) queda sin uso en código.

**Observación (fuera de alcance):** las vistas de admin usan `.btn-primary` de Bootstrap (azul literal de componente, no cambia con el fix). Es preexistente; pendiente de pulido si se desea.

## Verificación

- Sonda post-fix: `vw=390`, sin overflow de documento en TODAS las rutas; `--bs-primary-rgb = 39,174,96`; capturas post-fix revisadas una a una (headers envueltos, tarjetas apiladas, sin recortes).
- `php -l` sin errores en los 5 archivos.
- Suite completa `php tests/run.php`: **599 ✅ / 0 ❌** (exit 0).
- No regresión en lo demás: los únicos cambios a `git` son los 5 archivos del commit.

## Deploy — paquete regenerado (`deploy-infinityfree-htdocs.zip`)

Misma estructura que el paquete anterior (241 entradas, `.htaccess` raíz incluido), reconstruido desde el `main` actual. Todo el contenido va **dentro de `htdocs/`** (guía `docs/despliegue-infinityfree.md` §2).

Diferencias vs el paquete del 13/10 15:51 — si ese ya se subió, basta re-subir estos 9 archivos:

| Archivo (destino en `htdocs/`) | Origen |
|---|---|
| `.env.example` | commit `9496219` (QUEUE_AUTORUN) |
| `app/Models/UsuariosModel.php` | commit `040b0b9` (fix 2FA `getActiveById`) |
| `app/Services/NotificationQueueWorker.php` | commit `9496219` |
| `public/index.php` | commits `9496219`/`67ca3ed` (auto-despacho por tráfico) |
| `app/views/layouts/header.php` | `35e8599` (paleta) |
| `app/views/pagos/detalle.php` | `35e8599` |
| `app/views/pagos/residente/lista.php` | `35e8599` |
| `app/views/perfil/index.php` | `35e8599` |
| `app/views/residente/notificaciones.php` | `35e8599` |

## Pendientes / notas

- En el servidor: `QUEUE_AUTORUN=1` en `htdocs/.env` (guía §7) — sin él los correos quedan encolados.
- Capturas de la QA disponibles en `%TEMP%\qa-mobile\out2` (post-fix) y `out` (pre-fix) mientras dure la sesión de Windows.
- Aviso preexistente: la bandeja de notificaciones del residente de prueba tiene ~250 avisos sintéticos de tests (`RECHTEST…`/`DEP-TWIN…`); es data local de prueba, no código.
