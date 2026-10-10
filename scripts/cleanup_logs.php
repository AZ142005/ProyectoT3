<?php
/**
 * Script de limpieza y rotación de logs.
 *
 * 1. Purga los archivos notifications_*.log con más de 30 días de antigüedad.
 * 2. Rota los logs de append continuo (app/logs/mail_mock.log, storage/logs/backups.log
 *    y storage/logs/cron_backup.log) cuando superan 2 MB: el archivo pasa a <nombre>.1,
 *    reemplazando la rotación anterior.
 *
 * Windows: la tarea programada "Condominio-Logs" (creada por scripts/setup_scheduled_tasks.ps1)
 * ejecuta este script a diario a las 02:15.
 */
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

// Rotación de logs de append continuo (> 2 MB → <nombre>.1)
$umbralRotacion = 2 * 1024 * 1024; // 2 MB
$rotados = 0;
$logsContinuos = [
    BASE_PATH . '/app/logs/mail_mock.log',
    BASE_PATH . '/storage/logs/backups.log',
    BASE_PATH . '/storage/logs/cron_backup.log',
];

echo "\nIniciando rutina de rotación de logs de append continuo (> 2 MB)...\n";

foreach ($logsContinuos as $log) {
    if (!is_file($log) || filesize($log) <= $umbralRotacion) {
        continue; // No existe o aún no alcanza el umbral
    }

    $rotado = $log . '.1';
    if (file_exists($rotado)) {
        @unlink($rotado); // En Windows el rename falla si el destino ya existe
    }

    if (@rename($log, $rotado)) {
        echo "  🔄 Rotado: " . basename($log) . " → " . basename($rotado) . "\n";
        $rotados++;
    }
}

echo "✨ Limpieza finalizada: {$eliminados} archivos de log purgados, {$rotados} archivo(s) rotado(s).\n";
