<?php
namespace Tests;

/**
 * Verifica la unificación de "Usuarios" y "Solicitudes de Registro"
 * en una sola sección con pestañas (patrón Estructura).
 */
class UsuariosSolicitudesTabsTest extends TestCase {

    private function readFile(string $relativePath): string {
        return file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    }

    public function testVistaUsuariosContienePestanas(): void {
        $content = $this->readFile('app/views/admin/usuarios/index.php');

        $this->assertStringContains('id="usuariosTabs"', $content, "La vista debe declarar el contenedor de pestañas usuariosTabs");
        $this->assertStringContains('data-bs-toggle="pill"', $content, "Las pestañas deben usar el patrón nav-pills de Bootstrap");
        $this->assertStringContains('id="tab-usuarios"', $content, "Debe existir el pane de usuarios");
        $this->assertStringContains('id="tab-solicitudes"', $content, "Debe existir el pane de solicitudes");
        $this->assertStringContains('id="tab-usuarios-btn"', $content, "Debe existir el botón de la pestaña de usuarios");
        $this->assertStringContains('id="tab-solicitudes-btn"', $content, "Debe existir el botón de la pestaña de solicitudes");
        $this->assertStringContains('Solicitudes de Registro', $content, "El botón de la segunda pestaña debe rotularse 'Solicitudes de Registro'");
    }

    public function testVistaUsuariosIncluyePartialDeSolicitudes(): void {
        $content = $this->readFile('app/views/admin/usuarios/index.php');

        $this->assertStringContains('admin/solicitudes_registro/index', $content, "La vista combinada debe incluir el partial de solicitudes");
        $this->assertStringContains("name=\"tab\"", $content, "El formulario de filtros de usuarios debe enviar la pestaña activa");
        $this->assertStringContains('tab=solicitudes', $content, "Los enlaces de solicitudes deben conservar la pestaña en la URL");
        $this->assertStringContains('$queryParams[\'tab\'] = \'usuarios\'', $content, "La paginación propia de usuarios debe preservar su pestaña");
        $this->assertStringContains('/admin/usuarios?tab=usuarios', $content, "El enlace de limpiar filtros debe volver a la pestaña de usuarios");
        $this->assertStringContains("history.replaceState", $content, "El cambio de pestaña debe sincronizar la URL");
    }

    public function testPartialSolicitudesConservaFuncionalidad(): void {
        $content = $this->readFile('app/views/admin/solicitudes_registro/index.php');

        $this->assertStringContains('$paginacionSolicitudes', $content, "El partial debe usar la paginación de solicitudes");
        $this->assertStringContains('$estadoSolicitud', $content, "El partial debe usar el filtro de estado de solicitudes");
        $this->assertStringContains('$pendientesCount', $content, "El partial debe conservar el badge de pendientes");
        $this->assertStringContains('/admin/solicitudes-registro/aprobar', $content, "El partial debe conservar el POST de aprobación");
        $this->assertStringContains('/admin/solicitudes-registro/rechazar', $content, "El partial debe conservar el POST de rechazo");
        $this->assertStringContains('csrf_field()', $content, "El partial debe conservar los tokens CSRF");
        $this->assertStringContains('abrirModalRechazoRegistro', $content, "El partial debe conservar el modal de rechazo");
        $this->assertStringContains("'tab' => 'solicitudes'", $content, "El partial debe construir los filtros con la pestaña de solicitudes");
    }

    public function testPartialSolicitudesYaNoEsVistaAutonoma(): void {
        $content = $this->readFile('app/views/admin/solicitudes_registro/index.php');

        $this->assertFalse(str_contains($content, 'layouts/admin_sidebar.php'), "El partial no debe incluir su propio sidebar");
        $this->assertFalse(str_contains($content, 'components/flash_messages.php'), "El partial no debe incluir su propio bloque de flash");
        $this->assertFalse(str_contains($content, '/admin/solicitudes-registro"'), "El partial no debe enlazar a la antigua ruta autónoma");
    }

    public function testControladorUsuariosCargaAmbasPestanas(): void {
        $content = $this->readFile('app/controllers/UsuarioAdminController.php');

        $this->assertStringContains("'solicitudes'", $content, "El controlador debe aceptar la pestaña solicitudes en la whitelist");
        $this->assertStringContains('tabActual', $content, "El controlador debe calcular la pestaña activa");
        $this->assertStringContains('paginacionSolicitudes', $content, "El controlador debe pasar la paginación de solicitudes");
        $this->assertStringContains('SolicitudesRegistroModel', $content, "El controlador debe cargar el modelo de solicitudes");
    }

    public function testControladorSolicitudesRedirigeALaPestana(): void {
        $content = $this->readFile('app/controllers/SolicitudesRegistroController.php');

        $this->assertStringContains('/admin/usuarios?tab=solicitudes', $content, "La antigua ruta debe redirigir a la pestaña de solicitudes");
        $this->assertStringContains("header('Location: /admin/usuarios?tab=solicitudes')", $content, "El index antiguo debe redirigir con header Location");
    }

    public function testSidebarAdminSoloConservaEntradaUsuarios(): void {
        $content = $this->readFile('app/views/layouts/admin_sidebar.php');

        $this->assertFalse(str_contains($content, 'solicitudes_registro'), "El sidebar no debe contener la ruta solicitudes_registro");
        $this->assertFalse(str_contains($content, "'/admin/solicitudes-registro'"), "El sidebar no debe enlazar a /admin/solicitudes-registro");
        $this->assertStringContains("['route' => 'usuarios'", $content, "El sidebar debe conservar la entrada de usuarios");
    }

    public function testPaginacionPreservaLaPestana(): void {
        $content = $this->readFile('app/views/components/pagination.php');

        $this->assertStringContains("'tab'", $content, "El componente de paginación debe preservar el parámetro tab");
    }
}
