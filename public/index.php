<?php
// Cargar dependencias de Composer y constantes del sistema
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Controllers\AdminAuthController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\EstructuraController;
use App\Controllers\PagoController;
use App\Controllers\ResidenteController;
use App\Core\Router;
use App\Core\Security;
use App\Core\UserRole;

// Configuración reforzada de seguridad para cookies de sesión
session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'domain'   => '', // Vacío para desarrollo local
    'secure'   => (ENVIRONMENT === 'production'),
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline' 'unsafe-eval'; style-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com https://fonts.googleapis.com data:; img-src 'self' data: https: blob:; connect-src 'self';");
if (ENVIRONMENT === 'production') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Exception handler global — captura errores no manejados
set_exception_handler(function (Throwable $e) {
    error_log("[EXCEPTION] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    http_response_code(500);

    if (ENVIRONMENT === 'development') {
        echo "<h2>Error 500: " . e($e->getMessage()) . "</h2>";
        echo "<pre>" . e($e->getTraceAsString()) . "</pre>";
    } else {
        $errorView = VIEWS_PATH . '/errors/500.php';
        if (file_exists($errorView)) {
            require $errorView;
        } else {
            echo "<h2>Error interno del servidor</h2>";
            echo "<p>Ha ocurrido un error inesperado. Por favor, intenta de nuevo.</p>";
        }
    }
    exit;
});

// Normalización de URI solicitada
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
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
Security::validateCSRF();

// Instanciación y registro de rutas en el Router declarativo
$router = new Router();

// --- Módulo de Autenticación ---
$router->any('/', [AuthController::class, 'login']);
$router->any('/login', [AuthController::class, 'login']);
$router->any('/auth/login', [AuthController::class, 'login']);
$router->any('/auth/register', [AuthController::class, 'register']);
$router->get('/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/auth/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/admin/login', [AdminAuthController::class, 'login']);
$router->get('/admin/logout', [AdminAuthController::class, 'logout'], ['auth']);

// --- Módulo de Pagos ---
$router->get('/pagos', [PagoController::class, 'listar'], ['auth']);
$router->get('/pagos/nuevo', [PagoController::class, 'nuevo'], [UserRole::RESIDENTE]);
$router->post('/pagos/subir', [PagoController::class, 'subir'], [UserRole::RESIDENTE]);
$router->any('/pagos/extraer', [PagoController::class, 'extraer'], ['auth']);
$router->post('/pagos/cambiar-estado', [PagoController::class, 'cambiarEstado'], [UserRole::ADMIN]);
$router->post('/admin/pagos/aprobar-masivo', [PagoController::class, 'aprobarMasivo'], [UserRole::ADMIN]);
$router->get('/pagos/detalle/{id}', [PagoController::class, 'detalle'], ['auth']);

// --- Módulo de Residente ---
$router->get('/residente/dashboard', [ResidenteController::class, 'dashboard'], [UserRole::RESIDENTE]);
$router->any('/residente/enviar-pago', [ResidenteController::class, 'enviarPago'], [UserRole::RESIDENTE]);
$router->get('/residente/historial', [ResidenteController::class, 'historial'], [UserRole::RESIDENTE]);

// --- Módulo de Administración ---
$router->get('/admin/dashboard', [AdminController::class, 'dashboard'], [UserRole::ADMIN]);
$router->get('/admin/comprobantes', [AdminController::class, 'listarComprobantes'], [UserRole::ADMIN]);
$router->any('/admin/comprobante/verificar', [AdminController::class, 'verificarComprobante'], [UserRole::ADMIN]);
$router->any('/admin/facturas/generar', [AdminController::class, 'generarFacturas'], [UserRole::ADMIN]);

// --- Módulo de Estructura del Conjunto ---
$router->get('/admin/estructura', [EstructuraController::class, 'index'], [UserRole::ADMIN]);
$router->post('/admin/estructura/edificio/guardar', [EstructuraController::class, 'guardarEdificio'], [UserRole::ADMIN]);
$router->post('/admin/estructura/edificio/toggle', [EstructuraController::class, 'toggleEdificio'], [UserRole::ADMIN]);
$router->post('/admin/estructura/unidad/guardar', [EstructuraController::class, 'guardarUnidad'], [UserRole::ADMIN]);
$router->post('/admin/estructura/unidad/toggle', [EstructuraController::class, 'toggleUnidad'], [UserRole::ADMIN]);

// --- Módulo de Estacionamientos y Vehículos (RF 12) ---
$router->get('/admin/estacionamientos', [\App\Controllers\EstacionamientoController::class, 'index'], [UserRole::ADMIN]);
$router->post('/admin/estacionamientos/guardar', [\App\Controllers\EstacionamientoController::class, 'guardar'], [UserRole::ADMIN]);
$router->post('/admin/estacionamientos/asignar', [\App\Controllers\EstacionamientoController::class, 'asignar'], [UserRole::ADMIN]);
$router->post('/admin/estacionamientos/eliminar', [\App\Controllers\EstacionamientoController::class, 'eliminar'], [UserRole::ADMIN]);
$router->post('/admin/vehiculos/guardar', [\App\Controllers\EstacionamientoController::class, 'guardarVehiculo'], ['auth']);
$router->post('/admin/vehiculos/eliminar', [\App\Controllers\EstacionamientoController::class, 'eliminarVehiculo'], ['auth']);

// --- Módulo de Reportes de Morosidad (RF 23) ---
$router->get('/admin/reportes/morosidad', [\App\Controllers\ReporteController::class, 'morosidad'], [UserRole::ADMIN]);
$router->get('/admin/reportes/morosidad/imprimir', [\App\Controllers\ReporteController::class, 'imprimirMorosidad'], [UserRole::ADMIN]);
$router->get('/admin/reportes/morosidad/exportar-csv', [\App\Controllers\ReporteController::class, 'exportarCsv'], [UserRole::ADMIN]);

// Despachar la petición
$router->dispatch($_SERVER['REQUEST_METHOD'], $route);
