<?php
namespace Tests;

use App\Core\Encryption;
use App\Services\NotificationService;
use App\Services\EmailService;
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
        $this->assertStringContains("150.50", $html);
        $this->assertStringContains("REF-998877", $html);
    }
}
