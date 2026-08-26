<?php
namespace App\Models;

use PDO;

class FacturasModel extends BaseModel {
    /**
     * Obtiene las facturas pendientes de una unidad específica.
     *
     * @param int $unidad_id
     * @return array
     */
    public function getPendientesByUnidad($unidad_id) {
        $db = $this->db();
        
        $sql = "
            SELECT f.*, 
                   (SELECT COUNT(*) FROM comprobantes_pago WHERE factura_id = f.id AND estado = 'pendiente') as tiene_pendiente
            FROM facturas f
            WHERE f.unidad_id = :unidad_id AND f.saldo > 0
            ORDER BY f.fecha_vencimiento ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['unidad_id' => $unidad_id]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Calcula la deuda total acumulada para una unidad.
     *
     * @param int $unidad_id
     * @return float
     */
    public function getTotalDeudaByUnidad($unidad_id) {
        $db = $this->db();
        
        $stmt = $db->prepare("SELECT SUM(saldo) as total FROM facturas WHERE unidad_id = :unidad_id AND saldo > 0");
        $stmt->execute(['unidad_id' => $unidad_id]);
        $row = $stmt->fetch();
        
        return floatval($row['total'] ?? 0);
    }
    
    /**
     * Calcula el saldo a favor para una unidad (saldos negativos).
     *
     * @param int $unidad_id
     * @return float
     */
    public function getSaldoFavorByUnidad($unidad_id) {
        $db = $this->db();
        
        $stmt = $db->prepare("SELECT SUM(saldo) as total FROM facturas WHERE unidad_id = :unidad_id AND saldo < 0");
        $stmt->execute(['unidad_id' => $unidad_id]);
        $row = $stmt->fetch();
        
        return floatval($row['total'] ?? 0);
    }

    /**
     * Obtiene el resumen financiero consolidado (deuda y saldo a favor) en una sola consulta optimizada.
     *
     * @param int $unidad_id
     * @return array ['total_deuda' => float, 'saldo_a_favor' => float]
     */
    public function getResumenFinancieroUnidad($unidad_id) {
        $db = $this->db();
        
        $sql = "
            SELECT 
                COALESCE(SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END), 0) as total_deuda,
                COALESCE(SUM(CASE WHEN saldo < 0 THEN ABS(saldo) ELSE 0 END), 0) as saldo_a_favor
            FROM facturas 
            WHERE unidad_id = :unidad_id
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['unidad_id' => $unidad_id]);
        $row = $stmt->fetch();
        
        return [
            'total_deuda'    => floatval($row['total_deuda'] ?? 0),
            'saldo_a_favor'  => floatval($row['saldo_a_favor'] ?? 0)
        ];
    }

    /**
     * Obtiene una factura pendiente por ID y Unidad.
     *
     * @param int $id
     * @param int $unidad_id
     * @return array|false
     */
    public function getByIdAndUnidad($id, $unidad_id) {
        $db = $this->db();
        $stmt = $db->prepare("SELECT * FROM facturas WHERE id = :id AND unidad_id = :unidad_id AND saldo > 0");
        $stmt->execute(['id' => $id, 'unidad_id' => $unidad_id]);
        return $stmt->fetch();
    }

    /**
     * Obtiene cualquier factura por ID y Unidad (sin filtro de saldo).
     *
     * @param int $id
     * @param int $unidad_id
     * @return array|false
     */
    public function getByIdAndUnidadGeneral($id, $unidad_id) {
        $db = $this->db();
        $stmt = $db->prepare("SELECT * FROM facturas WHERE id = :id AND unidad_id = :unidad_id");
        $stmt->execute(['id' => $id, 'unidad_id' => $unidad_id]);
        return $stmt->fetch();
    }

    /**
     * Cuenta el número de facturas existentes para un periodo de mes y año.
     *
     * @param int $mes
     * @param int $anio
     * @return int
     */
    public function countByPeriod($mes, $anio) {
        $db = $this->db();
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM facturas WHERE mes = :mes AND anio = :anio");
        $stmt->execute(['mes' => $mes, 'anio' => $anio]);
        $row = $stmt->fetch();
        return intval($row['total'] ?? 0);
    }

    /**
     * Genera facturas masivamente para todas las unidades activas aplicando saldo a favor.
     *
     * @param array $unidades
     * @param int $mes
     * @param int $anio
     * @return array Retorna un array con estadísticas de generación ['generadas' => X, 'con_saldo_favor' => Y, 'total_saldo_favor_usado' => Z]
     */
    public function crearFacturasMasivas($unidades, $mes, $anio) {
        $db = $this->db();
        
        $stats = [
            'generadas' => 0,
            'con_saldo_favor' => 0,
            'total_saldo_favor_usado' => 0
        ];

        try {
            $db->beginTransaction();

            // Consultas preparadas reutilizables para optimizar rendimiento
            $stmtSaldoFavor = $db->prepare("SELECT SUM(saldo) as total FROM facturas WHERE unidad_id = :unidad_id AND saldo < 0");
            $stmtUpdateSaldoFavor = $db->prepare("UPDATE facturas SET saldo = 0 WHERE unidad_id = :unidad_id AND saldo < 0");
            
            $stmtInsert = $db->prepare("
                INSERT INTO facturas 
                (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado) 
                VALUES (:numero_factura, :unidad_id, :mes, :anio, :fecha_emision, :fecha_vencimiento, :monto_total, :monto_pagado, :saldo, :estado)
            ");

            foreach ($unidades as $unidad) {
                $unidad_id = $unidad['id'];
                
                // 1. Obtener saldo a favor actual
                $stmtSaldoFavor->execute(['unidad_id' => $unidad_id]);
                $row = $stmtSaldoFavor->fetch();
                $saldo_favor = floatval($row['total'] ?? 0);

                $monto_factura = floatval($unidad['cuota_mensual']);
                $monto_a_pagar = $monto_factura;
                $saldo_restante = 0.0;
                $estado = 'pendiente';

                if ($saldo_favor < 0) {
                    $saldo_favor_abs = abs($saldo_favor);
                    $stats['con_saldo_favor']++;
                    $stats['total_saldo_favor_usado'] += min($saldo_favor_abs, $monto_factura);

                    if ($saldo_favor_abs >= $monto_factura) {
                        // El saldo a favor cubre toda la factura
                        $monto_a_pagar = 0.0;
                        $saldo_restante = 0.0;
                        $estado = 'pagada';
                        
                        // Consumir el saldo a favor poniendo en 0 las facturas anteriores
                        $stmtUpdateSaldoFavor->execute(['unidad_id' => $unidad_id]);
                    } else {
                        // El saldo a favor cubre parte de la factura
                        $monto_a_pagar = $monto_factura - $saldo_favor_abs;
                        $saldo_restante = $monto_a_pagar;
                        $estado = 'pendiente';

                        // Consumir el saldo a favor completo
                        $stmtUpdateSaldoFavor->execute(['unidad_id' => $unidad_id]);
                    }
                } else {
                    $saldo_restante = $monto_a_pagar;
                }

                // Generar código factura
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
                    'monto_pagado'      => ($monto_factura - $monto_a_pagar),
                    'saldo'             => $saldo_restante,
                    'estado'            => $estado
                ]);

                $stats['generadas']++;
            }

            $db->commit();
            return $stats;
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Error al crear facturas masivas: " . $e->getMessage());
            return false;
        }
    }
}
