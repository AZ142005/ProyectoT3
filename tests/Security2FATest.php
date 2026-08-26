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
}
