<?php
namespace App\Core;

/**
 * Rate Limiter simple basado en sesión.
 * Limita intentos por ventana de tiempo para prevenir brute force.
 */
class RateLimiter {

    /**
     * Verifica si una acción está dentro del límite.
     *
     * @param string $key Identificador de la acción (ej: 'login', 'otp_verify')
     * @param int $maxAttempts Máximo de intentos permitidos
     * @param int $windowSeconds Ventana de tiempo en segundos
     * @return bool true si permitido, false si excedido
     */
    public static function attempt(string $key, int $maxAttempts, int $windowSeconds): bool {
        $now = time();
        $sessionKey = 'rate_' . $key;

        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = ['attempts' => 0, 'window_start' => $now];
        }

        $data = &$_SESSION[$sessionKey];

        // Resetear ventana si expiró
        if (($now - $data['window_start']) >= $windowSeconds) {
            $data = ['attempts' => 0, 'window_start' => $now];
        }

        $data['attempts']++;

        return $data['attempts'] <= $maxAttempts;
    }

    /**
     * Retorna los segundos restantes hasta poder intentar de nuevo.
     *
     * @param string $key
     * @param int $windowSeconds
     * @return int Segundos restantes (0 si ya puede intentar)
     */
    public static function secondsUntilAvailable(string $key, int $windowSeconds): int {
        $sessionKey = 'rate_' . $key;
        if (!isset($_SESSION[$sessionKey])) {
            return 0;
        }

        $elapsed = time() - $_SESSION[$sessionKey]['window_start'];
        $remaining = $windowSeconds - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Limpia el contador para una key específica.
     */
    public static function clear(string $key): void {
        unset($_SESSION['rate_' . $key]);
    }
}
