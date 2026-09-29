<?php
namespace Tests;

use App\Controllers\PagoDirectoController;
use App\Core\Database;
use App\Models\PagoModel;
use App\Models\UnidadesModel;
use PDO;

/**
 * Tests del portal público de Pago Directo sin iniciar sesión.
 *
 * Cubre la existencia del controlador y sus vistas, el registro de rutas
 * públicas, el enlace desde el login, la migración de residente_id nullable
 * y el ciclo real de alta/consulta/limpieza de un pago invitado.
 */
class PagoDirectoTest extends TestCase {

    private function getDb(): PDO {
        return Database::getConnection();
    }

    public function testControladorMetodosYVistasExisten(): void {
        $this->assertTrue(class_exists(PagoDirectoController::class), "PagoDirectoController debe existir");

        foreach (['index', 'deuda', 'reportar', 'exito'] as $metodo) {
            $this->assertTrue(
                method_exists(PagoDirectoController::class, $metodo),
                "PagoDirectoController debe tener el método público {$metodo}()"
            );
        }

        $base = dirname(__DIR__);
        $this->assertFileExists($base . '/app/views/pago_directo/index.php', 'La vista pública index.php debe existir');
        $this->assertFileExists($base . '/app/views/pago_directo/exito.php', 'La vista de confirmación exito.php debe existir');
    }

    public function testRutasRegistradasEnIndexPublico(): void {
        $content = file_get_contents(dirname(__DIR__) . '/public/index.php');

        $this->assertStringContains("'/pago-directo'", $content, 'Debe registrarse la ruta GET /pago-directo');
        $this->assertStringContains("'/pago-directo/deuda'", $content, 'Debe registrarse la ruta GET /pago-directo/deuda');
        $this->assertStringContains("'/pago-directo/reportar'", $content, 'Debe registrarse la ruta POST /pago-directo/reportar');
        $this->assertStringContains("'/pago-directo/exito'", $content, 'Debe registrarse la ruta GET /pago-directo/exito');
        $this->assertStringContains('PagoDirectoController', $content, 'index.php debe referenciar PagoDirectoController');
    }

    public function testLoginEnlazaAlPagoDirecto(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/views/auth/login.php');

        $this->assertStringContains('/pago-directo', $content, 'El login debe enlazar al portal de pago directo');
        $this->assertStringContains('Pagar sin iniciar sesión', $content, 'El login debe mostrar la opción de pago sin sesión');
    }

    public function testMigracionExisteYReferenciaColumna(): void {
        $path = dirname(__DIR__) . '/scripts/migrate_pago_directo.php';
        $this->assertFileExists($path, 'La migración scripts/migrate_pago_directo.php debe existir');
        $this->assertStringContains('residente_id', (string)file_get_contents($path), 'La migración debe operar sobre pagos.residente_id');
    }

    public function testRateLimiterBloqueaTrasMaxIntentos(): void {
        $db = $this->getDb();
        $key = 'test_rl_' . bin2hex(random_bytes(4));

        try {
            for ($intento = 1; $intento <= 6; $intento++) {
                $permitido = \App\Core\RateLimiter::attempt($key, 5, 60);

                if ($intento <= 5) {
                    $this->assertTrue($permitido, "El intento {$intento} de 5 debe estar permitido");
                } else {
                    $this->assertFalse($permitido, 'El intento 6 debe quedar bloqueado por el rate limiter');
                }
            }

            $restante = \App\Core\RateLimiter::secondsUntilAvailable($key, 60);
            $this->assertGreaterThan(0, $restante, 'secondsUntilAvailable() debe indicar espera tras exceder el límite');
            $this->assertTrue($restante <= 60, 'secondsUntilAvailable() no debe superar la ventana configurada');
        } finally {
            $db->prepare("DELETE FROM rate_limits WHERE `key` = :k")->execute(['k' => $key]);
        }
    }

    public function testRateLimiterComparaVentanasEnHoraSql(): void {
        $content = (string)file_get_contents(dirname(__DIR__) . '/app/core/RateLimiter.php');

        $this->assertStringContains('DATE_SUB(NOW(', $content,
            'attempt() debe comparar la ventana con la hora de MySQL (DATE_SUB(NOW(), ...))');
        $this->assertStringContains('TIMESTAMPDIFF', $content,
            'secondsUntilAvailable() debe calcular el tiempo restante en hora de MySQL (TIMESTAMPDIFF)');
    }

    public function testPagoInvitadoConResidenteNuloSeRegistraYSeLimpia(): void {
        $db = $this->getDb();
        $unidades = (new UnidadesModel())->getActivas(1);

        if (empty($unidades)) {
            $this->skip('No hay unidades activas para ejecutar la prueba de registro');
            return;
        }

        $unidadId = (int)$unidades[0]['id'];
        $referencia = 'TEST-PD-' . time();
        $pagoId = 0;
        $pagoModel = new PagoModel();

        try {
            $datos = [
                'monto'              => 1.25,
                'fecha_pago'         => date('Y-m-d'),
                'metodo_pago'        => 'transferencia',
                'referencia'         => $referencia,
                'observaciones'      => 'Pago directo sin sesión (portal público). Prueba automatizada',
                'banco_pagador'      => 'Banesco',
                'banco_receptor'     => 'Banco de Venezuela',
                'cuenta_bancaria_id' => null
            ];

            $ok = $pagoModel->crearPago(null, $unidadId, $datos, 'test.png');
            $this->assertTrue($ok, 'crearPago() debe aceptar residente_id null para pagos directos');

            $stmt = $db->prepare("SELECT id FROM pagos WHERE referencia = :ref ORDER BY id DESC LIMIT 1");
            $stmt->execute(['ref' => $referencia]);
            $pagoId = (int)$stmt->fetchColumn();
            $this->assertTrue($pagoId > 0, 'El pago directo debe quedar registrado en la tabla pagos');

            $detalle = $pagoModel->obtenerPagoPorId($pagoId);
            $this->assertNotEquals(false, $detalle, 'obtenerPagoPorId() debe retornar el pago registrado');
            if (is_array($detalle)) {
                $this->assertNull($detalle['residente_id'], 'residente_id debe ser NULL en un pago directo');
                $this->assertEquals('Pago directo (sin usuario)', $detalle['residente_nombre'], 'El detalle debe mostrar la etiqueta de pago directo');
            }

            $listado = $pagoModel->obtenerTodosPagos([], 1, 100);
            $encontrado = false;
            foreach ($listado['datos'] as $fila) {
                if ((int)$fila['id'] === $pagoId) {
                    $encontrado = true;
                    $this->assertEquals('Pago directo (sin usuario)', $fila['residente_nombre'], 'El listado administrativo debe mostrar la etiqueta de pago directo');
                }
            }
            $this->assertTrue($encontrado, 'El pago directo debe aparecer en el listado administrativo');
        } finally {
            if ($pagoId > 0) {
                $db->prepare("DELETE FROM pagos WHERE id = :id")->execute(['id' => $pagoId]);
            }
        }
    }
}
