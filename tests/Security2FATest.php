<?php
namespace Tests;

use App\Models\OtpModel;

class Security2FATest extends TestCase {

    public function testOtpModelClassExistsAndHasRequiredMethods() {
        $this->assertTrue(class_exists(OtpModel::class));
        $this->assertTrue(method_exists(OtpModel::class, 'generarOtp'));
        $this->assertTrue(method_exists(OtpModel::class, 'verificarOtp'));
    }

    public function testPasswordHashAlgorithmsFor2fa() {
        $otp = "123456";
        $hash = password_hash($otp, PASSWORD_BCRYPT);

        $this->assertTrue(!empty($hash));
        $this->assertTrue(password_verify("123456", $hash));
        $this->assertFalse(password_verify("654321", $hash));
    }

    public function testOtpNumericFormatting() {
        for ($i = 0; $i < 10; $i++) {
            $code = sprintf('%06d', random_int(100000, 999999));
            $this->assertEquals(6, strlen($code));
            $this->assertTrue(ctype_digit($code));
        }
    }

    /**
     * Regresión post-merge: AuthController::procesar2fa recarga la cuenta con
     * getActiveById() tras validar el OTP; el método faltaba en UsuariosModel
     * y rompía el login 2FA de admin/auditor con Error 500.
     */
    public function testAuthModelsExposeGetActiveById() {
        $this->assertTrue(method_exists(\App\Models\UsuariosModel::class, 'getActiveById'),
            'UsuariosModel debe exponer getActiveById (usado por AuthController::procesar2fa)');
        $this->assertTrue(method_exists(\App\Models\PersonasModel::class, 'getActiveById'),
            'PersonasModel debe exponer getActiveById (usado por procesar2fa y PerfilController)');
    }
}
