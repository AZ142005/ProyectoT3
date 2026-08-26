<?php
// Configuración de rutas del sistema
define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', BASE_PATH . '/app');
define('VIEWS_PATH', APP_PATH . '/views');
define('UPLOADS_PATH', BASE_PATH . '/uploads');

// Cargar variables de entorno desde .env si existe
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue; // Skip comments and empty lines
        }
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Constantes de entorno y Logs
define('LOG_PATH', BASE_PATH . '/logs/app.log');
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'condominio_cobranzas');
define('ENVIRONMENT', $_ENV['ENVIRONMENT'] ?? 'development');

// Crear directorio de logs si no existe
$logDir = dirname(LOG_PATH);
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
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

// Límites de uploads (defensa en profundidad, complementa php.ini)
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '10M');
ini_set('max_input_time', 60);
ini_set('max_execution_time', 30);
