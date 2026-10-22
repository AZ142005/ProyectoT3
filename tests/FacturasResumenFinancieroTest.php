<?php
namespace Tests;

use App\Core\Database;
use App\Models\FacturasModel;

/**
 * Semántica de FacturasModel::getResumenFinancieroUnidad(): la deuda pendiente
 * suma solo los saldos positivos y el saldo a favor se reporta por separado
 * sin netear la deuda. Esta cifra alimenta los paneles de deuda de las vistas
 * de pago, por lo que debe mantenerse estable.
 *
 * Nota histórica: esta prueba nació junto con la regla «monto >= deuda» al
 * reportar un pago. Esa regla fue retirada (los pagos parciales vuelven a estar
 * permitidos); se conserva únicamente la verificación de datos, que sigue
 * siendo relevante para las vistas.
 */
class FacturasResumenFinancieroTest extends TestCase {

    public function testTotalDeudaEsSumaSaldosPositivosSinNetearSaldoAFavor(): void {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // 1. Unidad con facturas de saldo 100, 50 y -40: la deuda solo suma los
            //    saldos positivos (150.00) y el abono se reporta como saldo a favor
            //    (40.00) sin netear la deuda.
            $stmtU = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:num, 100.00, 1)");
            $stmtU->execute(['num' => 'TEST-DEUDA-' . mt_rand(1000, 9999)]);
            $unidadId = intval($db->lastInsertId());

            $stmtF = $db->prepare("
                INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado)
                VALUES (:num, :uid, 9, 2026, '2026-09-01', '2026-09-15', :total, :pagado, :saldo, :estado)
            ");
            $stmtF->execute(['num' => 'FAC-TEST-DEUDA-1-' . mt_rand(1000, 9999), 'uid' => $unidadId, 'total' => 100.00, 'pagado' => 0.00, 'saldo' => 100.00, 'estado' => 'pendiente']);
            $stmtF->execute(['num' => 'FAC-TEST-DEUDA-2-' . mt_rand(1000, 9999), 'uid' => $unidadId, 'total' => 50.00, 'pagado' => 0.00, 'saldo' => 50.00, 'estado' => 'pendiente']);
            $stmtF->execute(['num' => 'ABONO-TEST-DEUDA-' . mt_rand(1000, 9999), 'uid' => $unidadId, 'total' => 0.00, 'pagado' => 40.00, 'saldo' => -40.00, 'estado' => 'pagada']);

            $facturasModel = new FacturasModel();
            $resumen = $facturasModel->getResumenFinancieroUnidad($unidadId);

            $this->assertEquals(150.00, $resumen['total_deuda'], 'La deuda debe sumar solo los saldos positivos (100 + 50)');
            $this->assertEquals(40.00, $resumen['saldo_favor'], 'El saldo a favor (40) debe reportarse por separado, sin netear la deuda');

            // 2. Unidad sin saldos positivos: la deuda debe ser 0.00.
            $stmtU2 = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:num, 100.00, 1)");
            $stmtU2->execute(['num' => 'TEST-DEUDA-CERO-' . mt_rand(1000, 9999)]);
            $unidadId2 = intval($db->lastInsertId());

            $stmtF->execute(['num' => 'ABONO-TEST-CERO-' . mt_rand(1000, 9999), 'uid' => $unidadId2, 'total' => 0.00, 'pagado' => 25.00, 'saldo' => -25.00, 'estado' => 'pagada']);

            $resumenSinDeuda = $facturasModel->getResumenFinancieroUnidad($unidadId2);

            $this->assertEquals(0.00, $resumenSinDeuda['total_deuda'], 'Una unidad sin saldos positivos no tiene deuda');
            $this->assertEquals(25.00, $resumenSinDeuda['saldo_favor'], 'El saldo a favor debe conservarse aunque no exista deuda');
        } finally {
            $db->rollBack();
        }
    }
}
