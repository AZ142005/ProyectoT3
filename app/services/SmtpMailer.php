<?php
namespace App\Services;

use Exception;

/**
 * Cliente SMTP mínimo en PHP puro (sin dependencias externas) con AUTH LOGIN.
 *
 * Modos de cifrado soportados:
 *  - 'tls'  : conexión TCP plana + STARTTLS (habitualmente puerto 587).
 *  - 'ssl'  : TLS implícito desde el inicio (habitualmente puerto 465).
 *  - 'none' : sin cifrado (solo para servidores locales de prueba).
 *
 * El envío solo retorna true cuando el servidor acepta el mensaje (250 tras DATA).
 * Cualquier fallo lanza \Exception indicando la etapa del protocolo y la respuesta
 * del servidor (truncada si es muy larga).
 */
class SmtpMailer {

    /** Máximo de caracteres de respuesta del servidor incluidos en los errores. */
    private const MAX_LARGO_RESPUESTA = 400;

    private array $cfg;

    public function __construct(array $config) {
        $host = trim((string)($config['host'] ?? ''));
        if ($host === '') {
            throw new Exception("SMTP: el host no puede estar vacío.");
        }

        $fromEmail = trim((string)($config['from_email'] ?? ''));
        if ($fromEmail === '') {
            throw new Exception("SMTP: from_email no configurado.");
        }

        $port = (int)($config['port'] ?? 587);
        if ($port < 1 || $port > 65535) {
            throw new Exception("SMTP: puerto inválido ({$port}); debe estar entre 1 y 65535.");
        }

        $encryption = strtolower((string)($config['encryption'] ?? 'tls'));
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new Exception("SMTP: cifrado no soportado '{$encryption}'; use tls, ssl o none.");
        }

        $timeout = (int)($config['timeout'] ?? 20);
        if ($timeout < 1) {
            $timeout = 20;
        }

        $this->cfg = [
            'host'        => $host,
            'port'        => $port,
            'encryption'  => $encryption,
            'user'        => (string)($config['user'] ?? ''),
            'pass'        => (string)($config['pass'] ?? ''),
            'timeout'     => $timeout,
            'verify_peer' => array_key_exists('verify_peer', $config) ? (bool)$config['verify_peer'] : true,
            'cafile'      => $config['cafile'] ?? null,
            'from_email'  => $fromEmail,
            'from_name'   => (string)($config['from_name'] ?? 'Condominio Digital'),
            'ehlo_host'   => (string)($config['ehlo_host'] ?? (gethostname() ?: 'localhost')),
        ];
    }

    /**
     * Envía un correo HTML por SMTP.
     *
     * @param string $to      Destinatario (se valida el formato).
     * @param string $subject Asunto en UTF-8.
     * @param string $html    Cuerpo HTML en UTF-8.
     * @return bool true solo si el servidor aceptó el mensaje.
     * @throws Exception en cualquier fallo del protocolo, con etapa y respuesta.
     */
    public function send(string $to, string $subject, string $html): bool {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("SMTP: dirección de destinatario inválida: {$to}");
        }

        $socket = $this->conectar();
        try {
            $this->esperarCodigo($socket, 'saludo inicial', [220]);
            $this->comando($socket, 'EHLO ' . $this->cfg['ehlo_host'], 'EHLO', [250]);

            if ($this->cfg['encryption'] === 'tls') {
                $this->comando($socket, 'STARTTLS', 'STARTTLS', [220]);
                $negociado = $this->sinAvisos(function () use ($socket) {
                    return stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                });
                if ($negociado !== true) {
                    throw new Exception("No se pudo negociar TLS (STARTTLS).");
                }
                // Tras el handshake TLS se debe reintroducir el cliente con EHLO.
                $this->comando($socket, 'EHLO ' . $this->cfg['ehlo_host'], 'EHLO tras STARTTLS', [250]);
            }

            if ($this->cfg['user'] !== '') {
                $this->comando($socket, 'AUTH LOGIN', 'AUTH LOGIN', [334]);
                $this->comando($socket, base64_encode($this->cfg['user']), 'AUTH usuario', [334]);
                $this->comando($socket, base64_encode($this->cfg['pass']), 'AUTH contraseña', [235]);
            }

            $this->comando($socket, 'MAIL FROM:<' . $this->cfg['from_email'] . '>', 'MAIL FROM', [250]);
            $this->comando($socket, 'RCPT TO:<' . $to . '>', 'RCPT TO', [250, 251]);
            $this->comando($socket, 'DATA', 'DATA', [354]);

            $mensaje = $this->construirMensaje($to, $subject, $html);
            if (fwrite($socket, $mensaje) === false) {
                throw new Exception("SMTP [contenido]: no se pudo escribir el mensaje en el socket.");
            }
            $this->esperarCodigo($socket, 'aceptación del mensaje', [250]);

            // Cierre cortés: best-effort, no invalida un mensaje ya aceptado.
            $this->sinAvisos(function () use ($socket) {
                stream_set_timeout($socket, 3);
                fwrite($socket, "QUIT\r\n");
                fgets($socket, 1024);
                return true;
            });

            return true;
        } finally {
            if (is_resource($socket)) {
                $this->sinAvisos(function () use ($socket) {
                    fclose($socket);
                    return true;
                });
            }
        }
    }

    /**
     * Abre la conexión TCP/TLS según la configuración.
     *
     * @return resource
     * @throws Exception con errno/errstr si la conexión falla.
     */
    private function conectar() {
        $esquema = $this->cfg['encryption'] === 'ssl' ? 'ssl://' : 'tcp://';
        $remoto  = $esquema . $this->cfg['host'] . ':' . $this->cfg['port'];

        $opcionesSsl = [
            'verify_peer'       => $this->cfg['verify_peer'],
            'verify_peer_name'  => $this->cfg['verify_peer'],
            'allow_self_signed' => false,
            'peer_name'         => $this->cfg['host'],
        ];
        if (!empty($this->cfg['cafile'])) {
            $opcionesSsl['cafile'] = $this->cfg['cafile'];
        }
        $contexto = stream_context_create(['ssl' => $opcionesSsl]);

        $errno  = 0;
        $errstr = '';
        $socket = $this->sinAvisos(function () use ($remoto, $contexto, &$errno, &$errstr) {
            return stream_socket_client(
                $remoto,
                $errno,
                $errstr,
                $this->cfg['timeout'],
                STREAM_CLIENT_CONNECT,
                $contexto
            );
        });

        if ($socket === false) {
            $detalle = trim((string)$errstr) !== '' ? trim($errstr) : 'sin detalle del sistema';
            throw new Exception("SMTP: no se pudo conectar a {$remoto} (errno {$errno}): {$detalle}");
        }

        stream_set_timeout($socket, $this->cfg['timeout']);
        return $socket;
    }

    /**
     * Envía una línea de comando (CRLF incluido) y valida el código de respuesta.
     */
    private function comando($socket, string $linea, string $etapa, array $codigosEsperados): void {
        if (fwrite($socket, $linea . "\r\n") === false) {
            throw new Exception("SMTP [{$etapa}]: no se pudo escribir en el socket.");
        }
        $this->esperarCodigo($socket, $etapa, $codigosEsperados);
    }

    /**
     * Lee una respuesta completa (multilínea) y exige uno de los códigos esperados.
     * Lanza excepción con el texto del servidor si el código no coincide.
     */
    private function esperarCodigo($socket, string $etapa, array $codigosEsperados): void {
        [$codigo, $respuesta] = $this->leerRespuesta($socket, $etapa);
        if (!in_array($codigo, $codigosEsperados, true)) {
            $esperados = implode(' o ', $codigosEsperados);
            throw new Exception(
                "SMTP [{$etapa}]: se esperaba {$esperados} y el servidor respondió: " .
                $this->acortar(trim($respuesta))
            );
        }
    }

    /**
     * Lee líneas hasta la respuesta final (la que no lleva '-' tras el código).
     *
     * @return array{0:int,1:string} Código numérico y texto completo de la respuesta.
     */
    private function leerRespuesta($socket, string $etapa): array {
        $respuesta = '';
        while (true) {
            $linea = fgets($socket, 2048);
            if ($linea === false) {
                $meta   = stream_get_meta_data($socket);
                $motivo = !empty($meta['timed_out'])
                    ? 'tiempo de espera agotado'
                    : 'la conexión se cerró sin respuesta';
                $extra  = $respuesta !== ''
                    ? ' Última respuesta recibida: ' . $this->acortar(trim($respuesta))
                    : '';
                throw new Exception("SMTP [{$etapa}]: {$motivo}.{$extra}");
            }
            $respuesta .= $linea;
            if (strlen($linea) < 4 || substr($linea, 3, 1) !== '-') {
                break;
            }
        }
        return [(int)substr($respuesta, 0, 3), $respuesta];
    }

    /**
     * Construye el mensaje RFC 5322 con codificación UTF-8 (asunto y cuerpo en base64).
     */
    private function construirMensaje(string $to, string $subject, string $html): string {
        $fromEmail = $this->cfg['from_email'];
        $fromName  = str_replace(['\\', '"'], ['\\\\', '\\"'], $this->cfg['from_name']);

        $cabeceras   = [];
        $cabeceras[] = 'Date: ' . date('r');
        // Nombre visible: si contiene caracteres no ASCII se codifica como encoded-word (RFC 2047)
        $nombreMostrar = preg_match('/[^\x20-\x7E]/', $fromName)
            ? '=?UTF-8?B?' . base64_encode($fromName) . '?='
            : '"' . $fromName . '"';
        $cabeceras[] = 'From: ' . $nombreMostrar . ' <' . $fromEmail . '>';
        $cabeceras[] = 'To: <' . $to . '>';
        $cabeceras[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
        $cabeceras[] = 'MIME-Version: 1.0';
        $cabeceras[] = 'Content-Type: text/html; charset=UTF-8';
        $cabeceras[] = 'Content-Transfer-Encoding: base64';
        $cabeceras[] = 'Reply-To: <' . $fromEmail . '>';
        $cabeceras[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->cfg['ehlo_host'] . '>';
        $cabeceras[] = 'X-Mailer: CondominioDigital';

        $cuerpo = chunk_split(base64_encode($html), 76, "\r\n");

        $mensaje = implode("\r\n", $cabeceras) . "\r\n\r\n" . $cuerpo;
        // El cuerpo en base64 ya termina en CRLF; solo falta el punto final de DATA.
        if (!str_ends_with($mensaje, "\r\n")) {
            $mensaje .= "\r\n";
        }
        return $mensaje . ".\r\n";
    }

    /** Trunca respuestas del servidor para que los mensajes de error sean legibles. */
    private function acortar(string $texto): string {
        if (strlen($texto) <= self::MAX_LARGO_RESPUESTA) {
            return $texto;
        }
        return substr($texto, 0, self::MAX_LARGO_RESPUESTA) . '… [truncado]';
    }

    /**
     * Ejecuta una operación silenciando avisos de PHP.
     *
     * El runner de pruebas convierte cualquier warning en excepción, y en
     * producción los fallos de socket se reportan por valor de retorno, no por aviso.
     */
    private function sinAvisos(callable $fn) {
        set_error_handler(static function () {
            return true;
        });
        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }
}
