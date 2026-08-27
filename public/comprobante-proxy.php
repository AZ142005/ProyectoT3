<?php
/**
 * Proxy seguro para servir comprobantes de pago.
 * Valida sesión activa antes de servir el archivo.
 *
 * Uso: /comprobante-proxy.php?file=abc123.jpg
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

session_start();

// Solo usuarios autenticados pueden ver comprobantes
if (empty($_SESSION['auth_user']['id'])) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$filename = basename(trim($_GET['file'] ?? ''));
if (empty($filename) || $filename === '.' || $filename === '..') {
    http_response_code(400);
    exit('Parámetro inválido.');
}

$filepath = UPLOADS_PATH . '/comprobantes/' . $filename;

if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

// Determinar MIME type
$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
$mimeType = $finfo ? finfo_file($finfo, $filepath) : 'application/octet-stream';
if ($finfo) finfo_close($finfo);

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filepath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

readfile($filepath);
exit;