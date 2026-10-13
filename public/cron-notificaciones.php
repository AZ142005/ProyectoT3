<?php
/**
 * Web-cron: dispara el worker de la cola de correos (OTP, cartas, avisos).
 *
 * InfinityFree (plan gratuito) no tiene cron ni SSH: un servicio externo
 * (p. ej. cron-job.org) llama a esta URL cada 5 minutos.
 *
 * Uso:   /cron-notificaciones.php?token=XXX[&limit=20]
 * Token: CRON_TOKEN del .env del servidor (el mismo que configures en el cron).
 *
 * Seguridad: hash_equals (timing-safe), límite de lote acotado, lockfile del
 * worker compartido con el CLI (no hay ejecuciones simultáneas).
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/helpers.php';
require_once __DIR__ . '/../app/Core/Encryption.php';
require_once __DIR__ . '/../app/Services/EmailService.php';
require_once __DIR__ . '/../app/Services/NotificationQueueWorker.php';

use App\Services\NotificationQueueWorker;

header('Content-Type: application/json; charset=utf-8');

// 1. Token obligatorio (CRON_TOKEN del .env; fallback a variable de entorno real)
$tokenEsperado = trim($_ENV['CRON_TOKEN'] ?? (getenv('CRON_TOKEN') ?: ''));
if ($tokenEsperado === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'CRON_TOKEN no configurado en el servidor.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenRecibido = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
if ($tokenRecibido === '' || !hash_equals($tokenEsperado, $tokenRecibido)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Ejecución acotada para contexto web
ignore_user_abort(true);
set_time_limit(120);

$limit = intval($_GET['limit'] ?? 20);
$limit = max(1, min(50, $limit));

try {
    $worker  = new NotificationQueueWorker();
    $resumen = $worker->run($limit);

    echo json_encode([
        'ok'         => ($resumen['estado'] !== 'error'),
        'estado'     => $resumen['estado'],
        'procesados' => $resumen['procesados'],
        'exitosos'   => $resumen['exitosos'],
        'fallidos'   => $resumen['fallidos'],
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error inesperado en el worker.'], JSON_UNESCAPED_UNICODE);
}
