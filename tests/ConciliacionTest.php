<?php
namespace Tests;

use App\Services\ConciliacionBancariaService;

class ConciliacionTest extends TestCase {

    public function testNormalizarReferenciaStripsNonNumericAndLeadingZeros() {
        $service = new ConciliacionBancariaService();

        $this->assertEquals("123456", $service->normalizarReferencia("REF-00123456"));
        $this->assertEquals("998877", $service->normalizarReferencia("TRANSF-998877"));
        $this->assertEquals("1234567890", $service->normalizarReferencia("0001234567890"));
        $this->assertEquals("0", $service->normalizarReferencia("00000"));
        $this->assertEquals("0", $service->normalizarReferencia("---"));
    }

    public function testJaroWinklerSimilarity() {
        $service = new ConciliacionBancariaService();

        // Cadenas idénticas
        $this->assertEquals(1.0, $service->calcularSimilitudJaroWinkler("123456", "123456"));

        // Transposición de caracteres (123456 vs 123465) debe superar umbral 0.85
        $similitudTransp = $service->calcularSimilitudJaroWinkler("123456", "123465");
        $this->assertTrue($similitudTransp >= 0.85);

        // Cadenas completamente diferentes
        $similitudDif = $service->calcularSimilitudJaroWinkler("111111", "999999");
        $this->assertTrue($similitudDif < 0.5);
    }

    public function testParserDetectsDebitsAndNormalizesAmounts() {
        $service = new ConciliacionBancariaService();

        $csvTemp = tempnam(sys_get_temp_dir(), 'test_extracto_');
        $lineas = [
            "Fecha;Referencia;Descripcion;Monto",
            "25/08/2026;REF-00123456;PAGO MOVIL RESIDENTE;1.250,50",
            "25/08/2026;REF-999000;COMISION MANTENIMIENTO;-15,00"
        ];
        file_put_contents($csvTemp, implode("\n", $lineas));

        $resultado = $service->parsearArchivo($csvTemp, 'mercantil');
        unlink($csvTemp);

        $this->assertEquals(2, count($resultado));
        $this->assertEquals("2026-08-25", $resultado[0]['fecha']);
        $this->assertEquals(1250.50, $resultado[0]['monto']);
        $this->assertEquals("credito", $resultado[0]['tipo']);

        $this->assertEquals("2026-08-25", $resultado[1]['fecha']);
        $this->assertEquals(15.00, $resultado[1]['monto']);
        $this->assertEquals("debito", $resultado[1]['tipo']);
    }
}
