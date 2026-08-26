<?php
namespace Tests;

use App\Services\ComprobanteParserService;

class ComprobanteParserTest extends TestCase {

    public function testAnalizarTextoIdentifiesVenezuelanBankPatterns() {
        $service = new ComprobanteParserService();

        $texto = "Transferencia Exitosa Banco Mercantil Ref: 00987654 Monto: Bs. 2.450,75 Fecha: 26/08/2026";
        $resultado = $service->analizarTexto($texto);

        $this->assertTrue($resultado['detectado']);
        $this->assertEquals('mercantil', $resultado['banco']);
        $this->assertEquals('00987654', $resultado['referencia']);
        $this->assertEquals(2450.75, $resultado['monto']);
        $this->assertEquals('2026-08-26', $resultado['fecha']);
    }

    public function testAnalizarTextoIdentifiesBanescoPagoMovil() {
        $service = new ComprobanteParserService();

        $texto = "Pago Móvil Banesco Operación #88776655 por 1500.00 el día 15-08-2026";
        $resultado = $service->analizarTexto($texto);

        $this->assertTrue($resultado['detectado']);
        $this->assertEquals('banesco', $resultado['banco']);
        $this->assertEquals('88776655', $resultado['referencia']);
        $this->assertEquals(1500.00, $resultado['monto']);
        $this->assertEquals('2026-08-15', $resultado['fecha']);
    }

    public function testAnalizarTextoReturnsEmptyOnUnmatchedText() {
        $service = new ComprobanteParserService();

        $texto = "Hola esto es un texto cualquiera sin datos bancarios.";
        $resultado = $service->analizarTexto($texto);

        $this->assertFalse($resultado['detectado']);
        $this->assertNull($resultado['banco']);
        $this->assertNull($resultado['referencia']);
        $this->assertNull($resultado['monto']);
    }
}
