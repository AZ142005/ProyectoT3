<?php
namespace App\Models;

use PDO;

class ConciliacionModel extends BaseModel {

    protected string $table = 'extractos_bancarios';

    /**
     * Inserta un lote de movimientos bancarios validando duplicados y clasificando débitos/créditos.
     *
     * Los duplicados no conciliados de un lote anterior se adoptan: se actualiza
     * su lote, banco, descripciones y fecha de carga sin tocar estado, vínculo
     * de pago ni administrador. Los duplicados conciliados nunca se modifican.
     *
     * @param array $movimientos [['fecha' => 'Y-m-d', 'referencia' => '...', 'descripcion' => '...', 'monto' => float, 'tipo' => 'credito'|'debito']]
     * @param string $banco
     * @param string $lote
     * @return array ['insertados' => int, 'duplicados' => int, 'debitos' => int, 'adoptados' => int]
     */
    public function insertarExtracto(array $movimientos, string $banco, string $lote): array {
        $db = $this->db();

        $stmtCheck = $db->prepare("
            SELECT id, estado, estado_conciliacion, pago_id, lote_importacion,
                   EXISTS (
                       SELECT 1 FROM conciliacion_abono_pago c
                       WHERE c.movimiento_id = extractos_bancarios.id
                   ) AS tiene_vinculo
            FROM extractos_bancarios 
            WHERE referencia_bancaria = :ref AND fecha_movimiento = :fecha AND monto = :monto
            LIMIT 1
        ");

        // Adopción condicional y atómica: si entre el SELECT y el UPDATE la fila
        // fue conciliada o vinculada, rowCount() será 0 y no se contará adopción.
        $stmtAdopt = $db->prepare("
            UPDATE extractos_bancarios
               SET lote_importacion = :lote,
                   banco = :banco,
                   descripcion_banco = :desc_banco,
                   descripcion = :descripcion,
                   fecha_carga = CURRENT_TIMESTAMP,
                   actualizado_en = CURRENT_TIMESTAMP
             WHERE id = :id
               AND lote_importacion = :lote_esperado
               AND (estado IN ('disponible', 'pendiente') OR (estado IS NULL AND estado_conciliacion = 'pendiente'))
               AND (estado_conciliacion IS NULL OR estado_conciliacion = 'pendiente')
               AND (pago_id IS NULL OR pago_id = 0)
               AND NOT EXISTS (
                   SELECT 1 FROM conciliacion_abono_pago c
                   WHERE c.movimiento_id = extractos_bancarios.id
               )
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
        $adoptados = 0;

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
            $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existente !== false) {
                $duplicados++;

                $estadoFila = $existente['estado'];
                $estadoConciliacionFila = $existente['estado_conciliacion'];

                // Nunca se toca una fila ya conciliada, por estado, pago o vínculo 1:1.
                $conciliada = $estadoFila === 'conciliado'
                    || $estadoConciliacionFila === 'conciliado'
                    || !empty($existente['pago_id'])
                    || (int)($existente['tiene_vinculo'] ?? 0) === 1;

                $disponible = (in_array($estadoFila, ['disponible', 'pendiente'], true)
                        || ($estadoFila === null && $estadoConciliacionFila === 'pendiente'))
                    && ($estadoConciliacionFila === null || $estadoConciliacionFila === 'pendiente')
                    && empty($existente['pago_id']);

                // Un duplicado disponible de un lote anterior se adopta al lote nuevo.
                if (!$conciliada && $disponible && $existente['lote_importacion'] !== $lote) {
                    $stmtAdopt->execute([
                        'lote'         => $lote,
                        'banco'        => $banco,
                        'desc_banco'   => $desc,
                        'descripcion'   => $desc,
                        'id'            => $existente['id'],
                        'lote_esperado' => $existente['lote_importacion']
                    ]);

                    if ($stmtAdopt->rowCount() === 1) {
                        $adoptados++;
                    }
                }

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
            'debitos'    => $debitos,
            'adoptados'  => $adoptados
        ];
    }

    /**
     * Obtiene el lote de importación activo: el de mayor fecha_carga y, en
     * empate, el de mayor id. Devuelve null cuando no hay extractos.
     */
    public function obtenerLoteActivo(): ?string {
        $db = $this->db();
        $stmt = $db->query("
            SELECT lote_importacion
            FROM extractos_bancarios
            GROUP BY lote_importacion
            ORDER BY MAX(fecha_carga) DESC, MAX(id) DESC
            LIMIT 1
        ");
        $lote = $stmt->fetchColumn();

        if ($lote === false || $lote === null || $lote === '') {
            return null;
        }

        return (string)$lote;
    }

    /**
     * Obtiene los extractos bancarios pendientes de conciliación del lote activo
     * (solo créditos disponibles no vinculados). Aplica exclusión dura a nivel
     * de consulta SQL (defensa en profundidad).
     *
     * Un lote histórico no se expone en la bandeja: si $lote difiere del activo
     * devuelve []. Para reportes históricos usar obtenerDisponiblesLote().
     */
    public function obtenerExtractosPendientes(?string $lote = null): array {
        $loteActivo = $this->obtenerLoteActivo();

        if ($loteActivo === null) {
            return [];
        }

        if ($lote !== null && $lote !== '' && $lote !== $loteActivo) {
            return [];
        }

        return $this->consultarDisponiblesPorLote($loteActivo);
    }

    /**
     * Obtiene los créditos disponibles de un lote concreto sin validar que sea
     * el lote activo. Consulta cruda reutilizada por reportes históricos.
     */
    public function obtenerDisponiblesLote(string $lote): array {
        return $this->consultarDisponiblesPorLote($lote);
    }

    /**
     * Obtiene los metadatos de un lote de importación para los reportes.
     * Devuelve null cuando el lote no existe.
     */
    public function obtenerDatosLote(string $lote): ?array {
        $db = $this->db();
        $stmt = $db->prepare("
            SELECT lote_importacion,
                   GROUP_CONCAT(DISTINCT banco ORDER BY banco SEPARATOR ', ') AS banco,
                   MIN(fecha_carga) AS fecha_importacion,
                   MAX(fecha_carga) AS fecha_ultima_carga,
                   COUNT(*) AS total_movimientos
            FROM extractos_bancarios
            WHERE lote_importacion = :lote
            GROUP BY lote_importacion
            LIMIT 1
        ");
        $stmt->execute(['lote' => $lote]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : $fila;
    }

    /**
     * Obtiene una fila por cada extracto conciliado del historial, con los datos
     * de la unidad y el residente vinculado al pago.
     *
     * El COALESCE entre pago.residente_id y unidad.propietario_id evita filas
     * duplicadas cuando ambos apuntan a la misma persona. La fecha límite se
     * aplica sobre fecha_carga para congelar el corte del reporte.
     */
    public function obtenerMovimientosConciliados(?string $fechaLimite = null): array {
        $db = $this->db();
        $sql = "
            SELECT e.id,
                   e.lote_importacion,
                   e.banco,
                   e.fecha_movimiento,
                   e.referencia_bancaria,
                   e.referencia,
                   e.monto,
                   e.actualizado_en,
                   p.id AS pago_id,
                   p.fecha_pago,
                   p.referencia AS referencia_pago,
                   u.numero AS unidad_numero,
                   COALESCE(ed.nombre, 'Sin Torre') AS edificio_nombre,
                   CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre,
                   per.cedula AS residente_cedula
            FROM extractos_bancarios e
            LEFT JOIN pagos p ON e.pago_id = p.id
            LEFT JOIN unidades u ON p.unidad_id = u.id
            LEFT JOIN edificios ed ON u.edificio_id = ed.id
            LEFT JOIN personas per ON per.id = COALESCE(p.residente_id, u.propietario_id)
            WHERE (e.estado = 'conciliado'
                   OR e.estado_conciliacion = 'conciliado'
                   OR (e.pago_id IS NOT NULL AND e.pago_id > 0))
        ";

        $params = [];
        if ($fechaLimite !== null && $fechaLimite !== '') {
            $sql .= " AND e.fecha_carga <= :limite";
            $params['limite'] = $fechaLimite;
        }

        $sql .= " ORDER BY e.fecha_movimiento ASC, e.id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Consulta base de créditos disponibles no vinculados de un lote.
     */
    private function consultarDisponiblesPorLote(string $lote): array {
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
              AND (m.estado_conciliacion IS NULL OR m.estado_conciliacion = 'pendiente')
              AND (m.pago_id IS NULL OR m.pago_id = 0)
              AND m.tipo_movimiento = 'credito'
              AND m.lote_importacion = :lote
              AND NOT EXISTS (
                  SELECT 1 FROM conciliacion_abono_pago c
                  WHERE c.movimiento_id = m.id
              )
            ORDER BY fecha_movimiento DESC, id DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute(['lote' => $lote]);
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

            // PASO 1: Reservar atómicamente el extracto bancario (UPDATE condicional)
            $stmtUpdateExt = $db->prepare("
                UPDATE extractos_bancarios
                   SET estado = 'conciliado',
                       estado_conciliacion = 'conciliado',
                       pago_id = :pago_id,
                       admin_id = :admin_id,
                       actualizado_en = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND (estado IN ('disponible', 'pendiente') OR (estado IS NULL AND estado_conciliacion = 'pendiente'))
            ");
            $stmtUpdateExt->execute([
                'pago_id'  => $pagoId,
                'admin_id' => $usuarioId,
                'id'       => $movimientoId
            ]);
            $filasAfectadas = $stmtUpdateExt->rowCount();

            // PASO 2: Comprobar filas afectadas. Si es 0, el abono no estaba disponible.
            if ($filasAfectadas === 0) {
                if (!$isNested && $db->inTransaction()) {
                    $db->rollBack();
                }

                $stmtCheckExt = $db->prepare("SELECT COALESCE(estado, estado_conciliacion) FROM extractos_bancarios WHERE id = :id");
                $stmtCheckExt->execute(['id' => $movimientoId]);
                $estadoActual = $stmtCheckExt->fetchColumn();

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
            } catch (\Throwable $e) {
                \App\Core\Logger::warning('conciliacion_vincular_abono', 'No se pudo registrar vinculo en conciliacion_abono_pago: ' . $e->getMessage(), ['extracto_id' => $extractoId, 'pago_id' => $pagoId]);
            }
        }

        return $ok;
    }

    /**
     * Obtiene el listado de lotes importados con métricas de conciliación.
     * Marca con es_activo el lote vigente y cuenta como pendientes solo los
     * créditos realmente disponibles (sin vínculo de pago).
     */
    public function obtenerLotes(): array {
        $db = $this->db();
        $sql = "
            SELECT e.lote_importacion, e.banco, MIN(e.fecha_carga) AS fecha_importacion,
                   COUNT(*) AS total_movimientos,
                   SUM(CASE WHEN e.estado = 'conciliado' OR e.estado_conciliacion = 'conciliado' THEN 1 ELSE 0 END) AS conciliados,
                   SUM(CASE WHEN (e.estado IN ('disponible', 'pendiente') OR (e.estado IS NULL AND e.estado_conciliacion = 'pendiente'))
                             AND (e.estado_conciliacion IS NULL OR e.estado_conciliacion = 'pendiente')
                             AND e.tipo_movimiento = 'credito'
                             AND (e.pago_id IS NULL OR e.pago_id = 0)
                             AND vinculo.movimiento_id IS NULL
                        THEN 1 ELSE 0 END) AS pendientes,
                   SUM(CASE WHEN e.tipo_movimiento = 'debito' OR e.estado_conciliacion = 'descartado' THEN 1 ELSE 0 END) AS descartados
            FROM extractos_bancarios e
            LEFT JOIN (
                SELECT DISTINCT movimiento_id FROM conciliacion_abono_pago
            ) vinculo ON vinculo.movimiento_id = e.id
            GROUP BY e.lote_importacion, e.banco
            ORDER BY MAX(e.fecha_carga) DESC, MAX(e.id) DESC
        ";
        $lotes = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $loteActivo = $this->obtenerLoteActivo();

        foreach ($lotes as &$lote) {
            $lote['es_activo'] = ($loteActivo !== null && $lote['lote_importacion'] === $loteActivo) ? 1 : 0;
        }
        unset($lote);

        return $lotes;
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
            LEFT JOIN personas per ON per.id = COALESCE(p.residente_id, u.propietario_id)
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
            LEFT JOIN personas per ON per.id = COALESCE(c.residente_id, u.propietario_id)
            WHERE c.estado IN ('pendiente', 'PENDIENTE')
            ORDER BY fecha_creacion DESC, id DESC
        ";
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
