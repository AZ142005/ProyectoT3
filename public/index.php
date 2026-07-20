<?php
// Cargar dependencias de Composer y constantes del sistema
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

// Configuración reforzada de seguridad para cookies de sesión
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '', // Vacío para desarrollo local
    'secure' => false, // false para desarrollo sin HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generación global del token CSRF para formularios
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Limpieza y parseo de la URI solicitada
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Ajustar la ruta si el proyecto corre en un subdirectorio (ej. /ProyectoT3/public/)
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptName === '/' || $scriptName === '\\') {
    $scriptName = '';
}
$route = substr($uri, strlen($scriptName));
if (empty($route)) {
    $route = '/';
}
$route = '/' . ltrim($route, '/');

// Middleware global de verificación CSRF para cualquier petición POST
\App\Core\Security::validateCSRF();

// Enrutador principal de la aplicación (Front Controller)
switch ($route) {
    // --- Autenticación Unificada ---
    case '/':
    case '/login':
    case '/auth/login':
        $controller = new \App\Controllers\AuthController();
        $controller->login();
        break;

    case '/auth/register':
        $controller = new \App\Controllers\AuthController();
        $controller->register();
        break;

    case '/logout':
    case '/auth/logout':
        $controller = new \App\Controllers\AuthController();
        $controller->logout();
        break;

    // --- Compatibilidad Legacy Admin Auth ---
    case '/admin/login':
        $controller = new \App\Controllers\AdminAuthController();
        $controller->login();
        break;

    case '/admin/logout':
        $controller = new \App\Controllers\AdminAuthController();
        $controller->logout();
        break;

    // --- Portal Residente ---
    case '/residente/dashboard':
        $controller = new \App\Controllers\ResidenteController();
        $controller->dashboard();
        break;

    case '/residente/enviar-pago':
        $controller = new \App\Controllers\ResidenteController();
        $controller->enviarPago();
        break;

    case '/residente/historial':
        $controller = new \App\Controllers\ResidenteController();
        $controller->historial();
        break;

    // --- Portal Administración ---
    case '/admin/dashboard':
        $controller = new \App\Controllers\AdminController();
        $controller->dashboard();
        break;

    case '/admin/comprobantes':
        $controller = new \App\Controllers\AdminController();
        $controller->listarComprobantes();
        break;

    case '/admin/comprobante/verificar':
        $controller = new \App\Controllers\AdminController();
        $controller->verificarComprobante();
        break;

    case '/admin/facturas/generar':
        $controller = new \App\Controllers\AdminController();
        $controller->generarFacturas();
        break;

    // --- Estructura del Conjunto (Edificios y Unidades) ---
    case '/admin/estructura':
        $controller = new \App\Controllers\EstructuraController();
        $controller->index();
        break;

    case '/admin/estructura/edificio/guardar':
        $controller = new \App\Controllers\EstructuraController();
        $controller->guardarEdificio();
        break;

    case '/admin/estructura/edificio/toggle':
        $controller = new \App\Controllers\EstructuraController();
        $controller->toggleEdificio();
        break;

    case '/admin/estructura/unidad/guardar':
        $controller = new \App\Controllers\EstructuraController();
        $controller->guardarUnidad();
        break;

    case '/admin/estructura/unidad/toggle':
        $controller = new \App\Controllers\EstructuraController();
        $controller->toggleUnidad();
        break;

    default:
        http_response_code(404);
        $errorView = VIEWS_PATH . '/errors/404.php';
        if (file_exists($errorView)) {
            require_once $errorView;
        } else {
            echo "<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>";
            echo "<h1>Error 404: Página no encontrada</h1>";
            echo "<p>La ruta '" . e($route) . "' no existe en este servidor.</p>";
            echo "<a href='/'>Volver al inicio</a>";
            echo "</div>";
        }
        break;
}
