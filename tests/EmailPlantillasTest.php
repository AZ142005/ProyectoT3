<?php
namespace Tests;

/**
 * Regresión de plantillas de correo: la carta de deuda se envía como texto breve
 * con el membrete institucional, el aviso de que la carta viaja en PDF adjunto
 * y el aviso de no responder; la plantilla OTP comparte la marca actual y el pie
 * de no responder.
 */
class EmailPlantillasTest extends TestCase {

    private function readFile(string $relativePath): string {
        return (string)file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    }

    public function testAvisoCobroEsTextoBreveConMembreteYAvisoPdf(): void {
        $plantilla = $this->readFile('app/views/emails/aviso_cobro.php');

        $this->assertStringContains('CONDOMINIO CONJUNTO LAS MESETAS', $plantilla,
            'Debe usar el membrete institucional actual');
        $this->assertStringContains('RIF. J-400577870', $plantilla,
            'Debe usar el RIF actual');
        $this->assertStringContains('PDF', $plantilla,
            'El texto breve debe anunciar la Carta de Deuda en PDF adjunto');
        $this->assertStringContains('no responda a este correo', $plantilla,
            'Debe incluir el aviso de no responder');
        $this->assertFalse(str_contains($plantilla, 'dias_vencido'),
            'No debe quedar la clave dias_vencido (siempre mostraba 0 días)');
        $this->assertFalse(str_contains($plantilla, 'facturas'),
            'El texto breve ya no debe redactar la lista de facturas');
        $this->assertFalse(str_contains($plantilla, '<table'),
            'El texto breve no debe incluir tablas');
    }

    /**
     * La plantilla OTP debe usar la marca institucional actual y el pie de no
     * responder, conservando las variables y la vigencia del código.
     */
    public function testPlantillaOtpAlineadaConMarcaActual(): void {
        $plantilla = $this->readFile('app/views/emails/otp_codigo.php');

        $this->assertStringContains('Condominio Conjunto Las Mesetas', $plantilla,
            'Debe usar el membrete institucional actual');
        $this->assertStringContains('no responda a este correo', $plantilla,
            'Debe incluir el aviso de no responder');
        $this->assertStringContains('$codigoOtp', $plantilla,
            'Debe mantener la variable del código OTP');
        $this->assertStringContains('5 minutos', $plantilla,
            'Debe mantener la vigencia del código');
        $this->assertFalse(str_contains($plantilla, 'Conjunto Las Mesetas de Morón'),
            'No debe quedar la marca antigua');
        $this->assertFalse(str_contains($plantilla, 'Conjunto Digital'),
            'No debe quedar la firma antigua');
    }
}
