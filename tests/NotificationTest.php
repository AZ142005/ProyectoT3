<?php
namespace Tests;

use App\Core\Encryption;
use App\Services\NotificationService;
use App\Services\EmailService;
use App\Models\NotificacionesModel;
use App\Core\Database;
use InvalidArgumentException;

class NotificationTest extends TestCase {

    public function testEncryptionEncryptsAndDecryptsCorrectly() {
        $original = "residente@mesetasdemoron.com";
        $cifrado = Encryption::encrypt($original);

        $this->assertTrue(!empty($cifrado));
        $this->assertNotEquals($original, $cifrado);

        $descifrado = Encryption::decrypt($cifrado);
        $this->assertEquals($original, $descifrado);
    }

    public function testEncryptionFailsOnCorruptData() {
        $this->expectException(InvalidArgumentException::class, function() {
            Encryption::decrypt("cadena_corrupta_no_base64!!!");
        });
    }

    public function testAnalizarTelefonoIdentifiesMobileAndLandline() {
        // Móvil local venezolano
        $movil = NotificationService::analizarTelefono("0412-1234567");
        $this->assertTrue($movil['es_movil']);
        $this->assertEquals("584121234567", $movil['telefono']);

        // Fijo residencial
        $fijo = NotificationService::analizarTelefono("0212-9998877");
        $this->assertFalse($fijo['es_movil']);
        $this->assertNotNull($fijo['mensaje']);

        // Internacional
        $inter = NotificationService::analizarTelefono("+584241112233");
        $this->assertTrue($inter['es_movil']);
        $this->assertEquals("584241112233", $inter['telefono']);
    }

    public function testGenerarEnlaceWhatsAppReturnsNullForLandline() {
        $linkFijo = NotificationService::generarEnlaceWhatsApp("0212-9998877", "Hola");
        $this->assertNull($linkFijo);

        $linkMovil = NotificationService::generarEnlaceWhatsApp("0414-7778899", "Hola Residente");
        $this->assertNotNull($linkMovil);
        $this->assertStringContains("584147778899", $linkMovil);
    }

    public function testEmailServiceRendersTemplatesCorrectly() {
        $emailService = new EmailService();
        $html = $emailService->renderTemplate('pago_aprobado', [
            'nombreResidente' => 'Juan Pérez',
            'monto'           => 150.50,
            'referencia'      => 'REF-998877'
        ]);

        $this->assertStringContains("Juan Pérez", $html);
        $this->assertStringContains("150,50", $html);
        $this->assertStringContains("REF-998877", $html);
    }

    public function testRegistrarNotificacionResidenteYNotificacionesModel() {
        $db = Database::getConnection();
        $persona = $db->query("SELECT id FROM personas LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        if (!$persona) {
            $this->markTestSkipped("No hay personas en la base de datos para probar notificaciones.");
            return;
        }

        $residenteId = intval($persona['id']);
        $notifService = new NotificationService();
        $notifModel = new NotificacionesModel();

        $inicialNoLeidas = $notifModel->contarNoLeidas($residenteId);

        // 1. Registrar notificación
        $titulo = "Test Notificación " . uniqid();
        $mensaje = "Mensaje de prueba para validar inserción con residente_id.";
        $notifId = $notifService->registrarNotificacionResidente(
            $residenteId,
            $titulo,
            $mensaje,
            'success',
            '/residente/historial'
        );

        $this->assertTrue($notifId > 0, "El ID de la notificación debe ser mayor que 0");

        // 2. Comprobar conteo no leídas
        $nuevoNoLeidas = $notifModel->contarNoLeidas($residenteId);
        $this->assertEquals($inicialNoLeidas + 1, $nuevoNoLeidas);

        // 3. Comprobar obtención
        $noLeidas = $notifModel->obtenerNoLeidas($residenteId);
        $encontrada = false;
        foreach ($noLeidas as $item) {
            if (intval($item['id']) === $notifId) {
                $encontrada = true;
                $this->assertEquals($titulo, $item['titulo']);
                $this->assertEquals('success', $item['tipo']);
                $this->assertEquals('/residente/historial', $item['enlace']);
                $this->assertEquals(0, intval($item['leido']));
                break;
            }
        }
        $this->assertTrue($encontrada, "La notificación recién creada debe encontrarse entre las no leídas");

        // 4. Marcar como leída
        $marcada = $notifModel->marcarComoLeida($notifId, $residenteId);
        $this->assertTrue($marcada, "Debe retornar true al marcar como leída");

        $conteoPostLeida = $notifModel->contarNoLeidas($residenteId);
        $this->assertEquals($inicialNoLeidas, $conteoPostLeida);

        // Limpieza de prueba
        $db->prepare("DELETE FROM notificaciones WHERE id = :id")->execute(['id' => $notifId]);
    }

    public function testResolverTelefonoDeudorPrefierePropietarioYUsaFallbackMovil() {
        // 1. Propietario con móvil: se usa tal cual fue registrado
        $resolucion = NotificationService::resolverTelefonoDeudor('0414-1234567', []);
        $this->assertEquals('propietario', $resolucion['fuente']);
        $this->assertEquals('0414-1234567', $resolucion['telefono']);
        $this->assertNull($resolucion['nombre']);

        // 2. Propietario sin teléfono: fallback a la primera persona con móvil
        $resolucion = NotificationService::resolverTelefonoDeudor('', [
            ['telefono' => '0414-1234567', 'nombre' => 'Ana', 'apellido' => 'Pérez']
        ]);
        $this->assertEquals('persona', $resolucion['fuente']);
        $this->assertEquals('0414-1234567', $resolucion['telefono']);
        $this->assertEquals('Ana Pérez', $resolucion['nombre']);

        // 3. Propietario con línea fija: se salta y usa el móvil de la persona asociada
        $resolucion = NotificationService::resolverTelefonoDeudor('0212-1234567', [
            ['telefono' => '0212-9998877', 'nombre' => 'Luis', 'apellido' => 'Gómez'],
            ['telefono' => '0424-7654321', 'nombre' => 'Ana', 'apellido' => 'Pérez']
        ]);
        $this->assertEquals('persona', $resolucion['fuente']);
        $this->assertEquals('0424-7654321', $resolucion['telefono']);
        $this->assertEquals('Ana Pérez', $resolucion['nombre']);

        // 4. Sin ningún número disponible
        $resolucion = NotificationService::resolverTelefonoDeudor(null, []);
        $this->assertEquals('ninguna', $resolucion['fuente']);
        $this->assertEquals('', $resolucion['telefono']);
        $this->assertNull($resolucion['nombre']);

        // 5. Propietario con línea fija y sin personas asociadas
        $resolucion = NotificationService::resolverTelefonoDeudor('0212-1234567', []);
        $this->assertEquals('ninguna', $resolucion['fuente']);
        $this->assertEquals('', $resolucion['telefono']);
    }
}
