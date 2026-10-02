<?php
namespace App\Models;

use PDO;
use App\Core\EstadoPago;
use App\Services\LiquidacionPagoService;

class PagoModel extends BaseModel {
    /**
     * Normaliza la referencia de un pago para comparar identidad económica.
     *
     * Regla del lado pagos: trim + mayúsculas + eliminación de separadores
     * (\s, -, ., _, /, #); si el resultado queda vacío retorna null; si es
     * puramente numérico elimina ceros a la izquierda (todo ceros => '0').
     *
     * Nota: es intencionalmente más estricta que
     * ConciliacionBancariaService::normalizarReferencia(), que extrae solo
     * dígitos porque compara contra extractos bancarios.
     */
    public static function normalizarReferenciaPago(?string $ref): ?string {
        if ($ref === null) {
            return null;
        }

        $norm = strtoupper(trim($ref));
        $norm = preg_replace('/[\s\-\._\/#]+/', '', $norm);

        if ($norm === null || $norm === '') {
            return null;
        }

        if (ctype_digit($norm)) {
            $sinCeros = ltrim($norm, '0');
            return ($sinCeros === '') ? '0' : $sinCeros;
        }

        return $norm;
    }

    /**
     * Predicado SQL de identidad económica vigente de un pago: misma unidad y
     * misma referencia normalizada (o misma fecha_pago + monto cuando no hay
     * referencia normalizable), excluyendo RECHAZADO (permite re-subir tras
     * rechazo) y soft-deleted no aprobado (nunca acreditó). Un APROBADO
     * conserva su identidad aunque esté soft-deleted.
     *
     * Fuente única alineada con la columna generada `pagos.dup_guard`
     * (migración migrate_pago_duplicados.php): el fallback sin referencia es
     * unidad + fecha_pago + monto, y un APROBADO soft-deleted retiene identidad.
     * Si cambiara la expresión de `dup_guard`, este predicado debe reflejarlo.
     *
     * @param string $alias Alias de la tabla pagos (con punto incluido, 'p.' o '')
     */
    public static function sqlIdentidadEconomicaVigente(string $alias = ''): string {
        $u = $alias . 'unidad_id';
        $r = $alias . 'referencia_norm';
        $f = $alias . 'fecha_pago';
        $m = $alias . 'monto';
        $d = $alias . 'deleted_at';
        $e = $alias . 'estado';

        // `unidad_id` se factoriza al frente para que cada marcador nombrado
        // aparezca una sola vez: PDO con prepares nativos
        // (ATTR_EMULATE_PREPARES = false) rechaza placeholders nombrados
        // repetidos. La expresión es lógicamente idéntica a la de `dup_guard`.
        return "{$u} = :unidad_id AND ("
            . "({$r} = :referencia_norm AND {$e} != 'RECHAZADO' AND ({$d} IS NULL OR {$e} = 'APROBADO'))"
            . " OR ({$r} IS NULL AND {$f} = :fecha_pago AND {$m} = :monto
                 AND {$e} != 'RECHAZADO' AND ({$d} IS NULL OR {$e} = 'APROBADO'))"
            . ")";
    }

    /**
     * Inserta en la tabla pagos con estado inicial configurable.
     * Previene pago duplicado y maneja errores de integridad.
     *
     * @param int|null $residenteId Null = pago reportado desde el portal público sin usuario
     * @param int $unidadId
     * @param array $datos ['monto', 'fecha_pago', 'metodo_pago', 'referencia', 'estado' (opcional), ...]
     *                     'estado' es opcional: por defecto PENDIENTE; un valor fuera de
     *                     EstadoPago::all() también cae a PENDIENTE.
     * @param string $filename Nombre de archivo ya guardado por FileUploader
     * @return bool
     */
    public function crearPago($residenteId, $unidadId, $datos, $filename) {
        // $residenteId puede ser NULL: pago directo reportado sin iniciar sesión, asociado solo a la unidad.
        $monto = round(floatval($datos['monto']), 2);
        $referencia = !empty($datos['referencia']) ? trim($datos['referencia']) : null;
        $referenciaNorm = self::normalizarReferenciaPago($referencia);
        $fechaPago = $datos['fecha_pago'];

        // Estado inicial configurable (whitelist de EstadoPago); el portal directo lo crea en EN REVISIÓN.
        $estado = $datos['estado'] ?? EstadoPago::PENDIENTE;
        if (!in_array($estado, EstadoPago::all(), true)) {
            $estado = EstadoPago::PENDIENTE;
        }

        $db = $this->db();

        try {
            $db->beginTransaction();

            // Prevenir pago duplicado por identidad económica vigente.
            // El predicado se omite cuando no hay referencia normalizable y el
            // llamador no aporta fecha_pago o monto (sin esos bindings no puede
            // evaluarse la rama de fallback).
            if ($referenciaNorm !== null || (!empty($fechaPago) && $monto !== null)) {
                $stmtDup = $db->prepare("SELECT id FROM pagos WHERE " . self::sqlIdentidadEconomicaVigente() . " LIMIT 1");
                $stmtDup->execute([
                    'unidad_id'       => $unidadId,
                    'referencia_norm' => $referenciaNorm,
                    'fecha_pago'      => $fechaPago,
                    'monto'           => $monto
                ]);
                if ($stmtDup->fetch()) {
                    $db->rollBack();
                    return false;
                }
            }

            $sql = "INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, observaciones, estado, banco_pagador, banco_receptor, cuenta_bancaria_id)
                    VALUES (:residente_id, :unidad_id, :monto, :fecha_pago, :metodo_pago, :referencia, :referencia_norm, :archivo, :observaciones, :estado, :banco_pagador, :banco_receptor, :cuenta_bancaria_id)";
            
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                'residente_id'       => $residenteId,
                'unidad_id'          => $unidadId,
                'monto'              => $monto,
                'fecha_pago'         => $fechaPago,
                'metodo_pago'        => $datos['metodo_pago'] ?? '',
                'referencia'         => $referencia,
                'referencia_norm'    => $referenciaNorm,
                'archivo'            => $filename,
                'observaciones'      => !empty($datos['observaciones']) ? trim($datos['observaciones']) : null,
                'estado'             => $estado,
                'banco_pagador'      => !empty($datos['banco_pagador']) ? trim($datos['banco_pagador']) : null,
                'banco_receptor'     => !empty($datos['banco_receptor']) ? trim($datos['banco_receptor']) : null,
                'cuenta_bancaria_id' => !empty($datos['cuenta_bancaria_id']) ? intval($datos['cuenta_bancaria_id']) : null
            ]);

            $db->commit();
            return $result;
        } catch (\PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e->getCode() == 23000) {
                return false; // Duplicate key (uk_pago_dup_guard) — pago ya existe
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
        $unionSubquery = "(
            SELECT 'pago' AS tipo_origen,
                   p.id, p.residente_id, p.unidad_id, p.monto, p.fecha_pago,
                   p.metodo_pago, p.banco_pagador, p.banco_receptor, p.cuenta_bancaria_id,
                   p.referencia, p.archivo, p.observaciones, UPPER(p.estado) AS estado,
                   p.fecha_registro,
                   u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                   u.edificio_id,
                   COALESCE(CONCAT(per.nombre, ' ', per.apellido), 'Pago directo (sin usuario)') AS residente_nombre,
                   per.cedula AS residente_cedula
            FROM pagos p
            INNER JOIN unidades u ON p.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas per ON p.residente_id = per.id
            WHERE p.deleted_at IS NULL

            UNION ALL

            SELECT 'comprobante' AS tipo_origen,
                   c.id, c.residente_id, f.unidad_id, c.monto, c.fecha_pago,
                   c.metodo_pago, c.banco_pagador, c.banco_receptor, c.cuenta_bancaria_id,
                   c.referencia, c.archivo, c.observaciones, UPPER(c.estado) AS estado,
                   c.fecha_envio AS fecha_registro,
                   u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                   u.edificio_id,
                   CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre,
                   per.cedula AS residente_cedula
            FROM comprobantes_pago c
            INNER JOIN facturas f ON c.factura_id = f.id
            INNER JOIN unidades u ON f.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            INNER JOIN personas per ON c.residente_id = per.id
            WHERE c.deleted_at IS NULL
        ) AS p";

        $baseSql = "SELECT p.* FROM {$unionSubquery} WHERE 1=1";
        $countSql = "SELECT COUNT(*) as total FROM {$unionSubquery} WHERE 1=1";
        
        $params = [];
        if (!empty($filtros['estado'])) {
            $baseSql .= " AND p.estado = :estado";
            $countSql .= " AND p.estado = :estado";
            $params['estado'] = strtoupper(trim($filtros['estado']));
        }
        if (!empty($filtros['edificio'])) {
            $baseSql .= " AND p.edificio_id = :edificio";
            $countSql .= " AND p.edificio_id = :edificio";
            $params['edificio'] = intval($filtros['edificio']);
        }
        if (!empty($filtros['fecha'])) {
            $baseSql .= " AND p.fecha_pago = :fecha";
            $countSql .= " AND p.fecha_pago = :fecha";
            $params['fecha'] = $filtros['fecha'];
        }
        if (!empty($filtros['unidad_id'])) {
            // Lista unificada de una unidad (pagos + comprobantes): usada por el
            // portal del residente para que cualquier miembro de la unidad vea
            // todos los movimientos.
            $baseSql .= " AND p.unidad_id = :unidad_id";
            $countSql .= " AND p.unidad_id = :unidad_id";
            $params['unidad_id'] = intval($filtros['unidad_id']);
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
        $id = intval($id);
        $db = $this->db();
        $sql = "SELECT p.*, u.numero AS unidad_numero, e.nombre AS edificio_nombre,
                       COALESCE(CONCAT(per.nombre, ' ', per.apellido), 'Pago directo (sin usuario)') AS residente_nombre,
                       per.cedula AS residente_cedula
                FROM pagos p
                INNER JOIN unidades u ON p.unidad_id = u.id
                LEFT JOIN edificios e ON u.edificio_id = e.id
                LEFT JOIN personas per ON p.residente_id = per.id
                WHERE p.id = :id AND p.deleted_at IS NULL";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $pago = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pago) {
            $pago['tipo_origen'] = 'pago';
            $sqlLog = "SELECT l.*, u.nombre_completo AS admin_nombre
                       FROM log_auditoria l
                       INNER JOIN usuarios u ON l.admin_id = u.id
                       WHERE l.pago_id = :pago_id
                       ORDER BY l.fecha_registro DESC, l.id DESC";
            
            $stmtLog = $db->prepare($sqlLog);
            $stmtLog->execute(['pago_id' => $id]);
            $pago['log_auditoria'] = $stmtLog->fetchAll(PDO::FETCH_ASSOC);
            return $pago;
        }

        // Si no se encuentra en pagos, buscar en comprobantes_pago para compatibilidad total
        return $this->obtenerComprobantePorId($id);
    }

    /**
     * Detalle de un comprobante del sistema anterior (comprobantes_pago) con los
     * datos de su factura y unidad. Usado también por el detalle con ?tipo=comprobante
     * para evitar la colisión de IDs entre ambas tablas.
     *
     * @param int $id
     * @return array|false
     */
    public function obtenerComprobantePorId($id) {
        $id = intval($id);
        $db = $this->db();
        $sqlComp = "SELECT c.*, c.fecha_envio AS fecha_registro, UPPER(c.estado) AS estado,
                           f.numero_factura, f.saldo AS saldo_factura, f.monto_total, f.unidad_id,
                           u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                           CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre, per.cedula AS residente_cedula
                    FROM comprobantes_pago c
                    LEFT JOIN facturas f ON c.factura_id = f.id
                    LEFT JOIN unidades u ON f.unidad_id = u.id
                    LEFT JOIN edificios e ON u.edificio_id = e.id
                    LEFT JOIN personas per ON c.residente_id = per.id
                    WHERE c.id = :id AND c.deleted_at IS NULL";
        $stmtComp = $db->prepare($sqlComp);
        $stmtComp->execute(['id' => $id]);
        $comp = $stmtComp->fetch(PDO::FETCH_ASSOC);

        if (!$comp) {
            return false;
        }

        $comp['tipo_origen'] = 'comprobante';
        $comp['saldo_restante'] = ($comp['saldo_factura'] ?? 0) - ($comp['monto'] ?? 0);
        $comp['log_auditoria'] = [];
        $comp['action_url'] = '/admin/comprobante/verificar?id=' . $comp['id'];
        return $comp;
    }

    /**
     * Cambia el estado de un pago de forma atómica, re-validando la máquina de
     * estados y la identidad económica DENTRO del bloqueo FOR UPDATE.
     *
     * @param int $pagoId
     * @param string $nuevoEstado
     * @param string $motivo
     * @param int $adminId
     * @param string|null $ipAddress
     * @return array{ok: bool, code: string, estado_actual: ?string, message: string}
     *         code: actualizado | sin_cambios | transicion_invalida |
     *               duplicado_aprobado | no_encontrado | conflicto | error_bd
     */
    public function cambiarEstado($pagoId, $nuevoEstado, $motivo, $adminId, $ipAddress = null) {
        $pagoId = intval($pagoId);
        $nuevoEstado = strtoupper(trim((string)$nuevoEstado));
        $db = $this->db();

        // Reintentos acotados ante deadlock (1213) o lock wait timeout (1205).
        $maxIntentos = 3;
        for ($intento = 0; $intento < $maxIntentos; $intento++) {
            try {
                $db->beginTransaction();

                // Obtener estado y datos de identidad con bloqueo de fila.
                $stmtPrev = $db->prepare("SELECT id, estado, unidad_id, monto, referencia, referencia_norm FROM pagos WHERE id = :id FOR UPDATE");
                $stmtPrev->execute(['id' => $pagoId]);
                $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);

                if (!$prev) {
                    $db->rollBack();
                    return [
                        'ok'            => false,
                        'code'          => 'no_encontrado',
                        'estado_actual' => null,
                        'message'       => 'Pago no encontrado.'
                    ];
                }

                $estadoAnterior = strtoupper(trim((string)$prev['estado']));

                // Idempotencia: solicitar el mismo estado no es error, pero se audita.
                if ($estadoAnterior === $nuevoEstado) {
                    $this->registrarIntentoBloqueado(
                        $db, $pagoId, $adminId, $ipAddress, $estadoAnterior, $nuevoEstado,
                        "El pago ya se encontraba en estado {$estadoAnterior}."
                    );
                    $db->commit();
                    return [
                        'ok'            => true,
                        'code'          => 'sin_cambios',
                        'estado_actual' => $estadoAnterior,
                        'message'       => "El pago ya se encontraba en estado {$estadoAnterior}."
                    ];
                }

                // Máquina de estados centralizada, re-validada bajo el lock.
                if (!EstadoPago::puedeTransicionar($estadoAnterior, $nuevoEstado)) {
                    $this->registrarIntentoBloqueado(
                        $db, $pagoId, $adminId, $ipAddress, $estadoAnterior, $nuevoEstado,
                        "Transición no válida de '{$estadoAnterior}' a '{$nuevoEstado}'."
                    );
                    $db->commit();
                    return [
                        'ok'            => false,
                        'code'          => 'transicion_invalida',
                        'estado_actual' => $estadoAnterior,
                        'message'       => "No se puede cambiar de '{$estadoAnterior}' a '{$nuevoEstado}'. Transición no válida."
                    ];
                }

                // Guardia de duplicado económico para datos legacy: requiere un
                // APROBADO existente con la misma identidad económica, rastreado
                // por unidad + referencia normalizada. Incluye APROBADOS
                // soft-deleted a propósito (conservan su identidad mientras el
                // crédito no se revierta; misma política que dup_guard y crearPago).
                if ($nuevoEstado === EstadoPago::APROBADO && $prev['referencia_norm'] !== null && $prev['referencia_norm'] !== '') {
                    $stmtDup = $db->prepare(
                        "SELECT id FROM pagos
                         WHERE unidad_id = :unidad_id AND referencia_norm = :referencia_norm
                           AND estado = 'APROBADO' AND id != :id LIMIT 1"
                    );
                    $stmtDup->execute([
                        'unidad_id'       => intval($prev['unidad_id']),
                        'referencia_norm' => $prev['referencia_norm'],
                        'id'              => $pagoId
                    ]);
                    $twin = $stmtDup->fetch(PDO::FETCH_ASSOC);

                    if ($twin) {
                        $motivoBloqueo = "Duplicado económico: el pago #" . intval($twin['id']) . " ya está APROBADO para la misma unidad y referencia.";
                        $this->registrarIntentoBloqueado(
                            $db, $pagoId, $adminId, $ipAddress, $estadoAnterior, $nuevoEstado, $motivoBloqueo
                        );
                        $db->commit();
                        return [
                            'ok'            => false,
                            'code'          => 'duplicado_aprobado',
                            'estado_actual' => $estadoAnterior,
                            'message'       => $motivoBloqueo
                        ];
                    }
                }

                // Compare-and-set defensivo: ya tenemos el lock, esto protege
                // contra cualquier carrera residual.
                $stmtUpd = $db->prepare("UPDATE pagos SET estado = :estado WHERE id = :id AND estado = :esperado");
                $stmtUpd->execute([
                    'estado'   => $nuevoEstado,
                    'id'       => $pagoId,
                    'esperado' => $estadoAnterior
                ]);

                if ($stmtUpd->rowCount() !== 1) {
                    $db->rollBack();
                    return [
                        'ok'            => false,
                        'code'          => 'conflicto',
                        'estado_actual' => $estadoAnterior,
                        'message'       => 'El estado del pago cambió durante la operación. Intente nuevamente.'
                    ];
                }

                // Si se aprueba, liquidar facturas en cascada y acreditar saldo a favor si hay excedente
                if ($nuevoEstado === EstadoPago::APROBADO) {
                    if (!empty($prev['unidad_id'])) {
                        $liquidacionService = new LiquidacionPagoService();
                        $refTexto = !empty($prev['referencia']) ? "Ref. " . $prev['referencia'] : "Pago #{$pagoId}";
                        $liquidacionService->aplicarPagoAUnidad(
                            $db,
                            intval($prev['unidad_id']),
                            floatval($prev['monto']),
                            $refTexto,
                            $pagoId,
                            'pago'
                        );
                    }
                }

                // Crear el registro de auditoría del cambio real
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

                // Outbox de notificaciones DENTRO de la transacción: NotificationService
                // escribe en notificaciones_cola sobre la MISMA conexión PDO singleton
                // (Database::getConnection()), por lo que el encolado es atómico con el
                // cambio de estado. Un intento bloqueado/duplicado nunca llega aquí, lo
                // que evita notificaciones y correos duplicados.
                $this->notificarCambioEstadoPago($db, $pagoId, $nuevoEstado, $motivo);

                $db->commit();
                return [
                    'ok'            => true,
                    'code'          => 'actualizado',
                    'estado_actual' => $nuevoEstado,
                    'message'       => "El pago fue actualizado a estado {$nuevoEstado} exitosamente."
                ];

            } catch (\PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                $driverCode = $e->errorInfo[1] ?? null;
                if (($driverCode === 1213 || $driverCode === 1205) && $intento < $maxIntentos - 1) {
                    usleep($intento === 0 ? 50000 : 150000);
                    continue;
                }

                error_log("Error al cambiar estado del pago (ID: {$pagoId}): " . $e->getMessage());

                if ($driverCode === 1213 || $driverCode === 1205) {
                    return [
                        'ok'            => false,
                        'code'          => 'conflicto',
                        'estado_actual' => null,
                        'message'       => 'El pago está siendo procesado por otra operación. Intente nuevamente.'
                    ];
                }

                return [
                    'ok'            => false,
                    'code'          => 'error_bd',
                    'estado_actual' => null,
                    'message'       => 'Hubo un error de base de datos al registrar el cambio de estado.'
                ];
            } catch (\Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Error al cambiar estado del pago (ID: {$pagoId}): " . $e->getMessage());
                return [
                    'ok'            => false,
                    'code'          => 'error_bd',
                    'estado_actual' => null,
                    'message'       => 'Hubo un error de base de datos al registrar el cambio de estado.'
                ];
            }
        }

        return [
            'ok'            => false,
            'code'          => 'conflicto',
            'estado_actual' => null,
            'message'       => 'No se pudo completar la operación por alta concurrencia. Intente nuevamente.'
        ];
    }

    /**
     * Registra en log_auditoria un intento de cambio bloqueado (transición
     * inválida, reintento redundante o duplicado económico).
     *
     * estado_nuevo conserva el estado actual (nunca el intentado) para que la
     * detección de doble aprobación (accion='cambio_estado' AND
     * estado_nuevo='APROBADO') permanezca limpia.
     */
    private function registrarIntentoBloqueado(PDO $db, int $pagoId, $adminId, $ipAddress, string $estadoActual, string $estadoIntentado, string $motivo): void {
        $stmtLog = $db->prepare("
            INSERT INTO log_auditoria (pago_id, admin_id, estado_anterior, estado_nuevo, motivo, accion, ip_address)
            VALUES (:pago_id, :admin_id, :estado_anterior, :estado_nuevo, :motivo, 'intento_bloqueado', :ip_address)
        ");
        $stmtLog->execute([
            'pago_id'         => $pagoId,
            'admin_id'        => $adminId,
            'estado_anterior' => $estadoActual,
            'estado_nuevo'    => $estadoActual,
            'motivo'          => "Intento bloqueado hacia '{$estadoIntentado}': {$motivo}",
            'ip_address'      => $ipAddress
        ]);
    }

    /**
     * Auxiliar interno para encolar notificaciones y registrar en bandeja del residente.
     */
    private function notificarCambioEstadoPago(PDO $db, int $pagoId, string $nuevoEstado, string $motivo = '') {
        $stmtInfo = $db->prepare("
            SELECT p.id, p.residente_id, p.monto, p.referencia, p.fecha_pago, per.email, per.telefono, CONCAT(per.nombre, ' ', per.apellido) AS nombre_completo 
            FROM pagos p 
            LEFT JOIN personas per ON p.residente_id = per.id 
            WHERE p.id = :id
        ");
        $stmtInfo->execute(['id' => $pagoId]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        if (!$info || empty($info['email'])) {
            return;
        }

        $emailService = new \App\Services\EmailService();
        $notifService = new \App\Services\NotificationService();
        $estadoTexto = ($nuevoEstado === 'RECHAZADO')
            ? 'no fue aprobado'
            : 'ha sido ' . strtolower($nuevoEstado);
        $enlaceWhatsapp = \App\Services\NotificationService::generarEnlaceWhatsApp(
            $info['telefono'] ?? '',
            "Hola " . $info['nombre_completo'] . ", le informamos que su pago Ref: " . $info['referencia'] . " de " . formatearMoneda(floatval($info['monto'])) . " " . $estadoTexto . "."
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
            $notifService->registrarNotificacionResidente($info['residente_id'], "Pago Aprobado", "Su pago Ref. " . $info['referencia'] . " por " . formatearMoneda(floatval($info['monto'])) . " ha sido aprobado.", "success", "/pagos");

        } elseif ($nuevoEstado === 'RECHAZADO') {
            $asunto = "✖ Pago No Aprobado - Referencia " . $info['referencia'];
            $cuerpoHtml = $emailService->renderTemplate('pago_rechazado', [
                'nombreResidente' => $info['nombre_completo'],
                'monto'           => $info['monto'],
                'referencia'      => $info['referencia'],
                'motivoRechazo'   => $motivo
            ]);

            $notifService->encolarNotificacion($info['email'], $asunto, $cuerpoHtml, $info['telefono'], 'ambos', 'alta');
            $notifService->registrarNotificacionResidente($info['residente_id'], "Pago No Aprobado", "Su pago Ref. " . $info['referencia'] . " no fue aprobado. Motivo: " . $motivo, "danger", "/pagos/subir");
        } elseif ($nuevoEstado === 'EN REVISIÓN') {
            $notifService->registrarNotificacionResidente($info['residente_id'], "Pago en Revisión", "Su pago Ref. " . $info['referencia'] . " por " . formatearMoneda(floatval($info['monto'])) . " está siendo revisado por la administración.", "info", "/pagos");
        }
    }

    /**
     * Aprueba un lote de pagos (máximo 50) de forma transaccional con orden anti-deadlock.
     *
     * Detecta duplicados económicos (misma unidad + referencia normalizada) tanto
     * contra pagos ya APROBADOS como dentro del propio lote, bloqueándolos con
     * auditoría sin notificar.
     *
     * @param array $pagoIds
     * @param int $adminId
     * @return array ['procesados' => int, 'omitidos' => int, 'duplicados' => int]
     * @throws \Exception Si el lote excede el límite de 50 o falla la transacción
     */
    public function aprobarLote(array $pagoIds, int $adminId, $ipAddress = null): array {
        // Filtrar y validar IDs
        $ids = array_filter(array_map('intval', $pagoIds), fn($id) => $id > 0);
        $totalOriginal = count($ids);

        if ($totalOriginal === 0) {
            return ['procesados' => 0, 'omitidos' => 0, 'duplicados' => 0];
        }

        if ($totalOriginal > 50) {
            throw new \Exception("El lote de aprobación masiva no puede superar los 50 pagos por operación.");
        }

        // Ordenamiento numérico ascendente estricto para evitar bloqueos
        sort($ids, SORT_NUMERIC);

        $db = $this->db();
        $procesados = 0;
        $duplicados = 0;

        try {
            $db->beginTransaction();

            $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
            
            // Bloquear filas elegibles únicamente (PENDIENTE o EN REVISIÓN)
            $sqlSelect = "SELECT id, estado, unidad_id, referencia_norm FROM pagos\n"
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

                // Auditoría de duplicados bloqueados (estado_nuevo conserva el actual)
                $sqlAuditBloqueo = "INSERT INTO log_auditoria (pago_id, admin_id, estado_anterior, estado_nuevo, motivo, accion, ip_address)\n"
                                 . "VALUES (:pago_id, :admin_id, :estado_anterior, :estado_nuevo, 'Duplicado económico detectado en aprobación por lote', 'intento_bloqueado', :ip_address)";
                $stmtLogBloqueo = $db->prepare($sqlAuditBloqueo);

                // Identidad económica ya APROBADA (incluye APROBADOS soft-deleted:
                // conservan el crédito y su identidad; misma política que dup_guard).
                $sqlTwin = "SELECT id FROM pagos
                            WHERE unidad_id = :unidad_id AND referencia_norm = :referencia_norm
                              AND estado = 'APROBADO' AND id != :id LIMIT 1";
                $stmtTwin = $db->prepare($sqlTwin);

                $sqlPayInfo = "SELECT unidad_id, monto, referencia FROM pagos WHERE id = :id";
                $stmtPayInfo = $db->prepare($sqlPayInfo);

                $identidadesAprobadas = [];

                foreach ($pagosElegibles as $sqlPago) {
                    $referenciaNorm = trim((string)($sqlPago['referencia_norm'] ?? ''));

                    if ($referenciaNorm !== '') {
                        $identidad = intval($sqlPago['unidad_id']) . '|' . $referenciaNorm;
                        $esDuplicado = isset($identidadesAprobadas[$identidad]);

                        if (!$esDuplicado) {
                            $stmtTwin->execute([
                                'unidad_id'       => intval($sqlPago['unidad_id']),
                                'referencia_norm' => $referenciaNorm,
                                'id'              => intval($sqlPago['id'])
                            ]);
                            $esDuplicado = (bool)$stmtTwin->fetch(PDO::FETCH_ASSOC);
                        }

                        if ($esDuplicado) {
                            $stmtLogBloqueo->execute([
                                'pago_id'         => $sqlPago['id'],
                                'admin_id'        => $adminId,
                                'estado_anterior' => $sqlPago['estado'],
                                'estado_nuevo'    => $sqlPago['estado'],
                                'ip_address'      => $ipAddress
                            ]);
                            $duplicados++;
                            continue;
                        }

                        $identidadesAprobadas[$identidad] = true;
                    }

                    $stmtUpdExec->execute(['id' => $sqlPago['id']]);
                    $stmtLog->execute([
                        'pago_id'         => $sqlPago['id'],
                        'admin_id'        => $adminId,
                        'estado_anterior' => $sqlPago['estado'],
                        'ip_address'      => $ipAddress
                    ]);

                    // Liquidar facturas asociadas en cascada y generar saldo a favor si hay remanente ($montoRestante)
                    // Delegado a LiquidacionPagoService que actualiza facturas y MovimientosModel (movimientos_cuenta)
                    $stmtPayInfo->execute(['id' => $sqlPago['id']]);
                    $payInfo = $stmtPayInfo->fetch(PDO::FETCH_ASSOC);
                    if ($payInfo && !empty($payInfo['unidad_id'])) {
                        $montoRestante = floatval($payInfo['monto']);
                        $liquidacionService = new LiquidacionPagoService();
                        $refTexto = !empty($payInfo['referencia']) ? "Ref. " . $payInfo['referencia'] : "Aprobación lote Pago #{$sqlPago['id']}";
                        while ($montoRestante > 0.009) {
                            $liquidacionService->aplicarPagoAUnidad(
                                $db,
                                intval($payInfo['unidad_id']),
                                $montoRestante,
                                $refTexto,
                                $sqlPago['id'],
                                'pago'
                            );
                            break;
                        }
                    }

                    $this->notificarCambioEstadoPago($db, $sqlPago['id'], 'APROBADO', 'Aprobación masiva por lote');
                    $procesados++;
                }
            }

            $db->commit();

            return [
                'procesados' => $procesados,
                'omitidos'   => $totalOriginal - $procesados - $duplicados,
                'duplicados' => $duplicados
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
