<?php
namespace Tests;

use Tests\TestCase;
use App\Services\EmailService;
use App\Services\SmtpMailer;

/**
 * Pruebas del transporte de correo: modo mock, resolución de configuración SMTP
 * y protocolo SMTP completo contra un servidor falso local (sin red externa).
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
     * Protocolo completo (EHLO multilínea, AUTH LOGIN, MAIL/RCPT/DATA, QUIT) contra
     * un servidor SMTP falso local lanzado con proc_open. Nunca toca la red externa.
     */
    public function testProtocoloCompletoConServidorFalsoLocal(): void {
        if (!function_exists('proc_open')) {
            $this->skip('proc_open no está disponible en este entorno.');
            return;
        }
        if (!function_exists('stream_socket_server')) {
            $this->skip('stream_socket_server no está disponible en este entorno.');
            return;
        }

        $script = __DIR__ . '/helpers/fake_smtp_server.php';
        if (!file_exists($script)) {
            $this->skip('No se encontró tests/helpers/fake_smtp_server.php.');
            return;
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
            return;
        }

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
                return;
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

            $resultado = $mailer->send('residente@example.com', 'Asunto de prueba', '<p>Hola SMTP</p>');
            $this->assertTrue($resultado, 'El protocolo SMTP completo debe retornar true.');

            // El servidor escribe la captura al recibir el punto final de DATA.
            $contenido = '';
            $limite    = microtime(true) + 5;
            while (microtime(true) < $limite) {
                if (file_exists($captura)) {
                    $contenido = (string)file_get_contents($captura);
                    if ($contenido !== '') {
                        break;
                    }
                }
                usleep(50000);
            }

            $this->assertStringContains('Subject: =?UTF-8?B?', $contenido, 'El mensaje capturado debe llevar el asunto en base64 UTF-8.');
            $this->assertStringContains('Content-Transfer-Encoding: base64', $contenido, 'El mensaje capturado debe declarar cuerpo en base64.');
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
    }
}
