<?php
namespace App\Services;

use PDO;
use App\Models\MovimientosModel;

/**
 * Servicio contable para la liquidación en cascada de pagos contra facturas pendientes
 * y la acreditación inmutable de saldos a favor (por sobrepagos o pagos anticipados).
 */
class LiquidacionPagoService {

    /**
     * Aplica un pago a las facturas pendientes de una unidad en orden cronológico (cascada)
     * y acredita cualquier excedente como saldo a favor en facturas y en movimientos_cuenta.
     *
     * Debe invocarse dentro de una transacción PDO activa o manejará una si no existe.
     *
     * @param PDO $db Conexión PDO activa
     * @param int $unidadId ID de la unidad habitacional
     * @param float $montoTotal Monto total del pago a procesar
     * @param string $concepto Concepto o referencia descriptiva del pago
     * @param int|null $referenciaId ID del pago o comprobante de origen
     * @return array Resumen ['total_aplicado_deuda' => float, 'saldo_a_favor_generado' => float, 'facturas_afectadas' => array]
     */
    public function aplicarPagoAUnidad(PDO $db, int $unidadId, float $montoTotal, string $concepto = '', ?int $referenciaId = null): array {
        $montoTotal = round(floatval($montoTotal), 2);
        if ($montoTotal <= 0.00 || $unidadId <= 0) {
            return [
                'total_aplicado_deuda'   => 0.00,
                'saldo_a_favor_generado' => 0.00,
                'facturas_afectadas'     => []
            ];
        }

        $montoRestante = $montoTotal;
        $totalAplicadoDeuda = 0.00;
        $saldoAFavorGenerado = 0.00;
        $facturasAfectadas = [];

        // 1. Obtener todas las facturas pendientes de la unidad ordenadas cronológicamente con bloqueo pesimista
        $stmtPendientes = $db->prepare("
            SELECT id, numero_factura, saldo, monto_total, monto_pagado 
            FROM facturas 
            WHERE unidad_id = :uid AND estado = 'pendiente' AND deleted_at IS NULL 
            ORDER BY anio ASC, mes ASC, fecha_vencimiento ASC, id ASC 
            FOR UPDATE
        ");
        $stmtPendientes->execute(['uid' => $unidadId]);
        $facturasPendientes = $stmtPendientes->fetchAll(PDO::FETCH_ASSOC);

        $stmtUpdateFactura = $db->prepare("
            UPDATE facturas 
            SET saldo = :saldo, monto_pagado = :monto_pagado, estado = :estado 
            WHERE id = :id
        ");

        // 2. Liquidación en cascada sobre las facturas pendientes
        foreach ($facturasPendientes as $f) {
            if ($montoRestante <= 0.00) {
                break;
            }

            $saldoFactura = round(floatval($f['saldo']), 2);
            if ($saldoFactura <= 0.00) {
                continue;
            }

            $abono = round(min($montoRestante, $saldoFactura), 2);
            $nuevoSaldo = round($saldoFactura - $abono, 2);
            $nuevoMontoPagado = round(floatval($f['monto_pagado']) + $abono, 2);
            $nuevoEstado = ($nuevoSaldo <= 0.00) ? 'pagada' : 'pendiente';

            $stmtUpdateFactura->execute([
                'saldo'        => $nuevoSaldo,
                'monto_pagado' => $nuevoMontoPagado,
                'estado'       => $nuevoEstado,
                'id'           => $f['id']
            ]);

            $facturasAfectadas[] = [
                'id'             => $f['id'],
                'numero_factura' => $f['numero_factura'],
                'monto_aplicado' => $abono,
                'saldo_restante' => $nuevoSaldo,
                'estado'         => $nuevoEstado
            ];

            $totalAplicadoDeuda = round($totalAplicadoDeuda + $abono, 2);
            $montoRestante = round($montoRestante - $abono, 2);
        }

        // 3. Si existe excedente o se trata de un pago anticipado (sin facturas pendientes)
        if ($montoRestante > 0.009) {
            $saldoAFavorGenerado = $montoRestante;
            $numAbono = 'ABONO-' . date('Ym') . '-' . str_pad((string)$unidadId, 4, '0', STR_PAD_LEFT) . '-' . time() . '-' . mt_rand(100, 999);

            $stmtFavor = $db->prepare("
                INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado, observaciones)
                VALUES (:num, :uid, :mes, :anio, CURDATE(), CURDATE(), 0.00, :pagado, :saldo, 'pagada', :obs)
            ");
            $stmtFavor->execute([
                'num'    => $numAbono,
                'uid'    => $unidadId,
                'mes'    => intval(date('n')),
                'anio'   => intval(date('Y')),
                'pagado' => $saldoAFavorGenerado,
                'saldo'  => -$saldoAFavorGenerado,
                'obs'    => 'Saldo a favor generado por excedente o pago anticipado. Ref: ' . $concepto
            ]);
        }

        // 4. Registro inmutable en el libro mayor (movimientos_cuenta)
        $movModel = new MovimientosModel();
        $descripcion = "Abono por pago" . (!empty($concepto) ? " - {$concepto}" : "");
        if ($saldoAFavorGenerado > 0.009) {
            $descripcion .= " (Saldo a favor generado: " . formatearMoneda($saldoAFavorGenerado) . ")";
        }

        $movModel->registrarMovimiento(
            $unidadId,
            'abono_pago',
            $montoTotal,
            $descripcion,
            $referenciaId
        );

        return [
            'total_aplicado_deuda'   => $totalAplicadoDeuda,
            'saldo_a_favor_generado' => $saldoAFavorGenerado,
            'facturas_afectadas'     => $facturasAfectadas
        ];
    }
}
