<?php
namespace App\Models;

class FacturasModel extends BaseModel {
    protected string $table = 'facturas';

    public function getPendientesByUnidad($unidad_id) {
        $sql = "
            SELECT f.*,
                   EXISTS(
                       SELECT 1 FROM comprobantes_pago c 
                       WHERE c.factura_id = f.id AND c.estado = 'pendiente'
                   ) as tiene_pendiente
            FROM facturas f
            WHERE f.unidad_id = :unidad_id 
              AND f.saldo > 0
              AND f.deleted_at IS NULL
            ORDER BY f.fecha_vencimiento ASC
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['unidad_id' => $unidad_id]);
        return $stmt->fetchAll();
    }

    public function getTotalDeudaByUnidad($unidad_id): float {
        $stmt = $this->db()->prepare("SELECT SUM(saldo) as total FROM facturas WHERE unidad_id = :unidad_id AND saldo > 0 AND deleted_at IS NULL");
        $stmt->execute(['unidad_id' => $unidad_id]);
        return floatval($stmt->fetch()['total'] ?? 0);
    }

    public function getSaldoFavorByUnidad($unidad_id): float {
        $stmt = $this->db()->prepare("SELECT SUM(saldo) as total FROM facturas WHERE unidad_id = :unidad_id AND saldo < 0 AND deleted_at IS NULL");
        $stmt->execute(['unidad_id' => $unidad_id]);
        return abs(floatval($stmt->fetch()['total'] ?? 0));
    }

    /**
     * Obtiene el resumen financiero consolidado de una unidad habitacional
     * (deuda pendiente y saldo a favor) en una sola consulta optimizada.
     *
     * @param int $unidad_id
     * @return array ['total_deuda' => float, 'saldo_favor' => float]
     */
    public function getResumenFinancieroUnidad(int $unidad_id): array {
        $sql = "
            SELECT 
                COALESCE(SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END), 0) AS total_deuda,
                COALESCE(ABS(SUM(CASE WHEN saldo < 0 THEN saldo ELSE 0 END)), 0) AS saldo_favor
            FROM facturas
            WHERE unidad_id = :unidad_id AND deleted_at IS NULL
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['unidad_id' => $unidad_id]);
        $row = $stmt->fetch();

        return [
            'total_deuda' => floatval($row['total_deuda'] ?? 0),
            'saldo_favor' => floatval($row['saldo_favor'] ?? 0),
        ];
    }

    public function getByIdAndUnidad($id, $unidad_id) {
        $stmt = $this->db()->prepare("SELECT * FROM facturas WHERE id = :id AND unidad_id = :unidad_id AND saldo > 0 AND deleted_at IS NULL");
        $stmt->execute(['id' => $id, 'unidad_id' => $unidad_id]);
        return $stmt->fetch();
    }

    public function getByIdAndUnidadGeneral($id, $unidad_id) {
        $stmt = $this->db()->prepare("SELECT * FROM facturas WHERE id = :id AND unidad_id = :unidad_id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id, 'unidad_id' => $unidad_id]);
        return $stmt->fetch();
    }

    public function countByPeriod($mes, $anio): int {
        $stmt = $this->db()->prepare("SELECT COUNT(*) as total FROM facturas WHERE mes = :mes AND anio = :anio AND deleted_at IS NULL");
        $stmt->execute(['mes' => $mes, 'anio' => $anio]);
        return intval($stmt->fetch()['total'] ?? 0);
    }

    public function crearFacturasMasivas($unidades, $mes, $anio) {
        $db = $this->db();
        $stats = [
            'generadas' => 0,
            'con_saldo_favor' => 0,
            'total_saldo_favor_usado' => 0
        ];

        try {
            $db->beginTransaction();

            // Prevenir ejecución duplicada: si ya existen facturas para este período, abortar
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM facturas WHERE mes = :mes AND anio = :anio AND deleted_at IS NULL");
            $stmtCheck->execute(['mes' => $mes, 'anio' => $anio]);
            $existentes = intval($stmtCheck->fetch()['total'] ?? 0);
            if ($existentes > 0) {
                $db->rollBack();
                return false;
            }

            $stmtSaldoFavor = $db->prepare("SELECT SUM(saldo) as total FROM facturas WHERE unidad_id = :unidad_id AND saldo < 0 FOR UPDATE");
            $stmtUpdateSaldoFavor = $db->prepare("UPDATE facturas SET saldo = 0 WHERE unidad_id = :unidad_id AND saldo < 0");
            
            $stmtInsert = $db->prepare("
                INSERT INTO facturas 
                (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado) 
                VALUES (:numero_factura, :unidad_id, :mes, :anio, :fecha_emision, :fecha_vencimiento, :monto_total, :monto_pagado, :saldo, :estado)
            ");

            $stmtDup = $db->prepare("SELECT id FROM facturas WHERE unidad_id = :unidad_id AND mes = :mes AND anio = :anio AND deleted_at IS NULL LIMIT 1");

            foreach ($unidades as $unidad) {
                $unidad_id = $unidad['id'];
                
                // Doble verificación por unidad: prevenir duplicados si se ejecuta concurrentemente
                $stmtDup->execute(['unidad_id' => $unidad_id, 'mes' => $mes, 'anio' => $anio]);
                if ($stmtDup->fetch()) {
                    continue;
                }

                $stmtSaldoFavor->execute(['unidad_id' => $unidad_id]);
                $row = $stmtSaldoFavor->fetch();
                $saldo_favor = round(floatval($row['total'] ?? 0), 2);

                $monto_factura = round(floatval($unidad['cuota_mensual']), 2);
                $monto_a_pagar = $monto_factura;
                $saldo_restante = 0.0;
                $estado = 'pendiente';

                if ($saldo_favor < 0) {
                    $saldo_favor_abs = abs($saldo_favor);
                    $stats['con_saldo_favor']++;
                    $stats['total_saldo_favor_usado'] += round(min($saldo_favor_abs, $monto_factura), 2);

                    if ($saldo_favor_abs >= $monto_factura) {
                        $monto_a_pagar = 0.0;
                        $saldo_restante = 0.0;
                        $estado = 'pagada';
                        $stmtUpdateSaldoFavor->execute(['unidad_id' => $unidad_id]);
                    } else {
                        $monto_a_pagar = round($monto_factura - $saldo_favor_abs, 2);
                        $saldo_restante = $monto_a_pagar;
                        $estado = 'pendiente';
                        $stmtUpdateSaldoFavor->execute(['unidad_id' => $unidad_id]);
                    }
                } else {
                    $saldo_restante = $monto_a_pagar;
                }

                $numero_factura = 'FAC-' . $anio . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-' . str_pad($unidad_id, 4, '0', STR_PAD_LEFT);
                $fecha_emision = date('Y-m-d');
                $fecha_vencimiento = date('Y-m-d', strtotime('+15 days'));

                $stmtInsert->execute([
                    'numero_factura'    => $numero_factura,
                    'unidad_id'         => $unidad_id,
                    'mes'               => $mes,
                    'anio'              => $anio,
                    'fecha_emision'     => $fecha_emision,
                    'fecha_vencimiento' => $fecha_vencimiento,
                    'monto_total'       => $monto_factura,
                    'monto_pagado'      => round($monto_factura - $monto_a_pagar, 2),
                    'saldo'             => $saldo_restante,
                    'estado'            => $estado
                ]);

                $stats['generadas']++;
            }

            $db->commit();
            return $stats;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al crear facturas masivas: " . $e->getMessage());
            return false;
        }
    }
}
