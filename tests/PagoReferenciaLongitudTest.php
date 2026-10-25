<?php
namespace Tests;

/**
 * Prueba unitaria de validación de longitud del campo referencia (4 a 20 caracteres)
 * tanto en vistas (HTML5) como en controladores (Backend).
 */
class PagoReferenciaLongitudTest extends TestCase {

    public function testVistaResidenteSubirDefineAtributosLongitud(): void {
        $vista = (string)file_get_contents(dirname(__DIR__) . '/app/views/pagos/residente/subir.php');
        $this->assertStringContains('id="referencia"', $vista, 'La vista subir.php debe contener el input #referencia');
        $this->assertStringContains('minlength="4"', $vista, 'El input #referencia en subir.php debe tener minlength="4"');
        $this->assertStringContains('maxlength="20"', $vista, 'El input #referencia en subir.php debe tener maxlength="20"');
        $this->assertStringContains('required', $vista, 'El input #referencia en subir.php debe tener required');
    }

    public function testVistaPagoDirectoDefineAtributosLongitud(): void {
        $vista = (string)file_get_contents(dirname(__DIR__) . '/app/views/pago_directo/index.php');
        $this->assertStringContains('id="referencia"', $vista, 'La vista index.php de pago directo debe contener #referencia');
        $this->assertStringContains('minlength="4"', $vista, 'El input #referencia en pago directo debe tener minlength="4"');
        $this->assertStringContains('maxlength="20"', $vista, 'El input #referencia en pago directo debe tener maxlength="20"');
        $this->assertStringContains('required', $vista, 'El input #referencia en pago directo debe tener required');
    }

    public function testPagoControllerValidaLongitudReferencia(): void {
        $codigo = (string)file_get_contents(dirname(__DIR__) . '/app/Controllers/PagoController.php');
        $this->assertStringContains('$longitudRef < 4 || $longitudRef > 20', $codigo,
            'PagoController::subir() debe rechazar referencias con longitud menor a 4 o mayor a 20');
        $this->assertStringContains('El número de referencia debe tener entre 4 y 20 caracteres.', $codigo,
            'PagoController::subir() debe informar el mensaje de validación de 4 a 20 caracteres');
    }

    public function testPagoDirectoControllerValidaLongitudReferencia(): void {
        $codigo = (string)file_get_contents(dirname(__DIR__) . '/app/Controllers/PagoDirectoController.php');
        $this->assertStringContains('strlen($referencia) < 4 || strlen($referencia) > 20', $codigo,
            'PagoDirectoController::reportar() debe rechazar referencias con longitud menor a 4 o mayor a 20');
        $this->assertStringContains('El número de referencia debe tener entre 4 y 20 caracteres.', $codigo,
            'PagoDirectoController::reportar() debe informar el mensaje de validación de 4 a 20 caracteres');
    }
}
