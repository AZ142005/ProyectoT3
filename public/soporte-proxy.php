<?php
/**
 * Proxy seguro para servir y descargar soportes digitales de gastos.
 * Exige sesión activa, sanea el nombre del archivo y lo sirve desde el
 * directorio protegido uploads/soportes/ (fuera de la raíz web).
 *
 * Uso:
 *   Visualización: /soporte-proxy.php?file=abc123.pdf
 *   Descarga:      /soporte-proxy.php?file=abc123.pdf&download=1
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Autenticación requerida (transparencia comunitaria: cualquier rol con sesión)
if (empty($_SESSION['auth_user']['id'])) {
    http_response_code(403);
    exit('Acceso denegado.');
}

// 2. Validación del parámetro de archivo (sin traversal; solo soportes válidos)
$filename = basename(trim($_GET['file'] ?? ''));
if ($filename === '' || $filename === '.' || $filename === '..') {
    http_response_code(400);
    exit('Parámetro inválido.');
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
    http_response_code(400);
    exit('Tipo de archivo no permitido.');
}

// 3. Búsqueda del archivo en el directorio protegido
$filepath = UPLOADS_PATH . '/soportes/' . $filename;
if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

// 4. Determinación del MIME real
$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
$mimeType = $finfo ? finfo_file($finfo, $filepath) : 'application/octet-stream';
if ($finfo) {
    if (\PHP_VERSION_ID < 80500 && \is_resource($finfo)) {
        @finfo_close($finfo);
    }
    unset($finfo);
}

// 5. Descarga forzada (attachment) o visualización inline
$isDownload = !empty($_GET['download']) && in_array(strtolower(trim($_GET['download'])), ['1', 'true', 'yes'], true);

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filepath));
header('X-Content-Type-Options: nosniff');

if ($isDownload) {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-transform, no-store, must-revalidate');
    header('Pragma: no-cache');
} else {
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=3600');
}

readfile($filepath);
exit;
