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
    public function getRecientesByResidente($residente_id, $limit = 10) {
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
        // Usamos bindValue para pasar el límite como entero
        $stmt->bindValue(':residente_id', $residente_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Inserta un nuevo comprobante de pago en la base de datos.
     *
     * @param array $data
     * @return bool
     */
    public function create(array $data): string|false {
        $db = $this->db();
        
        $sql = "
            INSERT INTO comprobantes_pago 
            (residente_id, factura_id, monto, metodo_pago, referencia, fecha_pago, archivo, observaciones) 
            VALUES (:residente_id, :factura_id, :monto, :metodo_pago, :referencia, :fecha_pago, :archivo, :observaciones)
        ";
        
        $stmt = $db->prepare($sql);
        $ok = $stmt->execute([
            'residente_id'  => $data['residente_id'],
            'factura_id'    => $data['factura_id'],
            'monto'         => $data['monto'],
            'metodo_pago'   => $data['metodo_pago'],
            'referencia'    => $data['referencia'],
            'fecha_pago'    => $data['fecha_pago'],
            'archivo'       => $data['archivo'],
            'observaciones' => $data['observaciones']
        ]);
        return $ok ? (string) $db->lastInsertId() : false;
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
    public function getProcesados($limit = 5) {
        $db = $this->db();
        
        $sql = "
            SELECT 
                c.*,
                f.numero_factura,
                CONCAT(p.nombre, ' ', p.apellido) as residente
            FROM comprobantes_pago c
            INNER JOIN facturas f ON c.factura_id = f.id
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
    public function getById($id) {
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
        
        return $stmt->fetch();
    }

    /**
     * Obtiene todos los comprobantes aplicando filtros opcionales.
     *
     * @param string $estado
     * @param string $buscar
     * @param int $pagina
     * @param int $porPagina
     * @return array
     */
    public function getAllFiltered($estado = '', $buscar = '', int $pagina = 1, int $porPagina = 20): array {
        $baseSql = "
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
            WHERE 1=1
        ";
        
        $countSql = "SELECT COUNT(*) as total FROM comprobantes_pago c
                     INNER JOIN facturas f ON c.factura_id = f.id
                     INNER JOIN unidades u ON f.unidad_id = u.id
                     INNER JOIN personas p ON c.residente_id = p.id
                     WHERE 1=1";
        
        $params = [];
        
        if (!empty($estado)) {
            $baseSql .= " AND c.estado = :estado";
            $countSql .= " AND c.estado = :estado";
            $params['estado'] = $estado;
        }
        
        if (!empty($buscar)) {
            $likeClause = " AND (p.nombre LIKE :buscar OR p.apellido LIKE :buscar OR p.cedula LIKE :buscar OR f.numero_factura LIKE :buscar)";
            $baseSql .= $likeClause;
            $countSql .= $likeClause;
            $params['buscar'] = '%' . $buscar . '%';
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

            // Bloquear la factura asociada para evitar deducción concurrente del saldo
            $stmtFacturaLock = $db->prepare("SELECT id, saldo, monto_pagado FROM facturas WHERE id = :factura_id FOR UPDATE");
            $stmtFacturaLock->execute(['factura_id' => $comprobante['factura_id']]);
            $factura = $stmtFacturaLock->fetch(PDO::FETCH_ASSOC);

            if (!$factura) {
                $db->rollBack();
                return false;
            }
            
            $nuevo_saldo = max($factura['saldo'] - $comprobante['monto'], 0);
            $nuevo_pagado = $factura['monto_pagado'] + $comprobante['monto'];
            $saldoAFavor = $comprobante['monto'] - $factura['saldo'];
            
            // Actualizar saldo de factura
            $stmtFactura = $db->prepare("UPDATE facturas SET saldo = :saldo, monto_pagado = :monto_pagado, estado = :estado WHERE id = :factura_id");
            $stmtFactura->execute([
                'saldo'       => $nuevo_saldo,
                'monto_pagado'=> $nuevo_pagado,
                'estado'      => $nuevo_saldo <= 0 ? 'pagada' : 'pendiente',
                'factura_id'  => $comprobante['factura_id']
            ]);

            // 6B.5: Si hay saldo a favor, aplicar automáticamente a la siguiente factura pendiente
            if ($saldoAFavor > 0.01 && !empty($comprobante['residente_id'])) {
                $stmtSigiente = $db->prepare(
                    "SELECT f.id, f.saldo FROM facturas f
                     INNER JOIN unidades u ON f.unidad_id = u.id
                     WHERE u.propietario_id = :pid AND f.saldo > 0 AND f.id != :factura_id
                     AND f.deleted_at IS NULL
                     ORDER BY f.fecha_vencimiento ASC LIMIT 1"
                );
                $stmtSigiente->execute(['pid' => $comprobante['residente_id'], 'factura_id' => $comprobante['factura_id']]);
                $siguienteFactura = $stmtSigiente->fetch(PDO::FETCH_ASSOC);

                if ($siguienteFactura && $saldoAFavor > 0.01) {
                    $abono = min($saldoAFavor, $siguienteFactura['saldo']);
                    $nuevoSaldoSig = $siguienteFactura['saldo'] - $abono;
                    $stmtAbono = $db->prepare(
                        "UPDATE facturas SET saldo = :saldo, monto_pagado = monto_pagado + :abono,
                         estado = :estado WHERE id = :fid"
                    );
                    $stmtAbono->execute([
                        'saldo'   => $nuevoSaldoSig,
                        'abono'   => $abono,
                        'estado'  => $nuevoSaldoSig <= 0 ? 'pagada' : 'pendiente',
                        'fid'     => $siguienteFactura['id']
                    ]);
                }
            }
            
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
