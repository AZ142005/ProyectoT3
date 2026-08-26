<?php
namespace App\Models;

use PDO;
use Exception;
use App\Core\Auth;

class GastosModel extends BaseModel {

    protected string $table = 'gastos_comunes';

    /**
     * Registra un nuevo gasto común validando duplicados de factura por proveedor y período.
     *
     * @param array $datos
     * @return int ID del gasto creado
     * @throws Exception
     */
    public function crearGasto(array $datos): int {
        $mes = intval($datos['mes'] ?? 0);
        $anio = intval($datos['anio'] ?? 0);
        $nroFactura = !empty($datos['nro_factura_proveedor']) ? trim($datos['nro_factura_proveedor']) : null;
        $proveedor = trim($datos['proveedor'] ?? '');

        if ($mes < 1 || $mes > 12 || $anio < 2000) {
            throw new Exception("El período (mes/año) especificado es inválido.");
        }

        if (empty($proveedor) || empty($datos['descripcion'])) {
            throw new Exception("El proveedor y la descripción del gasto son obligatorios.");
        }

        $montoTotal = floatval($datos['monto_total'] ?? 0);
        if ($montoTotal <= 0) {
            throw new Exception("El monto total del gasto debe ser superior a 0.");
        }

        $db = $this->db();

        // Validación anti-duplicados si se especifica número de factura
        if (!empty($nroFactura)) {
            $stmtCheck = $db->prepare("
                SELECT id FROM gastos_comunes 
                WHERE mes = :mes AND anio = :anio AND nro_factura_proveedor = :factura AND proveedor = :proveedor AND deleted_at IS NULL
            ");
            $stmtCheck->execute([
                'mes'       => $mes,
                'anio'      => $anio,
                'factura'   => $nroFactura,
                'proveedor' => $proveedor
            ]);

            if ($stmtCheck->rowCount() > 0) {
                throw new Exception("Ya existe un gasto registrado con el Nro. de Factura '{$nroFactura}' del proveedor '{$proveedor}' para el período {$mes}/{$anio}.");
            }
        }

        $sql = "
            INSERT INTO gastos_comunes 
            (categoria_id, mes, anio, descripcion, monto_total, fecha_gasto, proveedor, nro_factura_proveedor, soporte_digital, admin_id)
            VALUES (:cat_id, :mes, :anio, :desc, :monto, :fecha, :prov, :nro_fac, :soporte, :admin_id)
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'cat_id'   => intval($datos['categoria_id']),
            'mes'      => $mes,
            'anio'     => $anio,
            'desc'     => trim($datos['descripcion']),
            'monto'    => $montoTotal,
            'fecha'    => !empty($datos['fecha_gasto']) ? $datos['fecha_gasto'] : date('Y-m-d'),
            'prov'     => $proveedor,
            'nro_fac'  => $nroFactura,
            'soporte'  => !empty($datos['soporte_digital']) ? trim($datos['soporte_digital']) : null,
            'admin_id' => intval($datos['admin_id'])
        ]);

        $gastoId = intval($db->lastInsertId());

        // Registro de auditoría
        $adminId = Auth::id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmtLog = $db->prepare("
            INSERT INTO log_auditoria (usuario_id, admin_id, accion, tabla_afectada, registro_id, estado_nuevo, detalles, ip_address)
            VALUES (:usuario_id, :admin_id, 'crear_gasto', 'gastos_comunes', :registro_id, 'activo', :detalles, :ip)
        ");
        $stmtLog->execute([
            'usuario_id' => $adminId,
            'admin_id'   => $adminId,
            'registro_id'=> $gastoId,
            'detalles'   => 'Proveedor: ' . $proveedor . ' | Monto: ' . $montoTotal . ' | Período: ' . $mes . '/' . $anio,
            'ip'         => $ip
        ]);

        return $gastoId;
    }

    /**
     * Obtiene el listado paginado de gastos para el panel de administración.
     */
    public function obtenerGastosAdmin(int $pagina = 1, int $porPagina = 15, array $filtros = []): array {
        $where = "WHERE g.deleted_at IS NULL";
        $params = [];

        if (!empty($filtros['mes'])) {
            $where .= " AND g.mes = :mes";
            $params['mes'] = intval($filtros['mes']);
        }
        if (!empty($filtros['anio'])) {
            $where .= " AND g.anio = :anio";
            $params['anio'] = intval($filtros['anio']);
        }
        if (!empty($filtros['categoria_id'])) {
            $where .= " AND g.categoria_id = :cat_id";
            $params['cat_id'] = intval($filtros['categoria_id']);
        }

        $baseSql = "
            SELECT g.*, c.nombre AS categoria_nombre, c.icono AS categoria_icono, c.color AS categoria_color,
                   u.nombre_completo AS admin_nombre
            FROM gastos_comunes g
            INNER JOIN categorias_gastos c ON g.categoria_id = c.id
            INNER JOIN usuarios u ON g.admin_id = u.id
            {$where}
        ";

        $countSql = "SELECT COUNT(*) AS total FROM gastos_comunes g {$where}";

        return $this->paginate($baseSql, $countSql, $params, $pagina, $porPagina, 'g.fecha_gasto DESC');
    }

    /**
     * Obtiene todos los gastos comunes de un período para el visor de residentes.
     */
    public function obtenerGastosPorPeriodo(int $mes, int $anio): array {
        $db = $this->db();
        $sql = "
            SELECT g.*, c.nombre AS categoria_nombre, c.icono AS categoria_icono, c.color AS categoria_color
            FROM gastos_comunes g
            INNER JOIN categorias_gastos c ON g.categoria_id = c.id
            WHERE g.mes = :mes AND g.anio = :anio AND g.deleted_at IS NULL
            ORDER BY c.nombre ASC, g.fecha_gasto ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute(['mes' => $mes, 'anio' => $anio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los totales acumulados por categoría para generar gráficos de distribución.
     */
    public function obtenerTotalesPorCategoria(int $mes, int $anio): array {
        $db = $this->db();
        $sql = "
            SELECT c.id AS categoria_id, c.nombre AS categoria_nombre, c.icono, c.color,
                   COALESCE(SUM(g.monto_total), 0) AS total_monto,
                   COUNT(g.id) AS cantidad_gastos
            FROM categorias_gastos c
            LEFT JOIN gastos_comunes g ON g.categoria_id = c.id AND g.mes = :mes AND g.anio = :anio AND g.deleted_at IS NULL
            WHERE c.activo = 1
            GROUP BY c.id, c.nombre, c.icono, c.color
            ORDER BY total_monto DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute(['mes' => $mes, 'anio' => $anio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula el monto total de gastos de un período.
     */
    public function obtenerTotalGastoMes(int $mes, int $anio): float {
        $db = $this->db();
        $stmt = $db->prepare("SELECT COALESCE(SUM(monto_total), 0) AS total FROM gastos_comunes WHERE mes = :mes AND anio = :anio AND deleted_at IS NULL");
        $stmt->execute(['mes' => $mes, 'anio' => $anio]);
        return floatval($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.0);
    }

    /**
     * Elimina lógicamente un gasto y elimina físicamente su archivo de soporte en disco.
     */
    public function eliminarGasto(int $id): bool {
        $db = $this->db();
        $stmt = $db->prepare("SELECT soporte_digital FROM gastos_comunes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $gasto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gasto) {
            return false;
        }

        // Borrado lógico en base de datos
        $stmtDel = $db->prepare("UPDATE gastos_comunes SET deleted_at = NOW() WHERE id = :id");
        $actualizado = $stmtDel->execute(['id' => $id]);

        if ($actualizado) {
            // Registro de auditoría
            $adminId = Auth::id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmtLog = $db->prepare("
                INSERT INTO log_auditoria (usuario_id, admin_id, accion, tabla_afectada, registro_id, estado_anterior, estado_nuevo, detalles, ip_address)
                VALUES (:usuario_id, :admin_id, 'eliminar_gasto', 'gastos_comunes', :registro_id, 'activo', 'eliminado', :detalles, :ip)
            ");
            $stmtLog->execute([
                'usuario_id' => $adminId,
                'admin_id'   => $adminId,
                'registro_id'=> $id,
                'detalles'   => 'Proveedor: ' . ($gasto['proveedor'] ?? 'N/A') . ' | Soporte eliminado: ' . ($gasto['soporte_digital'] ?? 'ninguno'),
                'ip'         => $ip
            ]);
        }

        // Limpieza física segura del archivo adjunto
        if ($actualizado && !empty($gasto['soporte_digital'])) {
            $archivoRuta = UPLOADS_PATH . '/soportes/' . $gasto['soporte_digital'];
            if (file_exists($archivoRuta) && is_file($archivoRuta)) {
                try {
                    unlink($archivoRuta);
                } catch (\Throwable $e) {
                    error_log("Advertencia: No se pudo eliminar archivo físico de soporte {$archivoRuta}: " . $e->getMessage());
                }
            }
        }

        return $actualizado;
    }
}
