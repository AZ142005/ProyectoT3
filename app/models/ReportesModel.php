<?php
namespace App\Models;

use PDO;

class ReportesModel extends BaseModel {

    /**
     * Obtiene el reporte paginado de morosidad agrupado por unidad habitacional.
     *
     * @param array $filtros ['edificio_id' => int, 'dias_mora' => int]
     * @param int $pagina
     * @param int $porPagina
     * @return array ['datos' => array, 'total' => int, 'pagina' => int, 'porPagina' => int, 'totalPaginas' => int]
     */
    public function obtenerReporteMorosidad(array $filtros = [], int $pagina = 1, int $porPagina = 50): array {
        $where = "WHERE f.saldo > 0 AND f.fecha_vencimiento < CURDATE()";
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
                SUM(f.saldo) AS total_deuda,
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
     */
    public function obtenerReporteMorosidadCompleto(array $filtros = []): array {
        $where = "WHERE f.saldo > 0 AND f.fecha_vencimiento < CURDATE()";
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
                SUM(f.saldo) AS total_deuda,
                DATEDIFF(CURDATE(), MIN(f.fecha_vencimiento)) AS dias_mora_max
            FROM facturas f
            INNER JOIN unidades u ON f.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas p ON u.propietario_id = p.id
            {$where}
            GROUP BY u.id, e.id, p.id
            ORDER BY total_deuda DESC
            LIMIT 10000
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula métricas KPI globales de morosidad con caché breve en sesión.
     */
    public function obtenerKpisMorosidad(bool $forzarRecalculo = false): array {
        if (!$forzarRecalculo && isset($_SESSION['kpis_morosidad_cache']) && (time() - ($_SESSION['kpis_morosidad_time'] ?? 0) < 300)) {
            return $_SESSION['kpis_morosidad_cache'];
        }

        $db = $this->db();
        
        $stmtDeuda = $db->query("SELECT COALESCE(SUM(saldo), 0) AS total_deuda FROM facturas WHERE saldo > 0 AND fecha_vencimiento < CURDATE()");
        $totalDeuda = floatval($stmtDeuda->fetch(PDO::FETCH_ASSOC)['total_deuda'] ?? 0);

        $stmtUnidades = $db->query("SELECT COUNT(DISTINCT unidad_id) AS unidades_morosas FROM facturas WHERE saldo > 0 AND fecha_vencimiento < CURDATE()");
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

        $_SESSION['kpis_morosidad_cache'] = $kpis;
        $_SESSION['kpis_morosidad_time'] = time();

        return $kpis;
    }

    /**
     * Invalida manualmente la caché de KPIs de morosidad.
     */
    public static function invalidarCacheKpis(): void {
        unset($_SESSION['kpis_morosidad_cache'], $_SESSION['kpis_morosidad_time']);
    }

    /**
     * Realiza exportación streaming directa en CSV con BOM UTF-8 (\xEF\xBB\xBF) para compatibilidad con Excel.
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
                $row['edificio_nombre'],
                $row['unidad_numero'],
                $row['propietario_nombre'],
                $row['propietario_cedula'],
                $row['propietario_telefono'],
                $row['propietario_email'],
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
     *
     * @param int $unidadId
     * @return array|false
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
            WHERE unidad_id = :unidad_id AND saldo > 0 AND fecha_vencimiento < CURDATE()
            ORDER BY fecha_vencimiento ASC
        ";

        $stmtF = $db->prepare($sqlFacturas);
        $stmtF->execute(['unidad_id' => $unidadId]);
        $facturas = $stmtF->fetchAll(PDO::FETCH_ASSOC);

        $totalDeuda = array_reduce($facturas, fn($carry, $f) => $carry + floatval($f['saldo']), 0.0);

        return [
            'unidad'      => $unidad,
            'facturas'    => $facturas,
            'total_deuda' => $totalDeuda
        ];
    }
}
