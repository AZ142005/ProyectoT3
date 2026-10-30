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
        foreach ($conFiltroSolvente as $ed) {
            foreach ($ed['unidades'] as $u) {
                $this->assertTrue(
                    (float)$u['total_deuda'] <= 0,
                    "El filtro 'solvente' solo debe incluir unidades sin deuda vencida"
                );
            }
        }

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

        // 3. Orden por mayor deuda (sin agrupar por edificio) y scroll vertical propio
        $controller = file_get_contents(dirname(__DIR__) . '/app/Controllers/ReporteController.php');
        $this->assertStringContains('usort($todasUnidades', $controller,
            "El controlador debe reordenar la lista plana por mayor deuda");
        $this->assertMatchesRegex('/max-h-\[calc\([^\]]*vh[^\]]*\)\][^>]*overflow-y-auto/', $content,
            "La lista debe tener scroll vertical propio cuando haya muchas unidades");
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

        $this->assertStringContains('name="estado"', $content, "Debe existir el filtro por estado financiero");
        $this->assertStringContains('value="solvente"', $content, "El filtro debe ofrecer la opción Solventes");
        $this->assertStringContains('value="saldo_favor"', $content, "El filtro debe ofrecer la opción Con Saldo a Favor");
        $this->assertStringContains('name="edificio_id"', $content, "Debe existir el filtro por edificio");
        $this->assertStringContains('name="dias_mora"', $content, "Debe existir el filtro por días de mora");
        $this->assertStringContains('name="buscar"', $content, "Debe existir el buscador server-side");
        $this->assertStringContains('Buscar en todos los departamentos', $content,
            "El buscador debe indicar que abarca todos los departamentos");
        $this->assertFalse(strpos($content, 'buscadorBalance') !== false,
            "La búsqueda en vivo de la página actual ya no debe existir");
        $this->assertFalse(strpos($content, 'filtrarBalance') !== false,
            "La función JS de filtrado en vivo ya no debe existir");
        $this->assertFalse(strpos($content, 'sinResultadosBalance') !== false,
            "El bloque de resultados vacíos de la búsqueda en vivo ya no debe existir");
    }

    public function testControladorPropagaBusquedaServidor(): void {
        $controller = file_get_contents(dirname(__DIR__) . '/app/Controllers/ReporteController.php');

        $this->assertStringContains("'buscar'", $controller,
            "El controlador debe leer el parámetro buscar");
        $this->assertStringContains('filtrosBalance()', $controller,
            "El controlador debe centralizar los filtros del balance");
    }

    public function testBusquedaServidorFiltraTodosLosDepartamentos(): void {
        $db = $this->getDb();
        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $numero = 'BUSQ-' . $sufijo;
        $nombre = 'Buscado' . $sufijo;
        $cedula = 'V-BUSQ-' . $sufijo;
        $email = 'busq_' . strtolower($sufijo) . '@example.com';
        $personaId = 0;
        $unidadId = 0;

        try {
            $stmt = $db->prepare("INSERT INTO personas (cedula, nombre, apellido, email, telefono, tipo, estado)
                                  VALUES (:cedula, :nombre, 'Busqueda', :email, '04121112233', 'propietario', 1)");
            $stmt->execute([
                'cedula' => $cedula,
                'nombre' => $nombre,
                'email'  => $email,
            ]);
            $personaId = (int)$db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado, propietario_id)
                                  VALUES (:numero, 0.00, 1, :propietario_id)");
            $stmt->execute([
                'numero'         => $numero,
                'propietario_id' => $personaId,
            ]);
            $unidadId = (int)$db->lastInsertId();

            $reportes = new ReportesModel();

            $porUnidad = $this->aplanarUnidades(
                $reportes->obtenerReporteBalanceAgrupadoPorEdificio(['buscar' => $numero])
            );
            $this->assertEquals(1, count($porUnidad),
                "La búsqueda server-side debe retornar la unidad aunque no esté en la página actual");
            $this->assertEquals($numero, $porUnidad[0]['unidad_numero'],
                "La unidad retornada debe ser la buscada");

            $porPropietario = $this->aplanarUnidades(
                $reportes->obtenerReporteBalanceAgrupadoPorEdificio(['buscar' => $nombre])
            );
            $this->assertEquals(1, count($porPropietario),
                "La búsqueda server-side debe encontrar por nombre del propietario");

            $completo = $reportes->obtenerReporteMorosidadCompleto(['buscar' => $numero]);
            $this->assertEquals(1, count($completo),
                "La búsqueda debe aplicarse también en impresión y exportación CSV");
            $this->assertEquals($numero, $completo[0]['unidad_numero'],
                "El reporte completo debe devolver la unidad buscada");
        } finally {
            if ($unidadId > 0) {
                $db->exec("DELETE FROM unidades WHERE id = {$unidadId}");
            }
            if ($personaId > 0) {
                $db->exec("DELETE FROM personas WHERE id = {$personaId}");
            }
        }
    }

    /**
     * Aplana las unidades del consolidado por edificio en una sola lista.
     */
    private function aplanarUnidades(array $consolidado): array {
        $unidades = [];
        foreach ($consolidado as $edificio) {
            foreach ($edificio['unidades'] as $unidad) {
                $unidades[] = $unidad;
            }
        }
        return $unidades;
    }

    public function testFiltroSolventesSaldoFavorYDeudores(): void {
        $reportesModel = new ReportesModel();

        $todas     = $this->aplanarUnidades($reportesModel->obtenerReporteBalanceAgrupadoPorEdificio());
        $solventes = $this->aplanarUnidades($reportesModel->obtenerReporteBalanceAgrupadoPorEdificio(['estado' => 'solvente']));
        $deudores  = $this->aplanarUnidades($reportesModel->obtenerReporteBalanceAgrupadoPorEdificio(['estado' => 'deudor']));
        $conFavor  = $this->aplanarUnidades($reportesModel->obtenerReporteBalanceAgrupadoPorEdificio(['estado' => 'saldo_favor']));

        foreach ($solventes as $unidad) {
            $this->assertTrue(
                (float)$unidad['total_deuda'] <= 0,
                "En 'solvente', toda unidad debe tener deuda vencida en 0"
            );
            $this->assertTrue(
                array_key_exists('saldo_favor', $unidad),
                "Cada unidad del consolidado debe exponer la clave saldo_favor"
            );
        }

        foreach ($deudores as $unidad) {
            $this->assertTrue(
                (float)$unidad['total_deuda'] > 0,
                "En 'deudor', toda unidad debe tener deuda vencida mayor a 0"
            );
        }

        foreach ($conFavor as $unidad) {
            $this->assertTrue(
                (float)$unidad['saldo_favor'] > 0,
                "En 'saldo_favor', toda unidad debe tener saldo a favor mayor a 0"
            );
        }

        $this->assertEquals(
            count($todas),
            count($solventes) + count($deudores),
            "Solventes y deudores deben particionar el total de unidades"
        );
    }

    public function testSaldoFavorSeExponeEnElConsolidado(): void {
        $db = $this->getDb();
        $db->beginTransaction();
        try {
            $unidadId = (int)$db->query("SELECT id FROM unidades WHERE estado = 1 ORDER BY id LIMIT 1")->fetchColumn();
            $this->assertTrue($unidadId > 0, "Debe existir al menos una unidad activa para el fixture");

            $stmt = $db->prepare("
                INSERT INTO facturas
                    (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado, observaciones)
                VALUES
                    (:numero, :unidad_id, :mes, :anio, CURDATE(), CURDATE(), 0.00, 50.00, -50.00, 'pagada', 'Fixture test saldo a favor')
            ");
            $stmt->execute([
                'numero'    => 'ABONO-TEST-' . uniqid(),
                'unidad_id' => $unidadId,
                'mes'       => (int)date('n'),
                'anio'      => (int)date('Y'),
            ]);

            $reportesModel = new ReportesModel();
            $conFavor = $this->aplanarUnidades(
                $reportesModel->obtenerReporteBalanceAgrupadoPorEdificio(['estado' => 'saldo_favor'])
            );

            $encontrada = null;
            foreach ($conFavor as $unidad) {
                if ((int)$unidad['unidad_id'] === $unidadId) {
                    $encontrada = $unidad;
                    break;
                }
            }

            $this->assertTrue(
                $encontrada !== null,
                "La unidad con saldo a favor debe aparecer en el filtro 'saldo_favor'"
            );
            $this->assertTrue(
                (float)$encontrada['saldo_favor'] >= 50.00,
                "El saldo a favor consolidado debe reflejar el crédito insertado"
            );
        } finally {
            $db->rollBack();
        }
    }

    public function testKpisIncluyenSaldoFavor(): void {
        $reportesModel = new ReportesModel();
        $kpis = $reportesModel->obtenerKpisMorosidad(true);

        $this->assertTrue(
            array_key_exists('saldo_favor_total', $kpis),
            "Los KPIs deben incluir saldo_favor_total"
        );
        $this->assertTrue(
            array_key_exists('unidades_con_saldo_favor', $kpis),
            "Los KPIs deben incluir unidades_con_saldo_favor"
        );
        $this->assertTrue(is_numeric($kpis['saldo_favor_total']), "saldo_favor_total debe ser numérico");
        $this->assertTrue(is_numeric($kpis['unidades_con_saldo_favor']), "unidades_con_saldo_favor debe ser numérico");
    }

    public function testVistaMuestraColumnaSaldoFavor(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/reportes/morosidad.php');

        $this->assertStringContains(
            'Saldo a Favor (Bs)',
            $content,
            "La tabla debe incluir la columna 'Saldo a Favor (Bs)'"
        );
        $this->assertStringContains(
            'saldo_favor',
            $content,
            "La vista debe usar la clave saldo_favor"
        );
        $this->assertStringContains(
            'name="estado"',
            $content,
            "La vista debe incluir el filtro por estado financiero"
        );
    }

    public function testPaginacionDe50UnidadesEnCartaDeDeuda(): void {
        $controller = file_get_contents(dirname(__DIR__) . '/app/Controllers/ReporteController.php');
        $this->assertStringContains(
            '$porPagina = 50',
            $controller,
            "La Carta de Deuda debe paginar de 50 en 50"
        );
        $this->assertStringContains(
            'array_slice($todasUnidades',
            $controller,
            "El controlador debe recortar la lista a la página actual"
        );
        $this->assertStringContains(
            "\$_GET['page']",
            $controller,
            "El controlador debe aceptar el parámetro page"
        );

        $view = file_get_contents(VIEWS_PATH . '/admin/reportes/morosidad.php');
        $this->assertStringContains(
            'pagination pagination-sm',
            $view,
            "La vista debe mostrar controles de paginación"
        );
        $this->assertStringContains(
            "'page' =>",
            $view,
            "Los enlaces de paginación deben conservar el número de página"
        );
        $this->assertStringContains(
            'Mostrando',
            $view,
            "La vista debe indicar el rango de unidades mostrado"
        );
    }

    public function testPaginacionIncluyeAccesoRapidoAPrimeraYUltimaPagina(): void {
        $view = file_get_contents(VIEWS_PATH . '/admin/reportes/morosidad.php');

        $this->assertStringContains(
            'aria-label="Primera página"',
            $view,
            "La paginación debe incluir un acceso rápido a la primera página"
        );
        $this->assertStringContains(
            'aria-label="Última página"',
            $view,
            "La paginación debe incluir un acceso rápido a la última página"
        );
        $this->assertStringContains(
            "['page' => 1]",
            $view,
            "El botón de primera página debe apuntar a la página 1"
        );
        $this->assertStringContains(
            "['page' => \$paginacion['totalPaginas']]",
            $view,
            "El botón de última página debe apuntar al total de páginas"
        );
    }
}
