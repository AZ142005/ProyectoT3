<?php
namespace Tests;

use App\Controllers\UsuarioAdminController;
use App\Models\SolicitudesModel;
use App\Core\Database;
use PDO;

/**
 * Verifica la pestaña "Cambios de Datos" del panel de usuarios (RF 9 opción B):
 * listado filtrable, comparación visual y procesamiento aprobar/rechazar.
 */
class SolicitudesCambioDatosTest extends TestCase {

    private function getDb(): PDO {
        return Database::getConnection();
    }

    /**
     * Crea una persona temporal para las pruebas y devuelve su ID.
     */
    private function crearPersonaTemporal(PDO $db, string $sufijo): int {
        $cedula = 'V-CAM' . substr((string)time(), -7) . $sufijo;
        $email  = 'cam_test_' . $sufijo . '_' . time() . '@example.com';

        $db->exec("DELETE FROM personas WHERE cedula = '{$cedula}'");
        $db->exec("INSERT INTO personas (cedula, nombre, apellido, email, telefono, tipo, estado)
                   VALUES ('{$cedula}', 'Camila', 'Datos{$sufijo}', '{$email}', '04121112233', 'propietario', 1)");

        return (int)$db->lastInsertId();
    }

    /**
     * Controlador anónimo que captura el redirect en lugar de emitir cabeceras.
     */
    private function crearControladorTest(): UsuarioAdminController {
        return new class extends UsuarioAdminController {
            public string $redirectUrl = '';
            protected function redirect($url): void {
                $this->redirectUrl = $url;
            }
        };
    }

    public function testObtenerTodasAdminFiltraPorEstado(): void {
        $db = $this->getDb();
        $modelo = new SolicitudesModel();

        $personaId = $this->crearPersonaTemporal($db, 'F');

        try {
            $idPendiente = $modelo->crearSolicitud($personaId, ['telefono' => '04122223333']);
            $idAprobada  = $modelo->crearSolicitud($personaId, ['telefono' => '04143334444']);

            $this->assertTrue($idPendiente > 0 && $idAprobada > 0, "Deben crearse las dos solicitudes temporales");

            $db->exec("UPDATE solicitudes_cambio_datos SET estado = 'aprobado', fecha_respuesta = NOW() WHERE id = {$idAprobada}");

            // Sin filtro aparecen ambas
            $todas = $modelo->obtenerTodasAdmin(1, 25, null);
            $idsTodas = array_column($todas['datos'], 'id');
            $this->assertContains($idPendiente, $idsTodas, "Sin filtro debe aparecer la solicitud pendiente");
            $this->assertContains($idAprobada, $idsTodas, "Sin filtro debe aparecer la solicitud aprobada");

            // Filtro pendiente solo trae la pendiente
            $pendientes = $modelo->obtenerTodasAdmin(1, 25, 'pendiente');
            $idsPendientes = array_column($pendientes['datos'], 'id');
            $this->assertContains($idPendiente, $idsPendientes, "El filtro 'pendiente' debe incluir la solicitud pendiente");
            $this->assertFalse(in_array($idAprobada, $idsPendientes), "El filtro 'pendiente' no debe incluir la solicitud aprobada");

            // Filtro aprobado solo trae la aprobada
            $aprobadas = $modelo->obtenerTodasAdmin(1, 25, 'aprobado');
            $idsAprobadas = array_column($aprobadas['datos'], 'id');
            $this->assertContains($idAprobada, $idsAprobadas, "El filtro 'aprobado' debe incluir la solicitud aprobada");
            $this->assertFalse(in_array($idPendiente, $idsAprobadas), "El filtro 'aprobado' no debe incluir la solicitud pendiente");

            // Un estado fuera de la whitelist se ignora (no lanza y no filtra)
            $invalido = $modelo->obtenerTodasAdmin(1, 25, 'inexistente');
            $this->assertTrue(isset($invalido['datos']) && is_array($invalido['datos']), "Un estado inválido no debe romper el listado");
        } finally {
            $db->exec("DELETE FROM solicitudes_cambio_datos WHERE persona_id = {$personaId}");
            $db->exec("DELETE FROM personas WHERE id = {$personaId}");
        }
    }

    public function testProcesarSolicitudCambioApruebaYAplicaDatos(): void {
        $db = $this->getDb();
        $modelo = new SolicitudesModel();

        $adminId = (int)$db->query("SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($adminId <= 0) {
            $this->skip('No hay usuarios admin en la base de datos de desarrollo.');
            return;
        }

        $personaId = $this->crearPersonaTemporal($db, 'A');

        try {
            $solicitudId = $modelo->crearSolicitud($personaId, ['telefono' => '04149998877']);
            $this->assertTrue($solicitudId > 0, "La solicitud de cambio debe crearse");

            $ctrl = $this->crearControladorTest();
            $_SESSION['auth_user'] = ['id' => $adminId, 'role' => 'admin', 'name' => 'Admin Test'];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = ['id' => $solicitudId, 'accion' => 'aprobar', 'motivo' => ''];

            $ctrl->procesarSolicitudCambio();

            $this->assertEquals('/admin/usuarios?tab=cambios', $ctrl->redirectUrl, "Debe redirigir a la pestaña de cambios");
            $flash = \App\Core\Flash::get('success');
            $this->assertStringContains('aprobada', $flash, "Debe emitir Flash de éxito al aprobar");

            // Persona actualizada con el dato solicitado
            $stmtPersona = $db->prepare("SELECT telefono FROM personas WHERE id = :id");
            $stmtPersona->execute(['id' => $personaId]);
            $this->assertEquals('04149998877', $stmtPersona->fetchColumn(), "El teléfono de la persona debe quedar actualizado");

            // Solicitud resuelta con fecha de respuesta
            $stmtSolicitud = $db->prepare("SELECT estado, fecha_respuesta FROM solicitudes_cambio_datos WHERE id = :id");
            $stmtSolicitud->execute(['id' => $solicitudId]);
            $fila = $stmtSolicitud->fetch(PDO::FETCH_ASSOC);
            $this->assertEquals('aprobado', $fila['estado'], "La solicitud debe quedar en estado aprobado");
            $this->assertNotNull($fila['fecha_respuesta'], "La solicitud debe registrar fecha_respuesta");
        } finally {
            $db->exec("DELETE FROM solicitudes_cambio_datos WHERE persona_id = {$personaId}");
            $db->exec("DELETE FROM notificaciones WHERE residente_id = {$personaId}");
            $db->exec("DELETE FROM personas WHERE id = {$personaId}");
        }
    }

    public function testProcesarSolicitudCambioRechazaConMotivo(): void {
        $db = $this->getDb();
        $modelo = new SolicitudesModel();

        $adminId = (int)$db->query("SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($adminId <= 0) {
            $this->skip('No hay usuarios admin en la base de datos de desarrollo.');
            return;
        }

        $personaId = $this->crearPersonaTemporal($db, 'R');

        try {
            $solicitudId = $modelo->crearSolicitud($personaId, ['telefono' => '04149998877']);

            $ctrl = $this->crearControladorTest();
            $_SESSION['auth_user'] = ['id' => $adminId, 'role' => 'admin', 'name' => 'Admin Test'];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = ['id' => $solicitudId, 'accion' => 'rechazar', 'motivo' => 'Documentación insuficiente.'];

            $ctrl->procesarSolicitudCambio();

            $this->assertEquals('/admin/usuarios?tab=cambios', $ctrl->redirectUrl, "Debe redirigir a la pestaña de cambios");
            $flash = \App\Core\Flash::get('success');
            $this->assertStringContains('rechazada', $flash, "Debe emitir Flash de éxito al rechazar");

            $stmtSolicitud = $db->prepare("SELECT estado, motivo_admin FROM solicitudes_cambio_datos WHERE id = :id");
            $stmtSolicitud->execute(['id' => $solicitudId]);
            $fila = $stmtSolicitud->fetch(PDO::FETCH_ASSOC);
            $this->assertEquals('rechazado', $fila['estado'], "La solicitud debe quedar en estado rechazado");
            $this->assertEquals('Documentación insuficiente.', $fila['motivo_admin'], "El motivo del rechazo debe guardarse");

            // La persona permanece intacta
            $stmtPersona = $db->prepare("SELECT telefono FROM personas WHERE id = :id");
            $stmtPersona->execute(['id' => $personaId]);
            $this->assertEquals('04121112233', $stmtPersona->fetchColumn(), "Al rechazar no deben aplicarse cambios en la persona");
        } finally {
            $db->exec("DELETE FROM solicitudes_cambio_datos WHERE persona_id = {$personaId}");
            $db->exec("DELETE FROM personas WHERE id = {$personaId}");
        }
    }

    public function testProcesarSolicitudCambioRechazaSinMotivoBloquea(): void {
        $db = $this->getDb();
        $modelo = new SolicitudesModel();

        $adminId = (int)$db->query("SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($adminId <= 0) {
            $this->skip('No hay usuarios admin en la base de datos de desarrollo.');
            return;
        }

        $personaId = $this->crearPersonaTemporal($db, 'S');

        try {
            $solicitudId = $modelo->crearSolicitud($personaId, ['telefono' => '04149998877']);

            $ctrl = $this->crearControladorTest();
            $_SESSION['auth_user'] = ['id' => $adminId, 'role' => 'admin', 'name' => 'Admin Test'];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = ['id' => $solicitudId, 'accion' => 'rechazar', 'motivo' => ''];

            $ctrl->procesarSolicitudCambio();

            $this->assertEquals('/admin/usuarios?tab=cambios', $ctrl->redirectUrl, "Debe redirigir a la pestaña de cambios");
            $errorMsg = \App\Core\Flash::get('error');
            $this->assertStringContains('motivo', $errorMsg, "Debe exigir el motivo del rechazo");

            // La solicitud sigue pendiente y la persona intacta
            $stmtSolicitud = $db->prepare("SELECT estado FROM solicitudes_cambio_datos WHERE id = :id");
            $stmtSolicitud->execute(['id' => $solicitudId]);
            $this->assertEquals('pendiente', $stmtSolicitud->fetchColumn(), "Sin motivo, la solicitud debe seguir pendiente");

            $stmtPersona = $db->prepare("SELECT telefono FROM personas WHERE id = :id");
            $stmtPersona->execute(['id' => $personaId]);
            $this->assertEquals('04121112233', $stmtPersona->fetchColumn(), "Sin motivo, no deben aplicarse cambios en la persona");
        } finally {
            $db->exec("DELETE FROM solicitudes_cambio_datos WHERE persona_id = {$personaId}");
            $db->exec("DELETE FROM personas WHERE id = {$personaId}");
        }
    }

    public function testVistaIncluyePestanaCambiosYComparacion(): void {
        $vistaUsuarios = file_get_contents(dirname(__DIR__) . '/app/views/admin/usuarios/index.php');
        $partial = file_get_contents(dirname(__DIR__) . '/app/views/admin/solicitudes_cambio/index.php');
        $controlador = file_get_contents(dirname(__DIR__) . '/app/controllers/UsuarioAdminController.php');
        $rutas = file_get_contents(dirname(__DIR__) . '/public/index.php');

        // Pestaña integrada en la página existente de usuarios
        $this->assertStringContains('id="tab-cambios-btn"', $vistaUsuarios, "Debe existir el botón de la pestaña Cambios de Datos");
        $this->assertStringContains('id="tab-cambios"', $vistaUsuarios, "Debe existir el pane de Cambios de Datos");
        $this->assertStringContains('Cambios de Datos', $vistaUsuarios, "La pestaña debe rotularse 'Cambios de Datos'");
        $this->assertStringContains('admin/solicitudes_cambio/index.php', $vistaUsuarios, "La pestaña debe incluir el partial de solicitudes_cambio");
        $this->assertStringContains('$cambiosPendientesCount', $vistaUsuarios, "La pestaña debe mostrar el badge de pendientes");

        // El partial existe y no se autonomiza con sidebar propio (módulo deprecado prohibido)
        $this->assertFileExists(dirname(__DIR__) . '/app/views/admin/solicitudes_cambio/index.php', "Debe existir el partial admin/solicitudes_cambio/index.php");
        $this->assertStringContains('$solicitudesCambio', $partial, "El partial debe usar el listado de solicitudes de cambio");
        $this->assertStringContains('$paginacionCambios', $partial, "El partial debe usar la paginación de cambios");
        $this->assertStringContains('$estadoCambio', $partial, "El partial debe usar el filtro de estado de cambios");
        $this->assertStringContains('$cambiosPendientesCount', $partial, "El partial debe conservar el badge de pendientes");
        $this->assertFalse(str_contains($partial, 'layouts/admin_sidebar.php'), "El partial no debe incluir su propio sidebar");

        // Comparación visual Actual -> Solicitado
        $this->assertStringContains('datos_nuevos_json', $partial, "El partial debe decodificar los datos nuevos de la solicitud");
        $this->assertStringContains('Actual:', $partial, "El partial debe mostrar el valor actual");
        $this->assertStringContains('Solicitado:', $partial, "El partial debe mostrar el valor solicitado");
        $this->assertStringContains('Sin cambio', $partial, "El partial debe indicar cuando el valor solicitado es igual al actual");
        $this->assertStringContains('residente_telefono_actual', $partial, "El partial debe comparar contra el teléfono actual");
        $this->assertStringContains('residente_email_actual', $partial, "El partial debe comparar contra el correo actual");

        // Formulario de procesamiento
        $this->assertStringContains('/admin/usuarios/procesar-solicitud-cambio', $partial, "El partial debe apuntar al endpoint de procesamiento");
        $this->assertStringContains('csrf_field()', $partial, "El formulario debe incluir token CSRF");
        $this->assertStringContains('name="accion"', $partial, "El formulario debe enviar la acción");
        $this->assertStringContains('value="aprobar"', $partial, "El formulario debe ofrecer la acción aprobar");
        $this->assertStringContains('value="rechazar"', $partial, "El formulario debe ofrecer la acción rechazar");
        $this->assertStringContains('name="motivo"', $partial, "El formulario debe incluir el campo motivo");
        $this->assertStringContains('validarRechazoCambio', $partial, "El rechazo debe exigir motivo en cliente");

        // Controlador y ruta registrados
        $this->assertStringContains('procesarSolicitudCambio', $controlador, "El controlador debe exponer procesarSolicitudCambio");
        $this->assertStringContains("'cambios'", $controlador, "El controlador debe aceptar la pestaña cambios");
        $this->assertStringContains('/admin/usuarios/procesar-solicitud-cambio', $rutas, "La ruta POST de procesamiento debe estar registrada");
    }
}
