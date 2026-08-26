<?php
namespace App\Models;

use PDO;

class PagoModel extends BaseModel {
    /**
     * Guarda el comprobante en public/uploads/ e inserta en la tabla pagos con estado PENDIENTE.
     *
     * @param int $residenteId
     * @param int $unidadId
     * @param array $datos
     * @param array|string $archivo Si es array es $_FILES['comprobante'], si es string es el nombre de archivo ya guardado.
     * @return bool
     */
    public function crearPago($residenteId, $unidadId, $datos, $archivo) {
        $filename = is_array($archivo) ? ($archivo['name'] ?? '') : (string) $archivo;
        if (is_array($archivo) && isset($archivo['tmp_name']) && file_exists($archivo['tmp_name'])) {
            $ext = pathinfo($archivo['name'], PATHINFO_EXTENSION);
            $filename = bin2hex(random_bytes(16)) . ($ext ? ".{$ext}" : '');
            $destDir = UPLOADS_PATH . '/comprobantes';
            if (!file_exists($destDir)) {
                mkdir($destDir, 0755, true);
            }
            if (!move_uploaded_file($archivo['tmp_name'], $destDir . '/' . $filename)) {
                return false;
            }
        }

        $db = $this->db();
        $sql = "INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, archivo, observaciones, estado, banco_pagador, banco_receptor)
                VALUES (:residente_id, :unidad_id, :monto, :fecha_pago, :metodo_pago, :referencia, :archivo, :observaciones, 'PENDIENTE', :banco_pagador, :banco_receptor)";
        
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            'residente_id'  => $residenteId,
            'unidad_id'     => $unidadId,
            'monto'         => floatval($datos['monto']),
            'fecha_pago'    => $datos['fecha_pago'],
            'metodo_pago'   => $datos['metodo_pago'] ?? '',
            'referencia'    => !empty($datos['referencia']) ? trim($datos['referencia']) : null,
            'archivo'       => $filename,
            'observaciones' => !empty($datos['observaciones']) ? trim($datos['observaciones']) : null,
            'banco_pagador' => !empty($datos['banco_pagador']) ? trim($datos['banco_pagador']) : null,
            'banco_receptor'=> !empty($datos['banco_receptor']) ? trim($datos['banco_receptor']) : null
        ]);
    }

    /**
     * Lista los pagos del residente autenticado, con JOIN a edificios y unidades.
     *
     * @param int $residenteId
     * @return array
     */
    public function obtenerPagosPorResidente($residenteId) {
        $db = $this->db();
        $sql = "SELECT p.*, u.numero AS unidad_numero, e.nombre AS edificio_nombre
                FROM pagos p
                INNER JOIN unidades u ON p.unidad_id = u.id
                LEFT JOIN edificios e ON u.edificio_id = e.id
                WHERE p.residente_id = :residente_id
                ORDER BY p.fecha_registro DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['residente_id' => $residenteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    public function cambiarEstado($pagoId, $nuevoEstado, $motivo, $adminId) {
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
            $stmtUpdate = $db->prepare("UPDATE pagos SET estado = :estado WHERE id = :id");
            $stmtUpdate->execute([
                'estado' => $nuevoEstado,
                'id'     => $pagoId
            ]);
            
            // Crear el registro de auditoría
            $stmtLog = $db->prepare("
                INSERT INTO log_auditoria (pago_id, admin_id, estado_anterior, estado_nuevo, motivo)
                VALUES (:pago_id, :admin_id, :estado_anterior, :estado_nuevo, :motivo)
            ");
            $stmtLog->execute([
                'pago_id'         => $pagoId,
                'admin_id'        => $adminId,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo'    => $nuevoEstado,
                'motivo'          => !empty($motivo) ? trim($motivo) : null
            ]);
            
            $db->commit();
            return true;
            
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Error al cambiar estado del pago (ID: {$pagoId}): " . $e->getMessage());
            return false;
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
    public function aprobarLote(array $pagoIds, int $adminId): array {
        // Filtrar y validar IDs
        $ids = array_filter(array_map('intval', $pagoIds), fn($id) => $id > 0);
        $totalOriginal = count($ids);

        if ($totalOriginal === 0) {
            return ['procesados' => 0, 'omitidos' => 0];
        }

        if ($totalOriginal > 50) {
            throw new \Exception("El lote de aprobación masiva no puede superar los 50 pagos por operación.");
        }

        // Ordenamiento numérico ascendente estricto para evitar deadlocks en FOR UPDATE
        sort($ids, SORT_NUMERIC);

        $db = $this->db();
        $procesados = 0;

        try {
            $db->beginTransaction();

            $inClause = implode(',', array_fill(0, count($ids), '?'));
            
            // Bloquear filas elegibles únicamente (PENDIENTE o EN REVISIÓN)
            $sqlSelect = "SELECT id, estado FROM pagos WHERE id IN ({$inClause}) AND estado IN ('PENDIENTE', 'EN REVISIÓN') ORDER BY id ASC FOR UPDATE";
            $stmtSelect = $db->prepare($sqlSelect);
            $stmtSelect->execute($ids);
            $pagosElegibles = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($pagosElegibles)) {
                $stmtUpdate = $db->prepare("UPDATE pagos SET estado = 'APROBADO' WHERE id = :id");
                $stmtLog = $db->prepare("
                    INSERT INTO log_auditoria (pago_id, admin_id, estado_anterior, estado_nuevo, motivo)
                    VALUES (:pago_id, :admin_id, :estado_anterior, 'APROBADO', 'Aprobación masiva por lote')
                ");

                foreach ($pagosElegibles as $pago) {
                    $stmtUpdate->execute(['id' => $pago['id']]);
                    $stmtLog->execute([
                        'pago_id'         => $pago['id'],
                        'admin_id'        => $adminId,
                        'estado_anterior' => $pago['estado']
                    ]);
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
