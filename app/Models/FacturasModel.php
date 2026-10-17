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

    /**
     * Cuenta las deudas emitidas (FAC-…) activas de un período.
     *
     * Las deudas de cartelera (CART-…) y los remanentes de saldo a favor
     * (ABONO-REM-…) no marcan el período como emitido: este conteo es el que
     * alimenta "Deudas Creadas" y el estado Enviar/Re-enviar de la vista.
     */
    public function countByPeriod($mes, $anio): int {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*) as total
            FROM facturas
            WHERE mes = :mes AND anio = :anio
              AND numero_factura LIKE 'FAC-%'
              AND deleted_at IS NULL
        ");
        $stmt->execute(['mes' => $mes, 'anio' => $anio]);
        return intval($stmt->fetch()['total'] ?? 0);
    }

    /**
     * Emite (o re-emite) las deudas del período con upsert por numero_factura.
     *
     * Reglas:
     * - Deuda manual: si la unidad tiene monto manual en el período, sustituye su
     *   cuota prorrateada y se aplica completo (sin repartir). Sin deuda manual se
     *   usa la distribución dinámica con fallback a cuota_mensual; cuota <= 0 => sin factura.
     * - Upsert por `FAC-{anio}-{mes}-{unidad}`: las filas activas se actualizan
     *   conservando `monto_pagado` y fechas; las soft-deleted se reactivan; si no
     *   existe se inserta con la lógica actual de saldo a favor (solo en la creación).
     *   Las deudas CART-… no participan ni se tocan, y no bloquean la emisión.
     * - Libro mayor: `cargo_factura` idempotente al crear; `ajuste` por delta
     *   (nuevo − anterior) al actualizar/reactivar, sin inflar el ledger.
     *
     * @param array $unidades Filas con id y cuota_mensual (UnidadesModel::getActivas)
     * @param int   $mes
     * @param int   $anio
     * @return array{generadas: int, actualizadas: int, reactivadas: int, con_saldo_favor: int, total_saldo_favor_usado: float}|false
     */
    public function crearFacturasMasivas($unidades, $mes, $anio) {
        $db = $this->db();
        $stats = [
            'generadas' => 0,
            'actualizadas' => 0,
            'reactivadas' => 0,
            'con_saldo_favor' => 0,
            'total_saldo_favor_usado' => 0
        ];

        $iniciaTransaccion = !$db->inTransaction();
        try {
            if ($iniciaTransaccion) {
                $db->beginTransaction();
            }

            $stmtSaldoFavor = $db->prepare("SELECT SUM(saldo) as total FROM facturas WHERE unidad_id = :unidad_id AND saldo < 0 FOR UPDATE");
            $stmtUpdateSaldoFavor = $db->prepare("UPDATE facturas SET saldo = 0 WHERE unidad_id = :unidad_id AND saldo < 0");

            // Upsert por número de factura FAC-…: la búsqueda genérica por período
            // (que confundía las CART-…) queda descartada; FOR UPDATE serializa
            // emisiones concurrentes sobre el mismo número.
            $stmtFacturaBuscar = $db->prepare("SELECT id, deleted_at, monto_total, monto_pagado FROM facturas WHERE numero_factura = :numero_factura LIMIT 1 FOR UPDATE");
            $stmtFacturaActualizar = $db->prepare("UPDATE facturas SET monto_total = :monto_total, saldo = :saldo, estado = :estado WHERE id = :id");
            $stmtFacturaReactivar = $db->prepare("UPDATE facturas SET deleted_at = NULL, monto_total = :monto_total, saldo = :saldo, estado = :estado WHERE id = :id");

            $stmtInsert = $db->prepare("
                INSERT INTO facturas 
                (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado) 
                VALUES (:numero_factura, :unidad_id, :mes, :anio, :fecha_emision, :fecha_vencimiento, :monto_total, :monto_pagado, :saldo, :estado)
            ");

            // Calcular distribución dinámica de cuotas a partir de los gastos declarados del período
            $gastosModel = new \App\Models\GastosModel();
            $distribucion = $gastosModel->calcularDistribucionCuotas($mes, $anio);
            $distribucionUnidades = $distribucion['unidades'] ?? [];

            // Deuda manual del período (unidad_id => monto): sustituye la cuota de esa unidad
            $deudasManuales = (new DeudasManualesModel())->obtenerMapaPorPeriodo($mes, $anio);

            $movModel = new MovimientosModel($db);
            $fecha_emision = date('Y-m-d');
            $fecha_vencimiento = date('Y-m-d', strtotime('+15 days'));

            foreach ($unidades as $unidad) {
                $unidad_id = intval($unidad['id']);
                $numero_factura = 'FAC-' . $anio . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-' . str_pad($unidad_id, 4, '0', STR_PAD_LEFT);

                // Monto de la factura: deuda manual (completa) o cuota prorrateada del período
                if (array_key_exists($unidad_id, $deudasManuales)) {
                    $monto_factura = round(floatval($deudasManuales[$unidad_id]), 2);
                } elseif (isset($distribucionUnidades[$unidad_id]) && $distribucionUnidades[$unidad_id]['cuota_total'] > 0) {
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

                $stmtFacturaBuscar->execute(['numero_factura' => $numero_factura]);
                $existente = $stmtFacturaBuscar->fetch();

                if ($existente !== false) {
                    // Ya existe (activa o soft-deleted): actualizar/reactivar conservando lo pagado
                    $monto_anterior = round(floatval($existente['monto_total']), 2);
                    $monto_pagado = round(floatval($existente['monto_pagado']), 2);
                    $saldo_restante = round(max(0, $monto_factura - $monto_pagado), 2);
                    $estado = $saldo_restante > 0 ? 'pendiente' : 'pagada';

                    if ($existente['deleted_at'] !== null) {
                        $stmtFacturaReactivar->execute([
                            'monto_total' => $monto_factura,
                            'saldo'       => $saldo_restante,
                            'estado'      => $estado,
                            'id'          => (int)$existente['id'],
                        ]);
                        $stats['reactivadas']++;
                    } else {
                        $stmtFacturaActualizar->execute([
                            'monto_total' => $monto_factura,
                            'saldo'       => $saldo_restante,
                            'estado'      => $estado,
                            'id'          => (int)$existente['id'],
                        ]);
                        $stats['actualizadas']++;
                    }

                    // Ajuste en el libro mayor por el delta. Sin referencia explícita:
                    // el mismo delta puede repetirse entre re-emisiones y la huella
                    // de idempotencia descartaría el segundo ajuste legítimo.
                    $delta = round($monto_factura - $monto_anterior, 2);
                    if (abs($delta) >= 0.01) {
                        $movModel->registrarMovimiento(
                            $unidad_id,
                            'ajuste',
                            $delta,
                            'Re-emisión ' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . "/{$anio} - Factura {$numero_factura} (monto anterior Bs. " . number_format($monto_anterior, 2, ',', '.') . ')'
                        );
                    }
                    continue;
                }

                // Alta nueva: el saldo a favor se consume una sola vez, en la creación
                $stmtSaldoFavor->execute(['unidad_id' => $unidad_id]);
                $row = $stmtSaldoFavor->fetch();
                $saldo_favor = round(floatval($row['total'] ?? 0), 2);

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
                $facturaId = (int)$db->lastInsertId();

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

                // Registrar cargo de la cuota en el libro mayor de la unidad con huella de idempotencia
                $movModel->registrarMovimiento(
                    $unidad_id,
                    'cargo_factura',
                    $monto_factura,
                    "Emisión cuota de mantenimiento " . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . "/{$anio} - Factura {$numero_factura}",
                    $facturaId,
                    'factura'
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
