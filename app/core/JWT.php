<?php
namespace App\Core;

use InvalidArgumentException;
use RuntimeException;
use App\Core\Database;

class JWT {

    /**
     * Obtiene la clave secreta para la firma HMAC-SHA256 validando su longitud mínima de 32 bytes.
     */
    private static function getSecret(): string {
        $secret = $_ENV['JWT_SECRET'] ?? $_ENV['APP_KEY'] ?? '';
        if (empty($secret)) {
            throw new RuntimeException("JWT_SECRET o APP_KEY debe estar definido en .env para firmar tokens.");
        }
        if (strlen($secret) < 64) {
            throw new RuntimeException("La clave secreta JWT_SECRET debe tener una longitud mínima de 64 bytes.");
        }
        return $secret;
    }

    /**
     * Codifica una cadena en formato Base64Url (RFC 7519).
     */
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodifica una cadena en formato Base64Url.
     */
    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    /**
     * Genera y firma un token JWT con algoritmo HS256.
     *
     * @param array $payload Datos a incluir en el token (sub, email, role, etc.)
     * @param int $expiresInSeconds Tiempo de expiración en segundos (default 2 horas)
     * @return string Token JWT firmado
     */
    public static function encode(array $payload, int $expiresInSeconds = 7200): string {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $expiresInSeconds;
        if (!isset($payload['jti'])) {
            $payload['jti'] = bin2hex(random_bytes(8));
        }
        if (!isset($payload['iss'])) {
            $payload['iss'] = 'condominio_digital';
        }
        if (!isset($payload['aud'])) {
            $payload['aud'] = 'api';
        }

        $headerEncoded = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", self::getSecret(), true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    /**
     * Agrega un jti a la blacklist (invocar al logout).
     */
    public static function blacklist(string $jti, ?int $userId = null, int $expiresInSeconds = 7200): void {
        try {
            $db = Database::getConnection();
        } catch (\Exception $e) {
            error_log("[JWT] No se pudo agregar jti a blacklist: " . $e->getMessage());
            return;
        }
        $stmt = $db->prepare("
            INSERT IGNORE INTO jwt_blacklist (jti, user_id, expires_at)
            VALUES (:jti, :uid, :exp)
        ");
        $stmt->execute([
            'jti' => $jti,
            'uid' => $userId,
            'exp' => date('Y-m-d H:i:s', time() + $expiresInSeconds)
        ]);
    }

    /**
     * Verifica si un jti está en la blacklist.
     */
    public static function isBlacklisted(string $jti): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT 1 FROM jwt_blacklist WHERE jti = :jti LIMIT 1");
        $stmt->execute(['jti' => $jti]);
        return (bool)$stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Limpia tokens expirados de la blacklist.
     */
    public static function cleanBlacklist(): void {
        try {
            $db = Database::getConnection();
            $db->exec("DELETE FROM jwt_blacklist WHERE expires_at < NOW()");
        } catch (\Exception $e) {
            error_log("[JWT] No se pudo limpiar blacklist: " . $e->getMessage());
        }
    }

    /**
     * Valida y decodifica un token JWT.
     *
     * @param string $token
     * @return array Payload del token
     * @throws InvalidArgumentException Si la firma no coincide o el token expiró
     */
    public static function decode(string $token): array {
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            throw new InvalidArgumentException("Estructura de token JWT inválida.");
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $partes;

        $signature = self::base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", self::getSecret(), true);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new InvalidArgumentException("Firma del token JWT inválida.");
        }

        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException("Payload del token JWT corrupto.");
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new InvalidArgumentException("Token JWT expirado.");
        }

        // Verificar blacklist (logout forzado)
        if (isset($payload['jti'])) {
            try {
                if (self::isBlacklisted($payload['jti'])) {
                    throw new InvalidArgumentException("Token JWT revocado.");
                }
            } catch (\Exception $e) {
                if ($e instanceof InvalidArgumentException) throw $e;
                // Si la BD no está disponible, no bloquear — el token expirará naturalmente
            }
        }

        if (isset($payload['iss']) && $payload['iss'] !== 'condominio_digital') {
            throw new InvalidArgumentException("Emisor (iss) del token JWT no reconocido.");
        }

        if (isset($payload['aud']) && $payload['aud'] !== 'api') {
            throw new InvalidArgumentException("Audiencia (aud) del token JWT no válida.");
        }

        return $payload;
    }
}
