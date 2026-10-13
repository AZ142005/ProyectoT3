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

        // Móviles venezolanos (0412, 0422, 0414, 0424, 0416, 0426)
        if (preg_match('/^0?(412|422|414|424|416|426)([0-9]{7})$/', $digits, $matches)) {
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
     * Encola una notificación en `notificaciones_cola` cifrando integralmente el email, cuerpo HTML y adjuntos.
     *
     * @param string $destinatarioEmail
     * @param string $asunto
     * @param string $cuerpoHtml
     * @param string|null $telefono
     * @param string $canal
     * @param string $prioridad
     * @param array|null $adjuntos Lista de adjuntos con la forma
     *                            ['nombre' => string, 'mime' => string, 'contenido' => string binario].
     *                            Cada contenido se guarda en base64 dentro de un JSON cifrado.
     * @throws Exception si un adjunto excede el límite de 2 MB o su estructura es inválida.
     */
    public function encolarNotificacion(
        string $destinatarioEmail,
        string $asunto,
        string $cuerpoHtml,
        ?string $telefono = null,
        string $canal = 'email',
        string $prioridad = 'normal',
        ?array $adjuntos = null
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

        $adjuntosCifrados = null;
        if (!empty($adjuntos)) {
            $adjuntosCifrados = Encryption::encrypt(json_encode($this->normalizarAdjuntos($adjuntos)));
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO notificaciones_cola 
            (destinatario_email, destinatario_telefono, asunto, cuerpo_html, adjuntos, canal, prioridad, estado) 
            VALUES (:email, :telefono, :asunto, :cuerpo, :adjuntos, :canal, :prioridad, 'pendiente')
        ");

        return $stmt->execute([
            'email'     => $emailCifrado,
            'telefono'  => $telefonoCifrado,
            'asunto'    => $asunto,
            'cuerpo'    => $cuerpoCifrado,
            'adjuntos'  => $adjuntosCifrados,
            'canal'     => $canal,
            'prioridad' => $prioridad
        ]);
    }

    /**
     * Valida y normaliza los adjuntos para su almacenamiento cifrado.
     *
     * El contenido binario se convierte a base64 (ASCII, seguro para JSON).
     *
     * @param array $adjuntos
     * @return array Lista de adjuntos con 'contenido' en base64.
     * @throws Exception si falta un nombre/contenido o el adjunto supera 2 MB.
     */
    private function normalizarAdjuntos(array $adjuntos): array {
        $normalizados = [];

        foreach ($adjuntos as $indice => $adjunto) {
            $nombre = trim((string)($adjunto['nombre'] ?? ''));
            $mime = trim((string)($adjunto['mime'] ?? ''));
            $contenido = $adjunto['contenido'] ?? '';

            if ($nombre === '' || !is_string($contenido)) {
                throw new Exception("Adjunto #" . ($indice + 1) . " inválido: se requiere nombre y contenido binario.");
            }
            if (strlen($contenido) > 2097152) {
                throw new Exception("El adjunto '{$nombre}' excede el límite máximo permitido de 2 MB.");
            }

            $normalizados[] = [
                'nombre'    => $nombre,
                'mime'      => $mime !== '' ? $mime : 'application/octet-stream',
                'contenido' => base64_encode($contenido),
            ];
        }

        return $normalizados;
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
