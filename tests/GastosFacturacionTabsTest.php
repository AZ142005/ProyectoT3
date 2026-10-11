<?php
namespace Tests;

/**
 * Verifica la unificación de "Generar Factura" y "Gastos" bajo el módulo
 * "Carga y Emisión" (secciones combinadas en una sola vista) y la
 * retrocompatibilidad de las rutas antiguas.
 */
class GastosFacturacionTabsTest extends TestCase {

    private function readFile(string $relativePath): string {
        return (string)file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    }

    public function testSidebarAdminUnificaGastosYFacturacionYDeprecaGenerarFacturas(): void {
        $adminSidebar = $this->readFile('app/views/layouts/admin_sidebar.php');

        $this->assertStringContains("'label' => 'Carga y Emisión'", $adminSidebar,
            "El sidebar admin debe etiquetar la sección como 'Carga y Emisión'");
        $this->assertFalse(str_contains($adminSidebar, "'label' => 'Carga y Emisión de Deuda'"),
            "El sidebar admin no debe conservar 'de deuda' en la etiqueta");
        $this->assertFalse(str_contains($adminSidebar, "'label' => 'Generar Facturas'"),
            "El sidebar admin no debe contener la entrada independiente 'Generar Facturas'");
        $this->assertFalse(str_contains($adminSidebar, "'/admin/facturas/generar'"),
            "El sidebar admin no debe enlazar a la ruta independiente /admin/facturas/generar");

        $auditorSidebar = $this->readFile('app/views/layouts/auditor_sidebar.php');
        $this->assertStringContains("'label' => 'Carga y Emisión'", $auditorSidebar,
            "El sidebar auditor debe etiquetar la sección como 'Carga y Emisión'");
        $this->assertFalse(str_contains($auditorSidebar, "'label' => 'Carga y Emisión de Deuda'"),
            "El sidebar auditor no debe conservar 'de deuda' en la etiqueta");
    }

    public function testVistaGastosMuestraSeccionesCombinadas(): void {
        $content = $this->readFile('app/views/admin/gastos/index.php');

        $this->assertStringContains('Carga y Emisión', $content,
            "El encabezado de la vista debe titularse 'Carga y Emisión' sin 'de deuda'");
        $this->assertFalse(str_contains($content, 'Carga y Emisión de Deuda'),
            "El encabezado no debe conservar 'Carga y Emisión de Deuda'");
        $this->assertStringContains('id="seccion-gastos"', $content,
            "Debe existir la sección de Registro de Gastos");
        $this->assertStringContains('id="seccion-emision"', $content,
            "Debe existir la sección de Emisión de Deuda");
        $this->assertStringContains('Registro de Gastos', $content,
            "El título de la primera sección debe ser 'Registro de Gastos'");
        $this->assertStringContains('Emisión de Deuda Mensual', $content,
            "El título de la segunda sección debe ser 'Emisión de Deuda Mensual'");
        $this->assertFalse(str_contains($content, 'gastosTabs'),
            "No deben existir pestañas (contenedor gastosTabs)");
        $this->assertFalse(str_contains($content, 'data-bs-toggle="pill"'),
            "No deben existir botones nav-pills de pestañas");
        $this->assertFalse(str_contains($content, 'history.replaceState'),
            "No debe existir la sincronización de pestaña en la URL");
    }

    public function testSeccionEmisionContieneFormularioYResumenCuotas(): void {
        $content = $this->readFile('app/views/admin/gastos/index.php');

        $this->assertStringContains('Resumen y Generación de Cuotas', $content,
            "La pestaña de facturación debe contener el panel de generación de cuotas");
        $this->assertStringContains('Deudas Creadas', $content,
            "Debe mostrar el contador de Deudas Creadas");
        $this->assertFalse(str_contains($content, 'Total Gastos Declarados'),
            "No debe mostrarse el KPI de Total Gastos Declarados (no rellenable con el reporte)");
        $this->assertFalse(str_contains($content, 'Cuota Base Común'),
            "No debe mostrarse el KPI de Cuota Base Común (no rellenable con el reporte)");
        $this->assertFalse(str_contains($content, 'Cálculo Dinámico de Cuotas'),
            "No debe mostrarse el cuadro de cálculo dinámico de cuotas (no rellenable con el reporte)");
        $this->assertStringContains('csrf_field()', $content,
            "El formulario de generación debe incluir token CSRF");
        $this->assertStringContains('generar-facturas', $content,
            "El formulario debe apuntar a la acción de generar facturas");
    }

    public function testControladorGastosCargaDatosDeLasSecciones(): void {
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
        $this->assertStringContains('Carga y Emisión - Administrador', $content,
            "El título de la vista debe ser 'Carga y Emisión - Administrador'");
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
