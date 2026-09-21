# Especificación Técnica: Motor OCR Agnóstico para Comprobantes (RF 15)

## 1. Contexto y Objetivos
El SRS exige en el **RF 15**:
> "El sistema debe integrar un motor de extracción de datos para sugerir el llenado automático de campos al analizar el comprobante cargado."

Para garantizar que la aplicación funcione en **cualquier servidor web** (Linux, Apache, Nginx, Docker, VPS o hosting compartido) sin requerir acceso root ni instalación de binarios externos (como Tesseract CLI o PowerShell):
- Los **PDF** se procesan en el backend con el parser nativo en PHP Vanilla (`FlateDecode` con descompresión de flujos gzip/deflate, sin librerías externas).
- Las **Imágenes** (capturas de pantalla en JPG, JPEG, PNG) se procesan en el cliente mediante **Tesseract.js / WebAssembly**, enviando el texto reconocido al backend para su estructuración bancaria.

## 2. Requerimientos de la Especificación
- **Cero dependencias de servidor**: El servidor no requiere librerías binarias ni herramientas de sistema operativo compiladas.
- **Canalización Híbrida Inteligente**:
  - Si el usuario sube un **PDF**, el frontend delega al backend la extracción directa vía `ComprobanteParserService::extraerTextoDePdf()`.
  - Si el usuario sube una **Imagen**, el frontend inicializa el Web Worker de Tesseract.js con diccionario en español (`spa`), reporta el progreso visual en la UI (0% a 100%), y envía el texto reconocido al endpoint `/pagos/extraer`.
- **Heurísticas Bancarias Unificadas (Backend)**:
  - Todo texto (provenga de PDF nativo o de OCR en el cliente) es validado por `ComprobanteParserService::analizarTexto()`.
  - Detección de bancos venezolanos (Banesco, Mercantil, Venezuela, Provincial, Bancamiga, Pago Móvil).
  - Detección de referencias numéricas (6 a 12 dígitos).
  - Normalización de montos sin inflación 100x (distinción venezolana `1.250,50` vs internacional `1250.50`).
  - Detección y formato de fechas (`AAAA-MM-DD`).
- **Seguridad**:
  - Endpoint `/pagos/extraer` protegido con `Auth::requireLogin()`, CSRF y Rate Limiting (20 peticiones/minuto por usuario).
  - Sanitización del texto recibido contra inyecciones y límite de longitud (máx. 50 KB de texto plano).
