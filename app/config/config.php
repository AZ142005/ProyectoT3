<?php
// Configuración de rutas del sistema
define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', BASE_PATH . '/app');
define('VIEWS_PATH', APP_PATH . '/views');
define('UPLOADS_PATH', BASE_PATH . '/uploads');

// Constantes de entorno y Logs
define('ENVIRONMENT', 'development'); // 'development' o 'production'
define('LOG_PATH', BASE_PATH . '/logs/app.log');

// Credenciales de Base de Datos (Basado en el archivo heredado)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'condominio_cobranzas');

// Crear directorio de logs si no existe
$logDir = dirname(LOG_PATH);
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Configuración de visualización y reporte de errores según el entorno
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH);
}
