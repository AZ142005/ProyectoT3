<?php
namespace App\Models;

use PDO;

class PagoModel extends BaseModel {
    /**
     * Inserta en la tabla pagos con estado PENDIENTE.
     * Previene pago duplicado y maneja errores de integridad.
     *
     * @param int $residenteId
     * @param int $unidadId
     * @param array $datos ['monto', 'fecha_pago', 'metodo_pago', 'referencia', ...]
     * @param string $filename Nombre de archivo ya guardado por FileUploader
     * @return bool
     */
    public function crearPago($residenteId, $unidadId, $datos, $filename) {
        $monto = round(floatval($datos['monto']), 2);
        $referencia = !empty($datos['referencia']) ? trim($datos['referencia']) : null;
        $fechaPago = $datos['fecha_pago'];

        $db = $this->db();

        try {
            $db->beginTransaction();

            // Prevenir pago duplicado: misma unidad, referencia, fecha y monto (excluye rechazados)
            if ($referencia) {
                $stmtDup = $db->prepare(
                    "SELECT id FROM pagos WHERE unidad_id = :unidad_id AND referencia = :referencia 
                     AND fecha_pago = :fecha_pago AND monto = :monto AND estado != 'RECHAZADO' LIMIT 1"
                );
                $stmtDup->execute([
                    'unidad_id' => $unidadId,
                    'referencia'=> $referencia,
                    'fecha_pago'=> $fechaPago,
                    'monto'     => $monto
                ]);
                if ($stmtDup->fetch()) {
                    $db->rollBack();
                    return false;
                }
            }

            $sql = "INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, archivo, observaciones, estado, banco_pagador, banco_receptor)
                    VALUES (:residente_id, :unidad_id, :monto, :fecha_pago, :metodo_pago, :referencia, :archivo, :observaciones, 'PENDIENTE', :banco_pagador, :banco_receptor)";
            
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                'residente_id'  => $residenteId,
                'unidad_id'     => $unidadId,
                'monto'         => $monto,
                'fecha_pago'    => $fechaPago,
                'metodo_pago'   => $datos['metodo_pago'] ?? '',
                'referencia'    => $referencia,
                'archivo'       => $filename,
                'observaciones' => !empty($datos['observaciones']) ? trim($datos['observaciones']) : null,
                'banco_pagador' => !empty($datos['banco_pagador']) ? trim($datos['banco_pagador']) : null,
                'banco_receptor'=> !empty($datos['banco_receptor']) ? trim($datos['banco_receptor']) : null
            ]);

            $db->commit();
            return $result;
        } catch (\PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e->getCode() == 23000) {
                return false; // Duplicate key — pago ya existe
            }
            error_log("[PAGO] Error crearPago: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retorna el total de deuda pendiente (facturas saldo) de una unidad.
     *
     * @param int $unidadId
     * @return float
     */
    public function obtenerTotalDeuda($unidadId) {
        $db = $this->db();
        $stmt = $db->prepare("SELECT COALESCE(SUM(saldo), 0) AS total_deuda FROM facturas WHERE unidad_id = :uid AND saldo > 0 AND deleted_at IS NULL");
        $stmt->execute(['uid' => $unidadId]);
        return round(floatval($stmt->fetch(PDO::FETCH_ASSOC)['total_deuda'] ?? 0), 2);
    }

    /**
     * Lista los pagos del residente autenticado, con JOIN a edificios y unidades.
     *
     * @param int $residenteId
     * @return array
     */
    public function obtenerPagosPorResidente($residenteId, int $pagina = 1, int $porPagina = 20): array {
        $baseSql = "SELECT p.*, u.numero AS unidad_numero, e.nombre AS edificio_nombre
                FROM pagos p
                INNER JOIN unidades u ON p.unidad_id = u.id
                LEFT JOIN edificios e ON u.edificio_id = e.id
                WHERE p.residente_id = :residente_id";
        $countSql = "SELECT COUNT(*) as total FROM pagos WHERE residente_id = :residente_id";
        return $this->paginate($baseSql, $countSql, ['residente_id' => $residenteId], $pagina, $porPagina, 'p.fecha_registro DESC');
    }

    /**
     * Para el admin, con filtros por estado, edificio, fecha.
     *
     * @param array $filtros
     * @param int $pagina
     * @param int $porPagina
     * @return array
     */
    public function obtenerTodosPagos($filtros = [], int $pagina = 1, int $porPagina = 20): array {
        $baseSql = "SELECT p.*, u.numero AS unidad_numero, e.nombre AS edificio_nombre,
                       CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre
                FROM pagos p
                INNER JOIN unidades u ON p.unidad_id = u.id
                LEFT JOIN edificios e ON u.edificio_id = e.id
                INNER JOIN personas per ON p.residente_id = per.id
                WHERE 1=1";
        
        $countSql = "SELECT COUNT(*) as total FROM pagos p 
                     INNER JOIN unidades u ON p.unidad_id = u.id
                     LEFT JOIN edificios e ON u.edificio_id = e.id
                     INNER JOIN personas per ON p.residente_id = per.id
                     WHERE 1=1";
        
        $params = [];
        if (!empty($filtros['estado'])) {
            $baseSql .= " AND p.estado = :estado";
            $countSql .= " AND p.estado = :estado";
            $params['estado'] = $filtros['estado'];
        }
        if (!empty($filtros['edificio'])) {
            $baseSql .= " AND u.edificio_id = :edificio";
            $countSql .= " AND u.edificio_id = :edificio";
            $params['edificio'] = intval($filtros['edificio']);
        }
        if (!empty($filtros['fecha'])) {
            $baseSql .= " AND p.fecha_pago = :fecha";
            $countSql .= " AND p.fecha_pago = :fecha";
            $params['fecha'] = $filtros['fecha'];
        }
        
        $result = $this->paginate($baseSql, $countSql, $params, $pagina, $porPagina, 'p.fecha_registro DESC');
        return $result;
    }

    /**
     * Detalle de un pago con su historial de auditoría.
     *
     * @param int $id
     * @return array|false
     */
    public function obtenerPagoPorId($id) {
        $db = $this->db();
        $sql = "SELECT p.*, u.numero AS unidad_numero, e.nombre AS edificio_nombre,
                       CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre, per.cedula AS residente_cedula
                FROM pagos p
                INNER JOIN unidades u ON p.unidad_id = u.id
                LEFT JOIN edificios e ON u.edificio_id = e.id
                INNER JOIN personas per ON p.residente_id = per.id
                WHERE p.id = :id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $pago = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pago) {
            $sqlLog = "SELECT l.*, u.nombre_completo AS admin_nombre
                       FROM log_auditoria l
                       INNER JOIN usuarios u ON l.admin_id = u.id
                       WHERE l.pago_id = :pago_id
                       ORDER BY l.fecha_registro DESC, l.id DESC";
            
            $stmtLog = $db->prepare($sqlLog);
            $stmtLog->execute(['pago_id' => $id]);
            $pago['log_auditoria'] = $stmtLog->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $pago = false;
        }
        
        return $pago;
    }

    /**
     * Dentro de una transacción PDO actualiza el estado del pago y crea un registro en log_auditoria.
     *
     * @param int $pagoId
     * @param string $nuevoEstado
     * @param string $motivo
     * @param int $adminId
     * @return bool
     */
    public function cambiarEstado($pagoId, $nuevoEstado, $motivo, $adminId, $ipAddress = null) {
        $db = $this->db();
        
        try {
            $db->beginTransaction();
            
            // Obtener el estado anterior con bloqueo de fila
            $stmtPrev = $db->prepare("SELECT estado FROM pagos WHERE id = :id FOR UPDATE");
            $stmtPrev->execute(['id' => $pagoId]);
            $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);
            
            if (!$prev) {
                $db->rollBack();
                return false;
            }
            
            $estadoAnterior = $prev['estado'];
            
            // Actualizar el estado del pago
            $stmtUpd = $db->prepare("UPDATE pagos SET estado = :estado WHERE id = :id");
            $stmtUpd->execute([
                'estado' => $nuevoEstado,
                'id'     => $pagoId
            ]);

            // Si se aprueba, descontar saldo de la factura asociada
            if ($nuevoEstado === 'APROBADO') {
                $stmtPagoInfo = $db->prepare("SELECT unidad_id, monto FROM pagos WHERE id = :id");
                $stmtPagoInfo->execute(['id' => $pagoId]);
                $pagoInfo = $stmtPagoInfo->fetch(PDO::FETCH_ASSOC);

                if ($pagoInfo && !empty($pagoInfo['unidad_id'])) {
                    $stmtFactura = $db->prepare("
                        SELECT id, saldo FROM facturas 
                        WHERE unidad_id = :uid AND estado = 'PENDIENTE' AND deleted_at IS NULL
                        ORDER BY anio ASC, mes ASC LIMIT 1 FOR UPDATE
                    ");
                    $stmtFactura->execute(['uid' => $pagoInfo['unidad_id']]);
                    $factura = $stmtFactura->fetch(PDO::FETCH_ASSOC);

                    if ($factura) {
                        $nuevoSaldo = round(max(0, floatval($factura['saldo']) - floatval($pagoInfo['monto'])), 2);
                        $stmtSaldo = $db->prepare("UPDATE facturas SET saldo = :saldo WHERE id = :fid");
                        $stmtSaldo->execute(['saldo' => $nuevoSaldo, 'fid' => $factura['id']]);
                    }
                }
            }
            
            // Crear el registro de auditoría
            $stmtLog = $db->prepare("
                INSERT INTO log_auditoria (pago_id, admin_id, estado_anterior, estado_nuevo, motivo, ip_address)
                VALUES (:pago_id, :admin_id, :estado_anterior, :estado_nuevo, :motivo, :ip_address)
            ");
            $stmtLog->execute([
                'pago_id'         => $pagoId,
                'admin_id'        => $adminId,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo'    => $nuevoEstado,
                'motivo'          => !empty($motivo) ? trim($motivo) : null,
                'ip_address'      => $ipAddress
            ]);
            
            // Outbox Disparador de Notificaciones de Evento (RF 35)
            $this->notificarCambioEstadoPago($db, $pagoId, $nuevoEstado, $motivo);

            $db->commit();
            return true;
            
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Error al cambiar estado del pago (ID: {$pagoId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Auxiliar interno para encolar notificaciones y registrar en bandeja del residente.
     */
    private function notificarCambioEstadoPago(PDO $db, int $pagoId, string $nuevoEstado, string $motivo = '') {
        $stmtInfo = $db->prepare("
            SELECT p.id, p.residente_id, p.monto, p.referencia, p.fecha_pago, per.email, per.telefono, CONCAT(per.nombre, ' ', per.apellido) AS nombre_completo 
            FROM pagos p 
            INNER JOIN personas per ON p.residente_id = per.id 
            WHERE p.id = :id
        ");
        $stmtInfo->execute(['id' => $pagoId]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        if (!$info || empty($info['email'])) {
            return;
        }

        $emailService = new \App\Services\EmailService();
        $notifService = new \App\Services\NotificationService();
        $enlaceWhatsapp = \App\Services\NotificationService::generarEnlaceWhatsApp(
            $info['telefono'] ?? '',
            "Hola " . $info['nombre_completo'] . ", le informamos que su pago Ref: " . $info['referencia'] . " de Bs. " . number_format(floatval($info['monto']), 2) . " ha sido " . strtolower($nuevoEstado) . "."
        );

        if ($nuevoEstado === 'APROBADO') {
            $asunto = "✔ Pago Aprobado - Referencia " . $info['referencia'];
            $cuerpoHtml = $emailService->renderTemplate('pago_aprobado', [
                'nombreResidente' => $info['nombre_completo'],
                'monto'           => $info['monto'],
                'referencia'      => $info['referencia'],
                'fechaPago'       => $info['fecha_pago'],
                'enlaceWhatsapp'  => $enlaceWhatsapp
            ]);

            $notifService->encolarNotificacion($info['email'], $asunto, $cuerpoHtml, $info['telefono'], 'ambos', 'alta');
            $notifService->registrarNotificacionResidente($info['residente_id'], "Pago Aprobado", "Su pago Ref. " . $info['referencia'] . " por Bs. " . number_format(floatval($info['monto']), 2) . " ha sido aprobado.", "success", "/pagos");

        } elseif ($nuevoEstado === 'RECHAZADO') {
            $asunto = "✖ Pago Rechazado - Referencia " . $info['referencia'];
            $cuerpoHtml = $emailService->renderTemplate('pago_rechazado', [
                'nombreResidente' => $info['nombre_completo'],
                'monto'           => $info['monto'],
                'referencia'      => $info['referencia'],
                'motivoRechazo'   => $motivo
            ]);

            $notifService->encolarNotificacion($info['email'], $asunto, $cuerpoHtml, $info['telefono'], 'ambos', 'alta');
            $notifService->registrarNotificacionResidente($info['residente_id'], "Pago Rechazado", "Su pago Ref. " . $info['referencia'] . " ha sido rechazado. Motivo: " . $motivo, "danger", "/pagos/subir");
        }
    }

    /**
     * Aprueba un lote de pagos (máximo 50) de forma transaccional con orden anti-deadlock.
     *
     * @param array $pagoIds
     * @param int $adminId
     * @return array ['procesados' => int, 'omitidos' => int]
     * @throws \Exception Si el lote excede el límite de 50 o falla la transacción
     */
    public function aprobarLote(array $pagoIds, int $adminId, $ipAddress = null): array {
        // Filtrar y validar IDs
        $ids = array_filter(array_map('intval', $pagoIds), fn($id) => $id > 0);
        $totalOriginal = count($ids);

        if ($totalOriginal === 0) {
            return ['procesados' => 0, 'omitidos' => 0];
        }

        if ($totalOriginal > 50) {
            throw new \Exception("El lote de aprobación masiva no puede superar los 50 pagos por operación.");
        }

        // Ordenamiento numérico ascendente estricto para evitar bloqueos
        sort($ids, SORT_NUMERIC);

        $db = $this->db();
        $procesados = 0;

        try {
            $db->beginTransaction();

            $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
            
            // Bloquear filas elegibles únicamente (PENDIENTE o EN REVISIÓN)
            $sqlSelect = "SELECT id, estado FROM pagos\n"
                       . "WHERE id IN (" . $inPlaceholders . ")\n"
                       . "AND estado IN ('PENDIENTE', 'EN REVISIÓN')\n"
                       . "ORDER BY id ASC FOR UPDATE";
            $stmtSel = $db->prepare($sqlSelect);
            $stmtSel->execute($ids);
            $pagosElegibles = $stmtSel->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($pagosElegibles)) {
                $sqlUpd = "UPDATE pagos SET estado = 'APROBADO' WHERE id = :id";
                $stmtUpdExec = $db->prepare($sqlUpd);

                $sqlAudit = "INSERT INTO log_auditoria (pago_id, admin_id, estado_anterior, estado_nuevo, motivo, ip_address)\n"
                          . "VALUES (:pago_id, :admin_id, :estado_anterior, 'APROBADO', 'Aprobación masiva por lote', :ip_address)";
                $stmtLog = $db->prepare($sqlAudit);

                $sqlPayInfo = "SELECT unidad_id, monto FROM pagos WHERE id = :id";
                $stmtPayInfo = $db->prepare($sqlPayInfo);

                $sqlFactura = "SELECT id, saldo FROM facturas WHERE unidad_id = :uid AND saldo > 0 AND estado != 'pagada' AND deleted_at IS NULL ORDER BY anio ASC, mes ASC LIMIT 1 FOR UPDATE";
                $stmtFactura = $db->prepare($sqlFactura);

                $sqlSaldo = "UPDATE facturas SET saldo = :saldo, estado = :estado WHERE id = :fid";
                $stmtSaldo = $db->prepare($sqlSaldo);

                foreach ($pagosElegibles as $sqlPago) {
                    $stmtUpdExec->execute(['id' => $sqlPago['id']]);
                    $stmtLog->execute([
                        'pago_id'         => $sqlPago['id'],
                        'admin_id'        => $adminId,
                        'estado_anterior' => $sqlPago['estado'],
                        'ip_address'      => $ipAddress
                    ]);

                    // Descontar saldo de factura asociada
                    $stmtPayInfo->execute(['id' => $sqlPago['id']]);
                    $payInfo = $stmtPayInfo->fetch(PDO::FETCH_ASSOC);
                    if ($payInfo && !empty($payInfo['unidad_id'])) {
                        $montoRestante = round(floatval($payInfo['monto']), 2);
                        // Cascade: apply payment across invoices until fully absorbed
                        while ($montoRestante > 0.01) {
                            $stmtFactura->execute(['uid' => $payInfo['unidad_id']]);
                            $factura = $stmtFactura->fetch(PDO::FETCH_ASSOC);
                            if (!$factura) break;

                            $saldoFactura = round(floatval($factura['saldo']), 2);
                            $aplicar = round(min($montoRestante, $saldoFactura), 2);
                            $nuevoSaldo = round($saldoFactura - $aplicar, 2);
                            $nuevoEstadoFactura = ($nuevoSaldo <= 0.00) ? 'pagada' : 'pendiente';

                            $stmtSaldo->execute([
                                'saldo'  => $nuevoSaldo,
                                'estado' => $nuevoEstadoFactura,
                                'fid'    => $factura['id']
                            ]);

                            $montoRestante = round($montoRestante - $aplicar, 2);
                        }
                        // Remainder becomes saldo a favor (negative saldo)
                        if ($montoRestante > 0.01) {
                            $stmtFavor = $db->prepare("
                                INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado)
                                VALUES (:num, :uid, :mes, :anio, CURDATE(), CURDATE(), 0, :pagado, :saldo, 'pagada')
                            ");
                            $stmtFavor->execute([
                                'num' => 'ABONO-' . date('Y-m') . '-' . str_pad($payInfo['unidad_id'], 4, '0', STR_PAD_LEFT),
                                'uid' => $payInfo['unidad_id'],
                                'mes' => intval(date('n')),
                                'anio' => intval(date('Y')),
                                'pagado' => $montoRestante,
                                'saldo' => -$montoRestante
                            ]);
                            // Register in movimientos_cuenta for traceability
                            $movModel = new MovimientosModel();
                            $movModel->registrarMovimiento(
                                intval($payInfo['unidad_id']),
                                'abono_pago',
                                $montoRestante,
                                "Saldo a favor por exceso de pago en lote",
                                $pago['id']
                            );
                        }
                    }

                    $this->notificarCambioEstadoPago($db, $pago['id'], 'APROBADO', 'Aprobación masiva por lote');
                    $procesados++;
                }
            }

            $db->commit();

            return [
                'procesados' => $procesados,
                'omitidos'   => $totalOriginal - $procesados
            ];
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en aprobarLote: " . $e->getMessage());
            throw $e;
        }
    }
}
