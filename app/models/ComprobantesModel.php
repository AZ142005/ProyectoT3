<?php
namespace App\Models;

use PDO;
use App\Core\Auth;

class ComprobantesModel extends BaseModel {
    /**
     * Obtiene los comprobantes de pago recientes de un residente.
     *
     * @param int $residente_id
     * @param int $limit
     * @return array
     */
    public function getRecientesByResidente($residente_id, int $limitOrPagina = 10, ?int $porPagina = null): array {
        if ($porPagina !== null) {
            $pagina = max(1, $limitOrPagina);
            $baseSql = "
                SELECT c.*, f.numero_factura 
                FROM comprobantes_pago c
                INNER JOIN facturas f ON c.factura_id = f.id
                WHERE c.residente_id = :residente_id
            ";
            $countSql = "SELECT COUNT(*) as total FROM comprobantes_pago WHERE residente_id = :residente_id";
            return $this->paginate($baseSql, $countSql, ['residente_id' => $residente_id], $pagina, $porPagina, 'c.fecha_envio DESC');
        }

        $db = $this->db();
        
        $sql = "
            SELECT c.*, f.numero_factura 
            FROM comprobantes_pago c
            INNER JOIN facturas f ON c.factura_id = f.id
            WHERE c.residente_id = :residente_id
            ORDER BY c.fecha_envio DESC
            LIMIT :limit
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':residente_id', $residente_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limitOrPagina, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Verifica si ya existe un comprobante o pago activo con la misma referencia normalizada
     * o archivo hash para la misma unidad o factura.
     *
     * @return array|null Información del duplicado o null si no existe
     */
    public function verificarDuplicado(int $facturaId, ?string $referencia, string $fechaPago, float $monto, ?string $archivoHash = null): ?array {
        $db = $this->db();
        $refNorm = PagoModel::normalizarReferenciaPago($referencia);

        // Obtener la unidad asociada a la factura
        $stmtF = $db->prepare("SELECT unidad_id FROM facturas WHERE id = :factura_id");
        $stmtF->execute(['factura_id' => $facturaId]);
        $unidadId = $stmtF->fetchColumn();

        // 1. Chequeo por referencia en comprobantes_pago para la misma unidad/factura
        if (!empty($refNorm)) {
            $sqlComp = "
                SELECT c.id, c.estado, c.referencia 
                FROM comprobantes_pago c
                INNER JOIN facturas f ON c.factura_id = f.id
                WHERE (c.factura_id = :factura_id " . ($unidadId ? "OR f.unidad_id = :unidad_id" : "") . ")
                  AND c.referencia_norm = :ref_norm
                  AND c.estado != 'rechazado'
                  AND c.deleted_at IS NULL
                LIMIT 1
            ";
            $params = ['factura_id' => $facturaId, 'ref_norm' => $refNorm];
            if ($unidadId) {
                $params['unidad_id'] = $unidadId;
            }
            $stmtC = $db->prepare($sqlComp);
            $stmtC->execute($params);
            $dupHit = $stmtC->fetch(PDO::FETCH_ASSOC);
            if ($dupHit) {
                return ['tipo' => 'comprobante', 'id' => $dupHit['id'], 'criterio' => 'referencia'];
            }

            // Chequeo cruzado contra pagos registrados para la misma unidad
            if ($unidadId) {
                $stmtP = $db->prepare("
                    SELECT id, estado, referencia 
                    FROM pagos 
                    WHERE unidad_id = :unidad_id 
                      AND referencia_norm = :ref_norm 
                      AND estado != 'RECHAZADO'
                      AND deleted_at IS NULL
                    LIMIT 1
                ");
                $stmtP->execute(['unidad_id' => $unidadId, 'ref_norm' => $refNorm]);
                $pagoHit = $stmtP->fetch(PDO::FETCH_ASSOC);
                if ($pagoHit) {
                    return ['tipo' => 'pago', 'id' => $pagoHit['id'], 'criterio' => 'referencia'];
                }
            }
        }

        // 2. Chequeo por hash de archivo si está presente (evita re-subir el mismo archivo)
        if (!empty($archivoHash)) {
            $stmtH = $db->prepare("
                SELECT c.id 
                FROM comprobantes_pago c
                INNER JOIN facturas f ON c.factura_id = f.id
                WHERE (c.factura_id = :factura_id " . ($unidadId ? "OR f.unidad_id = :unidad_id" : "") . ")
                  AND c.archivo_hash = :hash
                  AND c.estado != 'rechazado'
                  AND c.deleted_at IS NULL
                LIMIT 1
            ");
            $paramsH = ['factura_id' => $facturaId, 'hash' => $archivoHash];
            if ($unidadId) {
                $paramsH['unidad_id'] = $unidadId;
            }
            $stmtH->execute($paramsH);
            $hashHit = $stmtH->fetch(PDO::FETCH_ASSOC);
            if ($hashHit) {
                return ['tipo' => 'comprobante', 'id' => $hashHit['id'], 'criterio' => 'archivo_hash'];
            }

            // Chequeo cruzado contra pagos por hash de archivo (misma vigencia de
            // pagos, global por hash: el mismo archivo no puede registrarse dos veces).
            $stmtPH = $db->prepare("
                SELECT id FROM pagos
                WHERE archivo_hash = :hash
                  AND estado != 'RECHAZADO'
                  AND (deleted_at IS NULL OR estado = 'APROBADO')
                LIMIT 1
            ");
            $stmtPH->execute(['hash' => $archivoHash]);
            $pagoHashHit = $stmtPH->fetch(PDO::FETCH_ASSOC);
            if ($pagoHashHit) {
                return ['tipo' => 'pago', 'id' => $pagoHashHit['id'], 'criterio' => 'archivo_hash'];
            }
        }

        // 3. Chequeo fallback sin referencia: misma factura + misma fecha + mismo monto
        if (empty($refNorm)) {
            $stmtS = $db->prepare("
                SELECT id 
                FROM comprobantes_pago 
                WHERE factura_id = :factura_id 
                  AND fecha_pago = :fecha_pago 
                  AND monto = :monto
                  AND estado != 'rechazado'
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmtS->execute([
                'factura_id' => $facturaId,
                'fecha_pago' => $fechaPago,
                'monto'      => $monto
            ]);
            $fallbackHit = $stmtS->fetch(PDO::FETCH_ASSOC);
            if ($fallbackHit) {
                return ['tipo' => 'comprobante', 'id' => $fallbackHit['id'], 'criterio' => 'monto_fecha'];
            }
        }

        return null;
    }

    /**
     * Inserta un nuevo comprobante de pago en la base de datos con protección de duplicados.
     *
     * @param string|array $tableOData
     * @param array|null $data
     * @return string|false
     */
    public function create($tableOData, ?array $data = null): string|false {
        if (is_array($tableOData)) {
            $data = $tableOData;
        } else {
            $data = $data ?? [];
        }

        $facturaId = (int)($data['factura_id'] ?? 0);
        $monto = round(floatval($data['monto'] ?? 0), 2);
        $fechaPago = $data['fecha_pago'] ?? date('Y-m-d');
        $referencia = !empty($data['referencia']) ? trim($data['referencia']) : null;
        $referenciaNorm = PagoModel::normalizarReferenciaPago($referencia);
        $archivoHash = $data['archivo_hash'] ?? null;

        // Pre-chequeo indexado contra duplicados
        $dup = $this->verificarDuplicado($facturaId, $referencia, $fechaPago, $monto, $archivoHash);
        if ($dup !== null) {
            return false;
        }

        $db = $this->db();
        
        $sql = "
            INSERT INTO comprobantes_pago 
            (residente_id, factura_id, monto, metodo_pago, banco_pagador, banco_receptor, cuenta_bancaria_id, referencia, referencia_norm, fecha_pago, archivo, archivo_hash, observaciones) 
            VALUES (:residente_id, :factura_id, :monto, :metodo_pago, :banco_pagador, :banco_receptor, :cuenta_bancaria_id, :referencia, :referencia_norm, :fecha_pago, :archivo, :archivo_hash, :observaciones)
        ";
        
        try {
            $stmt = $db->prepare($sql);
            $ok = $stmt->execute([
                'residente_id'       => $data['residente_id'],
                'factura_id'         => $facturaId,
                'monto'              => $monto,
                'metodo_pago'        => $data['metodo_pago'] ?? '',
                'banco_pagador'      => !empty($data['banco_pagador']) ? trim($data['banco_pagador']) : null,
                'banco_receptor'     => !empty($data['banco_receptor']) ? trim($data['banco_receptor']) : null,
                'cuenta_bancaria_id' => !empty($data['cuenta_bancaria_id']) ? (int)$data['cuenta_bancaria_id'] : null,
                'referencia'         => $referencia,
                'referencia_norm'    => $referenciaNorm,
                'fecha_pago'         => $fechaPago,
                'archivo'            => $data['archivo'] ?? null,
                'archivo_hash'       => $archivoHash,
                'observaciones'      => $data['observaciones'] ?? null
            ]);
            return $ok ? (string) $db->lastInsertId() : false;
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000 || ($e->errorInfo[1] ?? 0) === 1062) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Obtiene todos los comprobantes de pago de un residente con detalles de factura.
     *
     * @param int $residente_id
     * @return array
     */
    public function getAllByResidente($residente_id, int $pagina = 1, int $porPagina = 20): array {
        $baseSql = "
            SELECT c.*, f.numero_factura, f.mes, f.anio
            FROM comprobantes_pago c
            INNER JOIN facturas f ON c.factura_id = f.id
            WHERE c.residente_id = :residente_id
        ";
        $countSql = "SELECT COUNT(*) as total FROM comprobantes_pago WHERE residente_id = :residente_id";
        return $this->paginate($baseSql, $countSql, ['residente_id' => $residente_id], $pagina, $porPagina, 'c.fecha_envio DESC');
    }

    /**
     * Obtiene los comprobantes de pago pendientes de verificación (Administración).
     *
     * @param int $limit
     * @return array
     */
    public function getPendientesVerificar($limit = 10) {
        $db = $this->db();
        
        $sql = "
            SELECT 
                c.*,
                f.numero_factura,
                u.numero as unidad,
                CONCAT(p.nombre, ' ', p.apellido) as residente,
                p.cedula
            FROM comprobantes_pago c
            INNER JOIN facturas f ON c.factura_id = f.id
            INNER JOIN unidades u ON f.unidad_id = u.id
            INNER JOIN personas p ON c.residente_id = p.id
            WHERE c.estado = 'pendiente'
            ORDER BY c.fecha_envio DESC
            LIMIT :limit
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Obtiene los últimos comprobantes procesados (aprobados o rechazados).
     *
     * @param int $limit
     * @return array
     */
    public function getProcesados($limit = 10) {
        $db = $this->db();
        
        $sql = "
            SELECT 
                c.*,
                f.numero_factura,
                u.numero as unidad,
                CONCAT(p.nombre, ' ', p.apellido) as residente,
                p.cedula
            FROM comprobantes_pago c
            INNER JOIN facturas f ON c.factura_id = f.id
            LEFT JOIN unidades u ON f.unidad_id = u.id
            INNER JOIN personas p ON c.residente_id = p.id
            WHERE c.estado IN ('aprobado', 'rechazado')
            ORDER BY c.fecha_envio DESC
            LIMIT :limit
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un comprobante detallado por su ID.
     *
     * @param int $id
     * @return array|false
     */
    public function getById($tableTablaOId, ?int $id = null): array|false {
        $id = (is_int($tableTablaOId) || is_numeric($tableTablaOId)) ? (int)$tableTablaOId : (int)$id;
        $db = $this->db();
        
        $sql = "
            SELECT 
                c.*,
                f.numero_factura,
                f.monto_total,
                f.saldo,
                CONCAT(p.nombre, ' ', p.apellido) as residente,
                p.cedula,
                u.numero as unidad
            FROM comprobantes_pago c
            INNER JOIN facturas f ON c.factura_id = f.id
            INNER JOIN personas p ON c.residente_id = p.id
            INNER JOIN unidades u ON f.unidad_id = u.id
            WHERE c.id = :id
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        return $stmt->fetch() ?: false;
    }

    /**
     * Obtiene los comprobantes de pago procesados (historial) aplicando filtros opcionales.
     * Excluye expresamente los comprobantes con estado 'pendiente'.
     *
     * @param string|array $estadoOrFiltros Filtro de estado o array asociativo de filtros
     * @param string $buscar Texto para buscar en nombre, cédula o número de factura
     * @param int $pagina Número de página actual
     * @param int $porPagina Cantidad de registros por página
     * @param int|null $edificioId Filtro por ID de edificio
     * @param string $unidad Filtro por número de unidad/apartamento
     * @param string $fechaDesde Fecha inicial de pago (YYYY-MM-DD)
     * @param string $fechaHasta Fecha final de pago (YYYY-MM-DD)
     * @return array
     */
    public function getAllFiltered($estadoOrFiltros = '', $buscar = '', int $pagina = 1, int $porPagina = 20, $edificioId = null, $unidad = '', $fechaDesde = '', $fechaHasta = ''): array {
        if (is_array($estadoOrFiltros)) {
            $filtros = $estadoOrFiltros;
            $estado = trim((string)($filtros['estado'] ?? ''));
            $buscar = trim((string)($filtros['buscar'] ?? ''));
            $pagina = max(1, intval($filtros['pagina'] ?? ($filtros['page'] ?? $pagina)));
            $porPagina = max(1, intval($filtros['porPagina'] ?? $porPagina));
            $edificioId = !empty($filtros['edificio_id']) ? intval($filtros['edificio_id']) : (!empty($filtros['edificio']) ? intval($filtros['edificio']) : null);
            $unidad = trim((string)($filtros['unidad'] ?? ''));
            $fechaDesde = trim((string)($filtros['fecha_desde'] ?? ''));
            $fechaHasta = trim((string)($filtros['fecha_hasta'] ?? ''));
        } else {
            $estado = trim((string)$estadoOrFiltros);
            $buscar = trim((string)$buscar);
            $unidad = trim((string)$unidad);
            $fechaDesde = trim((string)$fechaDesde);
            $fechaHasta = trim((string)$fechaHasta);
            $edificioId = !empty($edificioId) ? intval($edificioId) : null;
        }

        $baseSql = "
            SELECT 
                c.*,
                f.numero_factura,
                u.numero as unidad,
                e.nombre as edificio,
                e.id as edificio_id,
                CONCAT(p.nombre, ' ', p.apellido) as residente,
                p.cedula
            FROM comprobantes_pago c
            INNER JOIN facturas f ON c.factura_id = f.id
            INNER JOIN unidades u ON f.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            INNER JOIN personas p ON c.residente_id = p.id
            WHERE c.estado != 'pendiente'
        ";
        
        $countSql = "SELECT COUNT(*) as total FROM comprobantes_pago c
                     INNER JOIN facturas f ON c.factura_id = f.id
                     INNER JOIN unidades u ON f.unidad_id = u.id
                     LEFT JOIN edificios e ON u.edificio_id = e.id
                     INNER JOIN personas p ON c.residente_id = p.id
                     WHERE c.estado != 'pendiente'";
        
        $params = [];
        
        if (!empty($estado)) {
            if ($estado === 'pendiente') {
                $baseSql .= " AND 1=0";
                $countSql .= " AND 1=0";
            } else {
                $baseSql .= " AND c.estado = :estado";
                $countSql .= " AND c.estado = :estado";
                $params['estado'] = $estado;
            }
        }
        
        if (!empty($buscar)) {
            $likeClause = " AND (p.nombre LIKE :buscar OR p.apellido LIKE :buscar OR p.cedula LIKE :buscar OR f.numero_factura LIKE :buscar)";
            $baseSql .= $likeClause;
            $countSql .= $likeClause;
            $params['buscar'] = '%' . $buscar . '%';
        }

        if (!empty($edificioId)) {
            $baseSql .= " AND u.edificio_id = :edificio_id";
            $countSql .= " AND u.edificio_id = :edificio_id";
            $params['edificio_id'] = $edificioId;
        }

        if (!empty($unidad)) {
            $baseSql .= " AND u.numero LIKE :unidad";
            $countSql .= " AND u.numero LIKE :unidad";
            $params['unidad'] = '%' . $unidad . '%';
        }

        if (!empty($fechaDesde)) {
            $baseSql .= " AND c.fecha_pago >= :fecha_desde";
            $countSql .= " AND c.fecha_pago >= :fecha_desde";
            $params['fecha_desde'] = $fechaDesde;
        }

        if (!empty($fechaHasta)) {
            $baseSql .= " AND c.fecha_pago <= :fecha_hasta";
            $countSql .= " AND c.fecha_pago <= :fecha_hasta";
            $params['fecha_hasta'] = $fechaHasta;
        }
        
        return $this->paginate($baseSql, $countSql, $params, $pagina, $porPagina, 'c.fecha_envio DESC');
    }

    /**
     * Aprueba un comprobante y deduce el monto del saldo de la factura.
     *
     * @param int $id
     * @param string $observaciones
     * @return bool
     */
    public function aprobar($id, $observaciones) {
        $db = $this->db();
        
        try {
            $db->beginTransaction();
            
            // Obtener comprobante con FOR UPDATE para bloquear fila contra aprobación concurrente
            $stmtLock = $db->prepare("SELECT * FROM comprobantes_pago WHERE id = :id FOR UPDATE");
            $stmtLock->execute(['id' => $id]);
            $comprobante = $stmtLock->fetch(PDO::FETCH_ASSOC);
            
            if (!$comprobante || $comprobante['estado'] !== 'pendiente') {
                $db->rollBack();
                return false;
            }

            // Bloquear la factura asociada para obtener la unidad
            $stmtFacturaLock = $db->prepare("SELECT id, unidad_id, saldo, monto_pagado FROM facturas WHERE id = :factura_id FOR UPDATE");
            $stmtFacturaLock->execute(['factura_id' => $comprobante['factura_id']]);
            $factura = $stmtFacturaLock->fetch(PDO::FETCH_ASSOC);

            if (!$factura) {
                $db->rollBack();
                return false;
            }

            $unidadId = intval($factura['unidad_id']);
            $montoComprobante = floatval($comprobante['monto']);

            // Guardia contra aprobación de gemelos (mismo pago / referencia ya aprobado)
            $refNorm = $comprobante['referencia_norm'] ?? PagoModel::normalizarReferenciaPago($comprobante['referencia'] ?? null);
            if (!empty($refNorm)) {
                // 1. Chequeo de comprobante gemelo ya aprobado para la misma unidad
                $stmtTwinComp = $db->prepare("
                    SELECT c.id FROM comprobantes_pago c
                    INNER JOIN facturas f ON c.factura_id = f.id
                    WHERE f.unidad_id = :unidad_id 
                      AND c.referencia_norm = :ref_norm 
                      AND c.estado = 'aprobado' 
                      AND c.id != :id
                    LIMIT 1
                ");
                $stmtTwinComp->execute([
                    'unidad_id' => $unidadId,
                    'ref_norm'  => $refNorm,
                    'id'        => $id
                ]);
                if ($stmtTwinComp->fetch()) {
                    $db->rollBack();
                    return false;
                }

                // 2. Chequeo de pago gemelo ya aprobado en tabla pagos para la misma unidad
                $stmtTwinPago = $db->prepare("
                    SELECT id FROM pagos 
                    WHERE unidad_id = :unidad_id 
                      AND referencia_norm = :ref_norm 
                      AND estado = 'APROBADO'
                    LIMIT 1
                ");
                $stmtTwinPago->execute([
                    'unidad_id' => $unidadId,
                    'ref_norm'  => $refNorm
                ]);
                if ($stmtTwinPago->fetch()) {
                    $db->rollBack();
                    return false;
                }
            } else {
                // Fallback sin referencia: misma fecha y mismo monto ya aprobado para esta unidad
                $stmtTwinFallback = $db->prepare("
                    SELECT c.id FROM comprobantes_pago c
                    INNER JOIN facturas f ON c.factura_id = f.id
                    WHERE f.unidad_id = :unidad_id 
                      AND c.fecha_pago = :fecha_pago 
                      AND c.monto = :monto 
                      AND c.estado = 'aprobado' 
                      AND c.id != :id
                    LIMIT 1
                ");
                $stmtTwinFallback->execute([
                    'unidad_id'  => $unidadId,
                    'fecha_pago' => $comprobante['fecha_pago'],
                    'monto'      => $montoComprobante,
                    'id'         => $id
                ]);
                if ($stmtTwinFallback->fetch()) {
                    $db->rollBack();
                    return false;
                }
            }

            // Liquidación en cascada y generación de saldo a favor
            $liquidacionService = new \App\Services\LiquidacionPagoService();
            $refTexto = !empty($comprobante['referencia']) ? "Ref. " . $comprobante['referencia'] : "Comprobante #{$id}";
            $resumenLiq = $liquidacionService->aplicarPagoAUnidad(
                $db,
                $unidadId,
                $montoComprobante,
                $refTexto,
                $id,
                'comprobante'
            );

            // Contexto contable para trazabilidad y compatibilidad de auditoría
            $saldoAFavor = $resumenLiq['saldo_a_favor_generado'];
            $siguienteFactura = count($resumenLiq['facturas_afectadas']) > 1 ? $resumenLiq['facturas_afectadas'][1] : null;
            
            // Actualizar comprobante
            $stmtComprobante = $db->prepare("UPDATE comprobantes_pago SET estado = 'aprobado', observaciones = :observaciones WHERE id = :id");
            $stmtComprobante->execute([
                'observaciones' => $observaciones,
                'id'            => $id
            ]);

            // Registro de auditoría
            $adminId = Auth::id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmtLog = $db->prepare("
                INSERT INTO log_auditoria (usuario_id, admin_id, accion, tabla_afectada, registro_id, estado_anterior, estado_nuevo, detalles, ip_address)
                VALUES (:usuario_id, :admin_id, 'aprobar_comprobante', 'comprobantes_pago', :registro_id, 'pendiente', 'aprobado', :detalles, :ip)
            ");
            $stmtLog->execute([
                'usuario_id' => $adminId,
                'admin_id'   => $adminId,
                'registro_id'=> $id,
                'detalles'   => 'Monto: ' . $comprobante['monto'] . ' | Factura ID: ' . $comprobante['factura_id'] . ' | ' . $observaciones,
                'ip'         => $ip
            ]);
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al aprobar comprobante: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Rechaza un comprobante.
     *
     * @param int $id
     * @param string $observaciones
     * @return bool
     */
    public function rechazar($id, $observaciones) {
        $db = $this->db();

        try {
            $db->beginTransaction();

            // Obtener estado anterior
            $stmtPrev = $db->prepare("SELECT estado FROM comprobantes_pago WHERE id = :id FOR UPDATE");
            $stmtPrev->execute(['id' => $id]);
            $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);

            if (!$prev || $prev['estado'] !== 'pendiente') {
                $db->rollBack();
                return false;
            }

            $stmt = $db->prepare("UPDATE comprobantes_pago SET estado = 'rechazado', observaciones = :observaciones WHERE id = :id");
            $stmt->execute([
                'observaciones' => $observaciones,
                'id'            => $id
            ]);

            // Registro de auditoría
            $adminId = Auth::id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmtLog = $db->prepare("
                INSERT INTO log_auditoria (usuario_id, admin_id, accion, tabla_afectada, registro_id, estado_anterior, estado_nuevo, detalles, ip_address)
                VALUES (:usuario_id, :admin_id, 'rechazar_comprobante', 'comprobantes_pago', :registro_id, 'pendiente', 'rechazado', :detalles, :ip)
            ");
            $stmtLog->execute([
                'usuario_id' => $adminId,
                'admin_id'   => $adminId,
                'registro_id'=> $id,
                'detalles'   => $observaciones,
                'ip'         => $ip
            ]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al rechazar comprobante: " . $e->getMessage());
            return false;
        }
    }
}
