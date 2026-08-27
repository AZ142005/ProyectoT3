<?php
namespace App\Models;

use PDO;

class ReportesModel extends BaseModel {

    /**
     * Obtiene el reporte paginado de morosidad agrupado por unidad habitacional.
     */
    public function obtenerReporteMorosidad(array $filtros = [], int $pagina = 1, int $porPagina = 50): array {
        $where = "WHERE f.saldo > 0 AND f.fecha_vencimiento < CURDATE() AND f.deleted_at IS NULL";
        $params = [];

        if (!empty($filtros['edificio_id'])) {
            $where .= " AND u.edificio_id = :edificio_id";
            $params['edificio_id'] = intval($filtros['edificio_id']);
        }

        if (!empty($filtros['dias_mora'])) {
            $dias = intval($filtros['dias_mora']);
            $where .= " AND f.fecha_vencimiento <= (CURDATE() - INTERVAL :dias DAY)";
            $params['dias'] = $dias;
        }

        $baseSql = "
            SELECT 
                u.id AS unidad_id,
                u.numero AS unidad_numero,
                e.nombre AS edificio_nombre,
                CONCAT(p.nombre, ' ', p.apellido) AS propietario_nombre,
                p.cedula AS propietario_cedula,
                p.telefono AS propietario_telefono,
                p.email AS propietario_email,
                COUNT(f.id) AS facturas_vencidas,
                ROUND(SUM(f.saldo), 2) AS total_deuda,
                MIN(f.fecha_vencimiento) AS fecha_mas_antigua,
                DATEDIFF(CURDATE(), MIN(f.fecha_vencimiento)) AS dias_mora_max
            FROM facturas f
            INNER JOIN unidades u ON f.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas p ON u.propietario_id = p.id
            {$where}
            GROUP BY u.id, e.id, p.id
        ";

        $countSql = "
            SELECT COUNT(DISTINCT u.id) AS total
            FROM facturas f
            INNER JOIN unidades u ON f.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            {$where}
        ";

        return $this->paginate($baseSql, $countSql, $params, $pagina, $porPagina, 'total_deuda DESC');
    }

    /**
     * Obtiene todos los registros morosos sin paginación para exportación e impresión.
     * 6B.4: LIMIT configurable con truncado para evitar Memory Overflow.
     */
    public function obtenerReporteMorosidadCompleto(array $filtros = [], int $limiteMax = 5000): array {
        $limiteMax = max(100, min($limiteMax, 50000));

        $where = "WHERE f.saldo > 0 AND f.fecha_vencimiento < CURDATE() AND f.deleted_at IS NULL";
        $params = [];

        if (!empty($filtros['edificio_id'])) {
            $where .= " AND u.edificio_id = :edificio_id";
            $params['edificio_id'] = intval($filtros['edificio_id']);
        }

        if (!empty($filtros['dias_mora'])) {
            $dias = intval($filtros['dias_mora']);
            $where .= " AND f.fecha_vencimiento <= (CURDATE() - INTERVAL :dias DAY)";
            $params['dias'] = $dias;
        }

        $sql = "
            SELECT 
                u.numero AS unidad_numero,
                COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                CONCAT(p.nombre, ' ', p.apellido) AS propietario_nombre,
                COALESCE(p.cedula, 'N/A') AS propietario_cedula,
                COALESCE(p.telefono, 'N/A') AS propietario_telefono,
                COALESCE(p.email, 'N/A') AS propietario_email,
                COUNT(f.id) AS facturas_vencidas,
                ROUND(SUM(f.saldo), 2) AS total_deuda,
                DATEDIFF(CURDATE(), MIN(f.fecha_vencimiento)) AS dias_mora_max
            FROM facturas f
            INNER JOIN unidades u ON f.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas p ON u.propietario_id = p.id
            {$where}
            GROUP BY u.id, e.id, p.id
            ORDER BY total_deuda DESC
            LIMIT {$limiteMax}
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 6B.6: Caché cross-session de KPIs de morosidad usando archivo temporal.
     * Mejor que $_SESSION: funciona entre usuarios/sesiones y no depende del lifetime PHP.
     */
    public function obtenerKpisMorosidad(bool $forzarRecalculo = false): array {
        $cacheFile = sys_get_temp_dir() . '/kpis_morosidad_cache.json';
        $cacheTtl = 300; // 5 minutos

        // Intentar leer caché de archivo
        if (!$forzarRecalculo && file_exists($cacheFile)) {
            $cacheContent = file_get_contents($cacheFile);
            $cache = json_decode($cacheContent, true);
            if (is_array($cache) && isset($cache['data'], $cache['timestamp'])) {
                if ((time() - $cache['timestamp']) < $cacheTtl) {
                    return $cache['data'];
                }
            }
        }

        $db = $this->db();
        
        $stmtDeuda = $db->query("SELECT COALESCE(ROUND(SUM(saldo), 2), 0) AS total_deuda FROM facturas WHERE saldo > 0 AND fecha_vencimiento < CURDATE() AND deleted_at IS NULL");
        $totalDeuda = floatval($stmtDeuda->fetch(PDO::FETCH_ASSOC)['total_deuda'] ?? 0);

        $stmtUnidades = $db->query("SELECT COUNT(DISTINCT unidad_id) AS unidades_morosas FROM facturas WHERE saldo > 0 AND fecha_vencimiento < CURDATE() AND deleted_at IS NULL");
        $unidadesMorosas = intval($stmtUnidades->fetch(PDO::FETCH_ASSOC)['unidades_morosas'] ?? 0);

        $stmtTotalUnidades = $db->query("SELECT COUNT(*) AS total FROM unidades");
        $totalUnidades = intval($stmtTotalUnidades->fetch(PDO::FETCH_ASSOC)['total'] ?? 1);

        $tasaMorosidad = ($totalUnidades > 0) ? round(($unidadesMorosas / $totalUnidades) * 100, 1) : 0.0;

        $kpis = [
            'total_deuda'      => $totalDeuda,
            'unidades_morosas' => $unidadesMorosas,
            'total_unidades'   => $totalUnidades,
            'tasa_morosidad'   => $tasaMorosidad
        ];

        // Guardar caché en archivo
        file_put_contents($cacheFile, json_encode([
            'data'      => $kpis,
            'timestamp' => time()
        ]), LOCK_EX);

        return $kpis;
    }

    /**
     * Invalida manualmente la caché de KPIs de morosidad.
     */
    public static function invalidarCacheKpis(): void {
        $cacheFile = sys_get_temp_dir() . '/kpis_morosidad_cache.json';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
        // Limpiar también caché legacy de sesión
        unset($_SESSION['kpis_morosidad_cache'], $_SESSION['kpis_morosidad_time']);
    }

    /**
     * Realiza exportación streaming directa en CSV con BOM UTF-8 para compatibilidad con Excel.
     */
    public function exportarCsvStreaming(array $filtros = []): void {
        $datos = $this->obtenerReporteMorosidadCompleto($filtros);

        $filename = 'reporte_morosidad_' . date('Y-m-d_H-i') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Escribir BOM UTF-8 para Microsoft Excel
        fwrite($output, "\xEF\xBB\xBF");

        // Encabezados del CSV
        fputcsv($output, [
            'Edificio / Torre',
            'Unidad / Apto',
            'Propietario',
            'Cédula',
            'Teléfono',
            'Email',
            'Facturas Vencidas',
            'Días de Mora Máx.',
            'Total Deuda ($)'
        ], ';');

        foreach ($datos as $row) {
            fputcsv($output, [
                self::sanitizeCsvField($row['edificio_nombre']),
                self::sanitizeCsvField($row['unidad_numero']),
                self::sanitizeCsvField($row['propietario_nombre']),
                self::sanitizeCsvField($row['propietario_cedula']),
                self::sanitizeCsvField($row['propietario_telefono']),
                self::sanitizeCsvField($row['propietario_email']),
                $row['facturas_vencidas'],
                $row['dias_mora_max'],
                number_format(floatval($row['total_deuda']), 2, '.', '')
            ], ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Consolida el expediente completo de deuda de una unidad habitacional para la carta formal.
     */
    public function obtenerDetalleDeudaUnidad(int $unidadId) {
        $db = $this->db();
        
        $sqlUnidad = "
            SELECT 
                u.id AS unidad_id,
                u.numero AS unidad_numero,
                COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                p.id AS propietario_id,
                CONCAT(p.nombre, ' ', p.apellido) AS propietario_nombre,
                p.cedula AS propietario_cedula,
                p.telefono AS propietario_telefono,
                p.email AS propietario_email
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas p ON u.propietario_id = p.id
            WHERE u.id = :unidad_id
        ";
        
        $stmtU = $db->prepare($sqlUnidad);
        $stmtU->execute(['unidad_id' => $unidadId]);
        $unidad = $stmtU->fetch(PDO::FETCH_ASSOC);

        if (!$unidad) {
            return false;
        }

        $sqlFacturas = "
            SELECT id, numero_factura, mes, anio, fecha_vencimiento, monto_total, saldo, DATEDIFF(CURDATE(), fecha_vencimiento) AS dias_mora
            FROM facturas
            WHERE unidad_id = :unidad_id AND saldo > 0 AND fecha_vencimiento < CURDATE() AND deleted_at IS NULL
            ORDER BY fecha_vencimiento ASC
        ";

        $stmtF = $db->prepare($sqlFacturas);
        $stmtF->execute(['unidad_id' => $unidadId]);
        $facturas = $stmtF->fetchAll(PDO::FETCH_ASSOC);

        $totalDeuda = round(array_reduce($facturas, fn($carry, $f) => $carry + floatval($f['saldo']), 0.0), 2);

        return [
            'unidad'      => $unidad,
            'facturas'    => $facturas,
            'total_deuda' => $totalDeuda
        ];
    }

    /**
     * Previene CSV Injection prefijando campos que comienzan con caracteres peligrosos.
     */
    private static function sanitizeCsvField($value) {
        if (!is_string($value)) {
            return $value;
        }
        $trimmed = ltrim($value);
        if (!empty($trimmed) && in_array($trimmed[0], ['=', '+', '-', '@', "\r", "\n", "\t", ';'], true)) {
            return "\t" . $value;
        }
        return $value;
    }
}
