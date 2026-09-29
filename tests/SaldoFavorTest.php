<?php
namespace Tests;

use App\Core\Database;
use App\Models\FacturasModel;
use App\Models\MovimientosModel;
use App\Models\PagoModel;
use App\Services\LiquidacionPagoService;
use PDO;

class SaldoFavorTest extends TestCase {

    public function testClassesAndMethodsExist(): void {
        $this->assertTrue(class_exists(LiquidacionPagoService::class), "LiquidacionPagoService debe existir");
        $this->assertTrue(method_exists(LiquidacionPagoService::class, 'aplicarPagoAUnidad'), "aplicarPagoAUnidad debe existir");
        $this->assertTrue(method_exists(FacturasModel::class, 'getSaldoFavorByUnidad'), "getSaldoFavorByUnidad debe existir");
        $this->assertTrue(method_exists(FacturasModel::class, 'getResumenFinancieroUnidad'), "getResumenFinancieroUnidad debe existir");
        $this->assertTrue(method_exists(FacturasModel::class, 'crearFacturasMasivas'), "crearFacturasMasivas debe existir");
    }

    public function testSobrepagoGeneraSaldoAFavorYCancelaFactura(): void {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // 1. Crear unidad temporal
            $stmtU = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:num, 100.00, 1)");
            $numUnidad = 'TEST-SF-' . mt_rand(1000, 9999);
            $stmtU->execute(['num' => $numUnidad]);
            $unidadId = intval($db->lastInsertId());

            // 2. Crear factura pendiente de 80.00
            $numFactura = 'FAC-TEST-' . mt_rand(1000, 9999);
            $stmtF = $db->prepare("
                INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado)
                VALUES (:num, :uid, 9, 2026, CURDATE(), CURDATE(), 80.00, 0.00, 80.00, 'pendiente')
            ");
            $stmtF->execute(['num' => $numFactura, 'uid' => $unidadId]);
            $facturaId = intval($db->lastInsertId());

            // 3. Aplicar sobrepago de 120.00 (debe pagar los 80.00 y generar 40.00 de saldo a favor)
            $service = new LiquidacionPagoService();
            $resumen = $service->aplicarPagoAUnidad($db, $unidadId, 120.00, 'Test Sobrepago');

            $this->assertEquals(80.00, $resumen['total_aplicado_deuda'], "Debe aplicar 80.00 a la deuda");
            $this->assertEquals(40.00, $resumen['saldo_a_favor_generado'], "Debe generar 40.00 de saldo a favor");

            // 4. Verificar estado de la factura original
            $stmtCheckF = $db->prepare("SELECT saldo, monto_pagado, estado FROM facturas WHERE id = :id");
            $stmtCheckF->execute(['id' => $facturaId]);
            $fActual = $stmtCheckF->fetch(PDO::FETCH_ASSOC);

            $this->assertEquals(0.00, floatval($fActual['saldo']), "El saldo de la factura debe ser 0.00");
            $this->assertEquals(80.00, floatval($fActual['monto_pagado']), "El monto_pagado debe ser 80.00");
            $this->assertEquals('pagada', $fActual['estado'], "El estado de la factura debe ser 'pagada'");

            // 5. Verificar resumen financiero consolidado
            $facturasModel = new FacturasModel();
            $resumenFinanciero = $facturasModel->getResumenFinancieroUnidad($unidadId);
            $this->assertEquals(0.00, $resumenFinanciero['total_deuda'], "La deuda debe ser 0.00");
            $this->assertEquals(40.00, $resumenFinanciero['saldo_favor'], "El saldo a favor debe ser 40.00");

            // 6. Verificar libro mayor (movimientos_cuenta)
            $movModel = new MovimientosModel();
            $saldoMayor = $movModel->obtenerSaldoActualUnidad($unidadId);
            $this->assertEquals(-120.00, $saldoMayor, "El libro mayor debe reflejar el abono total");
        } finally {
            $db->rollBack();
        }
    }

    public function testPagoAnticipadoSinDeudaGeneraSaldoAFavor(): void {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // 1. Crear unidad temporal sin facturas
            $stmtU = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:num, 150.00, 1)");
            $numUnidad = 'TEST-PREP-' . mt_rand(1000, 9999);
            $stmtU->execute(['num' => $numUnidad]);
            $unidadId = intval($db->lastInsertId());

            // 2. Aplicar pago anticipado de 200.00
            $service = new LiquidacionPagoService();
            $resumen = $service->aplicarPagoAUnidad($db, $unidadId, 200.00, 'Test Pago Anticipado');

            $this->assertEquals(0.00, $resumen['total_aplicado_deuda'], "No había deuda pendiente");
            $this->assertEquals(200.00, $resumen['saldo_a_favor_generado'], "Los 200.00 deben ser saldo a favor");

            // 3. Verificar en FacturasModel
            $facturasModel = new FacturasModel();
            $saldoFavor = $facturasModel->getSaldoFavorByUnidad($unidadId);
            $this->assertEquals(200.00, $saldoFavor, "El saldo a favor consultado debe ser exactamente 200.00");

            $deuda = $facturasModel->getTotalDeudaByUnidad($unidadId);
            $this->assertEquals(0.00, $deuda, "La deuda total debe ser 0.00");
        } finally {
            $db->rollBack();
        }
    }

    public function testPagoEnCascadaMultiplesFacturasConRemanente(): void {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // 1. Crear unidad temporal
            $stmtU = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:num, 100.00, 1)");
            $numUnidad = 'TEST-CASC-' . mt_rand(1000, 9999);
            $stmtU->execute(['num' => $numUnidad]);
            $unidadId = intval($db->lastInsertId());

            // 2. Factura 1 (50.00, mes 7)
            $stmtF1 = $db->prepare("
                INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado)
                VALUES (:num, :uid, 7, 2026, '2026-07-01', '2026-07-15', 50.00, 0.00, 50.00, 'pendiente')
            ");
            $stmtF1->execute(['num' => 'FAC-CASC-1-' . mt_rand(1000, 9999), 'uid' => $unidadId]);

            // 3. Factura 2 (30.00, mes 8)
            $stmtF2 = $db->prepare("
                INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado)
                VALUES (:num, :uid, 8, 2026, '2026-08-01', '2026-08-15', 30.00, 0.00, 30.00, 'pendiente')
            ");
            $stmtF2->execute(['num' => 'FAC-CASC-2-' . mt_rand(1000, 9999), 'uid' => $unidadId]);

            // 4. Pago de 100.00 (debe pagar 50 + 30 = 80, dejando 20 de saldo a favor)
            $service = new LiquidacionPagoService();
            $resumen = $service->aplicarPagoAUnidad($db, $unidadId, 100.00, 'Test Cascada');

            $this->assertEquals(80.00, $resumen['total_aplicado_deuda'], "Total aplicado debe ser 80.00");
            $this->assertEquals(20.00, $resumen['saldo_a_favor_generado'], "Saldo a favor generado debe ser 20.00");
            $this->assertEquals(2, count($resumen['facturas_afectadas']), "Debe afectar exactamente 2 facturas");

            $facturasModel = new FacturasModel();
            $this->assertEquals(0.00, $facturasModel->getTotalDeudaByUnidad($unidadId), "Deuda debe ser 0.00");
            $this->assertEquals(20.00, $facturasModel->getSaldoFavorByUnidad($unidadId), "Saldo a favor debe ser 20.00");
        } finally {
            $db->rollBack();
        }
    }

    public function testFacturacionMasivaNoDestruyeSaldoFavorRemanente(): void {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // 1. Crear unidad con cuota mensual de 60.00
            $stmtU = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:num, 60.00, 1)");
            $numUnidad = 'TEST-MASIV-' . mt_rand(1000, 9999);
            $stmtU->execute(['num' => $numUnidad]);
            $unidadId = intval($db->lastInsertId());

            // 2. Establecer saldo a favor inicial de 100.00 (mediante abono)
            $service = new LiquidacionPagoService();
            $service->aplicarPagoAUnidad($db, $unidadId, 100.00, 'Abono previo de 100');

            $facturasModel = new FacturasModel();
            $this->assertEquals(100.00, $facturasModel->getSaldoFavorByUnidad($unidadId), "Saldo a favor inicial debe ser 100.00");

            // 3. Ejecutar facturación masiva para el mes 11 (período sin facturas previas)
            $unidades = [
                ['id' => $unidadId, 'cuota_mensual' => 60.00]
            ];
            $stats = $facturasModel->crearFacturasMasivas($unidades, 11, 2026);

            $this->assertTrue(is_array($stats), "crearFacturasMasivas debe retornar array de estadísticas");
            $this->assertEquals(1, $stats['generadas'], "Debe generar 1 factura");
            $this->assertEquals(1, $stats['con_saldo_favor'], "Debe contabilizar 1 unidad con saldo a favor");
            $this->assertEquals(60.00, $stats['total_saldo_favor_usado'], "Debe consumir 60.00 de saldo a favor");

            // 4. VERIFICACIÓN CRUCIAL: El remanente de 40.00 NO debe desaparecer
            $saldoFavorRestante = $facturasModel->getSaldoFavorByUnidad($unidadId);
            $this->assertEquals(40.00, $saldoFavorRestante, "El saldo a favor remanente (100 - 60) debe conservarse en 40.00");

            $deudaRestante = $facturasModel->getTotalDeudaByUnidad($unidadId);
            $this->assertEquals(0.00, $deudaRestante, "La factura nueva nació pagada, la deuda debe ser 0.00");
        } finally {
            $db->rollBack();
        }
    }
}
