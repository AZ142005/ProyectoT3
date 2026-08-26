<?php
namespace Tests;

use Tests\TestCase;

/**
 * Tests de Helpers — Funciones helper del proyecto
 *
 * formatearMoneda, validarCedula, validarEmail, validarTelefono,
 * nombreMes, diasHastaVencimiento, csrf helpers.
 */
class HelperTest extends TestCase {

    // =====================================================================
    // formatearMoneda()
    // =====================================================================

    public function testFormatearMonedaFormatsCorrectly(): void {
        $this->assertEquals('Bs. 1.234,56', formatearMoneda(1234.56),
            "Debe formatear number con separadores venezolanos");
    }

    public function testFormatearMonedaWithZero(): void {
        $this->assertEquals('Bs. 0,00', formatearMoneda(0),
            "0 debe formatearse como Bs. 0,00");
    }

    public function testFormatearMonedaWithNull(): void {
        $this->assertEquals('Bs. 0,00', formatearMoneda(null),
            "null debe formatearse como Bs. 0,00");
    }

    public function testFormatearMonedaWithEmptyString(): void {
        $this->assertEquals('Bs. 0,00', formatearMoneda(''),
            "String vacío debe formatearse como Bs. 0,00");
    }

    public function testFormatearMonedaWithString(): void {
        $this->assertEquals('Bs. 150,00', formatearMoneda('150'),
            "String numérico debe formatearse correctamente");
    }

    public function testFormatearMonedaLargeNumber(): void {
        $this->assertEquals('Bs. 1.234.567,89', formatearMoneda(1234567.89),
            "Números grandes deben formatearse con miles");
    }

    public function testFormatearMonedaNegativeNumber(): void {
        $this->assertEquals('Bs. -500,00', formatearMoneda(-500),
            "Números negativos deben formatearse correctamente");
    }

    // =====================================================================
    // validarCedula()
    // =====================================================================

    public function testValidarCedulaValidV(): void {
        $this->assertTrue(validarCedula('V12345678'),
            "Cédula V con 8 dígitos debe ser válida");
    }

    public function testValidarCedulaValidE(): void {
        $this->assertTrue(validarCedula('E8765432'),
            "Cédula E con 8 dígitos debe ser válida");
    }

    public function testValidarCedulaValidWithoutPrefix(): void {
        $this->assertTrue(validarCedula('12345678'),
            "Cédula sin prefijo V/E con 8 dígitos debe ser válida");
    }

    public function testValidarCedulaValid7Digits(): void {
        $this->assertTrue(validarCedula('V1234567'),
            "Cédula V con 7 dígitos debe ser válida");
    }

    public function testValidarCedulaInvalidTooShort(): void {
        $this->assertFalse(validarCedula('V123456'),
            "Cédula con 6 dígitos debe ser inválida");
    }

    public function testValidarCedulaInvalidLetters(): void {
        $this->assertFalse(validarCedula('VABCDEF1'),
            "Cédula con letras debe ser inválida");
    }

    public function testValidarCedulaEmpty(): void {
        $this->assertFalse(validarCedula(''),
            "Cédula vacía debe ser inválida");
    }

    public function testValidarCedulaCaseInsensitive(): void {
        $this->assertTrue(validarCedula('v12345678'),
            "Cédula con v minúscula debe ser válida");
        $this->assertTrue(validarCedula('e12345678'),
            "Cédula con e minúscula debe ser válida");
    }

    // =====================================================================
    // validarEmail()
    // =====================================================================

    public function testValidarEmailValid(): void {
        $this->assertTrue(validarEmail('usuario@dominio.com'),
            "Email válido debe pasar la validación");
    }

    public function testValidarEmailValidWithSubdomain(): void {
        $this->assertTrue(validarEmail('user@mail.empresa.com.ve'),
            "Email con subdominios debe ser válido");
    }

    public function testValidarEmailInvalid(): void {
        $this->assertFalse(validarEmail('no-es-email'),
            "String sin @ debe ser inválido");
    }

    public function testValidarEmailInvalidNoAt(): void {
        $this->assertFalse(validarEmail('usuario.com'),
            "Email sin @ debe ser inválido");
    }

    public function testValidarEmailInvalidNoDomain(): void {
        $this->assertFalse(validarEmail('user@'),
            "Email sin dominio debe ser inválido");
    }

    public function testValidarEmailEmpty(): void {
        $this->assertFalse(validarEmail(''),
            "Email vacío debe ser inválido");
    }

    // =====================================================================
    // validarTelefono()
    // =====================================================================

    /**
     * BUG DETECTADO: La regex actual es ^(0?4(1|2|4|6)\d{7})$ que acepta
     * números de 9-10 dígitos, pero el formato venezolano real es
     * 0412-1234567 (11 dígitos con 0, 10 sin 0).
     * La regex debería usar \d{8} en lugar de \d{7}.
     * Estos tests documentan el comportamiento ACTUAL (con el bug).
     */
    public function testValidarTelefonoRejects10DigitFormat(): void {
        // After fix: 10-digit format (04XX + 5 dígitos) should be rejected
        // Standard Venezuelan format is 11 digits: 04XX + 7 dígitos
        $this->assertFalse(validarTelefono('0412123456'),
            "Teléfono 0412123456 (10 dígitos) debe ser rechazado — formato estándar es 11 dígitos");
    }

    public function testValidarTelefonoRejectsStandardFormat(): void {
        // Formato estándar venezolano: 04XX + 7 dígitos = 11 dígitos
        // BUG: La regex no lo acepta porque usa \d{7} en lugar de \d{8}
        $result = validarTelefono('04121234567');
        if ($result) {
            $this->passed++;
        } else {
            $this->failures[] = "BUG CONOCIDO: validarTelefono('04121234567') retorna false — la regex debería aceptar el formato estándar de 11 dígitos (04XX-XXXXXXX). Usar \\d{8} en lugar de \\d{7}";
            $this->failed++;
        }
    }

    public function testValidarTelefonoRejectsNonMobile(): void {
        $this->assertFalse(validarTelefono('02121234567'),
            "Teléfono fijo 02XX no debe ser válido");
    }

    public function testValidarTelefonoRejectsTooShort(): void {
        $this->assertFalse(validarTelefono('04121234'),
            "Teléfono muy corto debe ser inválido");
    }

    public function testValidarTelefonoRejectsEmpty(): void {
        $this->assertFalse(validarTelefono(''),
            "Teléfono vacío debe ser inválido");
    }

    // =====================================================================
    // nombreMes()
    // =====================================================================

    public function testNombreMesReturnsCorrectMonth(): void {
        $this->assertEquals('Enero', nombreMes(1));
        $this->assertEquals('Febrero', nombreMes(2));
        $this->assertEquals('Marzo', nombreMes(3));
        $this->assertEquals('Abril', nombreMes(4));
        $this->assertEquals('Mayo', nombreMes(5));
        $this->assertEquals('Junio', nombreMes(6));
        $this->assertEquals('Julio', nombreMes(7));
        $this->assertEquals('Agosto', nombreMes(8));
        $this->assertEquals('Septiembre', nombreMes(9));
        $this->assertEquals('Octubre', nombreMes(10));
        $this->assertEquals('Noviembre', nombreMes(11));
        $this->assertEquals('Diciembre', nombreMes(12));
    }

    public function testNombreMesInvalidReturnsEmpty(): void {
        $this->assertEquals('', nombreMes(0),
            "Mes 0 debe retornar string vacío");
        $this->assertEquals('', nombreMes(13),
            "Mes 13 debe retornar string vacío");
        $this->assertEquals('', nombreMes(-1),
            "Mes negativo debe retornar string vacío");
    }

    public function testNombreMesStringInput(): void {
        $this->assertEquals('Enero', nombreMes('1'),
            "nombreMes debe aceptar string numérico");
    }

    // =====================================================================
    // diasHastaVencimiento()
    // =====================================================================

    public function testDiasHastaVencimientoFutureDate(): void {
        $futureDate = date('Y-m-d', strtotime('+10 days'));
        $result = diasHastaVencimiento($futureDate);

        $this->assertNotNull($result, "Debe retornar un número, no null");
        $this->assertGreaterThan(0, $result,
            "Fecha futura debe retornar días positivos");
    }

    public function testDiasHastaVencimientoPastDate(): void {
        $pastDate = date('Y-m-d', strtotime('-5 days'));
        $result = diasHastaVencimiento($pastDate);

        $this->assertNotNull($result, "Debe retornar un número, no null");
        $this->assertTrue($result < 0,
            "Fecha pasada debe retornar días negativos");
    }

    public function testDiasHastaVencimientoToday(): void {
        $today = date('Y-m-d');
        $result = diasHastaVencimiento($today);

        $this->assertEquals(0, $result,
            "Hoy debe retornar 0 días");
    }

    public function testDiasHastaVencimientoEmptyDate(): void {
        $this->assertNull(diasHastaVencimiento(''),
            "Fecha vacía debe retornar null");
    }

    // =====================================================================
    // CSRF HELPERS
    // =====================================================================

    public function testCsrfTokenFunctionExists(): void {
        $this->assertTrue(function_exists('csrf_token'),
            "Función csrf_token() debe existir");
    }

    public function testCsrfFieldFunctionExists(): void {
        $this->assertTrue(function_exists('csrf_field'),
            "Función csrf_field() debe existir");
    }

    public function testCsrfFieldReturnsHtmlInput(): void {
        $this->startTestSession();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $field = csrf_field();
        $this->assertStringContains('type="hidden"', $field,
            "csrf_field() debe retornar input hidden");
        $this->assertStringContains('name="csrf_token"', $field,
            "csrf_field() debe tener name=csrf_token");
        $this->assertStringContains($_SESSION['csrf_token'], $field,
            "csrf_field() debe contener el token de la sesión");

        $this->destroyTestSession();
    }

    public function testCsrfTokenReturnsSessionToken(): void {
        $this->startTestSession();
        $expectedToken = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $expectedToken;

        $this->assertEquals($expectedToken, csrf_token(),
            "csrf_token() debe retornar el token de la sesión");

        $this->destroyTestSession();
    }

    // =====================================================================
    // e() — XSS ESCAPING (tests adicionales)
    // =====================================================================

    public function testHelperEFunctionExists(): void {
        $this->assertTrue(function_exists('e'),
            "Función e() debe existir");
    }

    public function testHelperEWithNull(): void {
        $this->assertEquals('', e(null),
            "e(null) debe retornar string vacío");
    }

    // =====================================================================
    // ARCHIVOS DE HELPERS
    // =====================================================================

    public function testHelpersFileExists(): void {
        $this->assertFileExists(dirname(__DIR__) . '/app/core/helpers.php',
            "El archivo helpers.php debe existir en core/");
    }

    public function testHelpersHaveFunctionGuards(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/core/helpers.php');

        $this->assertStringContains('function_exists', $content,
            "helpers.php debe usar guards function_exists() para evitar redeclaración");
    }

    private function startTestSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    private function destroyTestSession(): void {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
