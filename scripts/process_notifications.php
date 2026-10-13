<?php
/**
 * Worker CLI de la cola de notificaciones (correos).
 *
 * Ejecutable local:  php scripts/process_notifications.php
 * En hostings sin cron/SSH (p. ej. InfinityFree) el disparador es el
 * web-cron:  public/cron-notificaciones.php?token=...
 * (ver docs/despliegue-infinityfree.md — sección "Correo en producción").
 */
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/helpers.php';
require_once __DIR__ . '/../app/Core/Encryption.php';
require_once __DIR__ . '/../app/Services/EmailService.php';
require_once __DIR__ . '/../app/Services/NotificationQueueWorker.php';

use App\Services\NotificationQueueWorker;

$worker = new NotificationQueueWorker();
$resumen = $worker->run();

if (php_sapi_name() === 'cli') {
    foreach ($worker->getSalida() as $linea) {
        echo $linea . "\n";
    }
}

exit($resumen['estado'] === 'error' ? 1 : 0);
