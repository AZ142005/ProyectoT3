<?php
namespace App\Core;

use App\Core\Database;

/**
 * Rate Limiter basado en base de datos con IP real (REMOTE_ADDR).
 * Previene brute-force, bypass con headers falsos y cookie-clearing.
 */
class RateLimiter {

    /**
     * Obtiene la IP real del cliente — solo REMOTE_ADDR, ignora headers de proxy.
     */
    private static function getClientIp(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Verifica si una acción está dentro del límite.
     *
     * @param string $key Identificador de la acción (ej: 'login', 'otp_verify')
     * @param int $maxAttempts Máximo de intentos permitidos
     * @param int $windowSeconds Ventana de tiempo en segundos
     * @return bool true si permitido, false si excedido
     */
    public static function attempt(string $key, int $maxAttempts, int $windowSeconds): bool {
        $ip = self::getClientIp();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT attempts, window_start FROM rate_limits 
            WHERE `key` = :k AND ip = :ip AND window_start > :expired
            LIMIT 1
        ");
        $expired = date('Y-m-d H:i:s', time() - $windowSeconds);
        $stmt->execute(['k' => $key, 'ip' => $ip, 'expired' => $expired]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $ins = $db->prepare("
                INSERT INTO rate_limits (`key`, ip, attempts, window_start) 
                VALUES (:k, :ip, 1, NOW())
            ");
            $ins->execute(['k' => $key, 'ip' => $ip]);
            return true;
        }

        if ((int)$row['attempts'] >= $maxAttempts) {
            return false;
        }

        $inc = $db->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE `key` = :k AND ip = :ip AND attempts < :max");
        $inc->execute(['k' => $key, 'ip' => $ip, 'max' => $maxAttempts]);
        return true;
    }

    /**
     * Retorna los segundos restantes hasta poder intentar de nuevo.
     *
     * @param string $key
     * @param int $windowSeconds
     * @return int Segundos restantes (0 si ya puede intentar)
     */
    public static function secondsUntilAvailable(string $key, int $windowSeconds): int {
        $ip = self::getClientIp();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT window_start FROM rate_limits 
            WHERE `key` = :k AND ip = :ip 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['k' => $key, 'ip' => $ip]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return 0;
        }

        $elapsed = time() - strtotime($row['window_start']);
        $remaining = $windowSeconds - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Limpia el contador para una key específica.
     */
    public static function clear(string $key): void {
        $ip = self::getClientIp();
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE `key` = :k AND ip = :ip");
        $stmt->execute(['k' => $key, 'ip' => $ip]);
    }
}