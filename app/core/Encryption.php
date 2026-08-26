<?php
namespace App\Core;

use RuntimeException;
use InvalidArgumentException;

class Encryption {

    private const CIPHER = 'AES-256-CBC';

    /**
     * Obtiene la clave de cifrado desde las variables de entorno o la configuración.
     */
    private static function getKey(): string {
        $key = $_ENV['NOTIFICATION_ENCRYPT_KEY'] ?? $_ENV['APP_KEY'] ?? getenv('NOTIFICATION_ENCRYPT_KEY') ?: getenv('APP_KEY');
        
        if (empty($key)) {
            throw new RuntimeException("NOTIFICATION_ENCRYPT_KEY o APP_KEY debe estar definido en .env para cifrar datos.");
        }

        if (mb_strlen($key, '8bit') < 32) {
            $key = hash('sha256', $key, true);
        } else {
            $key = mb_substr($key, 0, 32, '8bit');
        }

        return $key;
    }

    /**
     * Cifra una cadena de texto en claro usando AES-256-CBC.
     *
     * @param string $plaintext
     * @return string Payload codificado en Base64 que contiene el IV y el texto cifrado.
     * @throws RuntimeException
     */
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '') {
            return '';
        }

        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new RuntimeException("Fallo al cifrar los datos con " . self::CIPHER);
        }

        return base64_encode($iv . $ciphertext);
    }

    /**
     * Descifra una cadena cifrada previa codificada en Base64.
     *
     * @param string $payload
     * @return string Texto plano original.
     * @throws InvalidArgumentException|RuntimeException
     */
    public static function decrypt(string $payload): string {
        if ($payload === '') {
            return '';
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new InvalidArgumentException("Payload Base64 no válido para descifrado.");
        }

        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if (mb_strlen($decoded, '8bit') < $ivLength) {
            throw new InvalidArgumentException("La longitud del payload es inferior al vector de inicialización (IV).");
        }

        $iv = mb_substr($decoded, 0, $ivLength, '8bit');
        $ciphertext = mb_substr($decoded, $ivLength, null, '8bit');

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new RuntimeException("Error de descifrado criptográfico: Clave incorrecta o payload corrupto.");
        }

        return $plaintext;
    }
}
