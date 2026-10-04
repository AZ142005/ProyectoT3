<?php
/**
 * Servidor SMTP falso para pruebas locales (NO se ejecuta por sí solo en la suite;
 * lo lanza EmailSmtpTest con proc_open). Atiende UNA sola sesión SMTP en un puerto
 * efímero de 127.0.0.1 y guarda el mensaje recibido en el archivo indicado.
 *
 * Uso: php fake_smtp_server.php <archivo_captura>
 *
 * Protocolo servido: 220 -> EHLO (250 multilínea con AUTH LOGIN) ->
 * AUTH LOGIN (334/334/235) -> MAIL FROM (250) -> RCPT TO (250) -> DATA (354) ->
 * mensaje -> 250 -> QUIT (221).
 */

$archivoCaptura = $argv[1] ?? null;
if ($archivoCaptura === null) {
    fwrite(STDERR, "Falta el archivo de captura.\n");
    exit(2);
}

$servidor = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($servidor === false) {
    fwrite(STDERR, "No se pudo abrir el socket: {$errstr} ({$errno})\n");
    exit(3);
}

$nombre = stream_socket_get_name($servidor, false);
$puerto = intval(substr($nombre, strrpos($nombre, ':') + 1));
echo "PORT={$puerto}\n";
flush();

$conexion = @stream_socket_accept($servidor, 30);
if ($conexion === false) {
    fwrite(STDERR, "Timeout esperando la conexión del cliente.\n");
    fclose($servidor);
    exit(4);
}
stream_set_timeout($conexion, 30);

function responder($conexion, string $linea): void {
    fwrite($conexion, $linea . "\r\n");
}

responder($conexion, '220 fake-smtp listo');

$enData   = false;
$mensaje  = '';
$limite   = time() + 30;

while (time() < $limite) {
    $linea = fgets($conexion, 2048);
    if ($linea === false) {
        break; // El cliente cerró la conexión o se agotó el tiempo.
    }

    if ($enData) {
        if (rtrim($linea, "\r\n") === '.') {
            file_put_contents($archivoCaptura, $mensaje);
            responder($conexion, '250 2.0.0 Mensaje aceptado');
            $enData = false;
        } else {
            $mensaje .= $linea;
        }
        continue;
    }

    $comando = strtoupper(trim($linea));

    if (str_starts_with($comando, 'EHLO')) {
        // Respuesta multilínea: el cliente debe leer hasta la línea con espacio tras el código.
        fwrite($conexion, "250-fake-smtp saluda\r\n250-AUTH LOGIN\r\n250-SIZE 10485760\r\n250 8BITMIME\r\n");
    } elseif ($comando === 'AUTH LOGIN') {
        responder($conexion, '334 VXNlcm5hbWU6'); // base64("Username:")
        fgets($conexion, 2048);
        responder($conexion, '334 UGFzc3dvcmQ6'); // base64("Password:")
        fgets($conexion, 2048);
        responder($conexion, '235 2.7.0 Autenticación exitosa');
    } elseif (str_starts_with($comando, 'MAIL FROM')) {
        responder($conexion, '250 2.1.0 Remitente OK');
    } elseif (str_starts_with($comando, 'RCPT TO')) {
        responder($conexion, '250 2.1.5 Destinatario OK');
    } elseif ($comando === 'DATA') {
        responder($conexion, '354 Envíe el mensaje; termine con .');
        $enData  = true;
        $mensaje = '';
    } elseif ($comando === 'QUIT') {
        responder($conexion, '221 2.0.0 Adiós');
        break;
    } else {
        responder($conexion, '250 2.0.0 OK');
    }
}

fclose($conexion);
fclose($servidor);
exit(0);
