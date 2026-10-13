# Envío real de correo por SMTP (opción B)

Fecha: 2026-10-04
Origen: pedido del usuario tras la prueba de envío (hallazgo en engram `mail/transporte-dev-roto`: los correos se encolan pero `EmailService::enviar()` no tiene transporte; el mock de `.env` no estaba cableado).
Ruta: implementación DELEGADA — un escritor único (trigger: 2+ archivos no triviales). Verificación: escritor + orquestador (tests + sonda TLS a smtp.gmail.com). RDD nativo no disponible en OpenCode.
TDD: off — runner custom `php tests/run.php`.

## Objetivo

Implementar transporte SMTP autenticado real (B) configurable por `.env`, manteniendo el modo mock funcional y el contrato del worker de cola intacto. Sin credenciales en el repositorio.

## Contexto verificado

- `EmailService::enviar()` hoy cae a `@mail()` (SMTP localhost:25, sin servidor) → `false`; el modo mock no estaba cableado (el código leía `MAIL_DRIVER`/`APP_ENV` por `getenv`, mientras `.env` define `MAIL_MODE` y el loader llena `$_ENV`).
- Worker: `scripts/process_notifications.php` (decrypt → `EmailService::enviar` → estados `pendiente`/`enviado`/`fallido` con backoff). No requiere cambios.
- Consumidores de `EmailService`: `NotificationService::enviarOtp`, `ComunicadoController`, `ConciliacionBancariaService`, `PagoModel`, `ReporteController` (render). El contrato `enviar(string $dest, string $asunto, string $html): bool` debe mantenerse.
- Cola: 196 pendientes (ids 1–689) **anulados** el 2026-10-04 (estado=`fallido`, mensaje "backlog previo … no debe enviarse") para que no salgan al habilitar SMTP. Verificado: 0 pendientes.
- Gmail requiere 2FA + contraseña de aplicación para SMTP (usuario/contraseña normal no funciona).

## Checklist

- [x] T1 `app/services/SmtpMailer.php` (nuevo): cliente SMTP en PHP puro — AUTH LOGIN, modos `tls` (587 STARTTLS), `ssl` (465) y `none`; timeouts; `verify_peer`/`cafile` configurables; respuestas multilínea; headers UTF-8 (Subject base64, cuerpo base64); `send(): bool` con excepción descriptiva (etapa + respuesta) en fallos.
- [x] T2 `app/services/EmailService.php`: resolver config desde `$_ENV` (fallback `getenv`); `MAIL_MODE` = `mock` (escribe `app/logs/mail_mock.log` y retorna true, sin red) | `smtp` (usa `SmtpMailer`) | `mail` (conserva `@mail()`). Mantener `renderTemplate()` y la firma de `enviar()`.
- [x] T3 `.env.example`: documentar claves `MAIL_MODE/MAIL_HOST/MAIL_PORT/MAIL_ENCRYPTION/MAIL_USER/MAIL_PASS/MAIL_VERIFY_PEER/MAIL_CAFILE` con ejemplo Gmail comentado (smtp.gmail.com:587 tls + contraseña de aplicación). NO tocar `.env` real.
- [x] T4 `tests/EmailSmtpTest.php`: mock escribe log y retorna true; `smtp` sin host → excepción clara; resolución de config; servidor SMTP falso local (proc_open, protocolo plano) que recibe el mensaje — si no es estable, `skip()` documentado.
- [x] T5 Verificación: `php -l`, `--filter=Email`, `--filter=Notification`, `--filter=Auth`, suite completa sin ❌ antes del aborto conocido en SecurityTest.

## Aceptación

- `MAIL_MODE=mock`: `enviar()` escribe el log y retorna `true` sin tocar la red.
- `MAIL_MODE=smtp` con config válida: completa el protocolo SMTP autenticado y retorna `true`; falla con mensaje descriptivo si algo falla.
- `MAIL_MODE=smtp` sin `MAIL_HOST`: excepción clara (el worker la registra como error, sin fatal).
- Contrato `enviar(): bool` intacto; worker sin cambios; suite verde.

## Decisiones

- Cliente SMTP propio en PHP puro (estilo del proyecto, sin nuevas dependencias); PHPMailer descartado para no agregar `vendor`.
- `From` = `MAIL_FROM_ADDRESS`; con Gmail se recomienda usar la propia dirección Gmail como `From` (o configurar "Send mail as").
- La cola previa queda anulada (no se enviará). Los correos creados después de habilitar SMTP sí se enviarán.
- Las credenciales van solo en `.env` (gitignore); pendientes de que el usuario las provea.

## Evidencia

Ejecución: 2026-10-04. Implementación delegada; commits locales `7aa2529` (feat) y `729d39a` (test) — sin push.

- Archivos:
  - `app/services/SmtpMailer.php` (nuevo): cliente SMTP en PHP puro; AUTH LOGIN; modos `tls`/`ssl`/`none`; respuestas multilínea; errores con etapa + respuesta truncada; `send(): bool` solo true con 250 final.
  - `app/services/EmailService.php` (modificado): `conf()` resuelve `$_ENV` con fallback `getenv`; modos `mock`/`smtp`/`mail`; `renderTemplate()` y firma `enviar()` intactas; `require_once SmtpMailer.php` porque el worker no usa autoloader.
  - `.env.example`: sección de correo documentada (`MAIL_MODE` mock/smtp/mail, claves SMTP y ejemplo Gmail comentado con contraseña de aplicación de 16 caracteres). `.env` real no tocado.
  - `tests/EmailSmtpTest.php` (nuevo) y `tests/helpers/fake_smtp_server.php` (servidor falso local; el runner no lo descubre).
- Comandos → resultados:
  - `php -l app/services/SmtpMailer.php` → sin errores de sintaxis.
  - `php -l app/services/EmailService.php` → sin errores de sintaxis.
  - `php -l tests/helpers/fake_smtp_server.php` → sin errores de sintaxis.
  - `php -l tests/EmailSmtpTest.php` → sin errores de sintaxis.
  - `php tests/run.php --filter=Email` → 12 tests: ✅ 58, ❌ 0, 0 errores (incluye protocolo SMTP completo contra el servidor falso local).
  - `php tests/run.php --filter=Notification` → 6 tests: ✅ 25, ❌ 0.
  - `php tests/run.php --filter=Auth` → 37 tests: ✅ 315, ❌ 0.
  - `php tests/run.php` (completa) → aborto conocido en `SecurityTest`; antes del aborto: ✅ 526, ❌ 0, ⏭ 2 (pruebas de permisos de directorios, preexistentes).
  - Sonda de carga del worker sin autoloader (require manuales como `scripts/process_notifications.php`) → `App\Services\SmtpMailer` disponible y firma `enviar(string,string,string): bool` intacta.
- Notas:
  - El runner convierte cualquier warning en excepción incluso con `@`; por eso `SmtpMailer` silencia localmente connect/crypto/QUIT y el test comprueba existencia antes de `unlink`.
  - `MAIL_TIMEOUT` se añadió como clave opcional (default 20 s) para acotar la espera desde `.env`.
  - `MAIL_MODE` vacío → `mock` (compatibilidad legada con `MAIL_DRIVER=log`/`APP_ENV=testing`); modo no reconocido → excepción clara.
  - No hubo conexiones SMTP reales: test 3 apunta a `127.0.0.1:1` y test 4 a un puerto efímero local. Credenciales reales pendientes del usuario en `.env`.

### Verificación del orquestador (2026-10-04)

- Refinamiento aplicado por el orquestador antes de congelar: el nombre visible del remitente no-ASCII ahora se codifica como encoded-word (RFC 2047) en la cabecera `From` (`SmtpMailer::construirMensaje`); el nombre por defecto contiene "ó".
- Batería final: `php -l` 4/4 sin errores; `--filter=Email` 12 tests / 58 ✅; `--filter=Notification` 6 / 25 ✅; suite completa con 0 aserciones fallidas y 0 excepciones antes del aborto conocido en `SecurityTest`.
- **Sonda real contra Gmail** (`smtp.gmail.com:587`, STARTTLS, `verify_peer=true`, credenciales inválidas de prueba): conexión, handshake TLS con verificación de certificado, EHLO y AUTH llegaron a la respuesta `535 Username and Password not accepted` de Gmail → transporte operativo; solo falta una credencial válida. CA bundle del entorno: `C:\xampp\apache\bin\curl-ca-bundle.crt` (no hizo falta `MAIL_CAFILE`).
- **Cola blindada**: 196 pendientes (ids 1–689) → `fallido` con mensaje "Anulado 2026-10-04: backlog previo a habilitar el envío real por SMTP. No debe enviarse."; verificado: 0 pendientes. Al habilitar credenciales, los correos nuevos sí se enviarán.
- **Pendiente del usuario**: dirección de la cuenta emisora Gmail + contraseña de aplicación de 16 caracteres (requiere 2FA activo). Con eso: configurar `.env` (`MAIL_MODE=smtp`, `smtp.gmail.com:587`, `tls`, usuario/contraseña, `MAIL_FROM_ADDRESS` = la cuenta Gmail) y correr UNA prueba real (carta de deuda + OTP) hacia `azdeo142005@gmail.com`.

### Reapertura 2026-10-11 (habilitación real)

- **Cola purgada**: 48 filas `pendiente` (residuos sintéticos de tests de pagos, `RECHTEST…`/`DEP-TWIN…`) → `fallido` con mensaje "Anulado 2026-10-11: residuo de pruebas previo a habilitar el envío real por SMTP. No debe enviarse.". Verificado: 0 pendientes y ninguna carta real entre lo anulado.
- **`.env` configurado** (local, gitignored): `MAIL_MODE=smtp`, `smtp.gmail.com:587`, `tls`, `MAIL_USER`/`MAIL_FROM_ADDRESS` = cuenta Gmail provista por el usuario, `MAIL_FROM_NAME="Condominio Conjunto Las Mesetas"`, `MAIL_TIMEOUT=20`.
- **Plantilla del correo alineada** (commit `9aecc8c`): membrete actual (CONDOMINIO CONJUNTO LAS MESETAS / RIF. J-400577870 / dirección y teléfono), días reales (`dias_mora` en vez de `dias_vencido`, que siempre mostraba 0), aviso "no responda a este correo" en el pie; test `EmailPlantillasTest`. Nota de producción (worker por cron / Programador de tareas) en `.env.example`.
- **Prueba real (ejemplo)**: carta de la Unidad 5-25 (Torre 5, 4 facturas, Bs. 144.801,73) encolada hacia `azdeo142005@gmail.com` y worker ejecutado → **AUTH rechazada por Gmail**: `535-5.7.8 Username and Password not accepted`. El transporte operó (conexión + STARTTLS + EHLO + AUTH); la credencial provista no es una contraseña de aplicación válida (deben ser 16 caracteres generados en https://myaccount.google.com/apppasswords con 2FA activo). La fila del ejemplo quedó marcada como terminal (sin reintentos fantasma).
- **Pendiente único**: contraseña de aplicación correcta en `MAIL_PASS` → reencolar el ejemplo y correr el worker.
- **Costo**: SMTP de Gmail y las contraseñas de aplicación son gratuitos; cuenta gratuita ≈ 500 destinatarios/día (excederlo bloquea temporalmente, no genera cobro). No se usa ninguna API facturable de Google.
- **Servidor**: ver checklist en `.env.example` (claves MAIL_*, programar worker, migraciones, permisos de escritura en `app/logs`).

### Cierre 2026-10-13 — envío real verificado ✅

- Contraseña de aplicación (16 caracteres) aplicada en `MAIL_PASS` del `.env` local (gitignored; no se escribe en el repo).
- Prueba de punta a punta: carta de ejemplo de la Unidad 5-25 (Bs. 144.801,73) → **texto breve + `Carta-Deuda-Unidad-5-25.pdf` (117.086 bytes, `%PDF-`)** encolada y despachada por el worker: `[QUEUE_ID: 904] Correo enviado exitosamente a azdeo142005@gmail.com` (1 procesado, 1 exitoso, 0 fallidos).
- Verificación en BD: fila 904 `estado=enviado`, `intentos=1`, `error_mensaje=NULL`; cuerpo descifrado con el aviso "no responda"; adjunto con nombre/mime/tamaño correctos; cola en 0 pendientes.
- El pipeline completo (UI "Enviar por Email" → cola cifrada → worker → SMTP Gmail con adjunto) queda operativo. Para producción: replicar las claves MAIL_* del `.env` en el servidor y programar el worker.
