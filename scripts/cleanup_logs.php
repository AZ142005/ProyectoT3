<?php
require_once __DIR__ . '/../app/config/config.php';

date_default_timezone_set('America/Caracas');

$logDir = BASE_PATH . '/app/logs';
if (!is_dir($logDir)) {
    echo "El directorio de logs no existe. Nada que limpiar.\n";
    exit(0);
}

$archivos = glob($logDir . '/notifications_*.log');
$ahora = time();
$limiteSegundos = 30 * 86400; // 30 días
$eliminados = 0;

echo "Iniciando rutina de limpieza de logs antiguos (> 30 días)...\n";

foreach ($archivos as $archivo) {
    if (is_file($archivo)) {
        $mtime = filemtime($archivo);
        if (($ahora - $mtime) > $limiteSegundos) {
            if (@unlink($archivo)) {
                echo "  🗑️ Eliminado: " . basename($archivo) . "\n";
                $eliminados++;
            }
        }
    }
}

echo "✨ Limpieza finalizada: {$eliminados} archivos de log purgados.\n";
