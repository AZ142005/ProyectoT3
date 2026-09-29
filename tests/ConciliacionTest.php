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

        // Insertar comprobante de prueba en estado pendiente con banco de destino identificado
        $fechaPago = date('Y-m-d');
        $stmt = $db->prepare("
            INSERT INTO comprobantes_pago (residente_id, factura_id, monto, metodo_pago, referencia, fecha_pago, estado, observaciones)
            VALUES (:residente_id, :factura_id, :monto, 'pago_movil', :referencia, :fecha_pago, 'pendiente', 'Cuenta Destino: Banco de Venezuela (01020000000000007558) | Test Cruce Unitario')
        ");
        $stmt->execute([
            'residente_id' => $residenteId,
            'factura_id'   => $facturaId,
            'monto'        => $testMonto,
            'referencia'   => $testRef,
            'fecha_pago'   => $fechaPago
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

            $this->assertEquals(1, count($resultado['coincidencias_exactas']), "Debe encontrar 1 coincidencia exacta en comprobantes_pago con 4 dimensiones válidas");
            $match = $resultado['coincidencias_exactas'][0];
            $this->assertEquals($comprobanteId, $match['pago']['id'], "El ID del pago debe ser el del comprobante insertado");
            $this->assertEquals('comprobante', $match['pago']['origen_tabla'], "El origen_tabla debe ser 'comprobante'");
            $this->assertEquals($testRef, $match['pago']['referencia'], "La referencia debe coincidir");
            $this->assertEquals($testMonto, (float)$match['pago']['monto'], "El monto debe coincidir");
        } finally {
            $db->prepare("DELETE FROM comprobantes_pago WHERE id = :id")->execute(['id' => $comprobanteId]);
        }
    }

    public function testCruceInteligenteRechazaCoincidenciaExactaSiFechaDifiere() {
        $db = \App\Core\Database::getConnection();
        $service = new ConciliacionBancariaService();
        $testRef = '777888999' . rand(100, 999);
        $testMonto = 3200.00;

        $persona = $db->query("SELECT id FROM personas LIMIT 1")->fetch();
        $residenteId = $persona ? $persona['id'] : null;
        $factura = $db->query("SELECT id FROM facturas LIMIT 1")->fetch();
        $facturaId = $factura ? $factura['id'] : null;

        // Comprobante con fecha de pago 5 días en el pasado
        $fechaPago = date('Y-m-d', strtotime('-5 days'));
        $stmt = $db->prepare("
            INSERT INTO comprobantes_pago (residente_id, factura_id, monto, metodo_pago, referencia, fecha_pago, estado, observaciones)
            VALUES (:residente_id, :factura_id, :monto, 'transferencia', :referencia, :fecha_pago, 'pendiente', 'Cuenta Destino: Banco de Venezuela (0102...)')
        ");
        $stmt->execute([
            'residente_id' => $residenteId,
            'factura_id'   => $facturaId,
            'monto'        => $testMonto,
            'referencia'   => $testRef,
            'fecha_pago'   => $fechaPago
        ]);
        $comprobanteId = (int)$db->lastInsertId();

        try {
            // Movimiento bancario registrado HOY (fecha distinta al pago)
            $movimientosSimulados = [
                [
                    'id'                  => 888888,
                    'banco'               => 'Banco de Venezuela',
                    'fecha_movimiento'    => date('Y-m-d'),
                    'referencia_bancaria' => $testRef,
                    'descripcion'         => 'TRANSF TEST FECHA DISPAR',
                    'monto'               => $testMonto,
                    'tipo_movimiento'     => 'credito'
                ]
            ];

            $resultado = $service->ejecutarCruceInteligente($movimientosSimulados);

            // NO debe ser clasificado como coincidencia exacta porque difiere la fecha
            $this->assertEquals(0, count($resultado['coincidencias_exactas']), "No debe ser coincidencia exacta si la fecha difiere");
            // Debe ser redirigido a sugeridas con alerta explicativa
            $this->assertEquals(1, count($resultado['coincidencias_sugeridas']), "Debe enviarse a sugeridas para revisión manual");
            $sug = $resultado['coincidencias_sugeridas'][0];
            $this->assertTrue(isset($sug['alerta']), "Debe incluir alerta explicativa");
            $this->assertTrue(str_contains($sug['alerta'], 'Fecha dispar'), "Alerta debe indicar 'Fecha dispar'");
        } finally {
            $db->prepare("DELETE FROM comprobantes_pago WHERE id = :id")->execute(['id' => $comprobanteId]);
        }
    }

    public function testCruceInteligenteRechazaCoincidenciaExactaSiBancoDifiere() {
        $db = \App\Core\Database::getConnection();
        $service = new ConciliacionBancariaService();
        $testRef = '444555666' . rand(100, 999);
        $testMonto = 1800.75;

        $persona = $db->query("SELECT id FROM personas LIMIT 1")->fetch();
        $residenteId = $persona ? $persona['id'] : null;
        $factura = $db->query("SELECT id FROM facturas LIMIT 1")->fetch();
        $facturaId = $factura ? $factura['id'] : null;

        // Comprobante reportado con cuenta destino Banesco
        $stmt = $db->prepare("
            INSERT INTO comprobantes_pago (residente_id, factura_id, monto, metodo_pago, referencia, fecha_pago, estado, observaciones)
            VALUES (:residente_id, :factura_id, :monto, 'transferencia', :referencia, CURDATE(), 'pendiente', 'Cuenta Destino: Banesco Banco Universal (0134...)')
        ");
        $stmt->execute([
            'residente_id' => $residenteId,
            'factura_id'   => $facturaId,
            'monto'        => $testMonto,
            'referencia'   => $testRef
        ]);
        $comprobanteId = (int)$db->lastInsertId();

        try {
            // Movimiento bancario registrado en extracto de Banco de Venezuela (banco distinto al pago)
            $movimientosSimulados = [
                [
                    'id'                  => 777777,
                    'banco'               => 'Banco de Venezuela',
                    'fecha_movimiento'    => date('Y-m-d'),
                    'referencia_bancaria' => $testRef,
                    'descripcion'         => 'TRANSF TEST BANCO DISPAR',
                    'monto'               => $testMonto,
                    'tipo_movimiento'     => 'credito'
                ]
            ];

            $resultado = $service->ejecutarCruceInteligente($movimientosSimulados);

            // NO debe ser clasificado como coincidencia exacta porque difiere el banco
            $this->assertEquals(0, count($resultado['coincidencias_exactas']), "No debe ser coincidencia exacta si el banco difiere");
            // Debe ser redirigido a sugeridas con alerta explicativa
            $this->assertEquals(1, count($resultado['coincidencias_sugeridas']), "Debe enviarse a sugeridas para revisión manual");
            $sug = $resultado['coincidencias_sugeridas'][0];
            $this->assertTrue(isset($sug['alerta']), "Debe incluir alerta explicativa");
            $this->assertTrue(str_contains($sug['alerta'], 'Banco dispar'), "Alerta debe indicar 'Banco dispar'");
        } finally {
            $db->prepare("DELETE FROM comprobantes_pago WHERE id = :id")->execute(['id' => $comprobanteId]);
        }
    }

    public function testNormalizadoresBancoYFecha() {
        $service = new ConciliacionBancariaService();

        // Normalización de banco
        $this->assertEquals('venezuela', $service->normalizarNombreBanco('Banco de Venezuela S.A.'));
        $this->assertEquals('venezuela', $service->normalizarNombreBanco('BDV'));
        $this->assertEquals('venezuela', $service->normalizarNombreBanco('0102'));
        $this->assertEquals('mercantil', $service->normalizarNombreBanco('BANCO MERCANTIL C.A.'));
        $this->assertEquals('banesco', $service->normalizarNombreBanco('Banesco Banco Universal'));
        $this->assertEquals('provincial', $service->normalizarNombreBanco('BBVA Provincial'));
        $this->assertEquals('bnc', $service->normalizarNombreBanco('Banco Nacional de Crédito'));

        // Comparación de fechas
        $this->assertTrue($service->sonFechasCoincidentes('2026-09-22', '2026-09-22'));
        $this->assertTrue($service->sonFechasCoincidentes('22/09/2026', '2026-09-22'));
        $this->assertFalse($service->sonFechasCoincidentes('2026-09-21', '2026-09-22'));
        $this->assertFalse($service->sonFechasCoincidentes('', '2026-09-22'));

        // Comparación de bancos con pago
        $pagoVenezuela = ['banco_receptor' => 'Banco de Venezuela', 'observaciones' => ''];
        $pagoMercantil = ['banco_receptor' => '', 'observaciones' => 'Cuenta Destino: Banco Mercantil (0105...)'];

        $this->assertTrue($service->sonBancosCoincidentes('venezuela', $pagoVenezuela));
        $this->assertFalse($service->sonBancosCoincidentes('mercantil', $pagoVenezuela));
        $this->assertTrue($service->sonBancosCoincidentes('mercantil', $pagoMercantil));
        $this->assertFalse($service->sonBancosCoincidentes('banesco', $pagoMercantil));
    }

    public function testCruceInteligenteIncluyeArchivoYMetodoPago() {
        $db = \App\Core\Database::getConnection();
        $persona = $db->query("SELECT id FROM personas LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        $unidad = $db->query("SELECT id FROM unidades LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        if (!$persona || !$unidad) {
            $this->markTestSkipped("Datos insuficientes para prueba.");
            return;
        }

        $resId = intval($persona['id']);
        $uniId = intval($unidad['id']);
        $ref = 'TESTCRUCE' . rand(1000, 9999);
        $monto = 85.50;
        $archivo = 'test_comprobante_' . uniqid() . '.jpg';

        $fecha = date('Y-m-d');
        $ins = $db->prepare("
            INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, banco_receptor, archivo, estado)
            VALUES (:residente_id, :unidad_id, :monto, :fecha, 'transferencia', :referencia, 'Banco de Venezuela', :archivo, 'PENDIENTE')
        ");
        $ins->execute([
            'residente_id' => $resId,
            'unidad_id'    => $uniId,
            'monto'        => $monto,
            'fecha'        => $fecha,
            'referencia'   => $ref,
            'archivo'      => $archivo
        ]);
        $pagoId = intval($db->lastInsertId());

        try {
            $service = new ConciliacionBancariaService();
            $movimientos = [[
                'id' => 999999,
                'fecha_movimiento' => $fecha,
                'descripcion_banco' => 'TRANSFERENCIA BDV ' . $ref,
                'referencia_bancaria' => $ref,
                'monto' => $monto,
                'banco' => 'Banco de Venezuela',
                'lote_importacion' => 'LOTE-TEST'
            ]];

            $resultado = $service->ejecutarCruceInteligente($movimientos);
            $this->assertTrue(!empty($resultado['coincidencias_exactas']), "Debe existir coincidencia exacta");
            $match = $resultado['coincidencias_exactas'][0];
            $this->assertEquals($archivo, $match['pago']['archivo']);
            $this->assertEquals('transferencia', $match['pago']['metodo_pago']);
        } finally {
            $db->prepare("DELETE FROM pagos WHERE id = :id")->execute(['id' => $pagoId]);
        }
    }

    public function testRechazoPagoFlujoModel() {
        $db = \App\Core\Database::getConnection();
        $persona = $db->query("SELECT id FROM personas LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        $unidad = $db->query("SELECT id FROM unidades LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        if (!$persona || !$unidad) {
            $this->markTestSkipped("Datos insuficientes para prueba.");
            return;
        }

        $resId = intval($persona['id']);
        $uniId = intval($unidad['id']);
        $ref = 'RECHTEST' . rand(1000, 9999);
        $monto = 50.00;
        $archivo = 'recibo_rechazar.png';

        $ins = $db->prepare("
            INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, archivo, estado)
            VALUES (:residente_id, :unidad_id, :monto, '2026-09-28', 'transferencia', :referencia, :archivo, 'PENDIENTE')
        ");
        $ins->execute([
            'residente_id' => $resId,
            'unidad_id'    => $uniId,
            'monto'        => $monto,
            'referencia'   => $ref,
            'archivo'      => $archivo
        ]);
        $pagoId = intval($db->lastInsertId());

        try {
            $pagoModel = new \App\Models\PagoModel();
            $motivo = "El comprobante no corresponde al titular ni a la referencia bancaria declarada.";
            $resultado = $pagoModel->cambiarEstado($pagoId, \App\Core\EstadoPago::RECHAZADO, $motivo, 1);
            $this->assertTrue(!empty($resultado['ok']));
            $this->assertEquals('actualizado', $resultado['code']);

            $pagoActual = $pagoModel->obtenerPagoPorId($pagoId);
            $this->assertEquals('RECHAZADO', $pagoActual['estado']);
        } finally {
            $db->prepare("DELETE FROM log_auditoria WHERE pago_id = :id")->execute(['id' => $pagoId]);
            $db->prepare("DELETE FROM pagos WHERE id = :id")->execute(['id' => $pagoId]);
        }
    }
}

