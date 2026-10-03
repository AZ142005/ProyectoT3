<?php
namespace App\Models;

use App\Models\MovimientosModel;

class FacturasModel extends BaseModel {
    protected string $table = 'facturas';

    public function getPendientesByUnidad($unidad_id, ?int $pagina = null, int $porPagina = 25) {
        $baseSql = "
            SELECT f.*,
                   EXISTS(
                       SELECT 1 FROM comprobantes_pago c 
                       WHERE c.factura_id = f.id AND c.estado = 'pendiente'
                   ) as tiene_pendiente
            FROM facturas f
            WHERE f.unidad_id = :unidad_id 
              AND f.saldo > 0
              AND f.deleted_at IS NULL
        ";

        if ($pagina === null) {
            $stmt = $this->db()->prepare($baseSql . " ORDER BY f.fecha_vencimiento ASC");
            $stmt->execute(['unidad_id' => $unidad_id]);
            return $stmt->fetchAll();
        }

        $countSql = "
            SELECT COUNT(*) as total
            FROM facturas f
            WHERE f.unidad_id = :unidad_id 
              AND f.saldo > 0
              AND f.deleted_at IS NULL
        ";
        return $this->paginate($baseSql, $countSql, ['unidad_id' => $unidad_id], $pagina, $porPagina, 'f.fecha_vencimiento ASC');
    }

    public function getTotalDeudaByUnidad($unidad_id): float {
        $stmt = $this->db()->prepare("SELECT ROUND(COALESCE(SUM(saldo), 0), 2) as total FROM facturas WHERE unidad_id = :unidad_id AND saldo > 0 AND deleted_at IS NULL");
        $stmt->execute(['unidad_id' => $unidad_id]);
        return round(floatval($stmt->fetch()['total'] ?? 0), 2);
    }

    public function getSaldoFavorByUnidad($unidad_id): float {
        $stmt = $this->db()->prepare("SELECT ROUND(COALESCE(ABS(SUM(saldo)), 0), 2) as total FROM facturas WHERE unidad_id = :unidad_id AND saldo < 0 AND deleted_at IS NULL");
        $stmt->execute(['unidad_id' => $unidad_id]);
        return round(floatval($stmt->fetch()['total'] ?? 0), 2);
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
                ROUND(COALESCE(SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END), 0), 2) AS total_deuda,
                ROUND(COALESCE(ABS(SUM(CASE WHEN saldo < 0 THEN saldo ELSE 0 END)), 0), 2) AS saldo_favor
            FROM facturas
            WHERE unidad_id = :unidad_id AND deleted_at IS NULL
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['unidad_id' => $unidad_id]);
        $row = $stmt->fetch();

        return [
            'total_deuda' => round(floatval($row['total_deuda'] ?? 0), 2),
            'saldo_favor' => round(floatval($row['saldo_favor'] ?? 0), 2),
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

        $iniciaTransaccion = !$db->inTransaction();
        try {
            if ($iniciaTransaccion) {
                $db->beginTransaction();
            }

            // Prevenir ejecución duplicada: si ya existen facturas para este período, abortar
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM facturas WHERE mes = :mes AND anio = :anio AND deleted_at IS NULL FOR UPDATE");
            $stmtCheck->execute(['mes' => $mes, 'anio' => $anio]);
            $existentes = intval($stmtCheck->fetch()['total'] ?? 0);
            if ($existentes > 0) {
                if ($iniciaTransaccion) {
                    $db->rollBack();
                }
                return false;
            }

            $stmtSaldoFavor = $db->prepare("SELECT SUM(saldo) as total FROM facturas WHERE unidad_id = :unidad_id AND saldo < 0 FOR UPDATE");
            $stmtUpdateSaldoFavor = $db->prepare("UPDATE facturas SET saldo = 0 WHERE unidad_id = :unidad_id AND saldo < 0");
            
            $stmtInsert = $db->prepare("
                INSERT INTO facturas 
                (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado) 
                VALUES (:numero_factura, :unidad_id, :mes, :anio, :fecha_emision, :fecha_vencimiento, :monto_total, :monto_pagado, :saldo, :estado)
            ");

            $stmtDup = $db->prepare("SELECT id FROM facturas WHERE unidad_id = :unidad_id AND mes = :mes AND anio = :anio AND deleted_at IS NULL LIMIT 1 FOR UPDATE");

            // Calcular distribución dinámica de cuotas a partir de los gastos declarados del período
            $gastosModel = new \App\Models\GastosModel();
            $distribucion = $gastosModel->calcularDistribucionCuotas($mes, $anio);
            $distribucionUnidades = $distribucion['unidades'] ?? [];

            foreach ($unidades as $unidad) {
                $unidad_id = intval($unidad['id']);
                
                // Doble verificación por unidad: prevenir duplicados si se ejecuta concurrentemente
                $stmtDup->execute(['unidad_id' => $unidad_id, 'mes' => $mes, 'anio' => $anio]);
                if ($stmtDup->fetch()) {
                    continue;
                }

                $stmtSaldoFavor->execute(['unidad_id' => $unidad_id]);
                $row = $stmtSaldoFavor->fetch();
                $saldo_favor = round(floatval($row['total'] ?? 0), 2);

                // Cuota calculada dinámicamente: Fracción Global + Fracción Edificio
                if (isset($distribucionUnidades[$unidad_id]) && $distribucionUnidades[$unidad_id]['cuota_total'] > 0) {
                    $monto_factura = round(floatval($distribucionUnidades[$unidad_id]['cuota_total']), 2);
                } else {
                    // Fallback para entornos de prueba con cuotas preestablecidas
                    $monto_factura = round(floatval($unidad['cuota_mensual'] ?? 0), 2);
                }

                if ($monto_factura <= 0) {
                    error_log("[FACTURA] Skipping unidad {$unidad_id}: cuota calculada={$monto_factura} (<= 0)");
                    continue;
                }
                if ($monto_factura > 999999.99) {
                    error_log("[FACTURA] Skipping unidad {$unidad_id}: cuota={$monto_factura} (exceeds max)");
                    continue;
                }
                $monto_a_pagar = $monto_factura;
                $saldo_restante = 0.0;
                $estado = 'pendiente';
                $remanenteSaldoFavor = 0.0;

                if ($saldo_favor < 0) {
                    $saldo_favor_abs = abs($saldo_favor);
                    $stats['con_saldo_favor']++;
                    $stats['total_saldo_favor_usado'] += round(min($saldo_favor_abs, $monto_factura), 2);

                    if ($saldo_favor_abs >= $monto_factura) {
                        $monto_a_pagar = 0.0;
                        $saldo_restante = 0.0;
                        $estado = 'pagada';
                        $remanenteSaldoFavor = round($saldo_favor_abs - $monto_factura, 2);
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

                // Si quedó remanente de saldo a favor tras cubrir la cuota, persistirlo para no perder el crédito
                if ($remanenteSaldoFavor > 0.009) {
                    $numAbonoRem = 'ABONO-REM-' . $anio . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$unidad_id, 4, '0', STR_PAD_LEFT) . '-' . time() . '-' . mt_rand(100, 999);
                    $stmtInsert->execute([
                        'numero_factura'    => $numAbonoRem,
                        'unidad_id'         => $unidad_id,
                        'mes'               => $mes,
                        'anio'              => $anio,
                        'fecha_emision'     => $fecha_emision,
                        'fecha_vencimiento' => $fecha_vencimiento,
                        'monto_total'       => 0.00,
                        'monto_pagado'      => $remanenteSaldoFavor,
                        'saldo'             => -$remanenteSaldoFavor,
                        'estado'            => 'pagada'
                    ]);
                }

                // Registrar cargo de la cuota en el libro mayor de la unidad
                $movModel = new MovimientosModel();
                $movModel->registrarMovimiento(
                    $unidad_id,
                    'cargo_factura',
                    $monto_factura,
                    "Emisión cuota de mantenimiento " . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . "/{$anio} - Factura {$numero_factura}"
                );

                $stats['generadas']++;
            }

            if ($iniciaTransaccion) {
                $db->commit();
            }
            return $stats;
        } catch (\Exception $e) {
            if ($iniciaTransaccion && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al crear facturas masivas: " . $e->getMessage());
            return false;
        }
    }
}
