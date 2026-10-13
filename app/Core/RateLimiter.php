<?php
namespace App\Core;

use App\Core\Database;

/**
 * Rate Limiter basado en base de datos con IP real (REMOTE_ADDR).
 * Previene brute-force, bypass con headers falsos y cookie-clearing.
 *
 * Las ventanas de tiempo se evalúan en hora de MySQL (NOW()), no en hora de
 * PHP, para evitar desfases cuando ambas zonas horarias difieren.
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
        $windowSeconds = max(1, (int)$windowSeconds);

        // La ventana se compara en hora de MySQL para evitar desfases de zona horaria PHP ↔ MySQL.
        $stmt = $db->prepare("
            SELECT id, attempts FROM rate_limits
            WHERE `key` = :k AND ip = :ip
              AND window_start > DATE_SUB(NOW(), INTERVAL {$windowSeconds} SECOND)
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['k' => $key, 'ip' => $ip]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $ins = $db->prepare("INSERT INTO rate_limits (`key`, ip, attempts, window_start) VALUES (:k, :ip, 1, NOW())");
            $ins->execute(['k' => $key, 'ip' => $ip]);
            return true;
        }

        if ((int)$row['attempts'] >= $maxAttempts) {
            return false;
        }

        $inc = $db->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE id = :id AND attempts < :max");
        $inc->execute(['id' => $row['id'], 'max' => $maxAttempts]);
        return true;
    }

    /**
     * Retorna los segundos restantes hasta poder intentar de nuevo.
     * El cálculo se hace en hora de MySQL para evitar desfases de zona horaria.
     *
     * @param string $key
     * @param int $windowSeconds
     * @return int Segundos restantes (0 si ya puede intentar)
     */
    public static function secondsUntilAvailable(string $key, int $windowSeconds): int {
        $ip = self::getClientIp();
        $db = Database::getConnection();
        $windowSeconds = max(1, (int)$windowSeconds);

        $stmt = $db->prepare("
            SELECT GREATEST(0, :ventana - TIMESTAMPDIFF(SECOND, window_start, NOW())) AS restante
            FROM rate_limits
            WHERE `key` = :k AND ip = :ip
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['k' => $key, 'ip' => $ip, 'ventana' => $windowSeconds]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? max(0, (int)$row['restante']) : 0;
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
