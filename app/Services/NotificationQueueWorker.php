<?php
namespace App\Services;

use PDO;
use App\Core\Database;
use App\Core\Encryption;

/**
 * Worker de la cola de notificaciones (correo: OTP, cartas, avisos).
 *
 * Compartido por dos disparadores, con la misma lógica y lockfile:
 *   - CLI local:           scripts/process_notifications.php
 *   - Web-cron (hosting):  public/cron-notificaciones.php?token=...
 *
 * Conserva el lockfile con stale recovery (5 min) y el backoff exponencial
 * originales. No usa exit(): devuelve un resumen para que cada entrada
 * (CLI o HTTP) decida su salida. El entorno (config, Database, Encryption,
 * EmailService) debe estar cargado por el disparador (requires manuales).
 */
class NotificationQueueWorker {

    /** @var string[] Líneas registradas en la corrida actual (paridad con el echo CLI original). */
    private array $salida = [];

    private string $logDir;
    private string $logFile;
    private string $lockFile;

    public function __construct() {
        date_default_timezone_set('America/Caracas');
        $this->logDir = BASE_PATH . '/app/logs';
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        $this->logFile  = $this->logDir . '/notifications_' . date('Y-m-d') . '.log';
        $this->lockFile = $this->logDir . '/notifications.lock';
    }

    /** @return string[] Líneas registradas en la última corrida. */
    public function getSalida(): array {
        return $this->salida;
    }

    /**
     * Ejecuta una pasada del worker.
     *
     * @param int|null $batchSize Máximo de correos por corrida (se acota a 1–100).
     *                            null = QUEUE_BATCH_SIZE del entorno o 50.
     * @return array{estado:string,procesados:int,exitosos:int,fallidos:int,error?:string}
     */
    public function run(?int $batchSize = null): array {
        $resumen = [
            'estado'     => 'ok',
            'procesados' => 0,
            'exitosos'   => 0,
            'fallidos'   => 0,
        ];

        // 1. Control de lockfile con stale recovery (5 minutos)
        if (file_exists($this->lockFile)) {
            $lockTime = intval(@file_get_contents($this->lockFile));
            if (time() - $lockTime < 300) {
                $this->registrar('Worker ya se encuentra en ejecución. Lock vigente. Finalizando.', 'WARNING');
                $resumen['estado'] = 'bloqueado_por_lock';
                return $resumen;
            }
            $this->registrar('Detectado lock desactualizado (> 5 min). Rompiendo stale lock y recuperando proceso.', 'WARNING');
        }

        file_put_contents($this->lockFile, time());

        try {
            $db = Database::getConnection();
            $emailService = new EmailService();

            if ($batchSize === null) {
                $batchSize = intval($_ENV['QUEUE_BATCH_SIZE'] ?? (getenv('QUEUE_BATCH_SIZE') ?: 50));
            }
            $batchSize = max(1, min(100, intval($batchSize)));

            $this->registrar('Iniciando ejecución del worker de cola de notificaciones...');

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
                $this->registrar('No existen notificaciones pendientes para procesar.');
                if (!$hasSkipLocked) {
                    $db->query("SELECT RELEASE_LOCK('notificaciones_worker_lock')");
                }
                return $resumen;
            }

            $this->registrar('Se encontraron ' . count($filas) . ' notificaciones pendientes de procesamiento.');

            foreach ($filas as $row) {
                $id = $row['id'];
                $resumen['procesados']++;

                // Paso A: Descifrado criptográfico seguro
                $adjuntos = [];
                try {
                    $destinatario = Encryption::decrypt($row['destinatario_email']);
                    $cuerpoHtml   = Encryption::decrypt($row['cuerpo_html']);

                    // Adjuntos opcionales: JSON cifrado con contenido en base64.
                    if (!empty($row['adjuntos'] ?? null)) {
                        $adjuntosJson = Encryption::decrypt($row['adjuntos']);
                        $adjuntosCrudos = json_decode($adjuntosJson, true);
                        if (!is_array($adjuntosCrudos)) {
                            throw new \Exception("Payload de adjuntos inválido (JSON no decodificable).");
                        }

                        foreach ($adjuntosCrudos as $indice => $adjunto) {
                            $numero = $indice + 1;
                            if (!is_array($adjunto)
                                || !isset($adjunto['nombre'], $adjunto['mime'], $adjunto['contenido'])
                                || !is_string($adjunto['nombre'])
                                || !is_string($adjunto['mime'])
                                || !is_string($adjunto['contenido'])) {
                                throw new \Exception("Payload de adjuntos inválido (estructura del adjunto {$numero}).");
                            }

                            $binario = base64_decode($adjunto['contenido'], true);
                            if ($binario === false) {
                                throw new \Exception("Payload de adjuntos inválido (contenido base64 del adjunto {$numero}).");
                            }

                            $adjuntos[] = [
                                'nombre'    => $adjunto['nombre'],
                                'mime'      => $adjunto['mime'],
                                'contenido' => $binario,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    $this->registrar("[QUEUE_ID: {$id}] Error de descifrado criptográfico: " . $e->getMessage(), 'ERROR');

                    $db->prepare("
                        UPDATE notificaciones_cola 
                        SET estado = 'fallido', error_mensaje = :err 
                        WHERE id = :id
                    ")->execute([
                        'err' => 'Error de descifrado criptográfico: ' . $e->getMessage(),
                        'id'  => $id
                    ]);
                    $resumen['fallidos']++;
                    continue;
                }

                // Paso B: Intento de despacho SMTP / Mail
                try {
                    $enviado = $emailService->enviar($destinatario, $row['asunto'], $cuerpoHtml, $adjuntos);

                    if ($enviado) {
                        $db->prepare("
                            UPDATE notificaciones_cola 
                            SET estado = 'enviado', proximo_intento = NULL, intentos = intentos + 1 
                            WHERE id = :id
                        ")->execute(['id' => $id]);

                        $this->registrar("[QUEUE_ID: {$id}] Correo enviado exitosamente a {$destinatario}.");
                        $resumen['exitosos']++;
                    } else {
                        throw new \Exception("Fallo en el transporte de correo (mail/smtp).");
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

                    $this->registrar("[QUEUE_ID: {$id}] Fallo en el envío (Intento {$intentosActuales}/3): " . $e->getMessage(), 'ERROR');
                    $resumen['fallidos']++;
                }
            }

            if (!$hasSkipLocked) {
                $db->query("SELECT RELEASE_LOCK('notificaciones_worker_lock')");
            }

            $this->registrar("Worker finalizado: {$resumen['procesados']} procesados, {$resumen['exitosos']} exitosos, {$resumen['fallidos']} fallidos.");

        } catch (\Throwable $e) {
            $this->registrar('Error crítico en worker: ' . $e->getMessage(), 'ERROR');
            $resumen['estado'] = 'error';
            $resumen['error']  = $e->getMessage();
        } finally {
            if (file_exists($this->lockFile)) {
                @unlink($this->lockFile);
            }
        }

        return $resumen;
    }

    private function registrar(string $mensaje, string $nivel = 'INFO'): void {
        $linea = '[' . date('Y-m-d H:i:s') . "] [{$nivel}] {$mensaje}";
        @file_put_contents($this->logFile, $linea . "\n", FILE_APPEND);
        $this->salida[] = $linea;
    }
}
