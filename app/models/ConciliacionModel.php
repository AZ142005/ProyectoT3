<?php
namespace App\Models;

use PDO;

class ConciliacionModel extends BaseModel {

    protected string $table = 'extractos_bancarios';

    /**
     * Inserta un lote de movimientos bancarios validando duplicados y clasificando débitos/créditos.
     *
     * @param array $movimientos [['fecha' => 'Y-m-d', 'referencia' => '...', 'descripcion' => '...', 'monto' => float, 'tipo' => 'credito'|'debito']]
     * @param string $banco
     * @param string $lote
     * @return array ['insertados' => int, 'duplicados' => int, 'debitos' => int]
     */
    public function insertarExtracto(array $movimientos, string $banco, string $lote): array {
        $db = $this->db();

        $stmtCheck = $db->prepare("
            SELECT id FROM extractos_bancarios 
            WHERE referencia_bancaria = :ref AND fecha_movimiento = :fecha AND monto = :monto
            LIMIT 1
        ");

        $sqlInsert = "
            INSERT INTO extractos_bancarios 
            (banco, fecha_movimiento, referencia_bancaria, referencia, descripcion_banco, descripcion, monto, tipo_movimiento, estado_conciliacion, estado, lote_importacion)
            VALUES (:banco, :fecha, :ref_bancaria, :referencia, :desc_banco, :descripcion, :monto, :tipo, :estado_conciliacion, :estado, :lote)
        ";
        $stmtInsert = $db->prepare($sqlInsert);

        $insertados = 0;
        $duplicados = 0;
        $debitos = 0;

        foreach ($movimientos as $mov) {
            $monto = floatval($mov['monto'] ?? 0);
            $tipo = ($monto < 0 || ($mov['tipo'] ?? '') === 'debito') ? 'debito' : 'credito';
            $montoAbs = abs($monto);
            $referencia = trim($mov['referencia'] ?? '');
            $fecha = $mov['fecha'] ?? date('Y-m-d');
            $desc = trim($mov['descripcion'] ?? '');

            // Verificar si el movimiento ya fue importado
            $stmtCheck->execute([
                'ref'   => $referencia,
                'fecha' => $fecha,
                'monto' => $montoAbs
            ]);

            if ($stmtCheck->rowCount() > 0) {
                $duplicados++;
                continue;
            }

            // Los débitos se marcan automáticamente como descartados de la conciliación de cobranzas
            $estadoConciliacion = ($tipo === 'debito') ? 'descartado' : 'pendiente';
            $estado = ($tipo === 'debito') ? 'descartado' : 'disponible';

            // Cierre de carrera: si otra importación insertó la misma identidad
            // (ref, fecha, monto) entre el SELECT y el INSERT, el índice único
            // uk_extracto_identidad lanza 23000/1062 y se cuenta como duplicado.
            try {
                $stmtInsert->execute([
                    'banco'               => $banco,
                    'fecha'               => $fecha,
                    'ref_bancaria'        => $referencia,
                    'referencia'          => $referencia,
                    'desc_banco'          => $desc,
                    'descripcion'         => $desc,
                    'monto'               => $montoAbs,
                    'tipo'                => $tipo,
                    'estado_conciliacion' => $estadoConciliacion,
                    'estado'              => $estado,
                    'lote'                => $lote
                ]);
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || ($e->errorInfo[1] ?? 0) === 1062) {
                    $duplicados++;
                    continue;
                }
                throw $e;
            }

            // Solo cuenta débitos realmente insertados: un duplicado capturado
            // por 23000/1062 cuenta únicamente en $duplicados.
            if ($tipo === 'debito') {
                $debitos++;
            }
            $insertados++;
        }

        return [
            'insertados' => $insertados,
            'duplicados' => $duplicados,
            'debitos'    => $debitos
        ];
    }

    /**
     * Obtiene los extractos bancarios pendientes de conciliación (solo créditos disponibles no vinculados).
     * Aplica exclusión dura a nivel de consulta SQL (defensa en profundidad).
     */
    public function obtenerExtractosPendientes(?string $lote = null): array {
        $db = $this->db();
        $sql = "
            SELECT m.*,
                   m.id,
                   m.fecha_movimiento,
                   m.referencia_bancaria,
                   m.descripcion_banco,
                   m.monto,
                   m.tipo_movimiento,
                   COALESCE(m.estado, 'disponible') AS estado
            FROM extractos_bancarios m
            WHERE (m.estado IN ('disponible', 'pendiente') OR (m.estado IS NULL AND m.estado_conciliacion = 'pendiente'))
              AND m.estado NOT IN ('conciliado', 'anulado', 'descartado')
              AND (m.estado_conciliacion IS NULL OR m.estado_conciliacion = 'pendiente')
              AND m.tipo_movimiento = 'credito'
              AND NOT EXISTS (
                  SELECT 1 FROM conciliacion_abono_pago c
                  WHERE c.movimiento_id = m.id
              )
        ";
        $params = [];

        if (!empty($lote)) {
            $sql .= " AND m.lote_importacion = :lote";
            $params['lote'] = $lote;
        }

        $sql .= " ORDER BY fecha_movimiento DESC, id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene movimientos bancarios disponibles desde la tabla canonical movimientos_bancarios.
     */
    public function obtenerMovimientosPendientes(?string $lote = null): array {
        $db = $this->db();
        $sql = "
            SELECT m.*,
                   m.id,
                   COALESCE(m.fecha, m.fecha_movimiento) AS fecha,
                   COALESCE(m.fecha_movimiento, m.fecha) AS fecha_movimiento,
                   COALESCE(m.referencia, m.referencia_bancaria) AS referencia,
                   COALESCE(m.referencia_bancaria, m.referencia) AS referencia_bancaria,
                   COALESCE(m.descripcion, m.descripcion_banco) AS descripcion,
                   COALESCE(m.descripcion_banco, m.descripcion) AS descripcion_banco,
                   COALESCE(m.importe, m.monto) AS importe,
                   COALESCE(m.monto, m.importe) AS monto,
                   COALESCE(m.tipo, m.tipo_movimiento) AS tipo,
                   COALESCE(m.tipo_movimiento, m.tipo) AS tipo_movimiento,
                   m.estado
            FROM movimientos_bancarios m
            WHERE m.estado = 'disponible'
              AND COALESCE(m.tipo, m.tipo_movimiento) = 'credito'
              AND NOT EXISTS (
                  SELECT 1 FROM conciliacion_abono_pago c
                  WHERE c.movimiento_id = m.id
              )
        ";
        $params = [];

        if (!empty($lote)) {
            $sql .= " AND m.lote_importacion = :lote";
            $params['lote'] = $lote;
        }

        $sql .= " ORDER BY fecha DESC, id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ejecuta la conciliación atómica y condicional (núcleo anti-race condition) con relación 1:1 estricta.
     *
     * @param int $movimientoId
     * @param int $pagoId
     * @param int $usuarioId
     * @param string $origenTipo 'pago' | 'comprobante'
     * @param string|null $idempotencyKey
     * @param PDO|null $dbInstance
     * @return array ['ok' => bool, 'codigo' => string, 'status_http' => int, 'mensaje' => string, 'idempotente' => bool]
     */
    public function conciliarAbono(
        int $movimientoId,
        int $pagoId,
        int $usuarioId,
        string $origenTipo = 'pago',
        ?string $idempotencyKey = null,
        ?PDO $dbInstance = null
    ): array {
        $db = $dbInstance ?: $this->db();
        $isNested = $db->inTransaction();
        if (!$isNested) {
            $db->beginTransaction();
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        try {
            // 0. Idempotencia: Si es exactamente la misma asociación, responder con resultado previo sin error
            $stmtIdem = $db->prepare("
                SELECT id, movimiento_id, pago_id, conciliado_por 
                FROM conciliacion_abono_pago 
                WHERE movimiento_id = :mov AND pago_id = :pago
            ");
            $stmtIdem->execute(['mov' => $movimientoId, 'pago' => $pagoId]);
            $previo = $stmtIdem->fetch(PDO::FETCH_ASSOC);

            if ($previo) {
                if ((int)$previo['conciliado_por'] === $usuarioId || !empty($idempotencyKey)) {
                    if (!$isNested) {
                        $db->commit();
                    }
                    return [
                        'ok'          => true,
                        'idempotente' => true,
                        'status_http' => 200,
                        'codigo'      => 'OK_IDEMPOTENTE',
                        'mensaje'     => 'La conciliación ya fue procesada previamente para este movimiento y pago.'
                    ];
                }
            }

            // 0.1 Verificar si el pago ya fue conciliado contra OTRO movimiento (garantía 1:1 simétrica)
            $stmtPagoCheck = $db->prepare("
                SELECT id, movimiento_id 
                FROM conciliacion_abono_pago 
                WHERE pago_id = :pago 
                LIMIT 1
            ");
            $stmtPagoCheck->execute(['pago' => $pagoId]);
            $pagoExistente = $stmtPagoCheck->fetch(PDO::FETCH_ASSOC);

            if ($pagoExistente && (int)$pagoExistente['movimiento_id'] !== $movimientoId) {
                if (!$isNested && $db->inTransaction()) {
                    $db->rollBack();
                }
                $this->registrarAuditoriaIntento($db, $movimientoId, $pagoId, $usuarioId, 'rechazado', 'PAGO_YA_CONCILIADO', 'El pago ya se encuentra vinculado a otro abono bancario.', $ip);
                return [
                    'ok'          => false,
                    'idempotente' => false,
                    'status_http' => 409,
                    'codigo'      => 'PAGO_YA_CONCILIADO',
                    'mensaje'     => 'El pago ya fue conciliado previamente.'
                ];
            }

            // PASO 1: Intentar reservar atómicamente el movimiento bancario (UPDATE condicional)
            $filasAfectadas = 0;

            // Verificar si existe la tabla 'movimientos_bancarios'
            $hasMovTable = false;
            try {
                $checkTable = $db->query("SELECT 1 FROM movimientos_bancarios LIMIT 1");
                $hasMovTable = true;
            } catch (\Throwable $e) {
                $hasMovTable = false;
            }

            if ($hasMovTable) {
                $stmtUpdateMov = $db->prepare("
                    UPDATE movimientos_bancarios
                       SET estado = 'conciliado',
                           actualizado_en = CURRENT_TIMESTAMP
                     WHERE id = :id
                       AND estado = 'disponible'
                ");
                $stmtUpdateMov->execute(['id' => $movimientoId]);
                $filasAfectadas = $stmtUpdateMov->rowCount();
            }

            // Si no se afectó en movimientos_bancarios o el movimiento reside en extractos_bancarios:
            if ($filasAfectadas === 0) {
                $stmtUpdateExt = $db->prepare("
                    UPDATE extractos_bancarios
                       SET estado = 'conciliado',
                           estado_conciliacion = 'conciliado',
                           pago_id = :pago_id,
                           admin_id = :admin_id,
                           actualizado_en = CURRENT_TIMESTAMP
                     WHERE id = :id
                       AND (estado = 'disponible' OR (estado IS NULL AND estado_conciliacion = 'pendiente'))
                ");
                $stmtUpdateExt->execute([
                    'pago_id'  => $pagoId,
                    'admin_id' => $usuarioId,
                    'id'       => $movimientoId
                ]);
                $filasAfectadas = $stmtUpdateExt->rowCount();

                if ($filasAfectadas > 0 && $hasMovTable) {
                    $db->prepare("UPDATE movimientos_bancarios SET estado = 'conciliado', actualizado_en = CURRENT_TIMESTAMP WHERE id = :id")
                       ->execute(['id' => $movimientoId]);
                }
            } else {
                // Sincronizar también extractos_bancarios si fue afectado en movimientos_bancarios
                $stmtSyncExt = $db->prepare("
                    UPDATE extractos_bancarios
                       SET estado = 'conciliado',
                           estado_conciliacion = 'conciliado',
                           pago_id = :pago_id,
                           admin_id = :admin_id,
                           actualizado_en = CURRENT_TIMESTAMP
                     WHERE id = :id
                ");
                $stmtSyncExt->execute([
                    'pago_id'  => $pagoId,
                    'admin_id' => $usuarioId,
                    'id'       => $movimientoId
                ]);
            }

            // PASO 2: Comprobar filas afectadas. Si es 0, el abono no estaba disponible.
            if ($filasAfectadas === 0) {
                if (!$isNested && $db->inTransaction()) {
                    $db->rollBack();
                }

                $estadoActual = null;
                if ($hasMovTable) {
                    try {
                        $stmtCheckEstado = $db->prepare("SELECT estado FROM movimientos_bancarios WHERE id = :id");
                        $stmtCheckEstado->execute(['id' => $movimientoId]);
                        $estadoActual = $stmtCheckEstado->fetchColumn();
                    } catch (\Throwable $e) {}
                }

                if ($estadoActual === false || $estadoActual === null) {
                    $stmtCheckExt = $db->prepare("SELECT COALESCE(estado, estado_conciliacion) FROM extractos_bancarios WHERE id = :id");
                    $stmtCheckExt->execute(['id' => $movimientoId]);
                    $estadoActual = $stmtCheckExt->fetchColumn();
                }

                if ($estadoActual === false || $estadoActual === null) {
                    $this->registrarAuditoriaIntento($db, $movimientoId, $pagoId, $usuarioId, 'rechazado', 'ABONO_NO_ENCONTRADO', 'El abono no existe en el sistema.', $ip);
                    return [
                        'ok'          => false,
                        'idempotente' => false,
                        'status_http' => 404,
                        'codigo'      => 'ABONO_NO_ENCONTRADO',
                        'mensaje'     => 'El abono no existe.'
                    ];
                }

                if ($estadoActual === 'anulado' || $estadoActual === 'descartado') {
                    $this->registrarAuditoriaIntento($db, $movimientoId, $pagoId, $usuarioId, 'rechazado', 'ABONO_ANULADO', 'El abono se encuentra anulado.', $ip);
                    return [
                        'ok'          => false,
                        'idempotente' => false,
                        'status_http' => 409,
                        'codigo'      => 'ABONO_ANULADO',
                        'mensaje'     => 'El abono está anulado y no puede conciliarse.'
                    ];
                }

                $this->registrarAuditoriaIntento($db, $movimientoId, $pagoId, $usuarioId, 'rechazado', 'ABONO_YA_UTILIZADO', 'El abono ya fue utilizado por otra transacción o usuario.', $ip);
                return [
                    'ok'          => false,
                    'idempotente' => false,
                    'status_http' => 409,
                    'codigo'      => 'ABONO_YA_UTILIZADO',
                    'mensaje'     => 'El abono ya fue utilizado para conciliar otro pago.'
                ];
            }

            // PASO 3: Insertar el vínculo en conciliacion_abono_pago con control de constraint UNIQUE
            try {
                $stmtInsert = $db->prepare("
                    INSERT INTO conciliacion_abono_pago 
                    (movimiento_id, pago_id, conciliado_por, origen_tipo, idempotency_key)
                    VALUES (:mov_id, :pago_id, :usuario_id, :origen_tipo, :idempotency_key)
                ");
                $stmtInsert->execute([
                    'mov_id'          => $movimientoId,
                    'pago_id'         => $pagoId,
                    'usuario_id'      => $usuarioId,
                    'origen_tipo'     => $origenTipo,
                    'idempotency_key' => $idempotencyKey
                ]);
            } catch (\PDOException $e) {
                if (!$isNested && $db->inTransaction()) {
                    $db->rollBack();
                }

                $msg = $e->getMessage();
                $this->registrarAuditoriaIntento($db, $movimientoId, $pagoId, $usuarioId, 'error', 'ABONO_YA_UTILIZADO', 'Violación de constraint UNIQUE: ' . $msg, $ip);

                if (str_contains($msg, 'uq_pago') || str_contains($msg, 'pago_id')) {
                    return [
                        'ok'          => false,
                        'idempotente' => false,
                        'status_http' => 409,
                        'codigo'      => 'PAGO_YA_CONCILIADO',
                        'mensaje'     => 'El pago ya fue conciliado previamente.'
                    ];
                }

                return [
                    'ok'          => false,
                    'idempotente' => false,
                    'status_http' => 409,
                    'codigo'      => 'ABONO_YA_UTILIZADO',
                    'mensaje'     => 'El abono ya fue utilizado para conciliar otro pago.'
                ];
            }

            // Registrar intento ganador en auditoría append-only
            $this->registrarAuditoriaIntento($db, $movimientoId, $pagoId, $usuarioId, 'exito', 'CONCILIACION_EXITOSA', 'Conciliación 1:1 exitosa.', $ip);

            if (!$isNested) {
                $db->commit();
            }

            return [
                'ok'          => true,
                'idempotente' => false,
                'status_http' => 200,
                'codigo'      => 'CONCILIACION_EXITOSA',
                'mensaje'     => 'Abono conciliado y vinculado exitosamente.'
            ];

        } catch (\Throwable $e) {
            if (!$isNested && $db->inTransaction()) {
                $db->rollBack();
            }
            $this->registrarAuditoriaIntento($db, $movimientoId, $pagoId, $usuarioId, 'error', 'ERROR_INTERNO', $e->getMessage(), $ip);
            throw $e;
        }
    }

    /**
     * Registra un evento de auditoría en la tabla append-only historial_estado_abono.
     */
    public function registrarAuditoriaIntento(
        PDO $db,
        int $movimientoId,
        int $pagoId,
        int $usuarioId,
        string $resultado,
        string $codigo,
        string $detalles,
        string $ip
    ): void {
        try {
            $stmt = $db->prepare("
                INSERT INTO historial_estado_abono
                (movimiento_id, pago_id, estado_anterior, estado_nuevo, usuario_id, resultado, codigo_negocio, detalles, ip_address)
                VALUES (:mov_id, :pago_id, 'disponible', 'conciliado', :usuario_id, :resultado, :codigo, :detalles, :ip)
            ");
            $stmt->execute([
                'mov_id'     => $movimientoId,
                'pago_id'    => $pagoId,
                'usuario_id' => $usuarioId,
                'resultado'  => $resultado,
                'codigo'     => $codigo,
                'detalles'   => $detalles,
                'ip'         => $ip
            ]);
        } catch (\Throwable $e) {
            error_log("[HISTORIAL_ABONO] Error al registrar intento: " . $e->getMessage());
        }
    }

    /**
     * Marca un extracto bancario como conciliado y le vincula el pago y administrador responsable.
     * Mantiene sincronía con la tabla conciliacion_abono_pago.
     */
    public function marcarConciliado(int $extractoId, int $pagoId, int $adminId): bool {
        $db = $this->db();
        $stmt = $db->prepare("
            UPDATE extractos_bancarios 
            SET estado = 'conciliado', estado_conciliacion = 'conciliado', pago_id = :pago_id, admin_id = :admin_id, actualizado_en = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $ok = $stmt->execute([
            'pago_id'  => $pagoId,
            'admin_id' => $adminId,
            'id'       => $extractoId
        ]);

        if ($ok) {
            try {
                $db->prepare("
                    INSERT IGNORE INTO conciliacion_abono_pago (movimiento_id, pago_id, conciliado_por)
                    VALUES (:mov_id, :pago_id, :admin_id)
                ")->execute([
                    'mov_id'   => $extractoId,
                    'pago_id'  => $pagoId,
                    'admin_id' => $adminId
                ]);
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    /**
     * Obtiene el listado de lotes importados con métricas de conciliación.
     */
    public function obtenerLotes(): array {
        $db = $this->db();
        $sql = "
            SELECT lote_importacion, banco, MIN(fecha_carga) AS fecha_importacion,
                   COUNT(*) AS total_movimientos,
                   SUM(CASE WHEN estado_conciliacion = 'conciliado' THEN 1 ELSE 0 END) AS conciliados,
                   SUM(CASE WHEN estado_conciliacion = 'pendiente' AND tipo_movimiento = 'credito' THEN 1 ELSE 0 END) AS pendientes,
                   SUM(CASE WHEN tipo_movimiento = 'debito' OR estado_conciliacion = 'descartado' THEN 1 ELSE 0 END) AS descartados
            FROM extractos_bancarios
            GROUP BY lote_importacion, banco
            ORDER BY fecha_importacion DESC
        ";
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los pagos y comprobantes pendientes de verificación que han ingresado al sistema.
     *
     * @return array
     */
    public function obtenerTodosPagosPendientes(): array {
        $db = $this->db();
        $sql = "
            SELECT 'pago' AS origen_tabla,
                   p.id, p.unidad_id, p.monto, p.fecha_pago, p.referencia,
                   p.banco_pagador AS banco_origen, COALESCE(p.banco_receptor, cb.banco) AS banco_destino,
                   p.banco_pagador, COALESCE(p.banco_receptor, cb.banco) AS banco_receptor, p.estado,
                   p.observaciones, p.archivo, p.metodo_pago,
                   CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre, per.cedula AS residente_cedula,
                   per.email AS residente_email, per.telefono AS residente_telefono,
                   u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                   NULL AS factura_id, NULL AS numero_factura, p.fecha_registro AS fecha_creacion
            FROM pagos p
            LEFT JOIN unidades u ON p.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas per ON p.residente_id = per.id OR u.propietario_id = per.id
            LEFT JOIN cuentas_bancarias cb ON p.cuenta_bancaria_id = cb.id
            WHERE p.estado IN ('PENDIENTE', 'EN REVISIÓN')

            UNION ALL

            SELECT 'comprobante' AS origen_tabla,
                   c.id, f.unidad_id, c.monto, c.fecha_pago, c.referencia,
                   c.banco_pagador AS banco_origen, c.banco_receptor AS banco_destino,
                   c.banco_pagador, c.banco_receptor, c.estado,
                   c.observaciones, c.archivo, c.metodo_pago,
                   CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre, per.cedula AS residente_cedula,
                   per.email AS residente_email, per.telefono AS residente_telefono,
                   u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                   c.factura_id, f.numero_factura, c.fecha_envio AS fecha_creacion
            FROM comprobantes_pago c
            LEFT JOIN facturas f ON c.factura_id = f.id
            LEFT JOIN unidades u ON f.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas per ON c.residente_id = per.id OR u.propietario_id = per.id
            WHERE c.estado IN ('pendiente', 'PENDIENTE')
            ORDER BY fecha_creacion DESC, id DESC
        ";
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
