<?php
namespace Tests;

use App\Core\JWT;
use InvalidArgumentException;

class JwtTest extends TestCase {

    public function testJwtEncodeAndDecodeSuccess() {
        $payload = [
            'sub'   => 123,
            'email' => 'admin@condominiodigital.com',
            'role'  => 'admin'
        ];

        $token = JWT::encode($payload, 3600);
        $this->assertTrue(!empty($token));
        $this->assertEquals(3, count(explode('.', $token)));

        $decoded = JWT::decode($token);
        $this->assertEquals(123, $decoded['sub']);
        $this->assertEquals('admin@condominiodigital.com', $decoded['email']);
        $this->assertEquals('admin', $decoded['role']);
    }

    public function testJwtFailsOnInvalidSignature() {
        $payload = ['sub' => 123, 'role' => 'admin'];
        $token = JWT::encode($payload, 3600);

        // Alterar un carácter del token para romper la firma HMAC
        $tamperedToken = substr($token, 0, -5) . 'XYZ12';

        $this->expectException(InvalidArgumentException::class, function() use ($tamperedToken) {
            JWT::decode($tamperedToken);
        });
    }

    public function testJwtFailsOnExpiredToken() {
        $payload = ['sub' => 123, 'role' => 'admin'];
        // Crear token que expiró hace 10 segundos (-10)
        $tokenExpirado = JWT::encode($payload, -10);

        $this->expectException(InvalidArgumentException::class, function() use ($tokenExpirado) {
            JWT::decode($tokenExpirado);
        });
    }
}
