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
use App\Core\Auth;
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

// Session timeout: auto-expire after 30 minutes of inactivity
if (Auth::check() && isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    $flash = $_SESSION['flash'] ?? null;
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash'] = $flash ?? [];
    $_SESSION['flash']['warning'] = 'Su sesión ha expirado por inactividad. Por favor, inicie sesión nuevamente.';
    header('Location: /auth/login');
    exit;
}
if (Auth::check()) {
    $_SESSION['last_activity'] = time();
}

// Generar token CSRF si no existe en la sesión actual
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Security headers
header_remove('X-Powered-By');
ini_set('expose_php', 'Off');
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
    error_log("[EXCEPTION] " . sanitize_exception_message($e) . " in " . basename($e->getFile()) . ":" . $e->getLine());

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
$router->post('/admin/comprobante/verificar', [AdminController::class, 'verificarComprobante'], [UserRole::ADMIN]);
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
$router->post('/admin/vehiculos/guardar', [\App\Controllers\EstacionamientoController::class, 'guardarVehiculo'], [UserRole::ADMIN]);
$router->post('/admin/vehiculos/eliminar', [\App\Controllers\EstacionamientoController::class, 'eliminarVehiculo'], [UserRole::ADMIN]);

// --- Módulo de Reportes de Morosidad y Cartas de Deuda (RF 23, RF 25) ---
$router->get('/admin/reportes/morosidad', [\App\Controllers\ReporteController::class, 'morosidad'], [UserRole::ADMIN]);
$router->get('/admin/reportes/morosidad/imprimir', [\App\Controllers\ReporteController::class, 'imprimirMorosidad'], [UserRole::ADMIN]);
$router->get('/admin/reportes/morosidad/exportar-csv', [\App\Controllers\ReporteController::class, 'exportarCsv'], [UserRole::ADMIN]);
$router->get('/admin/reportes/carta-deuda/{unidadId}', [\App\Controllers\ReporteController::class, 'generarCartaDeuda'], [UserRole::ADMIN]);
$router->post('/admin/reportes/enviar-aviso-cobro', [\App\Controllers\ReporteController::class, 'enviarAvisoCobro'], [UserRole::ADMIN]);

// --- Módulo de Notificaciones y Comunicados (RF 35, RF 36, RF 37) ---
$router->get('/admin/comunicados', [\App\Controllers\ComunicadoController::class, 'index'], [UserRole::ADMIN]);
$router->post('/admin/comunicados/guardar', [\App\Controllers\ComunicadoController::class, 'guardar'], [UserRole::ADMIN]);
$router->post('/admin/comunicados/eliminar', [\App\Controllers\ComunicadoController::class, 'eliminar'], [UserRole::ADMIN]);

$router->get('/residente/notificaciones', [\App\Controllers\NotificacionController::class, 'index'], [UserRole::RESIDENTE]);
$router->get('/residente/notificaciones/cantidad-no-leidas', [\App\Controllers\NotificacionController::class, 'cantidadNoLeidas'], [UserRole::RESIDENTE]);
$router->post('/residente/notificaciones/marcar-leida', [\App\Controllers\NotificacionController::class, 'marcarLeida'], [UserRole::RESIDENTE]);
$router->get('/residente/cartelera', [\App\Controllers\ComunicadoController::class, 'carteleraResidente'], [UserRole::RESIDENTE]);

// --- Perfil y Solicitudes de Cambio de Datos (RF 9) ---
$router->get('/perfil', [\App\Controllers\PerfilController::class, 'verPerfil'], ['auth']);
$router->post('/perfil/solicitar-cambio', [\App\Controllers\PerfilController::class, 'solicitarCambio'], ['auth']);
$router->get('/admin/solicitudes-datos', [\App\Controllers\PerfilController::class, 'listarSolicitudes'], [UserRole::ADMIN]);
$router->post('/admin/solicitudes-datos/procesar', [\App\Controllers\PerfilController::class, 'procesarSolicitud'], [UserRole::ADMIN]);

// --- Módulo de Conciliación Bancaria Inteligente (RF 26, RF 27, RF 28) ---
$router->get('/admin/conciliacion', [\App\Controllers\ConciliacionController::class, 'index'], [UserRole::ADMIN]);
$router->post('/admin/conciliacion/importar', [\App\Controllers\ConciliacionController::class, 'importarExtracto'], [UserRole::ADMIN]);
$router->post('/admin/conciliacion/conciliar', [\App\Controllers\ConciliacionController::class, 'conciliarPago'], [UserRole::ADMIN]);
$router->post('/admin/conciliacion/conciliar-lote', [\App\Controllers\ConciliacionController::class, 'conciliarLote'], [UserRole::ADMIN]);

// --- Módulo de Gastos Comunes y Rendición de Cuentas (RF 30 - RF 34, RF 21, RF 22) ---
$router->get('/admin/gastos', [\App\Controllers\GastoController::class, 'index'], [UserRole::ADMIN]);
$router->post('/admin/gastos/guardar', [\App\Controllers\GastoController::class, 'guardar'], [UserRole::ADMIN]);
$router->post('/admin/gastos/eliminar', [\App\Controllers\GastoController::class, 'eliminar'], [UserRole::ADMIN]);
$router->get('/residente/gastos', [\App\Controllers\GastoController::class, 'rendicionResidente'], [UserRole::RESIDENTE]);

// --- Módulo de Estado de Cuenta y Libro Mayor (RF 18, RF 19, RF 20, RF 24) ---
$router->get('/residente/estado-cuenta', [\App\Controllers\EstadoCuentaController::class, 'index'], [UserRole::RESIDENTE]);
$router->get('/residente/estado-cuenta/imprimir', [\App\Controllers\EstadoCuentaController::class, 'imprimir'], [UserRole::RESIDENTE]);

// --- Autenticación 2FA (RF 3) ---
$router->get('/auth/verificar-2fa', [\App\Controllers\AuthController::class, 'verificar2faView']);
$router->post('/auth/verificar-2fa', [\App\Controllers\AuthController::class, 'procesar2fa']);
$router->post('/auth/reenviar-otp', [\App\Controllers\AuthController::class, 'reenviarOtp']);
$router->post('/perfil/2fa/toggle', [\App\Controllers\PerfilController::class, 'toggle2fa'], ['auth']);

// --- APIs con Autenticación JWT y Refresh Tokens (RF 6) ---
$router->post('/api/v1/auth/login', [\App\Controllers\ApiController::class, 'login']);
$router->post('/api/v1/auth/refresh', [\App\Controllers\ApiController::class, 'refresh']);
$router->get('/api/v1/residente/estado-cuenta', [\App\Controllers\ApiController::class, 'estadoCuenta']);

// --- Módulo de Auditoría y Fiscalización de Solo Lectura (RF 8) ---
$router->get('/auditor/dashboard', [\App\Controllers\AuditorController::class, 'dashboard'], [UserRole::AUDITOR, UserRole::ADMIN]);
$router->get('/auditor/log-transacciones', [\App\Controllers\AuditorController::class, 'logTransacciones'], [UserRole::AUDITOR, UserRole::ADMIN]);
$router->get('/auditor/exportar-log', [\App\Controllers\AuditorController::class, 'exportarLog'], [UserRole::AUDITOR, UserRole::ADMIN]);

// --- Extracción Asistida de Comprobantes (RF 15) ---
$router->post('/pagos/analizar-comprobante', [\App\Controllers\PagoController::class, 'analizarComprobante'], [UserRole::RESIDENTE]);

// --- Gestión Administrativa de Respaldos de Base de Datos (RNF 3) ---
$router->get('/admin/respaldos', [\App\Controllers\RespaldoController::class, 'index'], [UserRole::ADMIN]);
$router->post('/admin/respaldos/generar', [\App\Controllers\RespaldoController::class, 'generarManual'], [UserRole::ADMIN]);
$router->get('/admin/respaldos/descargar/{id}', [\App\Controllers\RespaldoController::class, 'descargar'], [UserRole::ADMIN]);

// Despachar la petición
$router->dispatch($_SERVER['REQUEST_METHOD'], $route);
