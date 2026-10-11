<?php
namespace Tests;

/**
 * Regresión de plantillas de correo: la carta de deuda debe usar el membrete
 * institucional actual, los días de mora reales (dias_mora, no dias_vencido)
 * y el aviso de no responder.
 */
class EmailPlantillasTest extends TestCase {

    private function readFile(string $relativePath): string {
        return (string)file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    }

    public function testAvisoCobroUsaMembreteActualDiasMoraYAvisoNoResponder(): void {
        $plantilla = $this->readFile('app/views/emails/aviso_cobro.php');

        $this->assertStringContains('CONDOMINIO CONJUNTO LAS MESETAS', $plantilla,
            'Debe usar el membrete institucional actual');
        $this->assertStringContains('RIF. J-400577870', $plantilla,
            'Debe usar el RIF actual');
        $this->assertStringContains('dias_mora', $plantilla,
            'Los días deben venir de dias_mora (el dato real que entrega el modelo)');
        $this->assertFalse(str_contains($plantilla, 'dias_vencido'),
            'No debe quedar la clave dias_vencido (siempre mostraba 0 días)');
        $this->assertStringContains('no responda a este correo', $plantilla,
            'Debe incluir el aviso de no responder');
    }
}
