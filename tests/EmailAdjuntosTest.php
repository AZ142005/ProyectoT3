<?php
namespace Tests;

use App\Core\Database;
use App\Core\Encryption;
use App\Services\EmailService;
use App\Services\NotificationService;

/**
 * Adjuntos de la cola de notificaciones: cifrado en cola con contenido
 * decodificable, límite de 2 MB por adjunto y registro en el modo mock.
 */
class EmailAdjuntosTest extends TestCase {

    /**
     * (a) Encolar con adjunto guarda el JSON cifrado y el contenido decodifica al binario original.
     */
    public function testEncolarConAdjuntoPersisteContenidoDecodificable(): void {
        $db = Database::getConnection();
        $marcador = '%PDF-1.4 adjunto-' . uniqid();
        $maxColaId = intval($db->query("SELECT COALESCE(MAX(id), 0) AS total FROM notificaciones_cola")->fetch()['total']);
        $colaId = null;

        try {
            $resultado = (new NotificationService())->encolarNotificacion(
                'adjuntos-' . uniqid() . '@example.com',
                'Prueba de adjunto',
                '<p>Cuerpo con PDF adjunto</p>',
                null,
                'email',
                'normal',
                [[
                    'nombre'    => 'carta.pdf',
                    'mime'      => 'application/pdf',
                    'contenido' => $marcador,
                ]]
            );
            $this->assertTrue($resultado, 'Encolar con adjunto debe retornar true');

            $stmt = $db->prepare("SELECT id, adjuntos FROM notificaciones_cola WHERE id > :max ORDER BY id DESC LIMIT 1");
            $stmt->execute(['max' => $maxColaId]);
            $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->assertNotNull($fila, 'La notificación encolada debe existir en la tabla');
            if ($fila === false) {
                return;
            }
            $colaId = intval($fila['id']);

            $this->assertNotNull($fila['adjuntos'], 'La columna adjuntos debe quedar cifrada, no nula');
            $adjuntos = json_decode(Encryption::decrypt($fila['adjuntos']), true);
            $this->assertIsArray($adjuntos, 'Los adjuntos deben decodificar como JSON válido');
            $this->assertEquals('carta.pdf', $adjuntos[0]['nombre'] ?? null);
            $this->assertEquals('application/pdf', $adjuntos[0]['mime'] ?? null);
            $this->assertEquals($marcador, base64_decode($adjuntos[0]['contenido'] ?? '', true),
                'El contenido base64 debe decodificar al binario original');
        } finally {
            if ($colaId !== null) {
                $db->prepare("DELETE FROM notificaciones_cola WHERE id = :id")->execute(['id' => $colaId]);
            }
        }
    }

    /**
     * (b) Un adjunto mayor a 2 MB lanza excepción en español y no encola nada.
     */
    public function testAdjuntoMayorADosMbLanzaExcepcionYSinEncolar(): void {
        $db = Database::getConnection();
        $colaAntes = intval($db->query("SELECT COUNT(*) AS total FROM notificaciones_cola")->fetch()['total']);

        $this->expectExceptionMessage('2 MB', function () {
            (new NotificationService())->encolarNotificacion(
                'adjunto-grande@example.com',
                'Adjunto demasiado grande',
                '<p>x</p>',
                null,
                'email',
                'normal',
                [[
                    'nombre'    => 'grande.pdf',
                    'mime'      => 'application/pdf',
                    'contenido' => str_repeat('A', 2097153), // 2 MB + 1 byte
                ]]
            );
        });

        $this->assertEquals($colaAntes, intval($db->query("SELECT COUNT(*) AS total FROM notificaciones_cola")->fetch()['total']),
            'Un adjunto fuera de límite no debe encolar ninguna fila');
    }

    /**
     * (c) En modo mock, EmailService::enviar acepta adjuntos y registra el nombre en el log.
     */
    public function testEmailServiceMockRegistraNombreDelAdjunto(): void {
        $envPrevio = $_ENV['MAIL_MODE'] ?? null;
        $_ENV['MAIL_MODE'] = 'mock';

        try {
            $archivoLog = BASE_PATH . '/app/logs/mail_mock.log';
            $nombre = 'adjunto-' . uniqid() . '.pdf';

            $resultado = (new EmailService())->enviar(
                'mock-adjunto-' . uniqid() . '@example.com',
                'Correo con adjunto',
                '<p>Hola</p>',
                [[
                    'nombre'    => $nombre,
                    'mime'      => 'application/pdf',
                    'contenido' => '%PDF-1.4 demo',
                ]]
            );

            $this->assertTrue($resultado, 'El modo mock con adjuntos debe retornar true');
            clearstatcache(true, $archivoLog);
            $this->assertFileExists($archivoLog, 'El envío mock debe escribir app/logs/mail_mock.log');
            $contenidoLog = is_file($archivoLog) ? (string)file_get_contents($archivoLog) : '';
            $this->assertStringContains('ADJUNTOS:', $contenidoLog, 'El log mock debe rotular los adjuntos');
            $this->assertStringContains($nombre, $contenidoLog, 'El log mock debe mencionar el nombre del adjunto');
        } finally {
            if ($envPrevio === null) {
                unset($_ENV['MAIL_MODE']);
            } else {
                $_ENV['MAIL_MODE'] = $envPrevio;
            }
        }
    }
}
