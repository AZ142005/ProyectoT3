<?php
namespace Tests;

use App\Models\ReportesModel;
use App\Models\EdificiosModel;
use App\Core\Database;
use PDO;

class BalanceAgrupadoEdificiosTest extends TestCase {

    private function getDb(): PDO {
        return Database::getConnection();
    }

    public function testReportesModelHasBalanceAgrupadoMethod(): void {
        $reportesModel = new ReportesModel();
        $this->assertTrue(
            method_exists($reportesModel, 'obtenerReporteBalanceAgrupadoPorEdificio'),
            "ReportesModel debe tener el método obtenerReporteBalanceAgrupadoPorEdificio"
        );
    }

    public function testObtenerReporteBalanceAgrupadoStructure(): void {
        $reportesModel = new ReportesModel();
        $resultado = $reportesModel->obtenerReporteBalanceAgrupadoPorEdificio();

        $this->assertTrue(is_array($resultado), "El resultado debe ser un arreglo de edificios");

        if (!empty($resultado)) {
            $primerEdificio = $resultado[0];
            $this->assertTrue(isset($primerEdificio['edificio_id']), "Debe contener clave 'edificio_id'");
            $this->assertTrue(isset($primerEdificio['edificio_nombre']), "Debe contener clave 'edificio_nombre'");
            $this->assertTrue(isset($primerEdificio['total_unidades']), "Debe contener clave 'total_unidades'");
            $this->assertTrue(isset($primerEdificio['unidades_solventes']), "Debe contener clave 'unidades_solventes'");
            $this->assertTrue(isset($primerEdificio['unidades_deudoras']), "Debe contener clave 'unidades_deudoras'");
            $this->assertTrue(isset($primerEdificio['balance_total']), "Debe contener clave 'balance_total'");
            $this->assertTrue(isset($primerEdificio['unidades']), "Debe contener clave 'unidades'");
            $this->assertTrue(is_array($primerEdificio['unidades']), "'unidades' debe ser un array");
        }
    }

    public function testFiltroPorEdificio(): void {
        $db = $this->getDb();
        $reportesModel = new ReportesModel();

        // Buscar un edificio con unidades
        $stmt = $db->query("SELECT e.id FROM edificios e INNER JOIN unidades u ON u.edificio_id = e.id WHERE e.estado = 1 LIMIT 1");
        $edificioId = $stmt->fetchColumn();

        if ($edificioId) {
            $resultado = $reportesModel->obtenerReporteBalanceAgrupadoPorEdificio(['edificio_id' => $edificioId]);
            $this->assertEquals(1, count($resultado), "El filtro por edificio_id debe retornar exactamente 1 edificio");
            $this->assertEquals((int)$edificioId, (int)$resultado[0]['edificio_id'], "El edificio retornado debe coincidir con el solicitado");
        } else {
            $this->assertTrue(true, "No hay edificios con unidades para probar filtro individual");
        }
    }

    public function testOrdenPorMayorDeudaPorDefecto(): void {
        $reportesModel = new ReportesModel();

        $consolidado = $reportesModel->obtenerReporteBalanceAgrupadoPorEdificio();
        for ($i = 1; $i < count($consolidado); $i++) {
            $this->assertTrue(
                $consolidado[$i - 1]['balance_total'] >= $consolidado[$i]['balance_total'],
                "Los edificios deben ordenarse por deuda descendente (más crítico primero)"
            );
        }

        $conFiltroSolvente = $reportesModel->obtenerReporteBalanceAgrupadoPorEdificio(['estado' => 'solvente']);
        $this->assertEquals(
            count($consolidado),
            count($conFiltroSolvente),
            "El parámetro 'estado' ya no debe alterar la cantidad de edificios del consolidado"
        );

        foreach ($consolidado as $ed) {
            $unidades = $ed['unidades'] ?? [];
            for ($i = 1; $i < count($unidades); $i++) {
                $this->assertTrue(
                    (float)$unidades[$i - 1]['total_deuda'] >= (float)$unidades[$i]['total_deuda'],
                    "Las unidades de cada edificio deben mantener el orden por deuda descendente"
                );
            }
        }
    }

    public function testFiltroPorEstadoSigueDisponibleEnElReporteCompleto(): void {
        $reportesModel = new ReportesModel();

        $sinFiltro = $reportesModel->obtenerReporteMorosidadCompleto();
        $deudores  = $reportesModel->obtenerReporteMorosidadCompleto(['estado' => 'deudor']);
        $solventes = $reportesModel->obtenerReporteMorosidadCompleto(['estado' => 'solvente']);

        foreach ($deudores as $u) {
            $this->assertTrue(floatval($u['total_deuda']) > 0,
                "En 'deudor', toda unidad del reporte completo debe tener deuda mayor a 0");
        }
        foreach ($solventes as $u) {
            $this->assertTrue(floatval($u['total_deuda']) <= 0,
                "En 'solvente', toda unidad del reporte completo debe tener deuda en 0");
        }
        $this->assertEquals(
            count($sinFiltro),
            count($deudores) + count($solventes),
            "El filtro de estado debe seguir disponible para impresión/CSV (partición del reporte completo)"
        );
    }

    public function testVistaRenombraTituloACartaDeDeuda(): void {
        $viewPath = VIEWS_PATH . '/admin/reportes/morosidad.php';
        $this->assertTrue(file_exists($viewPath), "La vista admin/reportes/morosidad.php debe existir");

        $content = file_get_contents($viewPath);

        // 1. Encabezado de la vista y de la tarjeta
        $this->assertStringContains('Carta de Deuda', $content, "La vista debe incluir el título 'Carta de Deuda'");
        $this->assertFalse(str_contains($content, 'Balance General'),
            "La vista ya no debe tener el título antiguo 'Balance General'");

        // 2. Navegación lateral (admin y auditor) alineada al nuevo nombre
        $adminSidebar = file_get_contents(VIEWS_PATH . '/layouts/admin_sidebar.php');
        $this->assertStringContains("'label' => 'Carta de Deuda'", $adminSidebar,
            "El sidebar de admin debe etiquetar la sección como 'Carta de Deuda'");

        $auditorSidebar = file_get_contents(VIEWS_PATH . '/layouts/auditor_sidebar.php');
        $this->assertStringContains("'label' => 'Carta de Deuda'", $auditorSidebar,
            "El sidebar de auditor debe etiquetar la sección como 'Carta de Deuda'");
    }

    public function testVistaListaPlanaPorUnidades(): void {
        $viewPath = VIEWS_PATH . '/admin/reportes/morosidad.php';
        $content = file_get_contents($viewPath);

        // 1. Tabla plana de unidades con su edificio
        $this->assertStringContains('id="tablaBalanceUnidades"', $content,
            "Debe existir la tabla plana con id='tablaBalanceUnidades'");
        $this->assertStringContains('class="fila-unidad ', $content,
            "La tabla debe contener filas de unidades (.fila-unidad)");
        $this->assertStringContains('data-busqueda=', $content,
            "Cada fila de unidad debe exponer data-busqueda para el filtrado en vivo");
        $this->assertStringContains('/admin/reportes/carta-deuda/', $content,
            "Cada unidad deudora debe enlazar a su Carta de Deuda");

        // 2. Sin agrupación por edificios ni drill-down colapsable
        $this->assertFalse(str_contains($content, 'id="collapse-edificio-'),
            "Ya no debe existir el contenedor colapsable por edificio");
        $this->assertFalse(str_contains($content, 'Ver Unidades'),
            "Ya no debe existir la acción 'Ver Unidades'");
        $this->assertFalse(str_contains($content, 'data-bs-toggle="collapse"'),
            "Ya no debe existir el toggle de colapso de Bootstrap");
    }

    public function testVistaSinAgrupacionPorEdificios(): void {
        $viewPath = VIEWS_PATH . '/admin/reportes/morosidad.php';
        $content = file_get_contents($viewPath);

        // Sin acordeón: ni contenedor, ni parent de colapso, ni listeners de Bootstrap
        $this->assertFalse(str_contains($content, 'id="accordionBalanceEdificios"'),
            "Ya no debe existir el contenedor del acordeón id='accordionBalanceEdificios'");
        $this->assertFalse(str_contains($content, 'data-bs-parent="#accordionBalanceEdificios"'),
            "Ya no debe existir data-bs-parent del acordeón de edificios");
        $this->assertFalse(str_contains($content, 'show.bs.collapse'),
            "Ya no debe existir el listener 'show.bs.collapse'");
    }

    public function testAdaptacionDeFiltrosYBuscador(): void {
        $viewPath = VIEWS_PATH . '/admin/reportes/morosidad.php';
        $content = file_get_contents($viewPath);

        $this->assertFalse(str_contains($content, 'name="estado"'), "Ya no debe existir el filtro por estado financiero");
        $this->assertStringContains('name="edificio_id"', $content, "Debe existir el filtro por edificio");
        $this->assertStringContains('name="dias_mora"', $content, "Debe existir el filtro por días de mora");
        $this->assertStringContains('id="buscadorBalance"', $content, "Debe existir el buscador rápido id='buscadorBalance'");
        $this->assertStringContains('filtrarBalance', $content, "Debe existir la función JS de filtrado");
    }
}
