<?php
namespace Tests;

use Tests\TestCase;
use App\Services\EmailService;
use App\Services\SmtpMailer;

/**
 * Pruebas del transporte de correo: modo mock, resolución de configuración SMTP
 * y protocolo SMTP completo contra un servidor falso local (sin red externa),
 * incluyendo el multipart/alternative (texto plano + HTML) y su anidado en
 * multipart/mixed cuando el mensaje lleva adjuntos PDF.
 */
class EmailSmtpTest extends TestCase {

    /** @var array<string, mixed> Valores previos de $_ENV que este test modificó. */
    private array $envPrevio = [];

    /**
     * Guarda el valor actual de las variables indicadas antes de modificarlas.
     */
    private function setEnv(array $variables): void {
        foreach ($variables as $clave => $valor) {
            if (!array_key_exists($clave, $this->envPrevio)) {
                $this->envPrevio[$clave] = array_key_exists($clave, $_ENV) ? $_ENV[$clave] : null;
            }
            $_ENV[$clave] = $valor;
        }
    }

    /**
     * Restaura las variables de entorno tocadas por el test.
     * run.php no invoca tearDown(), por eso cada test lo llama en su finally.
     */
    public function tearDown(): void {
        foreach ($this->envPrevio as $clave => $valor) {
            if ($valor === null) {
                unset($_ENV[$clave]);
            } else {
                $_ENV[$clave] = $valor;
            }
        }
        $this->envPrevio = [];
    }

    /**
     * Modo mock: debe escribir app/logs/mail_mock.log y retornar true sin usar la red.
     */
    public function testMockEscribeLogYRetornaTrue(): void {
        $this->setEnv(['MAIL_MODE' => 'mock']);
        try {
            $archivoLog   = BASE_PATH . '/app/logs/mail_mock.log';
            $tamanoAntes  = file_exists($archivoLog) ? filesize($archivoLog) : 0;
            $destinatario = 'mock-' . uniqid() . '@example.com';

            $resultado = (new EmailService())->enviar($destinatario, 'Prueba modo mock', '<p>Contenido</p>');

            $this->assertTrue($resultado, 'El modo mock debe retornar true.');
            clearstatcache(true, $archivoLog);
            $this->assertFileExists($archivoLog, 'El modo mock debe crear app/logs/mail_mock.log.');

            $tamanoDespues = filesize($archivoLog);
            $this->assertGreaterThan($tamanoAntes, $tamanoDespues, 'El log mock debe crecer con el nuevo envío.');

            $contenido = (string)file_get_contents($archivoLog);
            $this->assertStringContains($destinatario, $contenido, 'El log mock debe contener el destinatario.');
        } finally {
            $this->tearDown();
        }
    }

    /**
     * MAIL_MODE=smtp sin MAIL_HOST: excepción clara que el worker registra como error.
     */
    public function testSmtpSinHostLanzaExcepcionClara(): void {
        $this->setEnv(['MAIL_MODE' => 'smtp', 'MAIL_HOST' => '']);
        try {
            $this->expectExceptionMessage('MAIL_HOST', function () {
                (new EmailService())->enviar('destino@example.com', 'Sin host', '<p>x</p>');
            });
        } finally {
            $this->tearDown();
        }
    }

    /**
     * MAIL_MODE=smtp contra 127.0.0.1:1 (inalcanzable, sin red externa): debe fallar
     * con un mensaje que describa la etapa de conexión.
     */
    public function testSmtpConHostInalcanzableFallaConMensaje(): void {
        $this->setEnv([
            'MAIL_MODE'       => 'smtp',
            'MAIL_HOST'       => '127.0.0.1',
            'MAIL_PORT'       => '1',
            'MAIL_ENCRYPTION' => 'none',
            'MAIL_TIMEOUT'    => '2',
            'MAIL_VERIFY_PEER' => '0',
        ]);
        try {
            $mensaje = null;
            try {
                (new EmailService())->enviar('destino@example.com', 'Host inalcanzable', '<p>x</p>');
            } catch (\Throwable $e) {
                $mensaje = $e->getMessage();
            }

            $this->assertNotNull($mensaje, 'El envío a un host inalcanzable debe lanzar excepción.');
            if ($mensaje !== null) {
                $this->assertStringContains('no se pudo conectar', $mensaje, 'El error debe describir la etapa de conexión.');
                $this->assertStringContains('127.0.0.1:1', $mensaje, 'El error debe indicar el host y puerto intentados.');
            }
        } finally {
            $this->tearDown();
        }
    }

    /**
     * Lanza el servidor SMTP falso local, envía un mensaje con SmtpMailer y devuelve
     * [resultado del envío, mensaje capturado]. Retorna null si el entorno no permite
     * la prueba (en ese caso registra un skip). Nunca toca la red externa.
     *
     * @param array $adjuntos Adjuntos que se pasan a SmtpMailer::send().
     * @param string $html    Cuerpo HTML del mensaje; por defecto incluye entidades y
     *                        un emoji para ejercitar la derivación del texto plano.
     * @return array{0: bool, 1: string}|null
     */
    private function enviarContraServidorFalso(
        array $adjuntos = [],
        string $html = '<p>Estimado(a) residente, &copy; 2026: su código es <strong>482913</strong>. ⏱️</p>'
    ): ?array {
        if (!function_exists('proc_open')) {
            $this->skip('proc_open no está disponible en este entorno.');
            return null;
        }
        if (!function_exists('stream_socket_server')) {
            $this->skip('stream_socket_server no está disponible en este entorno.');
            return null;
        }

        $script = __DIR__ . '/helpers/fake_smtp_server.php';
        if (!file_exists($script)) {
            $this->skip('No se encontró tests/helpers/fake_smtp_server.php.');
            return null;
        }

        $captura = sys_get_temp_dir() . '/fake_smtp_' . uniqid() . '.eml';
        if (file_exists($captura)) {
            @unlink($captura);
        }

        $descriptores = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $tuberias = [];

        $proceso = proc_open(
            PHP_BINARY . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($captura),
            $descriptores,
            $tuberias,
            dirname(__DIR__)
        );

        if (!is_resource($proceso)) {
            $this->skip('proc_open no pudo iniciar el servidor falso SMTP.');
            return null;
        }

        $resultado = false;
        $contenido = '';

        try {
            stream_set_blocking($tuberias[1], false);
            stream_set_blocking($tuberias[2], false);

            // Espera acotada de la línea "PORT=<puerto>".
            $puerto = null;
            $limite = microtime(true) + 10;
            while (microtime(true) < $limite) {
                $linea = fgets($tuberias[1]);
                if ($linea !== false && preg_match('/^PORT=(\d+)/', trim($linea), $coincidencias)) {
                    $puerto = (int)$coincidencias[1];
                    break;
                }
                if (feof($tuberias[1])) {
                    break;
                }
                usleep(50000);
            }

            if ($puerto === null || $puerto <= 0) {
                $error = trim((string)stream_get_contents($tuberias[2]));
                $this->skip('El servidor falso SMTP no reportó un puerto utilizable.' . ($error !== '' ? " STDERR: {$error}" : ''));
                return null;
            }

            $mailer = new SmtpMailer([
                'host'        => '127.0.0.1',
                'port'        => $puerto,
                'encryption'  => 'none',
                'user'        => 'test',
                'pass'        => 'test',
                'timeout'     => 10,
                'verify_peer' => false,
                'from_email'  => 'no-reply@example.com',
                'from_name'   => 'Pruebas SMTP',
                'ehlo_host'   => 'localhost',
            ]);

            $resultado = $mailer->send('residente@example.com', 'Asunto de prueba', $html, $adjuntos);

            // El servidor escribe la captura al recibir el punto final de DATA.
            $limite = microtime(true) + 5;
            while (microtime(true) < $limite) {
                if (file_exists($captura)) {
                    $contenido = (string)file_get_contents($captura);
                    if ($contenido !== '') {
                        break;
                    }
                }
                usleep(50000);
            }
        } finally {
            if (is_resource($proceso)) {
                $estado = proc_get_status($proceso);
                if (!empty($estado['running'])) {
                    proc_terminate($proceso);
                    usleep(100000);
                }
            }
            foreach ($tuberias as $tuberia) {
                if (is_resource($tuberia)) {
                    fclose($tuberia);
                }
            }
            if (is_resource($proceso)) {
                proc_close($proceso);
            }
            if (file_exists($captura)) {
                @unlink($captura);
            }
        }

        return [$resultado, $contenido];
    }

    /**
     * Extrae y decodifica el cuerpo base64 de la parte MIME con el Content-Type dado.
     *
     * @param string $contenido Mensaje capturado completo.
     * @param string $marca     Texto exacto tras "Content-Type: " (ej. text/plain; charset=UTF-8).
     * @return string|null Bytes decodificados, o null si la parte no existe o no decodifica.
     */
    private function extraerCuerpoBase64(string $contenido, string $marca): ?string {
        $patron = '/Content-Type: ' . preg_quote($marca, '/') . "\r\n(?:[^\r\n]+\r\n)*\r\n(.+?)\r\n--/s";
        if (!preg_match($patron, $contenido, $coincidencias)) {
            return null;
        }
        $decodificado = base64_decode(str_replace(["\r", "\n"], '', $coincidencias[1]), true);
        return $decodificado === false ? null : $decodificado;
    }

    /**
     * Protocolo completo (EHLO multilínea, AUTH LOGIN, MAIL/RCPT/DATA, QUIT) contra
     * el servidor falso; sin adjuntos el cuerpo debe ser multipart/alternative con
     * las partes text/plain (derivada del HTML) y text/html, y el Message-ID debe
     * usar el dominio del remitente.
     */
    public function testProtocoloCompletoConServidorFalsoLocal(): void {
        $captura = $this->enviarContraServidorFalso();
        if ($captura === null) {
            return;
        }
        [$resultado, $contenido] = $captura;

        $this->assertTrue($resultado, 'El protocolo SMTP completo debe retornar true.');
        $this->assertStringContains('Subject: =?UTF-8?B?', $contenido, 'El mensaje capturado debe llevar el asunto en base64 UTF-8.');
        $this->assertMatchesRegex('/Message-ID: <[0-9a-f]{32}@example\.com>/', $contenido,
            'El Message-ID debe usar el dominio del remitente, no el hostname local.');
        $this->assertMatchesRegex('/Content-Type: multipart\/alternative; boundary="=_cond_alt_[0-9a-f]+"/', $contenido,
            'Sin adjuntos el mensaje debe declarar multipart/alternative.');
        $this->assertFalse(str_contains($contenido, 'multipart/mixed'),
            'Sin adjuntos el mensaje no debe ser multipart/mixed.');
        $this->assertStringContains("Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64", $contenido,
            'Debe existir la parte text/plain en base64.');
        $this->assertStringContains("Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64", $contenido,
            'Debe existir la parte text/html en base64.');
        $this->assertMatchesRegex('/--=_cond_alt_[0-9a-f]+--\r?\n/', $contenido,
            'El multipart/alternative debe cerrar con su boundary final.');

        $textoPlano = $this->extraerCuerpoBase64($contenido, 'text/plain; charset=UTF-8');
        $this->assertNotNull($textoPlano, 'Debe poder extraerse y decodificar la parte text/plain.');
        if ($textoPlano !== null) {
            $this->assertStringContains('Estimado(a) residente', $textoPlano, 'El text/plain debe derivarse del HTML enviado.');
            $this->assertStringContains('© 2026', $textoPlano, 'El text/plain debe decodificar las entidades HTML.');
            $this->assertStringContains('482913', $textoPlano, 'El text/plain debe conservar el contenido del cuerpo.');
            $this->assertFalse(str_contains($textoPlano, '<'), 'El text/plain no debe contener etiquetas HTML.');
        }
    }

    /**
     * Con adjuntos el mensaje debe ser multipart/mixed: su primer componente es el
     * multipart/alternative (texto plano + HTML) y luego el PDF como parte adjunta
     * con nombre de archivo ASCII seguro.
     */
    public function testMensajeConAdjuntosEsMultipartMixed(): void {
        $binario = '%PDF-1.4 carta-de-prueba 12345';
        $captura = $this->enviarContraServidorFalso([[
            'nombre'    => 'prueba.pdf',
            'mime'      => 'application/pdf',
            'contenido' => $binario,
        ]]);
        if ($captura === null) {
            return;
        }
        [$resultado, $contenido] = $captura;

        $this->assertTrue($resultado, 'El envío con adjuntos debe retornar true.');
        $this->assertMatchesRegex('/Content-Type: multipart\/mixed; boundary="=_cond_[0-9a-f]+"/', $contenido,
            'Con adjuntos el mensaje debe declarar multipart/mixed con boundary aleatorio.');
        $this->assertMatchesRegex('/Content-Type: multipart\/alternative; boundary="=_cond_alt_[0-9a-f]+"/', $contenido,
            'El primer componente del mixed debe ser un multipart/alternative anidado.');
        $this->assertStringContains('Content-Type: application/pdf; name="prueba.pdf"', $contenido,
            'La parte adjunta debe declarar el MIME y el nombre del archivo.');
        $this->assertStringContains('Content-Disposition: attachment; filename="prueba.pdf"', $contenido,
            'La parte adjunta debe declarar la disposición de adjunto con el nombre.');
        $this->assertMatchesRegex('/--=_cond_alt_[0-9a-f]+--\r?\n/', $contenido,
            'El multipart/alternative anidado debe cerrar con su boundary.');
        $this->assertMatchesRegex('/--=_cond_[0-9a-f]+--\r?\n/', $contenido,
            'El multipart/mixed debe cerrar con su boundary final.');

        $partePdf = $this->extraerCuerpoBase64($contenido, 'application/pdf; name="prueba.pdf"');
        $this->assertNotNull($partePdf, 'Debe poder extraerse y decodificar la parte PDF.');
        if ($partePdf !== null) {
            $this->assertEquals($binario, $partePdf, 'El contenido del adjunto debe viajar en base64 y decodificarse intacto.');
        }

        $textoPlano = $this->extraerCuerpoBase64($contenido, 'text/plain; charset=UTF-8');
        $this->assertNotNull($textoPlano, 'La alternativa anidada debe incluir la parte text/plain.');
        if ($textoPlano !== null) {
            $this->assertStringContains('Estimado(a) residente', $textoPlano, 'El text/plain anidado debe conservar el cuerpo derivado.');
        }
    }
}
