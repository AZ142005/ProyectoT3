<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/helpers.php';
require_once __DIR__ . '/../app/core/Encryption.php';
require_once __DIR__ . '/../app/services/EmailService.php';

use App\Core\Database;
use App\Core\Encryption;
use App\Services\EmailService;

date_default_timezone_set('America/Caracas');

$logDir = BASE_PATH . '/app/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$todayLog = $logDir . '/notifications_' . date('Y-m-d') . '.log';

function logWorker(string $message, string $level = 'INFO') {
    global $todayLog;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($todayLog, $line, FILE_APPEND);
    if (php_sapi_name() === 'cli') {
        echo $line;
    }
}

// 1. Control de Lockfile con Stale Recovery (5 minutos)
$lockFile = $logDir . '/notifications.lock';
if (file_exists($lockFile)) {
    $lockTime = intval(@file_get_contents($lockFile));
    if (time() - $lockTime < 300) {
        logWorker("Worker ya se encuentra en ejecución. Lock vigente. Finalizando.", "WARNING");
        exit(0);
    }
    logWorker("Detectado lock desactualizado (> 5 min). Rompiendo stale lock y recuperando proceso.", "WARNING");
}

file_put_contents($lockFile, time());

register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
});

logWorker("Iniciando ejecución del worker de cola de notificaciones...");

try {
    $db = Database::getConnection();
    $emailService = new EmailService();
    $batchSize = intval(getenv('QUEUE_BATCH_SIZE') ?: 50);

    // Intentar consulta con SKIP LOCKED o Fallback con GET_LOCK
    $hasSkipLocked = true;
    try {
        $stmt = $db->prepare("
            SELECT * FROM notificaciones_cola 
            WHERE estado = 'pendiente' AND (proximo_intento IS NULL OR proximo_intento <= NOW()) 
            ORDER BY FIELD(prioridad, 'alta', 'normal', 'baja'), fecha_registro ASC 
            LIMIT {$batchSize} FOR UPDATE SKIP LOCKED
        ");
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        // Fallback para MySQL 5.7 / MariaDB sin SKIP LOCKED
        $hasSkipLocked = false;
        $db->query("SELECT GET_LOCK('notificaciones_worker_lock', 10)");
        
        $stmt = $db->prepare("
            SELECT * FROM notificaciones_cola 
            WHERE estado = 'pendiente' AND (proximo_intento IS NULL OR proximo_intento <= NOW()) 
            ORDER BY FIELD(prioridad, 'alta', 'normal', 'baja'), fecha_registro ASC 
            LIMIT {$batchSize}
        ");
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($filas)) {
        logWorker("No existen notificaciones pendientes para procesar.");
        if (!$hasSkipLocked) {
            $db->query("SELECT RELEASE_LOCK('notificaciones_worker_lock')");
        }
        exit(0);
    }

    logWorker("Se encontraron " . count($filas) . " notificaciones pendientes de procesamiento.");

    $procesados = 0;
    $exitosos = 0;
    $fallidos = 0;

    foreach ($filas as $row) {
        $id = $row['id'];
        $procesados++;

        // Paso A: Descifrado criptográfico seguro
        try {
            $destinatario = Encryption::decrypt($row['destinatario_email']);
            $cuerpoHtml = Encryption::decrypt($row['cuerpo_html']);
        } catch (\Throwable $e) {
            logWorker("[QUEUE_ID: {$id}] Error de descifrado criptográfico: " . $e->getMessage(), "ERROR");
            
            $db->prepare("
                UPDATE notificaciones_cola 
                SET estado = 'fallido', error_mensaje = :err 
                WHERE id = :id
            ")->execute([
                'err' => 'Error de descifrado criptográfico: ' . $e->getMessage(),
                'id'  => $id
            ]);
            $fallidos++;
            continue;
        }

        // Paso B: Intento de despacho SMTP / Mail
        try {
            $enviado = $emailService->enviar($destinatario, $row['asunto'], $cuerpoHtml);

            if ($enviado) {
                $db->prepare("
                    UPDATE notificaciones_cola 
                    SET estado = 'enviado', proximo_intento = NULL, intentos = intentos + 1 
                    WHERE id = :id
                ")->execute(['id' => $id]);

                logWorker("[QUEUE_ID: {$id}] Correo enviado exitosamente a {$destinatario}.");
                $exitosos++;
            } else {
                throw new Exception("Fallo en el transporte de correo (mail/smtp).");
            }
        } catch (\Throwable $e) {
            $intentosActuales = intval($row['intentos']) + 1;
            $nuevoEstado = ($intentosActuales >= 3) ? 'fallido' : 'pendiente';

            // Fórmula SQL de backoff exponencial: (2^intentos) * 60 segundos
            $stmtUpdate = $db->prepare("
                UPDATE notificaciones_cola 
                SET intentos = :intentos,
                    proximo_intento = NOW() + INTERVAL (POW(2, :intentos_exp) * 60) SECOND,
                    error_mensaje = :error,
                    estado = :estado
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                'intentos'     => $intentosActuales,
                'intentos_exp' => $intentosActuales,
                'error'        => $e->getMessage(),
                'estado'       => $nuevoEstado,
                'id'           => $id
            ]);

            logWorker("[QUEUE_ID: {$id}] Fallo en el envío (Intento {$intentosActuales}/3): " . $e->getMessage(), "ERROR");
            $fallidos++;
        }
    }

    if (!$hasSkipLocked) {
        $db->query("SELECT RELEASE_LOCK('notificaciones_worker_lock')");
    }

    logWorker("Worker finalizado: {$procesados} procesados, {$exitosos} exitosos, {$fallidos} fallidos.");

} catch (\Throwable $e) {
    logWorker("Error crítico en worker: " . $e->getMessage(), "ERROR");
    exit(1);
}
