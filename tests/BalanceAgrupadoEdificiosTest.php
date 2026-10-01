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

    public function testVistaRenombraTituloABalanceGeneral(): void {
        $viewPath = VIEWS_PATH . '/admin/reportes/morosidad.php';
        $this->assertTrue(file_exists($viewPath), "La vista admin/reportes/morosidad.php debe existir");

        $content = file_get_contents($viewPath);

        // 1. Encabezado de la vista y de la tarjeta
        $this->assertStringContains('Balance General', $content, "La vista debe incluir el título 'Balance General'");
        $this->assertFalse(str_contains($content, 'Detalle de Unidades (Balance General)'),
            "La vista ya no debe tener el título antiguo 'Detalle de Unidades (Balance General)'");
    }

    public function testVistaAgrupacionEdificiosYDrillDown(): void {
        $viewPath = VIEWS_PATH . '/admin/reportes/morosidad.php';
        $content = file_get_contents($viewPath);

        // 1. Tabla principal de edificios y elementos de agrupación
        $this->assertStringContains('id="tablaBalanceEdificios"', $content,
            "Debe existir la tabla con id='tablaBalanceEdificios'");
        $this->assertStringContains('class="fila-edificio', $content,
            "La tabla principal debe contener filas para edificios (.fila-edificio)");

        // 2. Elementos de drill-down colapsables
        $this->assertStringContains('data-bs-toggle="collapse"', $content,
            "Las filas y botones deben contar con data-bs-toggle='collapse'");
        $this->assertStringContains('id="collapse-edificio-', $content,
            "Cada edificio debe tener su contenedor de unidades id='collapse-edificio-...'");
        $this->assertStringContains('Ver Unidades', $content,
            "Debe incluir la acción interactiva 'Ver Unidades'");
    }

    public function testVistaImplementaAcordeonExclusivo(): void {
        $viewPath = VIEWS_PATH . '/admin/reportes/morosidad.php';
        $content = file_get_contents($viewPath);

        // 1. Acordeón exclusivo via data-bs-parent y contenedor
        $this->assertStringContains('id="accordionBalanceEdificios"', $content,
            "Debe existir el contenedor del acordeón id='accordionBalanceEdificios'");
        $this->assertStringContains('data-bs-parent="#accordionBalanceEdificios"', $content,
            "Las filas de unidades deben tener data-bs-parent='#accordionBalanceEdificios' para garantizar el acordeón exclusivo");

        // 2. Controlador JavaScript que colapsa cualquier otro edificio abierto
        $this->assertStringContains('show.bs.collapse', $content,
            "El script debe escuchar el evento 'show.bs.collapse' para manejar la exclusividad del acordeón");
        $this->assertStringContains('bootstrap.Collapse', $content,
            "El script debe gestionar el colapso mediante la API de Bootstrap");
    }

    public function testAdaptacionDeFiltrosYBuscador(): void {
        $viewPath = VIEWS_PATH . '/admin/reportes/morosidad.php';
        $content = file_get_contents($viewPath);

        $this->assertStringContains('name="estado"', $content, "Debe existir el filtro por estado financiero");
        $this->assertStringContains('name="edificio_id"', $content, "Debe existir el filtro por edificio");
        $this->assertStringContains('name="dias_mora"', $content, "Debe existir el filtro por días de mora");
        $this->assertStringContains('id="buscadorBalance"', $content, "Debe existir el buscador rápido id='buscadorBalance'");
        $this->assertStringContains('filtrarBalance', $content, "Debe existir la función JS de filtrado");
    }
}
