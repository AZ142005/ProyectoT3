<?php
namespace App\Services;

use Exception;

class EmailService {

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
     * @param string $destinatario Email del destinatario
     * @param string $asunto Asunto del correo
     * @param string $cuerpoHtml Contenido HTML
     * @return bool Retorna verdadero si el transporte aceptó el mensaje.
     */
    public function enviar(string $destinatario, string $asunto, string $cuerpoHtml): bool {
        if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Dirección de email inválida: {$destinatario}");
        }

        $fromEmail = getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@condominiodigital.com';
        $fromName  = getenv('MAIL_FROM_NAME') ?: 'Condominio Digital - Las Mesetas de Morón';

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // En entornos sin servidor SMTP configurado (desarrollo local), simular despacho o usar mail()
        if (getenv('APP_ENV') === 'testing' || getenv('MAIL_DRIVER') === 'log') {
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

        return @mail($destinatario, "=?UTF-8?B?" . base64_encode($asunto) . "?=", $cuerpoHtml, $headers);
    }
}
