# Plan de Tareas SDD: OCR en Imágenes para Módulo 3 (Agnóstico de Servidor)

- [x] **Tarea 1**: Refactorizar `PagoController::extraer` para admitir tanto `texto_extraido` (desde OCR cliente) como archivo `comprobante` (para PDF nativo), delegando el análisis a `ComprobanteParserService` y eliminando el mock simulado `rand()`.
- [x] **Tarea 2**: Extender `ComprobanteParserService` con método helper para parseo directo de texto crudo y actualización de `procesarArchivo` para soportar flujo de texto OCR opcional.
- [x] **Tarea 3**: Integrar Tesseract.js v5 vía CDN en `app/views/pagos/residente/subir.php`, implementando la lógica asíncrona de Web Worker, reporte de porcentaje de avance en el botón OCR y despacho al backend.
- [x] **Tarea 4**: Actualizar la suite de pruebas unitarias (`tests/ComprobanteParserTest.php`) y pruebas de comportamiento para validar el endpoint y el servicio con los nuevos flujos.
- [x] **Tarea 5**: Ejecutar suite completa de verificación:
  - `php tests/run.php` (276 tests, 664 assertions passing)
  - `php scripts/check_purity.php` (Éxito MVC)
  - `php scripts/audit_security.php` (Cero vulnerabilidades)
