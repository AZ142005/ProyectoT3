<?php
namespace Tests;

use App\Services\ConciliacionBancariaService;

class ConciliacionTest extends TestCase {

    public function testNormalizarReferenciaStripsNonNumericAndLeadingZeros() {
        $service = new ConciliacionBancariaService();

        $this->assertEquals("123456", $service->normalizarReferencia("REF-00123456"));
        $this->assertEquals("998877", $service->normalizarReferencia("TRANSF-998877"));
        $this->assertEquals("1234567890", $service->normalizarReferencia("0001234567890"));
        $this->assertEquals("0", $service->normalizarReferencia("00000"));
        $this->assertEquals("0", $service->normalizarReferencia("---"));
    }

    public function testJaroWinklerSimilarity() {
        $service = new ConciliacionBancariaService();

        // Cadenas idénticas
        $this->assertEquals(1.0, $service->calcularSimilitudJaroWinkler("123456", "123456"));

        // Transposición de caracteres (123456 vs 123465) debe superar umbral 0.85
        $similitudTransp = $service->calcularSimilitudJaroWinkler("123456", "123465");
        $this->assertTrue($similitudTransp >= 0.85);

        // Cadenas completamente diferentes
        $similitudDif = $service->calcularSimilitudJaroWinkler("111111", "999999");
        $this->assertTrue($similitudDif < 0.5);
    }

    public function testParserDetectsDebitsAndNormalizesAmounts() {
        $service = new ConciliacionBancariaService();

        $csvTemp = tempnam(sys_get_temp_dir(), 'test_extracto_');
        $lineas = [
            "Fecha;Referencia;Descripcion;Monto",
            "25/08/2026;REF-00123456;PAGO MOVIL RESIDENTE;1.250,50",
            "25/08/2026;REF-999000;COMISION MANTENIMIENTO;-15,00"
        ];
        file_put_contents($csvTemp, implode("\n", $lineas));

        $resultado = $service->parsearArchivo($csvTemp, 'mercantil');
        unlink($csvTemp);

        $this->assertEquals(2, count($resultado));
        $this->assertEquals("2026-08-25", $resultado[0]['fecha']);
        $this->assertEquals(1250.50, $resultado[0]['monto']);
        $this->assertEquals("credito", $resultado[0]['tipo']);

        $this->assertEquals("2026-08-25", $resultado[1]['fecha']);
        $this->assertEquals(15.00, $resultado[1]['monto']);
        $this->assertEquals("debito", $resultado[1]['tipo']);
    }

    public function testParserBancoVenezuelaPdfNativo() {
        $service = new ConciliacionBancariaService();

        $cabecera = "Estado de cuenta moneda nacional\nCliente V31168523 - JUNIOR JOSE DAVILA PAREDES\n0102****7558 01/08/2026 - 31/08/2026\nReferencia Descripcion Fecha Mov Debito Credito Saldo";
        $saldoInicial = "SALDO INICIAL 01/08/2026 SI 0,00 0,00 0,50";
        $linea1 = "0677232314587 OPERACION PAGOMOVIL BDV 02/08/2026 NC 0,00 10.000,00 10.000,40";
        $linea2 = "9540400005422 COM MANTENIMIENTO DE CUEN 02/08/2026 ND -0,10 0,00 0,40";
        $linea3 = "0591385593359 TRASPASO OTRAS CTAS BDV E 03/08/2026 NC 0,00 376.270,00 385.662,40";
        $linea4 = "0027296099700 COMISION PAGOMOVILBDV 03/08/2026 ND -6,74 0,00 76.409,66";
        $pie = "El Banco de Venezuela S.A Banco Universal certifica la informacion contenida en el presente documento\nPagina: 1/14";

        $flujoTexto = "BT /F1 12 Tf 50 700 Td (" . $cabecera . ") Tj "
                    . "0 -20 Td (" . $saldoInicial . ") Tj "
                    . "0 -20 Td (" . $linea1 . ") Tj "
                    . "0 -20 Td (" . $linea2 . ") Tj "
                    . "0 -20 Td (" . $linea3 . ") Tj "
                    . "0 -20 Td (" . $linea4 . ") Tj "
                    . "0 -20 Td (" . $pie . ") Tj ET";

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

        $tempPdf = tempnam(sys_get_temp_dir(), 'test_bdv_') . '.pdf';
        file_put_contents($tempPdf, $pdfSintetico);

        try {
            $movimientos = $service->parsearArchivo($tempPdf, 'venezuela');

            $this->assertEquals(4, count($movimientos), "Debe detectar exactamente 4 movimientos operativos");

            // Movimiento 1: Pago móvil crédito (NC)
            $this->assertEquals('0677232314587', $movimientos[0]['referencia']);
            $this->assertEquals('OPERACION PAGOMOVIL BDV', $movimientos[0]['descripcion']);
            $this->assertEquals('2026-08-02', $movimientos[0]['fecha']);
            $this->assertEquals('credito', $movimientos[0]['tipo']);
            $this->assertEquals(10000.00, $movimientos[0]['monto']);

            // Movimiento 2: Comisión mantenimiento débito (ND)
            $this->assertEquals('9540400005422', $movimientos[1]['referencia']);
            $this->assertEquals('COM MANTENIMIENTO DE CUEN', $movimientos[1]['descripcion']);
            $this->assertEquals('2026-08-02', $movimientos[1]['fecha']);
            $this->assertEquals('debito', $movimientos[1]['tipo']);
            $this->assertEquals(0.10, $movimientos[1]['monto']);

            // Movimiento 3: Traspaso crédito (NC) con monto alto
            $this->assertEquals('0591385593359', $movimientos[2]['referencia']);
            $this->assertEquals('TRASPASO OTRAS CTAS BDV E', $movimientos[2]['descripcion']);
            $this->assertEquals('2026-08-03', $movimientos[2]['fecha']);
            $this->assertEquals('credito', $movimientos[2]['tipo']);
            $this->assertEquals(376270.00, $movimientos[2]['monto']);

            // Movimiento 4: Comisión pago móvil débito (ND)
            $this->assertEquals('0027296099700', $movimientos[3]['referencia']);
            $this->assertEquals('debito', $movimientos[3]['tipo']);
            $this->assertEquals(6.74, $movimientos[3]['monto']);
        } finally {
            if (file_exists($tempPdf)) {
                @unlink($tempPdf);
            }
        }
    }

    public function testFlashNormalizesDangerToError() {
        \App\Core\Flash::set('danger', 'Mensaje de prueba crítico');
        $error = \App\Core\Flash::get('error');
        $this->assertEquals('Mensaje de prueba crítico', $error, "Flash::get('error') debe recuperar mensajes establecidos con tipo 'danger'");
    }

    public function testExtractosBancariosPersistenciaYClasificacion() {
        $model = new \App\Models\ConciliacionModel();
        $lote = 'LOTE-UNIT-TEST-' . uniqid();

        $movimientos = [
            [
                'fecha'       => '2026-08-10',
                'referencia'  => '998877665544',
                'descripcion' => 'PAGO MOVIL INGRESO',
                'monto'       => 1500.00,
                'tipo'        => 'credito'
            ],
            [
                'fecha'       => '2026-08-10',
                'referencia'  => '998877665545',
                'descripcion' => 'COMISION BANCARIA',
                'monto'       => 15.00,
                'tipo'        => 'debito'
            ]
        ];

        try {
            $stats = $model->insertarExtracto($movimientos, 'Banco de Venezuela', $lote);
            $this->assertEquals(2, $stats['insertados'], "Debe insertar 2 registros");
            $this->assertEquals(1, $stats['debitos'], "Debe contabilizar 1 débito descartado");

            $pendientes = $model->obtenerExtractosPendientes($lote);
            $this->assertEquals(1, count($pendientes), "Solo los créditos deben estar pendientes");
            $this->assertEquals('998877665544', $pendientes[0]['referencia_bancaria']);
            $this->assertEquals(1500.00, (float)$pendientes[0]['monto']);
        } finally {
            $db = \App\Core\Database::getConnection();
            $db->prepare("DELETE FROM extractos_bancarios WHERE lote_importacion = :lote")->execute(['lote' => $lote]);
        }
    }

    public function testCruceInteligenteDetectaComprobantesPago() {
        $db = \App\Core\Database::getConnection();
        $service = new ConciliacionBancariaService();
        $testRef = '9876543210' . rand(100, 999);
        $testMonto = 4500.50;

        $persona = $db->query("SELECT id FROM personas LIMIT 1")->fetch();
        $residenteId = $persona ? $persona['id'] : null;

        $factura = $db->query("SELECT id FROM facturas LIMIT 1")->fetch();
        $facturaId = $factura ? $factura['id'] : null;

        // Insertar comprobante de prueba en estado pendiente
        $stmt = $db->prepare("
            INSERT INTO comprobantes_pago (residente_id, factura_id, monto, metodo_pago, referencia, fecha_pago, estado, observaciones)
            VALUES (:residente_id, :factura_id, :monto, 'pago_movil', :referencia, CURDATE(), 'pendiente', 'Test Cruce Unitario')
        ");
        $stmt->execute([
            'residente_id' => $residenteId,
            'factura_id'   => $facturaId,
            'monto'        => $testMonto,
            'referencia'   => $testRef
        ]);
        $comprobanteId = (int)$db->lastInsertId();

        try {
            $movimientosSimulados = [
                [
                    'id'                  => 999999,
                    'banco'               => 'Banco de Venezuela',
                    'fecha_movimiento'    => date('Y-m-d'),
                    'referencia_bancaria' => $testRef,
                    'descripcion'         => 'PAGO MOVIL TEST UNITARIO',
                    'monto'               => $testMonto,
                    'tipo_movimiento'     => 'credito',
                    'conciliado'          => 0,
                    'lote_importacion'    => 'LOTE-TEST-CRUCE'
                ]
            ];

            $resultado = $service->ejecutarCruceInteligente($movimientosSimulados);

            $this->assertEquals(1, count($resultado['coincidencias_exactas']), "Debe encontrar 1 coincidencia exacta en comprobantes_pago");
            $match = $resultado['coincidencias_exactas'][0];
            $this->assertEquals($comprobanteId, $match['pago']['id'], "El ID del pago debe ser el del comprobante insertado");
            $this->assertEquals('comprobante', $match['pago']['origen_tabla'], "El origen_tabla debe ser 'comprobante'");
            $this->assertEquals($testRef, $match['pago']['referencia'], "La referencia debe coincidir");
            $this->assertEquals($testMonto, (float)$match['pago']['monto'], "El monto debe coincidir");
        } finally {
            $db->prepare("DELETE FROM comprobantes_pago WHERE id = :id")->execute(['id' => $comprobanteId]);
        }
    }
}

