<?php
namespace App\Models;

use PDO;
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

    /**
     * Obtiene el estado de cuenta actual de una unidad en una sola consulta.
     * Así la deuda pendiente, las facturas listadas y el saldo a favor siempre
     * pertenecen al mismo instante y no se mezclan estados de pagos distintos.
     *
     * @return array{facturas: array, total_deuda: float, saldo_favor: float}
     */
    public function getEstadoCuentaUnidad(int $unidad_id): array {
        $sql = "
            SELECT numero_factura, mes, anio, fecha_vencimiento, saldo, estado
            FROM facturas
            WHERE unidad_id = :unidad_id
              AND deleted_at IS NULL
              AND saldo <> 0
            ORDER BY fecha_vencimiento ASC, id ASC
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['unidad_id' => $unidad_id]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $facturas = [];
        $totalDeuda = 0.0;
        $saldoFavor = 0.0;

        foreach ($filas as $fila) {
            $saldo = round(floatval($fila['saldo']), 2);
            $estado = strtolower((string)($fila['estado'] ?? ''));

            if ($estado === 'anulada') {
                continue;
            }

            if ($saldo > 0 && in_array($estado, ['pendiente', 'vencida'], true)) {
                $totalDeuda += $saldo;
                $facturas[] = [
                    'numero_factura'    => $fila['numero_factura'],
                    'mes'               => intval($fila['mes']),
                    'anio'              => intval($fila['anio']),
                    'fecha_vencimiento' => $fila['fecha_vencimiento'],
                    'saldo'             => $saldo,
                ];
            } elseif ($saldo < 0) {
                $saldoFavor += abs($saldo);
            }
        }

        return [
            'facturas'    => $facturas,
            'total_deuda' => round($totalDeuda, 2),
            'saldo_favor' => round($saldoFavor, 2),
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
     * Cuenta las deudas de cartelera (CART-…) activas de un período.
     *
     * Son deudas informativas importadas del reporte de cartelera: la emisión
     * (Enviar/Re-enviar Deuda) no las toca, no bloquean el proceso y tampoco
     * cuentan como deudas emitidas del sistema (esas son solo las FAC-…).
     */
    public function countCarteleraByPeriod($mes, $anio): int {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*) as total
            FROM facturas
            WHERE mes = :mes AND anio = :anio
              AND numero_factura LIKE 'CART-%'
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

            // Serialización de emisiones concurrentes con bloqueo pesimista
            $stmtFacturasPeriodo = $db->prepare("
                SELECT id, numero_factura, monto_total, monto_pagado, saldo, estado, deleted_at 
                FROM facturas 
                WHERE unidad_id = :unidad_id 
                  AND mes = :mes 
                  AND anio = :anio 
                  AND numero_factura LIKE 'FAC-%'
                ORDER BY id ASC 
                FOR UPDATE
            ");

            // Verificar si la factura o unidad tiene pagos en revisión asociados
            $stmtCheckPagosRevision = $db->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM comprobantes_pago WHERE factura_id = :factura_id AND estado IN ('pendiente', 'verificado')) +
                    (SELECT COUNT(*) FROM pagos WHERE unidad_id = :unidad_id AND estado = 'PENDIENTE' AND deleted_at IS NULL)
                AS total_revision
            ");

            // Verificar colisión de número de factura
            $stmtExisteNumero = $db->prepare("SELECT id FROM facturas WHERE numero_factura = :numero_factura LIMIT 1");

            // Regla de inmutabilidad: Modificar solo facturas activas impagas (NUNCA pagadas/canceladas)
            $stmtFacturaActualizar = $db->prepare("
                UPDATE facturas 
                SET monto_total = :monto_total, saldo = :saldo, estado = :estado 
                WHERE id = :id AND estado IN ('pendiente', 'vencida') AND deleted_at IS NULL
            ");

            $stmtFacturaReactivar = $db->prepare("
                UPDATE facturas 
                SET deleted_at = NULL, monto_total = :monto_total, saldo = :saldo, estado = :estado 
                WHERE id = :id AND deleted_at IS NOT NULL
            ");
            $stmtCrearFactura = $db->prepare("
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
            // El vencimiento es el primer día del mes facturado (toda deuda se vence automáticamente el día 1)
            $fecha_vencimiento = sprintf('%04d-%02d-01', $anio, $mes);

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

                $baseNumeroFactura = $numero_factura;

                // 1. Obtener todas las facturas FAC-… de esta unidad en el período
                $stmtFacturasPeriodo->execute([
                    'unidad_id' => $unidad_id,
                    'mes'       => $mes,
                    'anio'      => $anio
                ]);
                $facturasExistentes = $stmtFacturasPeriodo->fetchAll(PDO::FETCH_ASSOC);

                $facturasPagadas = [];
                $facturaPendiente = null;
                $facturaInactiva = null;

                foreach ($facturasExistentes as $f) {
                    if ($f['deleted_at'] !== null) {
                        $facturaInactiva = $f;
                    } elseif ($f['estado'] === 'pagada') {
                        $facturasPagadas[] = $f;
                    } elseif (in_array($f['estado'], ['pendiente', 'vencida']) && $facturaPendiente === null) {
                        $facturaPendiente = $f;
                    }
                }

                $totalYaPagado = 0.0;
                foreach ($facturasPagadas as $fp) {
                    $totalYaPagado += round(floatval($fp['monto_pagado']), 2);
                }

                // CASO A: Existe una factura activa PENDIENTE para el período
                if ($facturaPendiente !== null) {
                    $stmtCheckPagosRevision->execute([
                        'factura_id' => (int)$facturaPendiente['id'],
                        'unidad_id'  => $unidad_id
                    ]);
                    $pagosRevision = (int)$stmtCheckPagosRevision->fetchColumn();

                    // Si NO tiene pagos en revisión: actualiza el cobro activo pendiente (evita duplicados)
                    if ($pagosRevision === 0) {
                        $montoParaPendiente = ($totalYaPagado > 0)
                            ? round(max(0, $monto_factura - $totalYaPagado), 2)
                            : $monto_factura;

                        $monto_anterior = round(floatval($facturaPendiente['monto_total']), 2);
                        $monto_pagado_actual = round(floatval($facturaPendiente['monto_pagado']), 2);
                        $saldo_restante = round(max(0, $montoParaPendiente - $monto_pagado_actual), 2);
                        $estado = $saldo_restante > 0 ? 'vencida' : 'pagada';

                        $stmtFacturaActualizar->execute([
                            'monto_total' => $montoParaPendiente,
                            'saldo'       => $saldo_restante,
                            'estado'      => $estado,
                            'id'          => (int)$facturaPendiente['id'],
                        ]);
                        $stats['actualizadas']++;

                        $delta = round($montoParaPendiente - $monto_anterior, 2);
                        if (abs($delta) >= 0.01) {
                            $movModel->registrarMovimiento(
                                $unidad_id,
                                'ajuste',
                                $delta,
                                'Re-emisión ' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . "/{$anio} - Factura {$facturaPendiente['numero_factura']} (monto anterior Bs. " . number_format($monto_anterior, 2, ',', '.') . ')'
                            );
                        }
                        continue;
                    } else {
                        // Factura pendiente protegida: tiene pagos en revisión asociados.
                        // No se altera su monto mientras el comprobante/pago esté por auditar.
                        // Si la nueva cuota supera lo comprometido, se genera un cobro complementario por el delta.
                        $montoComprometido = $totalYaPagado + round(floatval($facturaPendiente['monto_total']), 2);
                        $montoAjuste = round($monto_factura - $montoComprometido, 2);
                        if ($montoAjuste <= 0.009) {
                            continue;
                        }

                        $seq = 1;
                        $numeroAjuste = $baseNumeroFactura . '-A' . $seq;
                        while (true) {
                            $stmtExisteNumero->execute(['numero_factura' => $numeroAjuste]);
                            if (!$stmtExisteNumero->fetch()) {
                                break;
                            }
                            $seq++;
                            $numeroAjuste = $baseNumeroFactura . '-A' . $seq;
                        }

                        $stmtCrearFactura->execute([
                            'numero_factura'    => $numeroAjuste,
                            'unidad_id'         => $unidad_id,
                            'mes'               => $mes,
                            'anio'              => $anio,
                            'fecha_emision'     => $fecha_emision,
                            'fecha_vencimiento' => $fecha_vencimiento,
                            'monto_total'       => $montoAjuste,
                            'monto_pagado'      => 0.00,
                            'saldo'             => $montoAjuste,
                            'estado'            => 'vencida'
                        ]);
                        $facturaId = (int)$db->lastInsertId();

                        $movModel->registrarMovimiento(
                            $unidad_id,
                            'cargo_factura',
                            $montoAjuste,
                            "Ajuste complementario " . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . "/{$anio} - Factura {$numeroAjuste}",
                            $facturaId,
                            'factura'
                        );
                        $stats['generadas']++;
                        continue;
                    }
                }

                // CASO B: Las facturas anteriores del mismo mes ya están PAGADAS (o pendiente bloqueada por pagos en revisión)
                // Inmutabilidad histórica: NO se toca ni altera el cobro pagado/cerrado.
                if (!empty($facturasPagadas) && $facturaPendiente === null) {
                    $montoAjuste = round($monto_factura - $totalYaPagado, 2);
                    if ($montoAjuste <= 0.009) {
                        // El residente ya cubrió la totalidad del nuevo monto con sus pagos previos
                        continue;
                    }

                    // Generar nuevo cobro activo (PENDIENTE) con sufijo determinista (-A1, -A2, ...)
                    $seq = 1;
                    $numeroAjuste = $baseNumeroFactura . '-A' . $seq;
                    while (true) {
                        $stmtExisteNumero->execute(['numero_factura' => $numeroAjuste]);
                        if (!$stmtExisteNumero->fetch()) {
                            break;
                        }
                        $seq++;
                        $numeroAjuste = $baseNumeroFactura . '-A' . $seq;
                    }

                    $stmtCrearFactura->execute([
                        'numero_factura'    => $numeroAjuste,
                        'unidad_id'         => $unidad_id,
                        'mes'               => $mes,
                        'anio'              => $anio,
                        'fecha_emision'     => $fecha_emision,
                        'fecha_vencimiento' => $fecha_vencimiento,
                        'monto_total'       => $montoAjuste,
                        'monto_pagado'      => 0.00,
                        'saldo'             => $montoAjuste,
                        'estado'            => 'vencida'
                    ]);
                    $facturaId = (int)$db->lastInsertId();

                    $movModel->registrarMovimiento(
                        $unidad_id,
                        'cargo_factura',
                        $montoAjuste,
                        "Ajuste cuota " . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . "/{$anio} - Factura {$numeroAjuste}",
                        $facturaId,
                        'factura'
                    );
                    $stats['generadas']++;
                    continue;
                }

                // CASO C: Factura soft-deleted sin facturas pagadas previas: reactivar
                if ($facturaInactiva !== null && empty($facturasPagadas) && $facturaPendiente === null) {
                    $monto_anterior = round(floatval($facturaInactiva['monto_total']), 2);
                    $monto_pagado = round(floatval($facturaInactiva['monto_pagado']), 2);
                    $saldo_restante = round(max(0, $monto_factura - $monto_pagado), 2);
                    $estado = $saldo_restante > 0 ? 'vencida' : 'pagada';

                    $stmtFacturaReactivar->execute([
                        'monto_total' => $monto_factura,
                        'saldo'       => $saldo_restante,
                        'estado'      => $estado,
                        'id'          => (int)$facturaInactiva['id'],
                    ]);
                    $stats['reactivadas']++;

                    $delta = round($monto_factura - $monto_anterior, 2);
                    if (abs($delta) >= 0.01) {
                        $movModel->registrarMovimiento(
                            $unidad_id,
                            'ajuste',
                            $delta,
                            'Reactivación ' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . "/{$anio} - Factura {$facturaInactiva['numero_factura']}"
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
                $estado = 'vencida';
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
                        $estado = 'vencida';
                        $stmtUpdateSaldoFavor->execute(['unidad_id' => $unidad_id]);
                    }
                } else {
                    $saldo_restante = $monto_a_pagar;
                    $estado = $saldo_restante > 0 ? 'vencida' : 'pagada';
                }

                $stmtCrearFactura->execute([
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
                    $stmtCrearFactura->execute([
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
