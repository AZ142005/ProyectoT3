<?php
namespace Tests;

use App\Core\Database;
use App\Models\GastosModel;
use App\Models\FacturasModel;
use App\Models\UnidadesModel;
use App\Models\EdificiosModel;

class GastosTipologiaCalculoTest extends TestCase {

    /**
     * Verifica que crearGasto persista correctamente tipo_gasto ('comun' y 'individual') y edificio_id.
     */
    public function testCrearGastoConTipologiaComunEIndividual(): void {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $edificioModel = new EdificiosModel();
            $edificioId = $edificioModel->create([
                'nombre'      => 'Torre Test Tipologia ' . time(),
                'descripcion' => 'Edificio para pruebas de tipologia'
            ]);
            $this->assertTrue($edificioId > 0, 'Debe crearse el edificio de prueba');

            $gastosModel = new GastosModel();

            // 1. Gasto común (global) en año 2099
            $gastoComunId = $gastosModel->crearGasto([
                'categoria_id'          => 1,
                'mes'                   => 12,
                'anio'                  => 2099,
                'descripcion'           => 'Reparación bomba de agua principal',
                'monto_total'           => 1000.00,
                'fecha_gasto'           => '2099-12-05',
                'proveedor'             => 'Bombas del Centro C.A. ' . time(),
                'nro_factura_proveedor' => 'FAC-COMUN-' . time(),
                'admin_id'              => 1,
                'tipo_gasto'            => 'comun',
            ]);
            $this->assertTrue($gastoComunId > 0, 'Debe crearse el gasto común');

            $stmt = $db->prepare("SELECT tipo_gasto, edificio_id FROM gastos_comunes WHERE id = :id");
            $stmt->execute(['id' => $gastoComunId]);
            $gastoComun = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->assertEquals('comun', $gastoComun['tipo_gasto'], 'El tipo_gasto debe ser comun');
            $this->assertTrue($gastoComun['edificio_id'] === null, 'El edificio_id debe ser NULL para gastos comunes');

            // 2. Gasto individual (por edificio)
            $gastoIndivId = $gastosModel->crearGasto([
                'categoria_id'          => 1,
                'mes'                   => 12,
                'anio'                  => 2099,
                'descripcion'           => 'Pintura de fachada Torre Test',
                'monto_total'           => 600.00,
                'fecha_gasto'           => '2099-12-08',
                'proveedor'             => 'Pinturas del Este S.A. ' . time(),
                'nro_factura_proveedor' => 'FAC-INDIV-' . time(),
                'admin_id'              => 1,
                'tipo_gasto'            => 'individual',
                'edificio_id'           => $edificioId
            ]);
            $this->assertTrue($gastoIndivId > 0, 'Debe crearse el gasto individual');

            $stmt->execute(['id' => $gastoIndivId]);
            $gastoIndiv = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->assertEquals('individual', $gastoIndiv['tipo_gasto'], 'El tipo_gasto debe ser individual');
            $this->assertEquals(intval($edificioId), intval($gastoIndiv['edificio_id']), 'El edificio_id debe coincidir con el asignado');
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }
    }

    /**
     * Verifica que crearGasto lance excepción si se declara un gasto individual sin edificio válido.
     */
    public function testCrearGastoIndividualSinEdificioLanzaExcepcion(): void {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $gastosModel = new GastosModel();
            $excepcionCapturada = false;

            try {
                $gastosModel->crearGasto([
                    'categoria_id' => 1,
                    'mes'          => 12,
                    'anio'         => 2099,
                    'descripcion'  => 'Gasto sin edificio',
                    'monto_total'  => 300.00,
                    'proveedor'    => 'Proveedor Test ' . time(),
                    'admin_id'     => 1,
                    'tipo_gasto'   => 'individual',
                    'edificio_id'  => 0 // Inválido
                ]);
            } catch (\Exception $e) {
                $excepcionCapturada = true;
                $this->assertStringContains('edificio válido', $e->getMessage());
            }

            $this->assertTrue($excepcionCapturada, 'Debe lanzar excepción si falta el edificio_id en gasto individual');
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }
    }

    /**
     * Verifica la fórmula de distribución dinámica:
     * Cuota = Fracción Global + Fracción Edificio
     */
    public function testCalculoDistribucionCuotasFormulaMatematica(): void {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $mes = 12;
            $anio = 2099;

            // Crear edificio de prueba
            $edificioModel = new EdificiosModel();
            $edificioId = $edificioModel->create([
                'nombre'      => 'Torre Formula ' . time(),
                'descripcion' => 'Edificio prueba cálculo'
            ]);

            // Registrar un gasto global de Bs. 1,000.00 y un gasto individual de Bs. 500.00
            $gastosModel = new GastosModel();
            $gastosModel->crearGasto([
                'categoria_id' => 1,
                'mes'          => $mes,
                'anio'         => $anio,
                'descripcion'  => 'Mantenimiento Global Ascensores',
                'monto_total'  => 1000.00,
                'proveedor'    => 'Ascensores Global ' . time(),
                'admin_id'     => 1,
                'tipo_gasto'   => 'comun'
            ]);

            $gastosModel->crearGasto([
                'categoria_id' => 1,
                'mes'          => $mes,
                'anio'         => $anio,
                'descripcion'  => 'Lámparas pasillo Torre Formula',
                'monto_total'  => 500.00,
                'proveedor'    => 'Iluminación Torre ' . time(),
                'admin_id'     => 1,
                'tipo_gasto'   => 'individual',
                'edificio_id'  => $edificioId
            ]);

            // Contar unidades activas totales y unidades activas del edificio
            $stmtTotal = $db->query("SELECT COUNT(*) as total FROM unidades WHERE estado = 1");
            $totalActivas = intval($stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

            $stmtEd = $db->prepare("SELECT COUNT(*) as total FROM unidades WHERE estado = 1 AND edificio_id = :ed");
            $stmtEd->execute(['ed' => $edificioId]);
            $activasEdificio = intval($stmtEd->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

            $distribucion = $gastosModel->calcularDistribucionCuotas($mes, $anio);

            $this->assertEquals(1000.00, $distribucion['total_global'], 'Total global debe ser 1000.00');
            $this->assertEquals($totalActivas, $distribucion['total_unidades'], 'Total unidades activas debe coincidir');

            $fraccionGlobalEsperada = ($totalActivas > 0) ? round(1000.00 / $totalActivas, 2) : 0.00;
            $this->assertEquals($fraccionGlobalEsperada, $distribucion['cuota_global_unidad'], 'Fracción global calculada debe coincidir');

            if ($activasEdificio > 0) {
                $fraccionEdificioEsperada = round(500.00 / $activasEdificio, 2);
                $this->assertEquals($fraccionEdificioEsperada, $distribucion['edificios'][$edificioId]['cuota_individual_unidad']);
            }
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }
    }

    /**
     * Verifica que en la vista y controlador de Estructura se haya eliminado por completo
     * la asignación manual de cuota_mensual.
     */
    public function testDeprecacionCuotaMensualManualEnEstructura(): void {
        // 1. En EstructuraController.php, guardarUnidad no debe procesar ni requerir cuota_mensual
        $controllerContent = file_get_contents(dirname(__DIR__) . '/app/controllers/EstructuraController.php');
        preg_match('/public function guardarUnidad\(\)(.*?)(?=public function|\Z)/s', $controllerContent, $m);
        $this->assertTrue(count($m) > 1, 'guardarUnidad debe existir en EstructuraController');
        $this->assertTrue(strpos($m[1], "\$_POST['cuota_mensual']") === false, 'guardarUnidad NO debe leer cuota_mensual de POST');
        $this->assertTrue(strpos($m[1], "'cuota_mensual' =>") === false, 'guardarUnidad NO debe asignar cuota_mensual al array $data');

        // 2. En estructura.php (vista), no debe haber input de cuota_mensual en el modal
        $viewContent = file_get_contents(dirname(__DIR__) . '/app/views/admin/estructura.php');
        $this->assertTrue(strpos($viewContent, 'id="unidad_cuota_mensual"') === false, 'No debe existir el input unidad_cuota_mensual');
        $this->assertTrue(strpos($viewContent, 'name="cuota_mensual"') === false, 'No debe existir el input name cuota_mensual');
        $this->assertTrue(strpos($viewContent, 'Cuota Mensual Base') === false, 'No debe existir la columna Cuota Mensual Base en la tabla');
    }

    /**
     * Verifica que los sidebars y headers usen el nombre "Gastos" y no "Gastos Comunes".
     */
    public function testRenombradoDeSeccionAGastos(): void {
        $adminSidebar = file_get_contents(dirname(__DIR__) . '/app/views/layouts/admin_sidebar.php');
        $this->assertTrue(strpos($adminSidebar, "'label' => 'Gastos'") !== false, 'admin_sidebar debe tener Gastos');
        $this->assertTrue(strpos($adminSidebar, "'label' => 'Gastos Comunes'") === false, 'admin_sidebar NO debe tener Gastos Comunes');

        $auditorSidebar = file_get_contents(dirname(__DIR__) . '/app/views/layouts/auditor_sidebar.php');
        $this->assertTrue(strpos($auditorSidebar, "'label' => 'Gastos'") !== false, 'auditor_sidebar debe tener Gastos');
        $this->assertTrue(strpos($auditorSidebar, "'label' => 'Gastos Comunes'") === false, 'auditor_sidebar NO debe tener Gastos Comunes');

        $gastoIndex = file_get_contents(dirname(__DIR__) . '/app/views/admin/gastos/index.php');
        $this->assertTrue(strpos($gastoIndex, '>Gastos<') !== false, 'index de gastos debe titularse Gastos');
        $this->assertTrue(strpos($gastoIndex, '>Gastos Comunes<') === false, 'header no debe decir Gastos Comunes');
    }
}
