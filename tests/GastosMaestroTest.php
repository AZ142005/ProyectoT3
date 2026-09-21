<?php
namespace Tests;

use App\Services\GastoParserService;
use App\Models\GastosModel;
use App\Models\PagoModel;
use App\Controllers\GastoController;

class GastosMaestroTest extends TestCase {

    private array $categoriasMock = [
        ['id' => 1, 'nombre' => 'Servicios Básicos'],
        ['id' => 2, 'nombre' => 'Mantenimiento'],
        ['id' => 3, 'nombre' => 'Vigilancia'],
        ['id' => 4, 'nombre' => 'Administrativos'],
        ['id' => 5, 'nombre' => 'Fondo de Reserva']
    ];

    public function testClassesAndMethodsExist() {
        $this->assertTrue(class_exists(GastoParserService::class), "GastoParserService debe existir");
        $this->assertTrue(method_exists(GastosModel::class, 'importarGastosMaestro'), "GastosModel debe tener importarGastosMaestro");
        $this->assertTrue(method_exists(GastoController::class, 'cargarMaestro'), "GastoController debe tener cargarMaestro");
        $this->assertTrue(method_exists(GastoController::class, 'parsearMaestro'), "GastoController debe tener parsearMaestro");
        $this->assertTrue(method_exists(GastoController::class, 'importarMaestro'), "GastoController debe tener importarMaestro");
    }

    public function testAnalizarLineasGastosExtractsVenezuelanFormat() {
        $parser = new GastoParserService();
        $texto = "15/09/2026 HidroServicios C.A. Factura #00982 Mantenimiento preventivo de bombas Bs. 4.500,50";

        $renglones = $parser->analizarLineasGastos($texto, 2, $this->categoriasMock, 9, 2026);

        $this->assertEquals(1, count($renglones), "Debe detectar exactamente 1 renglón");
        $g = $renglones[0];
        $this->assertEquals(4500.50, $g['monto_total'], "El monto debe ser 4500.50 sin inflación");
        $this->assertEquals('2026-09-15', $g['fecha_gasto']);
        $this->assertEquals('00982', $g['nro_factura_proveedor']);
        $this->assertEquals(2, $g['pagina_soporte']);
        $this->assertEquals(2, $g['categoria_id'], "Debe categorizar como Mantenimiento");
        $this->assertEquals($texto, $g['extracto_texto']);
    }

    public function testAnalizarLineasGastosExtractsInternationalFormat() {
        $parser = new GastoParserService();
        $texto = "2026-09-18 CORPOELEC Factura 4511 Electricidad Iluminacion Exterior $ 1250.00";

        $renglones = $parser->analizarLineasGastos($texto, 1, $this->categoriasMock, 9, 2026);

        $this->assertEquals(1, count($renglones));
        $g = $renglones[0];
        $this->assertEquals(1250.00, $g['monto_total']);
        $this->assertEquals('2026-09-18', $g['fecha_gasto']);
        $this->assertEquals(1, $g['categoria_id'], "Debe categorizar como Servicios Básicos");
    }

    public function testInferenciaSemanticaDeCategorias() {
        $parser = new GastoParserService();

        // 1. Vigilancia
        $lineaVigilancia = "01/09/2026 Seguridad y Vigilancia La Roca C.A. Factura 110 Servicio de custodia garita Bs. 8.200,00";
        $r1 = $parser->analizarLineasGastos($lineaVigilancia, 1, $this->categoriasMock, 9, 2026);
        $this->assertEquals(3, $r1[0]['categoria_id'], "Debe identificar categoría Vigilancia");

        // 2. Administrativos
        $lineaAdmin = "05/09/2026 Asesoría Contable & Auditoría Factura 501 Honorarios mensuales contador Bs. 2.100,00";
        $r2 = $parser->analizarLineasGastos($lineaAdmin, 1, $this->categoriasMock, 9, 2026);
        $this->assertEquals(4, $r2[0]['categoria_id'], "Debe identificar categoría Administrativos");

        // 3. Fondo de Reserva
        $lineaReserva = "30/09/2026 Aporte cuota extraordinaria fondo de reserva imprevistos Bs. 1.500,00";
        $r3 = $parser->analizarLineasGastos($lineaReserva, 1, $this->categoriasMock, 9, 2026);
        $this->assertEquals(5, $r3[0]['categoria_id'], "Debe identificar Fondo de Reserva");
    }

    public function testOmisionDeCabecerasYLineasNoFinancieras() {
        $parser = new GastoParserService();
        $textoMulti = "RELACION DE GASTOS MES DE SEPTIEMBRE 2026\n"
                    . "Fecha | Descripcion | Proveedor | Factura | Total Bs.\n"
                    . "--------------------------------------------------------\n"
                    . "10/09/2026 Mantenimiento de Ascensor Elevadores C.A. Fac 901 Bs. 3.200,00\n"
                    . "Nota: Todos los gastos fueron cancelados con cheque o transferencia.\n";

        $renglones = $parser->analizarLineasGastos($textoMulti, 1, $this->categoriasMock, 9, 2026);

        $this->assertEquals(1, count($renglones), "Solo debe detectar el renglón financiero real, ignorando cabeceras y notas");
        $this->assertEquals(3200.00, $renglones[0]['monto_total']);
    }

    public function testProcesamientoPdfNativoIndependiente() {
        $parser = new GastoParserService();

        // Generar un PDF sintético mínimo en memoria con flujo FlateDecode comprimido
        $lineaGasto = "12/09/2026 Hidrocentro Factura 8820 Servicio de Agua Potable Bs. 950,00";
        $flujoTexto = "BT /F1 12 Tf 50 700 Td (" . $lineaGasto . ") Tj ET";
        $flujoComprimido = gzcompress($flujoTexto);
        $longitudStream = strlen($flujoComprimido);

        $pdfSintetico = "%PDF-1.4\n"
                      . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
                      . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
                      . "3 0 obj << /Type /Page /Parent 2 0 R /Contents 4 0 R >> endobj\n"
                      . "4 0 obj << /Length " . $longitudStream . " /Filter /FlateDecode >>\n"
                      . "stream\n" . $flujoComprimido . "\nendstream\nendobj\n"
                      . "xref\n0 5\n0000000000 65535 f \n"
                      . "trailer << /Size 5 /Root 1 0 R >>\nstartxref\n100\n%%EOF";

        $tempPdf = tempnam(sys_get_temp_dir(), 'test_maestro_') . '.pdf';
        file_put_contents($tempPdf, $pdfSintetico);

        try {
            $resultado = $parser->procesarPdfMaestro($tempPdf, 9, 2026, $this->categoriasMock);

            $this->assertTrue($resultado['exito'], "Debe procesar el PDF sintético exitosamente");
            $this->assertEquals(1, $resultado['total']);
            $this->assertEquals(950.00, $resultado['suma']);
            $this->assertEquals(1, $resultado['renglones'][0]['pagina_soporte']);
            $this->assertEquals(1, $resultado['renglones'][0]['categoria_id']);
        } finally {
            if (file_exists($tempPdf)) {
                @unlink($tempPdf);
            }
        }
    }

    public function testPagoModelAprobarLoteNoTieneVariableIndefinida() {
        $pagoModelFile = file_get_contents(BASE_PATH . '/app/models/PagoModel.php');
        $this->assertFalse(
            strpos($pagoModelFile, '$pago[\'id\']'),
            "PagoModel no debe contener \$pago['id'] en el bucle foreach de sqlPago en aprobarLote"
        );
    }
}
