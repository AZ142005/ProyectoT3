<?php
namespace Tests;

use App\Services\ComprobanteParserService;

class DeteccionCuentaDestinoTest extends TestCase {

    private function getCuentasEjemplo(): array {
        return [
            [
                'id'             => 1,
                'banco'          => 'Banesco',
                'numero_cuenta'  => '01340001000000001234',
                'titular'        => 'Condominio Central',
                'identificacion' => 'J-30123456-0',
                'telefono'       => '04141234567',
                'activa'         => 1
            ],
            [
                'id'             => 2,
                'banco'          => 'Banco Mercantil',
                'numero_cuenta'  => '01050002000000005678',
                'titular'        => 'Condominio Central',
                'identificacion' => 'J-30123456-0',
                'telefono'       => '04247654321',
                'activa'         => 1
            ],
            [
                'id'             => 3,
                'banco'          => 'BBVA Provincial',
                'numero_cuenta'  => '01080003000000009999',
                'titular'        => 'Condominio Antiguo',
                'identificacion' => 'J-30123456-0',
                'telefono'       => '04169998877',
                'activa'         => 0 // Cuenta inactiva
            ]
        ];
    }

    public function testDeteccionPorCuentaCompleta20Digitos(): void {
        $service = new ComprobanteParserService();
        $texto = "Pago Móvil Interbancario\nCuenta Destino: 0105-0002-00-0000005678\nRef: 12345678\nMonto: Bs. 850,00\nFecha: 15/09/2026";
        $resultado = $service->analizarTexto($texto);

        $this->assertEquals('01050002000000005678', $resultado['cuenta_destino_numero'] ?? '');

        $validacion = $service->validarCuentaDestino($resultado, $this->getCuentasEjemplo());
        $this->assertEquals(2, $validacion['cuenta_bancaria_id']);
        $this->assertTrue($validacion['cuenta_destino_valida']);
        $this->assertEquals(1.0, $validacion['confianza']);
        $this->assertNull($validacion['inconsistencia']);
    }

    public function testDeteccionPorTelefonoYIdentificacion(): void {
        $service = new ComprobanteParserService();
        $texto = "Pago Móvil BDV\nTeléfono Beneficiario: 0414-1234567\nDocumento: J-30123456-0\nReferencia: 98765432\nMonto: 150.00\nFecha: 12/09/2026";
        $resultado = $service->analizarTexto($texto);

        $validacion = $service->validarCuentaDestino($resultado, $this->getCuentasEjemplo());
        $this->assertEquals(1, $validacion['cuenta_bancaria_id']);
        $this->assertTrue($validacion['cuenta_destino_valida']);
        $this->assertEquals('Banesco', $validacion['banco_receptor']);
        $this->assertNull($validacion['inconsistencia']);
    }

    public function testDeteccionPorPrefijo4Digitos(): void {
        $service = new ComprobanteParserService();
        $texto = "Transferencia a Cuenta 0105-XXXX-XX-XXXXXXXXXX\nRef: 44556677\nTotal: 400.00\nFecha: 10-09-2026";
        $resultado = $service->analizarTexto($texto);

        $validacion = $service->validarCuentaDestino($resultado, $this->getCuentasEjemplo());
        $this->assertEquals(2, $validacion['cuenta_bancaria_id']);
        $this->assertTrue($validacion['cuenta_destino_valida']);
        $this->assertEquals(0.85, $validacion['confianza']);
    }

    public function testCuentaInactivaGeneraInconsistencia(): void {
        $service = new ComprobanteParserService();
        $texto = "Transferencia Exitosa a Cuenta: 01080003000000009999\nRef: 11223344\nMonto: 300.00\nFecha: 05/09/2026";
        $resultado = $service->analizarTexto($texto);

        $validacion = $service->validarCuentaDestino($resultado, $this->getCuentasEjemplo());
        $this->assertFalse($validacion['cuenta_destino_valida']);
        $this->assertNull($validacion['cuenta_bancaria_id']);
        $this->assertNotNull($validacion['inconsistencia']);
        $this->assertTrue(str_contains(strtolower($validacion['inconsistencia']), 'inactiva'));
    }

    public function testCuentaDesconocidaGeneraInconsistencia(): void {
        $service = new ComprobanteParserService();
        // Cuenta de banco inexistente en la lista oficial (ej. Banco de Venezuela 0102...)
        $texto = "Transferencia Exitosa a Cuenta: 01020000000000007777\nRef: 99001122\nMonto: 120.00\nFecha: 08/09/2026";
        $resultado = $service->analizarTexto($texto);

        $validacion = $service->validarCuentaDestino($resultado, $this->getCuentasEjemplo());
        $this->assertFalse($validacion['cuenta_destino_valida']);
        $this->assertNull($validacion['cuenta_bancaria_id']);
        $this->assertNotNull($validacion['inconsistencia']);
        $this->assertTrue(str_contains(strtolower($validacion['inconsistencia']), 'no coincide'));
    }

    public function testFallbackUnicaCuentaActiva(): void {
        $service = new ComprobanteParserService();
        $soloUnaCuenta = [
            [
                'id'             => 10,
                'banco'          => 'Banesco',
                'numero_cuenta'  => '01340001000000001234',
                'titular'        => 'Condominio Central',
                'activa'         => 1
            ]
        ];

        // Texto sin cuenta destino ni banco especificado
        $texto = "Pago aprobado por punto de venta Ref: 55667788 Monto: 75.00 Fecha: 01/09/2026";
        $resultado = $service->analizarTexto($texto);

        $validacion = $service->validarCuentaDestino($resultado, $soloUnaCuenta);
        $this->assertEquals(10, $validacion['cuenta_bancaria_id']);
        $this->assertTrue($validacion['cuenta_destino_valida']);
        $this->assertEquals(0.5, $validacion['confianza']);
    }

    public function testInconsistenciaFechaFutura(): void {
        $service = new ComprobanteParserService();
        $texto = "Pago Móvil Mercantil Ref: 88776655 Monto: 100.00 Fecha: 31/12/2099";
        $resultado = $service->analizarTexto($texto);

        $this->assertNotNull($resultado['inconsistencias']['fecha_pago'] ?? null);
        $this->assertTrue(str_contains(strtolower($resultado['inconsistencias']['fecha_pago']), 'futura'));
        $this->assertTrue(($resultado['confianza']['fecha_pago'] ?? 1.0) <= 0.4);
    }

    public function testInconsistenciaReferenciaCorta(): void {
        $service = new ComprobanteParserService();
        $texto = "Pago Móvil Mercantil Ref: 42 Monto: 100.00 Fecha: 01/09/2026";
        $resultado = $service->analizarTexto($texto);

        $this->assertNotNull($resultado['inconsistencias']['referencia'] ?? null);
        $this->assertTrue(str_contains(strtolower($resultado['inconsistencias']['referencia']), 'corta'));
    }
}
