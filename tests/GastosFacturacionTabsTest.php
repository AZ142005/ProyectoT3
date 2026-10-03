<?php
namespace Tests;

/**
 * Verifica la unificación de "Generar Factura" y "Gastos" bajo el nuevo
 * módulo "Gastos y Facturación" con navegación por pestañas y backward compatibility.
 */
class GastosFacturacionTabsTest extends TestCase {

    private function readFile(string $relativePath): string {
        return (string)file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    }

    public function testSidebarAdminUnificaGastosYFacturacionYDeprecaGenerarFacturas(): void {
        $adminSidebar = $this->readFile('app/views/layouts/admin_sidebar.php');

        $this->assertStringContains("'label' => 'Gastos y Facturación'", $adminSidebar,
            "El sidebar admin debe etiquetar la sección como 'Gastos y Facturación'");
        $this->assertFalse(str_contains($adminSidebar, "'label' => 'Generar Facturas'"),
            "El sidebar admin no debe contener la entrada independiente 'Generar Facturas'");
        $this->assertFalse(str_contains($adminSidebar, "'/admin/facturas/generar'"),
            "El sidebar admin no debe enlazar a la ruta independiente /admin/facturas/generar");

        $auditorSidebar = $this->readFile('app/views/layouts/auditor_sidebar.php');
        $this->assertStringContains("'label' => 'Gastos y Facturación'", $auditorSidebar,
            "El sidebar auditor debe etiquetar la sección como 'Gastos y Facturación'");
    }

    public function testVistaGastosContienePestanasYEncabezadoUnificado(): void {
        $content = $this->readFile('app/views/admin/gastos/index.php');

        $this->assertStringContains('Gastos y Facturación', $content,
            "El encabezado de la vista debe titularse 'Gastos y Facturación'");
        $this->assertStringContains('id="gastosTabs"', $content,
            "Debe existir el contenedor de pestañas gastosTabs");
        $this->assertStringContains('data-bs-toggle="pill"', $content,
            "Las pestañas deben implementar el patrón nav-pills de Bootstrap 5");
        $this->assertStringContains('id="tab-gastos"', $content,
            "Debe existir el pane de Registro de Gastos");
        $this->assertStringContains('id="tab-facturacion"', $content,
            "Debe existir el pane de Emisión de Facturas");
        $this->assertStringContains('id="tab-gastos-btn"', $content,
            "Debe existir el botón selector de la pestaña de gastos");
        $this->assertStringContains('id="tab-facturacion-btn"', $content,
            "Debe existir el botón selector de la pestaña de facturación");
        $this->assertStringContains('Registro de Gastos', $content,
            "El botón de la primera pestaña debe rotularse 'Registro de Gastos'");
        $this->assertStringContains('Emisión de Facturas', $content,
            "El botón de la segunda pestaña debe rotularse 'Emisión de Facturas'");
        $this->assertStringContains('history.replaceState', $content,
            "El cambio de pestaña debe sincronizar la URL sin recarga completa");
    }

    public function testPestanaFacturacionContieneFormularioYResumenCuotas(): void {
        $content = $this->readFile('app/views/admin/gastos/index.php');

        $this->assertStringContains('Resumen y Generación de Cuotas', $content,
            "La pestaña de facturación debe contener el panel de generación de cuotas");
        $this->assertStringContains('Total Gastos Declarados', $content,
            "Debe mostrar el KPI de Total Gastos Declarados");
        $this->assertStringContains('Cuota Base Común', $content,
            "Debe mostrar el KPI de Cuota Base Común");
        $this->assertStringContains('Facturas Creadas', $content,
            "Debe mostrar el contador de Facturas Creadas");
        $this->assertStringContains('Cálculo Dinámico de Cuotas a Facturar', $content,
            "Debe detallar el desglose de cálculo global e individual");
        $this->assertStringContains('csrf_field()', $content,
            "El formulario de generación debe incluir token CSRF");
        $this->assertStringContains('generar-facturas', $content,
            "El formulario debe apuntar a la acción de generar facturas");
    }

    public function testControladorGastosCargaAmbasPestanas(): void {
        $content = $this->readFile('app/controllers/GastoController.php');

        $this->assertStringContains("'facturacion'", $content,
            "El controlador debe soportar el parámetro tab=facturacion");
        $this->assertStringContains('tabActual', $content,
            "El controlador debe calcular la pestaña activa tabActual");
        $this->assertStringContains('FacturasModel', $content,
            "El controlador debe interactuar con FacturasModel para los datos de emisión");
        $this->assertStringContains('facturas_existentes', $content,
            "El controlador debe contar las facturas del período");
        $this->assertStringContains('distribucion', $content,
            "El controlador debe calcular la distribución de cuotas");
        $this->assertStringContains('Gastos y Facturación', $content,
            "El título de la vista debe ser Gastos y Facturación");
    }

    public function testRutaAntiguaFacturasGenerarRedirigeALaPestanaFacturacion(): void {
        $adminController = $this->readFile('app/controllers/AdminController.php');

        $this->assertStringContains('/admin/gastos?tab=facturacion', $adminController,
            "La ruta antigua /admin/facturas/generar debe redirigir a /admin/gastos?tab=facturacion");

        $index = $this->readFile('public/index.php');
        $this->assertStringContains("'/admin/facturas/generar'", $index,
            "public/index.php debe conservar la ruta /admin/facturas/generar para evitar errores 404");
        $this->assertStringContains("'/admin/gastos/generar-facturas'", $index,
            "public/index.php debe exponer la ruta POST /admin/gastos/generar-facturas");
    }
}
