<?php
namespace Tests;

use App\Models\FacturasModel;
use App\Models\ComprobantesModel;
use App\Models\EstacionamientosModel;

/**
 * Verifica la estandarización de la paginación a 25 registros en todas
 * las listas solicitadas y el límite de 4 movimientos en el dashboard.
 */
class PaginacionEstandarTest extends TestCase {

    private function readFile(string $relativePath): string {
        return file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    }

    public function testComponentePaginacionEstandar(): void {
        $content = $this->readFile('app/views/components/pagination.php');
        $this->assertStringContains('$pageParamName', $content, "Debe soportar parametro de pagina configurable");
        $this->assertStringContains("'tab'", $content, "Debe preservar el parametro tab");
        $this->assertStringContains('$porPagina', $content, "Debe manejar el tamano por pagina");
    }

    public function testDashboardResidenteCargaComunicadosSinListasDePago(): void {
        $controller = $this->readFile('app/controllers/ResidenteController.php');
        $view = $this->readFile('app/views/residente/dashboard.php');

        $this->assertStringContains('obtenerPorResidente', $controller,
            "ResidenteController debe cargar los comunicados del residente");
        $this->assertStringContains("'comunicados'", $controller,
            "ResidenteController debe enviar los comunicados a la vista");

        $this->assertStringContains('Comunicados', $view,
            "El dashboard del residente debe mostrar la seccion de comunicados");
        $this->assertStringContains('/residente/cartelera', $view,
            "El dashboard debe enlazar a la cartelera completa");
        $this->assertFalse(str_contains($view, 'Facturas Pendientes'),
            "El dashboard del residente ya no debe listar facturas pendientes");
        $this->assertFalse(str_contains($view, 'Comprobantes Recientes'),
            "El dashboard del residente ya no debe listar comprobantes recientes");

        $this->assertTrue(method_exists(FacturasModel::class, 'getPendientesByUnidad'), "FacturasModel debe tener getPendientesByUnidad");
        $this->assertTrue(method_exists(ComprobantesModel::class, 'getRecientesByResidente'), "ComprobantesModel debe tener getRecientesByResidente");
    }

    public function testVistaResidenteMisPagosPaginada(): void {
        $controller = $this->readFile('app/controllers/PagoController.php');
        $view = $this->readFile('app/views/pagos/residente/lista.php');

        $this->assertStringContains('obtenerTodosPagos([\'unidad_id\' => $unidadId], $pagina, 25)', $controller,
            "Mis Pagos en PagoController debe paginar a 25 registros");
        $this->assertStringContains('components/pagination.php', $view,
            "Mis Pagos debe reutilizar components/pagination.php");
    }

    public function testDashboardAdminMaximoCuatroMovimientos(): void {
        $controller = $this->readFile('app/controllers/AdminController.php');
        $view = $this->readFile('app/views/admin/dashboard.php');

        $this->assertStringContains('getProcesados(4)', $controller,
            "El dashboard de admin debe limitar a maximo 4 movimientos financieros");
        $this->assertStringContains('Últimos Movimientos Financieros', $view,
            "La vista del dashboard debe tener la seccion de ultimos movimientos financieros");
    }

    public function testAdminHistorialPagosPaginacion25(): void {
        $controller = $this->readFile('app/controllers/AdminController.php');
        $view = $this->readFile('app/views/admin/comprobantes.php');

        $this->assertStringContains('\'porPagina\'   => 25', $controller,
            "listarComprobantes debe paginar a 25 registros");
        $this->assertStringContains('components/pagination.php', $view,
            "admin/comprobantes debe incluir components/pagination.php");
    }

    public function testAdminConciliacionBancariaPaginacion25(): void {
        $controller = $this->readFile('app/controllers/ConciliacionController.php');
        $view = $this->readFile('app/views/admin/conciliacion/index.php');

        $this->assertStringContains('$porPagina = 25;', $controller,
            "ConciliacionController debe paginar a 25 registros");
        $this->assertStringContains('components/pagination.php', $view,
            "admin/conciliacion/index debe incluir components/pagination.php");
    }

    public function testAdminGastosHistorialPaginacion25(): void {
        $controller = $this->readFile('app/controllers/GastoController.php');
        $view = $this->readFile('app/views/admin/gastos/index.php');

        $this->assertStringContains('obtenerGastosAdmin($pagina, 25, $filtros)', $controller,
            "GastoController debe consultar con limite de 25 registros");
        $this->assertStringContains('components/pagination.php', $view,
            "admin/gastos/index debe incluir components/pagination.php");
    }

    public function testAdminEstructuraPaginacion25(): void {
        $controller = $this->readFile('app/controllers/EstructuraController.php');
        $view = $this->readFile('app/views/admin/estructura.php');

        $this->assertStringContains('$porPagina = 25;', $controller,
            "EstructuraController debe paginar a 25 registros");
        $this->assertStringContains('components/pagination.php', $view,
            "admin/estructura debe incluir components/pagination.php");
    }

    public function testAdminEstacionamientosPaginacion25(): void {
        $controller = $this->readFile('app/controllers/EstacionamientoController.php');
        $view = $this->readFile('app/views/admin/estacionamientos/index.php');

        $this->assertStringContains('$porPagina = 25;', $controller,
            "EstacionamientoController debe paginar a 25 registros");
        $this->assertTrue(method_exists(EstacionamientosModel::class, 'obtenerPaginados'),
            "EstacionamientosModel debe disponer del metodo obtenerPaginados");
        $this->assertStringContains('components/pagination.php', $view,
            "admin/estacionamientos/index debe incluir components/pagination.php");
    }

    public function testAdminUsuariosYSolicitudesPaginacion25(): void {
        $controller = $this->readFile('app/controllers/UsuarioAdminController.php');
        $viewUsuarios = $this->readFile('app/views/admin/usuarios/index.php');
        $viewSolicitudes = $this->readFile('app/views/admin/solicitudes_registro/index.php');

        $this->assertStringContains('$porPagina = 25;', $controller,
            "UsuarioAdminController debe paginar a 25 registros");
        $this->assertStringContains('components/pagination.php', $viewUsuarios,
            "admin/usuarios/index debe incluir components/pagination.php");
        $this->assertStringContains('components/pagination.php', $viewSolicitudes,
            "admin/solicitudes_registro/index debe incluir components/pagination.php");
    }
}
