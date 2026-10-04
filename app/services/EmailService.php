<?php
namespace App\Services;

use Exception;

// El worker (scripts/process_notifications.php) carga las clases manualmente, sin
// autoloader; por eso el cliente SMTP se incluye aquí de forma explícita.
require_once __DIR__ . '/SmtpMailer.php';

class EmailService {

    /**
     * Resuelve una variable de configuración de correo.
     *
     * El cargador de .env del proyecto llena únicamente $_ENV, por lo que getenv()
     * por sí solo no ve la configuración; se consultan ambos orígenes.
     *
     * @param string $key Nombre de la variable (ej. MAIL_MODE).
     * @param mixed $default Valor por defecto si no está definida o está vacía.
     * @return mixed
     */
    private function conf(string $key, $default = null) {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        $valor = getenv($key);
        if ($valor !== false && $valor !== '') {
            return $valor;
        }

        return $default;
    }

    /**
     * Interpreta un valor booleano de configuración ('0'/'false' => false).
     */
    private function verificarPeer($valor): bool {
        if ($valor === null || $valor === '') {
            return true;
        }
        return !in_array(strtolower(trim((string)$valor)), ['0', 'false'], true);
    }

    /**
     * Renderiza una plantilla de correo PHP capturando el búfer de salida.
     *
     * @param string $templateName Nombre del archivo de plantilla (sin .php) en app/views/emails/
     * @param array $data Variables a extraer en el scope de la vista
     * @return string Contenido HTML procesado
     */
    public function renderTemplate(string $templateName, array $data = []): string {
        $file = VIEWS_PATH . '/emails/' . $templateName . '.php';
        if (!file_exists($file)) {
            throw new Exception("Plantilla de correo '{$templateName}' no encontrada en {$file}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Realiza el despacho de un correo electrónico en formato HTML con codificación UTF-8.
     *
     * Modos (MAIL_MODE): 'mock' escribe app/logs/mail_mock.log sin usar la red,
     * 'smtp' envía por un servidor SMTP autenticado y 'mail' usa la función mail() de PHP.
     *
     * @param string $destinatario Email del destinatario
     * @param string $asunto Asunto del correo
     * @param string $cuerpoHtml Contenido HTML
     * @return bool Retorna verdadero si el transporte aceptó el mensaje.
     */
    public function enviar(string $destinatario, string $asunto, string $cuerpoHtml): bool {
        if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Dirección de email inválida: {$destinatario}");
        }

        $fromEmail = $this->conf('MAIL_FROM_ADDRESS', 'no-reply@condominiodigital.com');
        $fromName  = $this->conf('MAIL_FROM_NAME', 'Condominio Digital - Las Mesetas de Morón');

        $mode = strtolower((string)$this->conf('MAIL_MODE', ''));
        if ($mode === '') {
            // Compatibilidad con la configuración anterior (MAIL_DRIVER/APP_ENV).
            if ($this->conf('MAIL_DRIVER') === 'log' || getenv('APP_ENV') === 'testing') {
                $mode = 'mock';
            } else {
                $mode = 'mock'; // Valor seguro por defecto: nunca se envía por red de forma accidental.
            }
        }
        if ($mode === 'log') {
            $mode = 'mock'; // Alias legado.
        }

        if ($mode === 'smtp') {
            $host = (string)$this->conf('MAIL_HOST', '');
            if ($host === '') {
                throw new Exception('MAIL_HOST no configurado: defina MAIL_MODE=smtp y las variables MAIL_* en .env.');
            }

            $cifrado = strtolower((string)$this->conf('MAIL_ENCRYPTION', 'tls'));
            if ($cifrado === '') {
                $cifrado = 'tls';
            }

            $puerto = intval($this->conf('MAIL_PORT', 587));
            if ($puerto <= 0) {
                $puerto = 587;
            }

            $timeout = intval($this->conf('MAIL_TIMEOUT', 20));
            if ($timeout <= 0) {
                $timeout = 20;
            }

            $cafile = $this->conf('MAIL_CAFILE', null);
            if (is_string($cafile)) {
                $cafile = trim($cafile);
                if ($cafile === '') {
                    $cafile = null;
                }
            }

            $config = [
                'host'        => $host,
                'port'        => $puerto,
                'encryption'  => $cifrado,
                'user'        => (string)$this->conf('MAIL_USER', ''),
                'pass'        => (string)$this->conf('MAIL_PASS', ''),
                'timeout'     => $timeout,
                'verify_peer' => $this->verificarPeer($this->conf('MAIL_VERIFY_PEER', '1')),
                'cafile'      => $cafile,
                'from_email'  => $fromEmail,
                'from_name'   => $fromName,
            ];

            $mailer = new SmtpMailer($config);
            return $mailer->send($destinatario, $asunto, $cuerpoHtml);
        }

        if ($mode === 'mail') {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
            $headers .= "Reply-To: {$fromEmail}\r\n";
            $headers .= "X-Mailer: CondominioDigital";

            return @mail($destinatario, "=?UTF-8?B?" . base64_encode($asunto) . "?=", $cuerpoHtml, $headers);
        }

        if ($mode !== 'mock') {
            throw new Exception("MAIL_MODE no reconocido: '{$mode}'. Use mock, smtp o mail en .env.");
        }

        // Modo mock: desarrollo sin red; se registra el envío simulado y se acepta.
        $logDir = BASE_PATH . '/app/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        file_put_contents(
            $logDir . '/mail_mock.log',
            "[" . date('Y-m-d H:i:s') . "] TO: {$destinatario} | SUBJECT: {$asunto}\n",
            FILE_APPEND
        );
        return true;
    }
}
