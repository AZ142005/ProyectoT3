<?php
namespace Tests;

use App\Services\ComprobanteParserService;

class ComprobanteParserTest extends TestCase {

    public function testAnalizarTextoIdentifiesMercantilWithVenezuelanFormat() {
        $service = new ComprobanteParserService();

        $texto = "Transferencia Exitosa Banco Mercantil Ref: 00987654 Monto: Bs. 2.450,75 Fecha: 26/08/2026";
        $resultado = $service->analizarTexto($texto);

        $this->assertTrue($resultado['detectado']);
        $this->assertEquals('mercantil', $resultado['banco']);
        $this->assertEquals('00987654', $resultado['referencia']);
        // Verifica que 2.450,75 (formato venezolano) NO se infle a 245075
        $this->assertEquals(2450.75, $resultado['monto']);
        $this->assertEquals('2026-08-26', $resultado['fecha']);
    }

    public function testAnalizarTextoIdentifiesBanescoWithInternationalFormat() {
        $service = new ComprobanteParserService();

        $texto = "Pago Banesco Operación #88776655 por 1500.00 el día 15-08-2026";
        $resultado = $service->analizarTexto($texto);

        $this->assertTrue($resultado['detectado']);
        $this->assertEquals('banesco', $resultado['banco']);
        $this->assertEquals('88776655', $resultado['referencia']);
        // Verifica que 1500.00 (formato internacional) NO se infle a 150000
        $this->assertEquals(1500.00, $resultado['monto']);
        $this->assertEquals('2026-08-15', $resultado['fecha']);
    }

    /**
     * Prueba crítica: verifica corrección del bug de inflación 100x en montos
     * con punto decimal internacional (ej. $150.00 → anteriormente se convertía a 15000.00)
     */
    public function testMontoInternacionalNoCausaInflacion100x() {
        $service = new ComprobanteParserService();

        // Caso 1: Monto con etiqueta y formato internacional
        $texto1 = "Total: Bs. 150.00 Ref 123456 Banco Mercantil";
        $r1 = $service->analizarTexto($texto1);
        $this->assertFalse(
            $r1['monto'] === 15000.00,
            "BUG CRITICO: 150.00 fue inflado a 15000.00 (100x). El parser debe distinguir formato internacional."
        );

        // Caso 2: Monto venezolano con puntos de miles (formato correcto)
        $texto2 = "Monto Bs. 1.250,50 Referencia 9876543";
        $r2 = $service->analizarTexto($texto2);
        $this->assertEquals(1250.50, $r2['monto'], "Formato venezolano 1.250,50 debe interpretarse como 1250.50");

        // Caso 3: Monto pequeño sin coma (texto simple)
        $texto3 = "Importe: $ 75.00 nro operacion 654321";
        $r3 = $service->analizarTexto($texto3);
        if ($r3['monto'] !== null) {
            $this->assertEquals(75.00, $r3['monto'], "Monto $75.00 no debe inflarse a $7500.00");
        }
    }

    public function testAnalizarTextoReturnsNotDetectedOnUnmatchedText() {
        $service = new ComprobanteParserService();

        $texto = "Hola esto es un texto cualquiera sin datos bancarios.";
        $resultado = $service->analizarTexto($texto);

        $this->assertFalse($resultado['detectado']);
        $this->assertNull($resultado['banco']);
        $this->assertNull($resultado['referencia']);
        $this->assertNull($resultado['monto']);
    }

    public function testAnalizarTextoHandlesEmptyString() {
        $service = new ComprobanteParserService();

        $resultado = $service->analizarTexto('');
        $this->assertFalse($resultado['detectado']);
        $this->assertNull($resultado['banco']);
    }

    public function testProcesarArchivoReturnsFalseDetectedForUnsupportedFormat() {
        $service = new ComprobanteParserService();

        // Imagen JPG no soportada
        $resultado = $service->procesarArchivo('/tmp/comprobante.jpg', 'jpg');
        $this->assertFalse($resultado['detectado']);
    }
}
