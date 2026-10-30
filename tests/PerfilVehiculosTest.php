<?php
namespace Tests;

use App\Controllers\PerfilController;
use App\Core\Database;
use App\Models\SolicitudesModel;
use App\Models\VehiculosModel;
use PDO;

/**
 * Verifica el registro/actualización de vehículos desde el perfil del residente
 * y la aprobación posterior por parte del administrador.
 */
class PerfilVehiculosTest extends TestCase {

    private function getDb(): PDO {
        return Database::getConnection();
    }

    private function crearUnidadYPersona(PDO $db, bool $conEstacionamiento = true): array {
        $sufijo = bin2hex(random_bytes(4));

        $stmt = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:numero, 0.00, 1)");
        $stmt->execute(['numero' => 'U-VEH-' . $sufijo]);
        $unidadId = (int)$db->lastInsertId();

        if ($conEstacionamiento) {
            $stmt = $db->prepare("INSERT INTO estacionamientos (unidad_id, numero, tipo, estado)
                                  VALUES (:unidad_id, :numero, 'descubierto', 1)");
            $stmt->execute([
                'unidad_id' => $unidadId,
                'numero'    => 'E-VEH-' . $sufijo,
            ]);
        }

        $cedula = 'V-VEH' . substr((string)time(), -6) . $sufijo;
        $email = 'veh_' . $sufijo . time() . '@example.com';
        $stmt = $db->prepare("INSERT INTO personas (cedula, nombre, apellido, email, telefono, unidad_id, tipo, estado)
                              VALUES (:cedula, 'Residente', 'Vehiculo', :email, '04121112233', :unidad_id, 'propietario', 1)");
        $stmt->execute([
            'cedula'    => $cedula,
            'email'     => $email,
            'unidad_id' => $unidadId,
        ]);
        $personaId = (int)$db->lastInsertId();

        return [$unidadId, $personaId, $sufijo];
    }

    private function limpiar(PDO $db, int $unidadId, int $personaId): void {
        $db->exec("DELETE FROM vehiculos WHERE persona_id = {$personaId}");
        $db->exec("DELETE FROM estacionamientos WHERE unidad_id = {$unidadId}");
        $db->exec("DELETE FROM solicitudes_cambio_datos WHERE persona_id = {$personaId}");
        $db->exec("DELETE FROM notificaciones WHERE residente_id = {$personaId}");
        $db->exec("DELETE FROM personas WHERE id = {$personaId}");
        $db->exec("DELETE FROM unidades WHERE id = {$unidadId}");
    }

    private function adminId(PDO $db): int {
        return (int)$db->query("SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
    }

    public function testSolicitudVehiculoSeApruebaYCreaVehiculo(): void {
        $db = $this->getDb();
        $adminId = $this->adminId($db);
        if ($adminId <= 0) {
            $this->skip('No hay usuarios admin en la base de datos de desarrollo.');
            return;
        }

        [$unidadId, $personaId, $sufijo] = $this->crearUnidadYPersona($db);
        $modelo = new SolicitudesModel();

        try {
            $solicitudId = $modelo->crearSolicitudVehiculo($personaId, [
                'accion_vehiculo' => 'crear',
                'vehiculo_tipo'   => 'pequeno',
                'vehiculo_marca'  => 'Toyota',
                'vehiculo_color'  => 'Blanco',
            ]);

            $this->assertTrue($solicitudId > 0, 'La solicitud de vehículo debe crearse');

            $modelo->procesarSolicitud($solicitudId, 'aprobado', null, $adminId);

            $stmt = $db->prepare("SELECT * FROM vehiculos WHERE persona_id = :pid ORDER BY id DESC LIMIT 1");
            $stmt->execute(['pid' => $personaId]);
            $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->assertTrue($vehiculo !== false, 'El vehículo debe registrarse al aprobar la solicitud');
            $this->assertEquals($personaId, (int)$vehiculo['persona_id'], 'El vehículo debe quedar asociado al residente');
            $this->assertEquals($unidadId, (int)$vehiculo['unidad_id'], 'El vehículo debe quedar asociado a la unidad');
            $this->assertEquals('pequeno', $vehiculo['tipo_vehiculo'], 'El tipo debe copiarse del payload');
            $this->assertEquals('Toyota', $vehiculo['marca'], 'La marca debe copiarse del payload');
            $this->assertEquals('Blanco', $vehiculo['color'], 'El color debe copiarse del payload');
            $this->assertTrue($vehiculo['placa'] === null, 'La placa debe quedar nula al no ser obligatoria');
            $this->assertTrue(!empty($vehiculo['estacionamiento_id']),
                'El vehículo debe quedar asociado a la plaza de estacionamiento de la unidad');

            $stmtSol = $db->prepare("SELECT estado FROM solicitudes_cambio_datos WHERE id = :id");
            $stmtSol->execute(['id' => $solicitudId]);
            $this->assertEquals('aprobado', $stmtSol->fetchColumn(), 'La solicitud debe quedar aprobada');
        } finally {
            $this->limpiar($db, $unidadId, $personaId);
        }
    }

    public function testSolicitudVehiculoSeApruebaYActualizaVehiculo(): void {
        $db = $this->getDb();
        $adminId = $this->adminId($db);
        if ($adminId <= 0) {
            $this->skip('No hay usuarios admin en la base de datos de desarrollo.');
            return;
        }

        [$unidadId, $personaId, $sufijo] = $this->crearUnidadYPersona($db);
        $vehiculosModel = new VehiculosModel();
        $modelo = new SolicitudesModel();

        try {
            $vehiculoId = $vehiculosModel->crearVehiculo([
                'unidad_id'          => $unidadId,
                'persona_id'         => $personaId,
                'estacionamiento_id' => null,
                'tipo_vehiculo'      => 'pequeno',
                'marca'              => 'Chevrolet',
                'color'              => 'Gris',
            ]);

            $solicitudId = $modelo->crearSolicitudVehiculo($personaId, [
                'accion_vehiculo' => 'actualizar',
                'vehiculo_id'     => $vehiculoId,
                'vehiculo_tipo'   => 'camioneta',
                'vehiculo_marca'  => 'Ford',
                'vehiculo_color'  => 'Rojo',
            ]);

            $modelo->procesarSolicitud($solicitudId, 'aprobado', null, $adminId);

            $stmt = $db->prepare("SELECT * FROM vehiculos WHERE id = :id");
            $stmt->execute(['id' => $vehiculoId]);
            $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->assertEquals('camioneta', $vehiculo['tipo_vehiculo'], 'El tipo debe actualizarse');
            $this->assertEquals('Ford', $vehiculo['marca'], 'La marca debe actualizarse');
            $this->assertEquals('Rojo', $vehiculo['color'], 'El color debe actualizarse');
        } finally {
            $this->limpiar($db, $unidadId, $personaId);
        }
    }

    public function testSolicitudVehiculoTipoInvalidoNoSeAprueba(): void {
        $db = $this->getDb();
        $adminId = $this->adminId($db);
        if ($adminId <= 0) {
            $this->skip('No hay usuarios admin en la base de datos de desarrollo.');
            return;
        }

        [$unidadId, $personaId, $sufijo] = $this->crearUnidadYPersona($db);
        $modelo = new SolicitudesModel();

        try {
            $solicitudId = $modelo->crearSolicitudVehiculo($personaId, [
                'accion_vehiculo' => 'crear',
                'vehiculo_tipo'   => 'invalido',
                'vehiculo_marca'  => 'Marca',
                'vehiculo_color'  => 'Color',
            ]);

            $this->assertTrue($solicitudId > 0, 'La solicitud con tipo inválido debe crearse para que la rechace el aprobador');

            $fallo = false;
            try {
                $modelo->procesarSolicitud($solicitudId, 'aprobado', null, $adminId);
            } catch (\Throwable $e) {
                $fallo = true;
            }

            $this->assertTrue($fallo, 'La aprobación debe fallar si el tipo de vehículo no es válido');

            $stmtSol = $db->prepare("SELECT estado FROM solicitudes_cambio_datos WHERE id = :id");
            $stmtSol->execute(['id' => $solicitudId]);
            $this->assertEquals('pendiente', $stmtSol->fetchColumn(), 'La solicitud debe seguir pendiente tras el fallo');

            $stmt = $db->prepare("SELECT COUNT(*) FROM vehiculos WHERE persona_id = :pid");
            $stmt->execute(['pid' => $personaId]);
            $this->assertEquals(0, (int)$stmt->fetchColumn(), 'No debe registrarse el vehículo con tipo inválido');
        } finally {
            $this->limpiar($db, $unidadId, $personaId);
        }
    }

    public function testControladorBloqueaRegistroSinPlaza(): void {
        $db = $this->getDb();
        [$unidadId, $personaId, $sufijo] = $this->crearUnidadYPersona($db, false);

        $ctrl = new class extends PerfilController {
            public string $redirectUrl = '';
            protected function redirect($url): void {
                $this->redirectUrl = $url;
            }
        };

        $_SESSION['auth_user'] = [
            'id'         => $personaId,
            'role'       => 'residente',
            'persona_id' => $personaId,
            'name'       => 'Residente Vehiculo',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST = [
            'accion_vehiculo' => 'crear',
            'vehiculo_id'     => 0,
            'tipo_vehiculo'   => 'pequeno',
            'marca'           => 'Toyota',
            'color'           => 'Blanco',
        ];

        try {
            $ctrl->solicitarCambioVehiculo();

            $this->assertEquals('/perfil', $ctrl->redirectUrl, 'Debe redirigir al perfil');
            $this->assertStringContains('plaza de estacionamiento', \App\Core\Flash::get('danger'),
                'Debe explicar que la unidad no tiene plaza de estacionamiento');

            $stmt = $db->prepare("SELECT COUNT(*) FROM solicitudes_cambio_datos WHERE persona_id = :pid");
            $stmt->execute(['pid' => $personaId]);
            $this->assertEquals(0, (int)$stmt->fetchColumn(),
                'No debe crearse una solicitud de registro sin plaza de estacionamiento');
        } finally {
            $this->limpiar($db, $unidadId, $personaId);
        }
    }

    public function testAprobacionRegistroSinPlazaFalla(): void {
        $db = $this->getDb();
        $adminId = $this->adminId($db);
        if ($adminId <= 0) {
            $this->skip('No hay usuarios admin en la base de datos de desarrollo.');
            return;
        }

        [$unidadId, $personaId, $sufijo] = $this->crearUnidadYPersona($db, false);
        $modelo = new SolicitudesModel();

        try {
            $solicitudId = $modelo->crearSolicitudVehiculo($personaId, [
                'accion_vehiculo' => 'crear',
                'vehiculo_tipo'   => 'pequeno',
                'vehiculo_marca'  => 'Toyota',
                'vehiculo_color'  => 'Blanco',
            ]);

            $fallo = false;
            try {
                $modelo->procesarSolicitud($solicitudId, 'aprobado', null, $adminId);
            } catch (\Throwable $e) {
                $fallo = true;
            }

            $this->assertTrue($fallo, 'La aprobación debe fallar si la unidad no tiene plaza de estacionamiento');

            $stmtSol = $db->prepare("SELECT estado FROM solicitudes_cambio_datos WHERE id = :id");
            $stmtSol->execute(['id' => $solicitudId]);
            $this->assertEquals('pendiente', $stmtSol->fetchColumn(),
                'La solicitud debe seguir pendiente tras el fallo');

            $stmt = $db->prepare("SELECT COUNT(*) FROM vehiculos WHERE persona_id = :pid");
            $stmt->execute(['pid' => $personaId]);
            $this->assertEquals(0, (int)$stmt->fetchColumn(),
                'No debe registrarse el vehículo sin plaza de estacionamiento');
        } finally {
            $this->limpiar($db, $unidadId, $personaId);
        }
    }

    public function testControladorAdminBloqueaRegistroSinPlaza(): void {
        $controller = file_get_contents(dirname(__DIR__) . '/app/Controllers/EstacionamientoController.php');

        $this->assertStringContains('no tiene una plaza de estacionamiento asignada', $controller,
            'El registro administrativo debe bloquear unidades sin plaza');
    }

    public function testControladorSolicitudVehiculoCreaSolicitud(): void {
        $db = $this->getDb();
        [$unidadId, $personaId, $sufijo] = $this->crearUnidadYPersona($db);

        $ctrl = new class extends PerfilController {
            public string $redirectUrl = '';
            protected function redirect($url): void {
                $this->redirectUrl = $url;
            }
        };

        $_SESSION['auth_user'] = [
            'id'         => $personaId,
            'role'       => 'residente',
            'persona_id' => $personaId,
            'name'       => 'Residente Vehiculo',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST = [
            'accion_vehiculo' => 'crear',
            'vehiculo_id'     => 0,
            'tipo_vehiculo'   => 'camion_f350',
            'marca'           => 'Nissan',
            'color'           => 'Azul',
        ];

        try {
            $ctrl->solicitarCambioVehiculo();

            $this->assertEquals('/perfil', $ctrl->redirectUrl, 'Debe redirigir al perfil');
            $this->assertStringContains('enviada', \App\Core\Flash::get('success'),
                'Debe confirmar el envío de la solicitud');

            $stmt = $db->prepare("SELECT datos_nuevos_json, estado FROM solicitudes_cambio_datos WHERE persona_id = :pid ORDER BY id DESC LIMIT 1");
            $stmt->execute(['pid' => $personaId]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->assertTrue($fila !== false, 'Debe existir una solicitud para el residente');
            $this->assertEquals('pendiente', $fila['estado'], 'La solicitud debe quedar pendiente');

            $payload = json_decode($fila['datos_nuevos_json'], true);
            $this->assertEquals('vehiculo', $payload['tipo_solicitud'] ?? null, 'El payload debe marcarse como vehículo');
            $this->assertEquals('crear', $payload['accion_vehiculo'] ?? null, 'El payload debe indicar creación');
            $this->assertEquals('camion_f350', $payload['vehiculo_tipo'] ?? null, 'El tipo debe guardarse en el payload');
            $this->assertEquals('Nissan', $payload['vehiculo_marca'] ?? null, 'La marca debe guardarse en el payload');
            $this->assertEquals('Azul', $payload['vehiculo_color'] ?? null, 'El color debe guardarse en el payload');
        } finally {
            $this->limpiar($db, $unidadId, $personaId);
        }
    }

    public function testPerfilIncluyeFormularioSolicitudVehiculo(): void {
        $view = file_get_contents(dirname(__DIR__) . '/app/views/perfil/index.php');

        $this->assertStringContains('Mis Vehículos', $view, 'El perfil debe incluir la sección de vehículos');
        $this->assertStringContains('/perfil/solicitar-cambio-vehiculo', $view, 'El formulario debe apuntar al endpoint de solicitud');
        $this->assertStringContains('id="vehiculo_solicitud_id"', $view, 'Debe existir el selector de vehículo');
        $this->assertStringContains('name="tipo_vehiculo"', $view, 'Debe existir el campo tipo de vehículo');
        $this->assertStringContains('name="color"', $view, 'Debe existir el campo color');
        $this->assertStringContains('name="marca"', $view, 'Debe existir el campo marca');
        $this->assertStringContains('prefillVehiculoSolicitud', $view, 'Debe existir el prellenado al elegir un vehículo');
        $this->assertStringContains('tieneEstacionamiento', $view, 'La vista debe condicionar el registro a la plaza asignada');
        $this->assertStringContains('Sin plaza de estacionamiento', $view, 'La vista debe avisar cuando no hay plaza asignada');
        $this->assertFalse(strpos($view, 'name="placa"') !== false, 'El formulario ya no debe pedir placa');
        $this->assertFalse(strpos($view, 'name="modelo"') !== false, 'El formulario ya no debe pedir modelo');
    }

    public function testAdminMuestraSolicitudesDeVehiculo(): void {
        $partial = file_get_contents(dirname(__DIR__) . '/app/views/admin/solicitudes_cambio/index.php');

        $this->assertStringContains('tipo_solicitud', $partial, 'La bandeja debe distinguir el tipo de solicitud');
        $this->assertStringContains('vehiculo_tipo', $partial, 'La bandeja debe mostrar el tipo de vehículo solicitado');
        $this->assertStringContains('vehiculo_marca', $partial, 'La bandeja debe mostrar la marca solicitada');
        $this->assertStringContains('vehiculo_color', $partial, 'La bandeja debe mostrar el color solicitado');
        $this->assertStringContains('Registro de vehículo', $partial, 'Debe mostrar la acción de registro');
        $this->assertStringContains('Actualización de vehículo', $partial, 'Debe mostrar la acción de actualización');
        $this->assertFalse(strpos($partial, 'vehiculo_placa') !== false, 'La bandeja ya no debe mostrar placa');
    }

    public function testRutaSolicitudVehiculoRegistrada(): void {
        $rutas = file_get_contents(dirname(__DIR__) . '/public/index.php');

        $this->assertStringContains('/perfil/solicitar-cambio-vehiculo', $rutas, 'La ruta de solicitud de vehículo debe existir');
        $this->assertStringContains('solicitarCambioVehiculo', $rutas, 'La ruta debe apuntar al controlador de perfil');
    }
}
