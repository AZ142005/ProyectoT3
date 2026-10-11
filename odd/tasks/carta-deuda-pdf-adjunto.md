# Carta de Deuda por correo: PDF adjunto + texto breve (FPDF embebido)

## Objetivo
Cambiar el correo de la carta de deuda: en vez de redactar la carta completa en el cuerpo HTML, enviar **el modelo preparado en PDF como adjunto** junto con un **texto breve**, y dejar el pipeline listo para servidor. Además: remitente con nombre visible del conjunto (ya aplicado en `.env`, sin costo) y verificación de que nada tiene costo monetario de Gmail (ya verificado: SMTP gratis, sin APIs facturables).

## Decisiones
- **Motor PDF: FPDF 1.9 (Setasign) embebido en el repo** (`app/libraries/fpdf/`) — archivo único + fuentes core JSON + licencia permisiva (MIT-like, uso gratuito). Motivo: no requiere Composer ni instalación en el servidor (el deploy actual copia la carpeta; `vendor/` está semi-ignorado en git), estilo "PHP puro sin dependencias externas" del proyecto (como SmtpMailer).
- **Codificación**: FPDF con fuentes core usa Windows-1252; verificado empíricamente que NO convierte UTF-8 automáticamente. Todo texto pasa por un helper `txt()` (UTF-8 → Windows-1252). Sin caracteres fuera de cp1252 (la lista de pagos usa `•`, no `✓`).
- **Logo**: `public/img/logo_condominio.png` incrustado directo (FPDF soporta PNG con alpha/SMask); si falta el archivo, se omite sin romper.
- **Generación al encolar**: el PDF se genera en `enviarAvisoCobro()` y viaja cifrado en la cola (columna nueva `adjuntos`); el worker solo descifra y despacha (no regenera).
- **Papel**: Letter vertical, márgenes 20 mm, Helvetica/Helvetica-Bold.
- **Aviso de no responder**: ya agregado al pie de la plantilla (se conserva en el texto breve).

## Alcance
- `app/libraries/fpdf/` (nuevo, embebido): `fpdf.php`, `font/helvetica.json`, `font/helveticab.json`, `LICENSE.txt`.
- `app/services/CartaDeudaPdfService.php` (nuevo): `generar(array $detalle): string` — réplica del modelo oficial (membrete con logo, fecha, destinatario, cuerpo justificado, tabla Mes/Monto agrupada, párrafo legal, medios de pago, "Sírvase dar recibo…", firma + caja "Recibido Por"). Reutiliza `agruparFacturasPorMes`, `montoEnLetras`, `formatearMoneda`, `nombreMes`.
- `scripts/migrate_notificaciones_adjuntos.php` (nuevo, idempotente): `ALTER TABLE notificaciones_cola ADD COLUMN adjuntos MEDIUMTEXT NULL AFTER cuerpo_html`.
- `app/services/NotificationService.php`: `encolarNotificacion(..., ?array $adjuntos = null)` — guarda JSON (nombre/mime/contenido base64) cifrado; límite 2 MB por adjunto.
- `app/services/EmailService.php`: `enviar(..., array $adjuntos = [])` — smtp pasa adjuntos a SmtpMailer; mock los registra en el log; mail() los ignora (documentado).
- `app/services/SmtpMailer.php`: `send(..., array $adjuntos = [])` + mensaje `multipart/mixed` (html + PDF base64, filename ASCII).
- `scripts/process_notifications.php`: descifra `adjuntos`, los decodifica y los pasa a `enviar()`; payload corrupto → fallo con mensaje.
- `app/views/emails/aviso_cobro.php`: se convierte en **texto breve** (membrete + "adjuntamos la Carta de Deuda en PDF" + pie no-responder).
- `app/controllers/ReporteController.php::enviarAvisoCobro()`: genera PDF (error → flash y sin encolar), arma el breve, encola con adjunto `Carta-Deuda-Unidad-{n}.pdf`.
- Tests: `CartaDeudaPdfTest` (nuevo), `EmailAdjuntosTest` (nuevo), actualizar `CartaDeudaEnvioTest`, `EmailSmtpTest`, `EmailPlantillasTest`.

## Fuera de alcance
- No se toca `generarCartaDeuda` (vista web) ni el mensaje de WhatsApp.
- No se ejecuta el worker contra SMTP real en tests (la clave real sigue pendiente).
- Sin Composer ni dependencias nuevas en `vendor/`.

## Checklist
- [x] T1 (commit 1): FPDF embebido + `CartaDeudaPdfService` + `CartaDeudaPdfTest`
- [x] T2 (commit 2): migración `adjuntos` + pipeline (NotificationService/EmailService/SmtpMailer/worker) + plantilla breve + controlador + tests actualizados/nuevos
- [x] T3: verificación + evidencia; muestra `carta_deuda_ejemplo.pdf`

## Verificación
- `php -l` de cada PHP tocado; migración ejecutada en dev (idempotente).
- Filtros: `CartaDeudaPdf`, `EmailAdjuntos`, `Email`, `EmailPlantillas`, `CartaDeudaEnvio`, `CartaDeudaFormato`, `ArreglosAuditoria`, `Rbac`, `Notification`.
- Test de acentos: inflar streams del PDF y verificar `é` como byte único cp1252 (no UTF-8 doble).
- Verificador independiente sobre los commits.

## Notas
- TDD: off (runner custom). RDD nativo no evaluable en OpenCode → suites + verificador independiente.
- Convención: DIRECTO EN MAIN, local, sin push.
- Costo: FPDF gratis; Gmail SMTP gratis; sin servicios facturables.

## Entrega
- Commit 1 (FPDF + servicio + test): `83d574c` — `feat(carta-deuda): generador PDF del modelo oficial (FPDF embebido)` (6 archivos: `fpdf.php`, `font/helvetica.json`, `font/helveticab.json`, `LICENSE.txt`, `CartaDeudaPdfService.php`, `CartaDeudaPdfTest.php`).
- Commit 2 (pipeline + plantilla + controlador + tests): `28e3cb2` — `feat(correo): carta de deuda como adjunto PDF con texto breve` (12 archivos, incluido este doc). Este bloque de evidencia se añadió después con `git commit --amend` (solo doc), por lo que `HEAD` puede diferir del hash citado: un commit no puede contener su propio hash.
- `php -l` sin errores en los 14 archivos PHP tocados (fpdf.php, servicio PDF, migración, 3 servicios, worker, controlador, plantilla y 5 tests); la suite completa muestra además 2 skips de `EnvironmentTest` (permisos de directorios) ajenos al cambio.
- Migración ejecutada en dev: `php scripts/migrate_notificaciones_adjuntos.php` → columna `adjuntos` agregada a `notificaciones_cola`; segunda ejecución reporta "ya existe" (idempotente).
- Baseline previo (filtros): Email 13 tests/63 passed, EmailPlantillas 1/5, CartaDeudaEnvio 8/36, ArreglosAuditoria 13/33 (0 failed / 0 errors).
- Con ambos commits aplicados:
  - `php tests/run.php --filter=CartaDeudaPdf` → 3 tests | 10 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=CartaDeudaFormato` → 9 tests | 28 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=EmailAdjuntos` → 3 tests | 13 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=Email` → 17 tests | 86 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=EmailPlantillas` → 1 test | 7 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=CartaDeudaEnvio` → 8 tests | 43 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=Rbac` → 9 tests | 234 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=Notification` → 6 tests | 25 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=ArreglosAuditoria` → 13 tests | 33 passed | 0 failed | 0 errors.
- Suite completa (`php tests/run.php`): 65 clases alcanzadas | 581 checks passed | 0 failed | 0 errors; no cierra porque `SecurityTest` provoca el aborto conocido (el `exit` de la validación CSRF) antes del resumen, en el mismo punto que el baseline.
- Muestra real (generada, SIN commitear): `carta_deuda_ejemplo.pdf` en la raíz del repo — unidad id 3191 (numero `5-25`, edificio Torre 5, 4 facturas vencidas, total Bs. 144.801,73) vía `ReportesModel::obtenerDetalleDeudaUnidad`. Regenerada tras el pulido: 117.087 bytes; 1 página (conteo `/Type /Page`); inicia `%PDF-1.4`, cierra `%%EOF`; SHA256 `EAE2241129D358C99076038C069A68208E9B3F18BDB1CFCA4BB0F27F6429A473`.
- Test de acentos: sobre los streams de contenido del PDF (resueltos por `/Contents N 0 R`, inflados con `gzuncompress`) la `é` aparece como byte único cp1252 (`0xE9`) y ningún par UTF-8 (`0xC3 0xA9`); `CartaDeudaPdfTest` lo verifica.
- Rollback: revertir el commit 2 elimina migración, pipeline de adjuntos, plantilla breve, controlador y tests sin tocar `generarCartaDeuda`, el mensaje de WhatsApp ni `cartas_deuda_envios`; revertir el commit 1 elimina FPDF y el servicio PDF sin efecto en el resto del sistema.
- Verificación independiente (read-only) de `83d574c` y `f534be6`: **VERIFIED WITH OBSERVATIONS** — alcance exacto (18 rutas, unión veraz), FPDF byte a byte idéntico al upstream (SHA256), muestra validada (estructura, 1 página, texto cp1252 inflado con literales y montos contra BD), pipeline revisado y suites reproducidas. Sin fallos.
- Pulido post-verificación (commit de cierre posterior, ver `git log`): las celdas de la tabla del PDF usan `formatearMonto` (sin prefijo "Bs." porque el encabezado ya dice "Monto Bs."), igual que la carta web; muestra regenerada. Observaciones menores restantes sin impacto: nombre `LICENSE.txt` vs `license.txt` upstream, orden de cabeceras del mensaje single-part, y fallo de adjuntos corruptos en el worker verificado por lectura (no ejecutable con SMTP real).
