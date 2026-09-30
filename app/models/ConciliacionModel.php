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
            (banco, fecha_movimiento, referencia_bancaria, referencia, descripcion_banco, descripcion, monto, tipo_movimiento, estado_conciliacion, lote_importacion)
            VALUES (:banco, :fecha, :ref_bancaria, :referencia, :desc_banco, :descripcion, :monto, :tipo, :estado, :lote)
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
            $estado = ($tipo === 'debito') ? 'descartado' : 'pendiente';
            if ($tipo === 'debito') {
                $debitos++;
            }

            $stmtInsert->execute([
                'banco'         => $banco,
                'fecha'         => $fecha,
                'ref_bancaria'  => $referencia,
                'referencia'    => $referencia,
                'desc_banco'    => $desc,
                'descripcion'   => $desc,
                'monto'         => $montoAbs,
                'tipo'          => $tipo,
                'estado'        => $estado,
                'lote'          => $lote
            ]);

            $insertados++;
        }

        return [
            'insertados' => $insertados,
            'duplicados' => $duplicados,
            'debitos'    => $debitos
        ];
    }

    /**
     * Obtiene los extractos bancarios pendientes de conciliación (solo créditos).
     */
    public function obtenerExtractosPendientes(?string $lote = null): array {
        $db = $this->db();
        $sql = "
            SELECT * FROM extractos_bancarios 
            WHERE tipo_movimiento = 'credito' AND estado_conciliacion = 'pendiente'
        ";
        $params = [];

        if (!empty($lote)) {
            $sql .= " AND lote_importacion = :lote";
            $params['lote'] = $lote;
        }

        $sql .= " ORDER BY fecha_movimiento DESC, id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca un extracto bancario como conciliado y le vincula el pago y administrador responsable.
     */
    public function marcarConciliado(int $extractoId, int $pagoId, int $adminId): bool {
        $db = $this->db();
        $stmt = $db->prepare("
            UPDATE extractos_bancarios 
            SET estado_conciliacion = 'conciliado', pago_id = :pago_id, admin_id = :admin_id 
            WHERE id = :id
        ");
        return $stmt->execute([
            'pago_id'  => $pagoId,
            'admin_id' => $adminId,
            'id'       => $extractoId
        ]);
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
