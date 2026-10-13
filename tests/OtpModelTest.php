<?php
namespace Tests;

use App\Models\OtpModel;
use PDO;
use PDOStatement;

/**
 * Doble de prueba para PDOStatement que simula consultas sobre auth_otp_tokens.
 */
class FakeOtpStatement extends PDOStatement {
    private string $sql;
    private FakeOtpPDO $pdo;
    private ?array $fetchedRow = null;
    private int $affectedRows = 1;

    public function __construct(string $sql, FakeOtpPDO $pdo) {
        $this->sql = $sql;
        $this->pdo = $pdo;
    }

    public function execute(?array $params = null): bool {
        $params = $params ?? [];
        $this->pdo->queries[] = [
            'sql'    => $this->sql,
            'params' => $params
        ];

        if (str_contains($this->sql, 'SELECT *') && str_contains($this->sql, 'FROM auth_otp_tokens')) {
            $this->fetchedRow = $this->pdo->activeTokenRow;
        } elseif (str_contains($this->sql, 'UPDATE auth_otp_tokens SET usado = 1 WHERE id = :id AND usado = 0')) {
            $this->affectedRows = $this->pdo->simulateConcurrentConsumption ? 0 : 1;
            if (!$this->pdo->simulateConcurrentConsumption && $this->pdo->activeTokenRow) {
                $this->pdo->activeTokenRow['usado'] = 1;
            }
        } elseif (str_contains($this->sql, 'UPDATE auth_otp_tokens SET intentos = :intentos, usado = 1')) {
            if ($this->pdo->activeTokenRow) {
                $this->pdo->activeTokenRow['intentos'] = $params['intentos'];
                $this->pdo->activeTokenRow['usado'] = 1;
            }
        } elseif (str_contains($this->sql, 'UPDATE auth_otp_tokens SET intentos = :intentos WHERE id = :id')) {
            if ($this->pdo->activeTokenRow) {
                $this->pdo->activeTokenRow['intentos'] = $params['intentos'];
            }
        } elseif (str_contains($this->sql, 'UPDATE auth_otp_tokens SET usado = 1 WHERE id = :id')) {
            if ($this->pdo->activeTokenRow) {
                $this->pdo->activeTokenRow['usado'] = 1;
            }
        }

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return $this->fetchedRow ?? false;
    }

    public function rowCount(): int {
        return $this->affectedRows;
    }
}

/**
 * Doble de prueba para PDO en OtpModelTest.
 */
class FakeOtpPDO extends PDO {
    public ?array $activeTokenRow = null;
    public bool $simulateConcurrentConsumption = false;
    public array $queries = [];

    public function __construct() {
        // Sin conexión externa
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new FakeOtpStatement($query, $this);
    }
}

class OtpModelTest extends TestCase {

    /**
     * TC-WB-04: Verificación de OTP cuando no existe token activo para el usuario.
     */
    public function testVerificarOtpSinTokenActivo() {
        $pdo = new FakeOtpPDO();
        $pdo->activeTokenRow = null;

        $model = new OtpModel($pdo);
        $res = $model->verificarOtp(15, '123456');

        $this->assertFalse($res['valido'], "Debe rechazar cuando no hay token activo");
        $this->assertStringContains('No hay un código de verificación activo', $res['error'] ?? '');
    }

    /**
     * TC-WB-05: Verificación de OTP expirado evaluado por el motor SQL (expirado = 1).
     */
    public function testVerificarOtpExpiradoMarcaTokenComoUsado() {
        $pdo = new FakeOtpPDO();
        $pdo->activeTokenRow = [
            'id'          => 10,
            'usuario_id'  => 15,
            'codigo_hash' => password_hash('654321', PASSWORD_BCRYPT),
            'intentos'    => 0,
            'usado'       => 0,
            'expires_at'  => '2026-01-01 00:00:00',
            'expirado'    => 1
        ];

        $model = new OtpModel($pdo);
        $res = $model->verificarOtp(15, '654321');

        $this->assertFalse($res['valido'], "Un token expirado no debe ser válido aunque el código coincida");
        $this->assertStringContains('ha expirado', $res['error'] ?? '');
        $this->assertEquals(1, $pdo->activeTokenRow['usado'], "El token expirado debe marcarse como usado = 1");
    }

    /**
     * TC-WB-06: Control de intentos fallidos e invalidación automática al 3er intento (BV-07 y BV-08).
     */
    public function testVerificarOtpBloqueaAlTercerIntentoFallido() {
        $pdo = new FakeOtpPDO();
        $pdo->activeTokenRow = [
            'id'          => 20,
            'usuario_id'  => 7,
            'codigo_hash' => password_hash('112233', PASSWORD_BCRYPT),
            'intentos'    => 0,
            'usado'       => 0,
            'expires_at'  => '2099-12-31 23:59:59',
            'expirado'    => 0
        ];

        $model = new OtpModel($pdo);

        // Intento 1 fallido
        $res1 = $model->verificarOtp(7, '000000');
        $this->assertFalse($res1['valido']);
        $this->assertStringContains('Intento 1 de 3', $res1['error'] ?? '');
        $this->assertEquals(1, $pdo->activeTokenRow['intentos']);
        $this->assertEquals(0, $pdo->activeTokenRow['usado']);

        // Intento 2 fallido (BV-07)
        $res2 = $model->verificarOtp(7, '999999');
        $this->assertFalse($res2['valido']);
        $this->assertStringContains('Intento 2 de 3', $res2['error'] ?? '');
        $this->assertEquals(2, $pdo->activeTokenRow['intentos']);
        $this->assertEquals(0, $pdo->activeTokenRow['usado']);

        // Intento 3 fallido -> Invalida el código (BV-08)
        $res3 = $model->verificarOtp(7, '888888');
        $this->assertFalse($res3['valido']);
        $this->assertStringContains('superado el límite de 3 intentos', $res3['error'] ?? '');
        $this->assertEquals(3, $pdo->activeTokenRow['intentos']);
        $this->assertEquals(1, $pdo->activeTokenRow['usado'], "Al tercer fallo el token debe quedar invalidado (usado = 1)");
    }

    /**
     * Verificación exitosa y protección contra condición de carrera (reuso concurrente).
     */
    public function testVerificarOtpValidoYPrevencionDeCondicionDeCarrera() {
        $pdo = new FakeOtpPDO();
        $pdo->activeTokenRow = [
            'id'          => 30,
            'usuario_id'  => 9,
            'codigo_hash' => password_hash('482910', PASSWORD_BCRYPT),
            'intentos'    => 1,
            'usado'       => 0,
            'expires_at'  => '2099-12-31 23:59:59',
            'expirado'    => 0
        ];

        $model = new OtpModel($pdo);
        $resOk = $model->verificarOtp(9, '482910');

        $this->assertTrue($resOk['valido'], "El código correcto y vigente debe validarse exitosamente");
        $this->assertNull($resOk['error']);
        $this->assertEquals(1, $pdo->activeTokenRow['usado']);

        // Simular condición de carrera donde otra petición consumió el token simultáneamente
        $pdoRace = new FakeOtpPDO();
        $pdoRace->activeTokenRow = [
            'id'          => 31,
            'usuario_id'  => 9,
            'codigo_hash' => password_hash('482910', PASSWORD_BCRYPT),
            'intentos'    => 0,
            'usado'       => 0,
            'expires_at'  => '2099-12-31 23:59:59',
            'expirado'    => 0
        ];
        $pdoRace->simulateConcurrentConsumption = true;

        $modelRace = new OtpModel($pdoRace);
        $resRace = $modelRace->verificarOtp(9, '482910');
        $this->assertFalse($resRace['valido'], "Si rowCount() es 0 por consumo concurrente, debe rechazar el token");
        $this->assertStringContains('ya fue utilizado', $resRace['error'] ?? '');
    }
}
