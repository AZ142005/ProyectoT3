<?php
namespace Tests;

use App\Core\MetodoPago;

/**
 * Tests de la regla compartida de métodos de pago y requisito de referencia.
 */
class MetodoPagoTest extends TestCase {

    public function testMetodosValidosYNormalizacion(): void {
        $this->assertTrue(MetodoPago::esValido('transferencia'), 'transferencia debe ser un método válido');
        $this->assertTrue(MetodoPago::esValido('Pago Móvil'), '"Pago Móvil" debe normalizarse y ser válido');
        $this->assertTrue(MetodoPago::esValido(' pago_movil '), 'El método debe normalizarse recortando espacios');
        $this->assertFalse(MetodoPago::esValido('efectivo'), 'efectivo no debe ser un método válido');

        $this->assertEquals('pago_movil', MetodoPago::normalizar('Pago Móvil'),
            'normalizar() debe convertir "Pago Móvil" a "pago_movil"');
    }

    public function testReferenciaOpcionalSoloParaPagoMovilBBVA(): void {
        $this->assertFalse(MetodoPago::requiereReferencia('pago_movil', 'BBVA Provincial'),
            'Pago Móvil en BBVA Provincial no debe exigir referencia');
        $this->assertFalse(MetodoPago::requiereReferencia('PAGO MOVIL', 'bbva provincial'),
            'La excepción debe aplicarse sin distinguir mayúsculas ni tildes');
        $this->assertTrue(MetodoPago::requiereReferencia('pago_movil', 'Banesco'),
            'Pago Móvil en otro banco debe exigir referencia');
        $this->assertTrue(MetodoPago::requiereReferencia('transferencia', 'BBVA Provincial'),
            'Transferencia en BBVA Provincial debe exigir referencia');
        $this->assertTrue(MetodoPago::requiereReferencia('pago_movil', ''),
            'Sin banco declarado debe exigir referencia');
        $this->assertTrue(MetodoPago::requiereReferencia('efectivo', 'BBVA Provincial'),
            'Un método desconocido debe exigir referencia (fail closed)');
    }

    public function testControladoresYVistasCompartenLaRegla(): void {
        $base = dirname(__DIR__);

        $pagoController = (string)file_get_contents($base . '/app/Controllers/PagoController.php');
        $pagoDirectoController = (string)file_get_contents($base . '/app/Controllers/PagoDirectoController.php');
        $vistaResidente = (string)file_get_contents($base . '/app/views/pagos/residente/subir.php');
        $vistaPublica = (string)file_get_contents($base . '/app/views/pago_directo/index.php');

        $this->assertStringContains('MetodoPago::requiereReferencia', $pagoController,
            'PagoController debe validar la referencia con MetodoPago::requiereReferencia');
        $this->assertStringContains('MetodoPago::requiereReferencia', $pagoDirectoController,
            'PagoDirectoController debe validar la referencia con MetodoPago::requiereReferencia');

        $this->assertStringContains('name="metodo_pago"', $vistaResidente,
            'La vista de residente debe enviar metodo_pago');
        $this->assertStringContains('data-excepciones-referencia', $vistaResidente,
            'La vista de residente debe exponer las excepciones de referencia al JS');
        $this->assertStringContains('actualizarRequisitoReferencia', $vistaResidente,
            'La vista de residente debe reevaluar el requisito de referencia');
        $this->assertStringContains('data.metodo_pago', $vistaResidente,
            'La vista de residente debe autocompletar el método desde el OCR');

        $this->assertStringContains('data-excepciones-referencia', $vistaPublica,
            'La vista pública debe exponer las excepciones de referencia al JS');
        $this->assertStringContains('actualizarRequisitoReferencia', $vistaPublica,
            'La vista pública debe reevaluar el requisito de referencia');
    }
}
