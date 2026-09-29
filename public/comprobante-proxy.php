<?php
/**
 * Proxy seguro para servir y descargar comprobantes de pago.
 * Valida sesión activa, autorización por rol y cabeceras de visualización/descarga.
 *
 * Uso:
 *   Visualización: /comprobante-proxy.php?file=abc123.jpg
 *   Descarga:      /comprobante-proxy.php?file=abc123.jpg&download=1
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Autenticación requerida
if (empty($_SESSION['auth_user']['id'])) {
    http_response_code(403);
    exit('Acceso denegado.');
}

// 2. Validación del parámetro de archivo
$filename = basename(trim($_GET['file'] ?? ''));
if (empty($filename) || $filename === '.' || $filename === '..') {
    http_response_code(400);
    exit('Parámetro inválido.');
}

// 3. Control de acceso por rol (BOLA / IDOR mitigation)
$userId = intval($_SESSION['auth_user']['id']);
$userRole = $_SESSION['auth_user']['role'] ?? '';

if ($userRole === 'residente') {
    try {
        $db = \App\Core\Database::getConnection();
        $stmt = $db->prepare(
            "SELECT 1 FROM pagos WHERE archivo = :archivo AND residente_id = :uid 
             UNION 
             SELECT 1 FROM comprobantes_pago WHERE archivo = :archivo2 AND residente_id = :uid2 
             LIMIT 1"
        );
        $stmt->execute([
            'archivo'  => $filename,
            'uid'      => $userId,
            'archivo2' => $filename,
            'uid2'     => $userId
        ]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            exit('Acceso denegado: no autorizado para acceder a este comprobante.');
        }
    } catch (\Exception $e) {
        error_log("[COMPROBANTE_PROXY] Error al validar autorización: " . $e->getMessage());
        http_response_code(500);
        exit('Error al procesar la solicitud.');
    }
}

// 4. Búsqueda y resolución de ruta del archivo en el servidor
$filepath = UPLOADS_PATH . '/comprobantes/' . $filename;
if (!file_exists($filepath) || !is_file($filepath)) {
    // Fallback en directorio uploads general
    $fallback = UPLOADS_PATH . '/' . $filename;
    if (file_exists($fallback) && is_file($fallback)) {
        $filepath = $fallback;
    } else {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }
}

// 5. Determinar MIME type
$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
$mimeType = $finfo ? finfo_file($finfo, $filepath) : 'application/octet-stream';
if ($finfo) {
    if (\PHP_VERSION_ID < 80500 && \is_resource($finfo)) {
        @finfo_close($finfo);
    }
    unset($finfo);
}

// 6. Determinar modo: descarga forzada (attachment) o visualización inline
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