<?php
namespace Tests;

use App\Models\ComprobantesModel;
use App\Core\Database;

class HistorialPagosTest extends TestCase {

    /**
     * Verifica que la vista admin/comprobantes.php ha sido renombrada a 'Historial de Pagos'
     * y contiene los filtros requeridos (edificio, unidad, rango de fechas).
     */
    public function testVistaContieneTituloYFiltrosAvanzados(): void {
        $viewPath = VIEWS_PATH . '/admin/comprobantes.php';
        $this->assertTrue(file_exists($viewPath), "La vista admin/comprobantes.php debe existir");

        $content = file_get_contents($viewPath);

        // 1. Título de sección actualizado
        $this->assertStringContains('Historial de Pagos', $content,
            "La vista debe tener el título 'Historial de Pagos'");

        // 2. Filtro de Edificio
        $this->assertStringContains('name="edificio_id"', $content,
            "La vista debe contener el selector de filtro por edificio");

        // 3. Filtro de Unidad
        $this->assertStringContains('name="unidad"', $content,
            "La vista debe contener el campo de filtro por unidad/apartamento");

        // 4. Rango de Fechas
        $this->assertStringContains('name="fecha_desde"', $content,
            "La vista debe contener el campo de filtro de fecha_desde");
        $this->assertStringContains('name="fecha_hasta"', $content,
            "La vista debe contener el campo de filtro de fecha_hasta");

        // 5. Preservación del enlace de descarga
        $this->assertStringContains('download=1', $content,
            "La vista debe preservar la descarga directa de comprobantes con download=1");

        // 6. Enlace a Ver Detalle
        $this->assertStringContains('Ver Detalle', $content,
            "La vista debe proveer acceso a 'Ver Detalle'");
    }

    /**
     * Verifica que la vista NO contenga botones o acciones para verificar o mutar pagos desde el listado.
     */
    public function testVistaDeprecaAccionesDeVerificacionEnPantalla(): void {
        $viewPath = VIEWS_PATH . '/admin/comprobantes.php';
        $content = file_get_contents($viewPath);

        // No debe haber botón de acción "Verificar" en la tabla
        $this->assertFalse(
            (bool)preg_match('/<a[^>]*>\s*<span[^>]*>verified<\/span>\s*Verificar\s*<\/a>/i', $content),
            "El listado de comprobantes no debe incluir botones o acciones de 'Verificar'"
        );

        // No debe haber formularios POST de verificación o aprobación/rechazo en esta vista
        $this->assertFalse(
            str_contains($content, 'name="accion" value="aprobar"'),
            "La vista no debe permitir aprobar pagos directamente"
        );
        $this->assertFalse(
            str_contains($content, 'name="accion" value="rechazar"'),
            "La vista no debe permitir rechazar pagos directamente"
        );
    }

    /**
     * Verifica que el sidebar administrativo muestre 'Historial de Pagos'.
     */
    public function testSidebarMuestraHistorialDePagos(): void {
        $sidebarPath = VIEWS_PATH . '/layouts/admin_sidebar.php';
        $content = file_get_contents($sidebarPath);

        $this->assertStringContains("'label' => 'Historial de Pagos'", $content,
            "El sidebar debe tener la etiqueta 'Historial de Pagos'");
        $this->assertFalse(
            str_contains($content, "'label' => 'Verificar Pagos'"),
            "El sidebar no debe mantener la etiqueta 'Verificar Pagos'"
        );
    }

    /**
     * Verifica que ComprobantesModel::getAllFiltered excluya pagos pendientes
     * y soporte los filtros por edificio, unidad y rango de fechas.
     */
    public function testComprobantesModelExcluyePendientesYFiltra(): void {
        $model = new ComprobantesModel();

        // 1. Invocación con array de filtros
        $resultado = $model->getAllFiltered([
            'estado'      => '',
            'edificio_id' => 99999, // Edificio inexistente
            'unidad'      => 'NON_EXISTENT_UNIT_XYZ',
            'fecha_desde' => '2000-01-01',
            'fecha_hasta' => '2000-01-02',
            'pagina'      => 1,
            'porPagina'   => 10
        ]);

        $this->assertTrue(is_array($resultado), "El resultado debe ser un array paginado");
        $this->assertEquals(0, count($resultado['datos']), "No debe retornar datos para filtros inexistentes");

        // 2. Si se pide explícitamente pendiente, debe retornar 0 datos
        $resultadoPendiente = $model->getAllFiltered('pendiente');
        $this->assertEquals(0, count($resultadoPendiente['datos']), "El historial no debe devolver comprobantes pendientes");

        // 3. Ningún resultado devuelto por getAllFiltered() debe tener estado 'pendiente'
        $todos = $model->getAllFiltered('', '', 1, 50);
        foreach ($todos['datos'] as $item) {
            $this->assertFalse(
                strtolower($item['estado']) === 'pendiente',
                "El listado histórico no debe contener pagos en estado 'pendiente'"
            );
        }
    }
}
