# change-002: Extracción OCR de Comprobantes en Imágenes (Módulo 3 - RF 15)

## Status
COMPLETED

## Description
Implementar el motor de extracción OCR de comprobantes de pago a partir de imágenes (capturas de pantalla en formato JPG, JPEG y PNG) en el Módulo 3 (RF 15) con arquitectura 100% agnóstica de servidor. Se conecta `ComprobanteParserService` mediante Tesseract.js Web Workers en el navegador y extracción nativa FlateDecode para PDF en el servidor con el endpoint `/pagos/extraer` de `PagoController` y la interfaz de usuario en `subir.php`.

## Scope
- [x] Backend: Servicio `ComprobanteParserService` (método `procesarTextoOcr()` y ampliación de `procesarArchivo()` para admitir texto OCR).
- [x] Backend: Controlador `PagoController::extraer` (reemplazo del mock simulado por procesamiento real con rate limiting y validación de entrada).
- [x] Frontend: `app/views/pagos/residente/subir.php` (integración con Tesseract.js v5 CDN, progreso dinámico del escaneo y mapeo a campos del formulario).
- [x] Tests: Pruebas unitarias en `tests/ComprobanteParserTest.php` y suite completa `tests/run.php`.

## Acceptance Criteria
- [x] Procesamiento exitoso de imágenes JPG, JPEG y PNG de capturas bancarias comunes (Banesco, Mercantil, BDV, Provincial).
- [x] El endpoint `/pagos/extraer` no retorna datos simulados `rand()`, sino los datos reales analizados del archivo subido o texto OCR.
- [x] Si la imagen no contiene texto reconocible o es ilegible, devuelve `detectado: false` de manera limpia sin fallar.
- [x] Cumplimiento total de pureza MVC (`check_purity.php`), auditoría OWASP (`audit_security.php`) y suite de pruebas (`tests/run.php`).
