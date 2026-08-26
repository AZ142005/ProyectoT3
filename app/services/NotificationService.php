<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Encryption;
use Exception;
use PDO;

class NotificationService {

    /**
     * Analiza y normaliza un número de teléfono identificando si es móvil o fijo.
     *
     * @param string $telefono
     * @return array ['es_movil' => bool, 'telefono' => string, 'mensaje' => ?string]
     */
    public static function analizarTelefono(string $telefono): array {
        $digits = preg_replace('/[^0-9]/', '', $telefono);

        if (empty($digits)) {
            return [
                'es_movil' => false,
                'telefono' => '',
                'mensaje'  => 'Número de teléfono no proporcionado'
            ];
        }

        // Si ya tiene código de país internacional (longitud >= 12 o prefijo 58)
        if (strlen($digits) >= 12 || (strpos($digits, '58') === 0 && strlen($digits) >= 11)) {
            return [
                'es_movil' => true,
                'telefono' => $digits,
                'mensaje'  => null
            ];
        }

        // Móviles venezolanos (0412, 0414, 0424, 0416, 0426)
        if (preg_match('/^0?(412|414|424|416|426)([0-9]{7})$/', $digits, $matches)) {
            return [
                'es_movil' => true,
                'telefono' => '58' . $matches[1] . $matches[2],
                'mensaje'  => null
            ];
        }

        // Líneas fijas (0212, 0241, 0243, etc.)
        if (preg_match('/^0?2[0-9]{9}$/', $digits)) {
            return [
                'es_movil' => false,
                'telefono' => '58' . ltrim($digits, '0'),
                'mensaje'  => 'Número fijo residencial, WhatsApp no disponible'
            ];
        }

        return [
            'es_movil' => true,
            'telefono' => '58' . ltrim($digits, '0'),
            'mensaje'  => null
        ];
    }

    /**
     * Genera un enlace interactivo seguro de 1-clic para WhatsApp Web / App.
     *
     * @param string $telefono
     * @param string $mensaje
     * @return string|null Retorna la URL del deep-link o null si el número es fijo/inválido.
     */
    public static function generarEnlaceWhatsApp(string $telefono, string $mensaje): ?string {
        $info = self::analizarTelefono($telefono);
        if (!$info['es_movil'] || empty($info['telefono'])) {
            return null;
        }

        return 'https://api.whatsapp.com/send?phone=' . $info['telefono'] . '&text=' . rawurlencode($mensaje);
    }

    /**
     * Encola una notificación en `notificaciones_cola` cifrando integralmente el email y cuerpo HTML.
     */
    public function encolarNotificacion(
        string $destinatarioEmail,
        string $asunto,
        string $cuerpoHtml,
        ?string $telefono = null,
        string $canal = 'email',
        string $prioridad = 'normal'
    ): bool {
        if (mb_strlen($cuerpoHtml) > 2097152) { // Max 2 MB
            throw new Exception("El cuerpo del correo excede el límite máximo permitido de 2 MB.");
        }

        $prioridadesValidas = ['alta', 'normal', 'baja'];
        if (!in_array($prioridad, $prioridadesValidas)) {
            $prioridad = 'normal';
        }

        // Cifrado simétrico AES-256-CBC con Encryption
        $emailCifrado = Encryption::encrypt($destinatarioEmail);
        $cuerpoCifrado = Encryption::encrypt($cuerpoHtml);
        $telefonoCifrado = !empty($telefono) ? Encryption::encrypt($telefono) : null;

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO notificaciones_cola 
            (destinatario_email, destinatario_telefono, asunto, cuerpo_html, canal, prioridad, estado) 
            VALUES (:email, :telefono, :asunto, :cuerpo, :canal, :prioridad, 'pendiente')
        ");

        return $stmt->execute([
            'email'     => $emailCifrado,
            'telefono'  => $telefonoCifrado,
            'asunto'    => $asunto,
            'cuerpo'    => $cuerpoCifrado,
            'canal'     => $canal,
            'prioridad' => $prioridad
        ]);
    }

    /**
     * Registra una notificación en la bandeja personal del residente.
     */
    public function registrarNotificacionResidente(
        int $residenteId,
        string $titulo,
        string $mensaje,
        string $tipo = 'info',
        ?string $enlace = null
    ): int {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO notificaciones 
            (residente_id, titulo, mensaje, tipo, enlace) 
            VALUES (:residente_id, :titulo, :mensaje, :tipo, :enlace)
        ");
        $stmt->execute([
            'residente_id' => $residenteId,
            'titulo'       => $titulo,
            'mensaje'      => $mensaje,
            'tipo'         => $tipo,
            'enlace'       => $enlace
        ]);

        return intval($db->lastInsertId());
    }

    /**
     * Despacha el código de autenticación 2FA al correo del usuario con alta prioridad.
     */
    public function enviarOtp(string $email, string $otp, string $nombreResidente = 'Usuario'): bool {
        $emailService = new EmailService();
        $cuerpoHtml = $emailService->renderTemplate('otp_codigo', [
            'nombreResidente' => $nombreResidente,
            'codigoOtp'       => $otp
        ]);

        return $this->encolarNotificacion(
            $email,
            'Código de Verificación 2FA - Condominio Las Mesetas de Morón',
            $cuerpoHtml,
            null,
            'email',
            'alta'
        );
    }
}
