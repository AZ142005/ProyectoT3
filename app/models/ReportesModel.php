<?php
namespace App\Models;

use PDO;

class ReportesModel extends BaseModel {

    /**
     * Obtiene el reporte paginado de morosidad agrupado por unidad habitacional.
     */
    public function obtenerReporteMorosidad(array $filtros = [], int $pagina = 1, int $porPagina = 50): array {
        $where = "WHERE u.estado = 1";
        $params = [];

        if (!empty($filtros['edificio_id'])) {
            $where .= " AND u.edificio_id = :edificio_id";
            $params['edificio_id'] = intval($filtros['edificio_id']);
        }

        $estadoFiltro = strtolower(trim($filtros['estado'] ?? ''));
        if ($estadoFiltro === 'solvente') {
            $where .= " AND (m.total_deuda IS NULL OR m.total_deuda = 0)";
        } elseif ($estadoFiltro === 'deudor' || $estadoFiltro === 'moroso') {
            $where .= " AND m.total_deuda > 0";
        }

        if (!empty($filtros['dias_mora'])) {
            $dias = intval($filtros['dias_mora']);
            $where .= " AND m.dias_mora_max >= :dias";
            $params['dias'] = $dias;
        }

        $baseSql = "
            SELECT 
                u.id AS unidad_id,
                u.numero AS unidad_numero,
                COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                e.id AS edificio_id,
                CONCAT(COALESCE(p.nombre, ''), ' ', COALESCE(p.apellido, '')) AS propietario_nombre,
                COALESCE(p.cedula, 'N/A') AS propietario_cedula,
                COALESCE(p.telefono, 'N/A') AS propietario_telefono,
                COALESCE(p.email, 'N/A') AS propietario_email,
                COALESCE(m.facturas_vencidas, 0) AS facturas_vencidas,
                COALESCE(m.total_deuda, 0.00) AS total_deuda,
                m.fecha_mas_antigua,
                COALESCE(m.dias_mora_max, 0) AS dias_mora_max,
                CASE 
                    WHEN COALESCE(m.total_deuda, 0.00) > 0 THEN 'deudor'
                    ELSE 'solvente'
                END AS estado_financiero
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas p ON u.propietario_id = p.id
            LEFT JOIN (
                SELECT 
                    f.unidad_id,
                    COUNT(f.id) AS facturas_vencidas,
                    ROUND(SUM(f.saldo), 2) AS total_deuda,
                    MIN(f.fecha_vencimiento) AS fecha_mas_antigua,
                    DATEDIFF(CURDATE(), MIN(f.fecha_vencimiento)) AS dias_mora_max
                FROM facturas f
                WHERE f.saldo > 0 AND f.fecha_vencimiento < CURDATE() AND f.deleted_at IS NULL
                GROUP BY f.unidad_id
            ) m ON m.unidad_id = u.id
            {$where}
        ";

        $countSql = "
            SELECT COUNT(*) AS total
            FROM unidades u
            LEFT JOIN (
                SELECT 
                    f.unidad_id,
                    ROUND(SUM(f.saldo), 2) AS total_deuda,
                    DATEDIFF(CURDATE(), MIN(f.fecha_vencimiento)) AS dias_mora_max
                FROM facturas f
                WHERE f.saldo > 0 AND f.fecha_vencimiento < CURDATE() AND f.deleted_at IS NULL
                GROUP BY f.unidad_id
            ) m ON m.unidad_id = u.id
            {$where}
        ";

        return $this->paginate($baseSql, $countSql, $params, $pagina, $porPagina, 'total_deuda DESC, u.numero ASC');
    }

    /**
     * Obtiene todos los registros sin paginación para exportación e impresión.
     * 6B.4: LIMIT configurable con truncado para evitar Memory Overflow.
     */
    public function obtenerReporteMorosidadCompleto(array $filtros = [], int $limiteMax = 5000): array {
        $limiteMax = max(100, min($limiteMax, 50000));

        $where = "WHERE u.estado = 1";
        $params = [];

        if (!empty($filtros['edificio_id'])) {
            $where .= " AND u.edificio_id = :edificio_id";
            $params['edificio_id'] = intval($filtros['edificio_id']);
        }

        $estadoFiltro = strtolower(trim($filtros['estado'] ?? ''));
        if ($estadoFiltro === 'solvente') {
            $where .= " AND (m.total_deuda IS NULL OR m.total_deuda = 0)";
        } elseif ($estadoFiltro === 'deudor' || $estadoFiltro === 'moroso') {
            $where .= " AND m.total_deuda > 0";
        }

        if (!empty($filtros['dias_mora'])) {
            $dias = intval($filtros['dias_mora']);
            $where .= " AND m.dias_mora_max >= :dias";
            $params['dias'] = $dias;
        }

        $sql = "
            SELECT 
                u.numero AS unidad_numero,
                COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                CONCAT(COALESCE(p.nombre, ''), ' ', COALESCE(p.apellido, '')) AS propietario_nombre,
                COALESCE(p.cedula, 'N/A') AS propietario_cedula,
                COALESCE(p.telefono, 'N/A') AS propietario_telefono,
                COALESCE(p.email, 'N/A') AS propietario_email,
                COALESCE(m.facturas_vencidas, 0) AS facturas_vencidas,
                COALESCE(m.total_deuda, 0.00) AS total_deuda,
                COALESCE(m.dias_mora_max, 0) AS dias_mora_max,
                CASE 
                    WHEN COALESCE(m.total_deuda, 0.00) > 0 THEN 'deudor'
                    ELSE 'solvente'
                END AS estado_financiero
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas p ON u.propietario_id = p.id
            LEFT JOIN (
                SELECT 
                    f.unidad_id,
                    COUNT(f.id) AS facturas_vencidas,
                    ROUND(SUM(f.saldo), 2) AS total_deuda,
                    MIN(f.fecha_vencimiento) AS fecha_mas_antigua,
                    DATEDIFF(CURDATE(), MIN(f.fecha_vencimiento)) AS dias_mora_max
                FROM facturas f
                WHERE f.saldo > 0 AND f.fecha_vencimiento < CURDATE() AND f.deleted_at IS NULL
                GROUP BY f.unidad_id
            ) m ON m.unidad_id = u.id
            {$where}
            ORDER BY total_deuda DESC, u.numero ASC
            LIMIT {$limiteMax}
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 6B.6: Caché cross-session de KPIs de balance y morosidad usando archivo temporal.
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

        $stmtTotalUnidades = $db->query("SELECT COUNT(*) AS total FROM unidades WHERE estado = 1");
        $totalUnidades = intval($stmtTotalUnidades->fetch(PDO::FETCH_ASSOC)['total'] ?? 1);

        $unidadesSolventes = max(0, $totalUnidades - $unidadesMorosas);
        $tasaMorosidad = ($totalUnidades > 0) ? round(($unidadesMorosas / $totalUnidades) * 100, 1) : 0.0;
        $tasaSolvencia = ($totalUnidades > 0) ? round(($unidadesSolventes / $totalUnidades) * 100, 1) : 0.0;

        $kpis = [
            'total_deuda'        => $totalDeuda,
            'unidades_morosas'   => $unidadesMorosas,
            'unidades_solventes' => $unidadesSolventes,
            'total_unidades'     => $totalUnidades,
            'tasa_morosidad'     => $tasaMorosidad,
            'tasa_solvencia'     => $tasaSolvencia
        ];

        // Atomic write: temp file → rename prevents race condition
        $tempFile = $cacheFile . '.' . getmypid() . '.tmp';
        file_put_contents($tempFile, json_encode([
            'data'      => $kpis,
            'timestamp' => time()
        ]));
        rename($tempFile, $cacheFile);

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
     * Obtiene el resumen financiero consolidado para el Dashboard de Administración (solo lectura).
     * Incluye KPIs de balance, recaudación, egresos y estado de cartera.
     *
     * @param int $mes
     * @param int $anio
     * @return array
     */
    public function obtenerResumenFinancieroDashboard(int $mes, int $anio): array {
        $db = $this->db();

        // 1. KPIs de Morosidad y Solvencia (utiliza caché de 5 min)
        $kpisMorosidad = $this->obtenerKpisMorosidad();

        // Total adeudado global (saldo acumulado por cobrar en facturas pendientes)
        $stmtCobrar = $db->query("
            SELECT COALESCE(ROUND(SUM(saldo), 2), 0) AS total_por_cobrar
            FROM facturas
            WHERE saldo > 0 AND deleted_at IS NULL
        ");
        $totalPorCobrar = floatval($stmtCobrar->fetch(PDO::FETCH_ASSOC)['total_por_cobrar'] ?? 0);

        // 2. Ingresos del mes (pagos aprobados)
        $stmtIngresosPagos = $db->prepare("
            SELECT COALESCE(ROUND(SUM(monto), 2), 0) AS total
            FROM pagos
            WHERE estado = 'aprobado'
              AND MONTH(fecha_pago) = :mes AND YEAR(fecha_pago) = :anio
              AND deleted_at IS NULL
        ");
        $stmtIngresosPagos->execute(['mes' => $mes, 'anio' => $anio]);
        $ingresosPagos = floatval($stmtIngresosPagos->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmtIngresosComp = $db->prepare("
            SELECT COALESCE(ROUND(SUM(monto), 2), 0) AS total
            FROM comprobantes_pago
            WHERE estado = 'aprobado'
              AND (
                (fecha_pago IS NOT NULL AND MONTH(fecha_pago) = :mes1 AND YEAR(fecha_pago) = :anio1)
                OR (fecha_pago IS NULL AND MONTH(fecha_envio) = :mes2 AND YEAR(fecha_envio) = :anio2)
              )
        ");
        $stmtIngresosComp->execute([
            'mes1'  => $mes,
            'anio1' => $anio,
            'mes2'  => $mes,
            'anio2' => $anio
        ]);
        $ingresosComp = floatval($stmtIngresosComp->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $totalIngresosMes = round($ingresosPagos + $ingresosComp, 2);

        // Ingresos históricos globales acumulados
        $stmtIngresosGlobal = $db->query("
            SELECT (
                (SELECT COALESCE(ROUND(SUM(monto), 2), 0) FROM pagos WHERE estado = 'aprobado' AND deleted_at IS NULL)
                +
                (SELECT COALESCE(ROUND(SUM(monto), 2), 0) FROM comprobantes_pago WHERE estado = 'aprobado')
            ) AS total_recaudado
        ");
        $totalRecaudadoHistorico = floatval($stmtIngresosGlobal->fetch(PDO::FETCH_ASSOC)['total_recaudado'] ?? 0);

        // 3. Egresos / Gastos del mes
        $stmtGastos = $db->prepare("
            SELECT COALESCE(ROUND(SUM(monto_total), 2), 0) AS total
            FROM gastos_comunes
            WHERE mes = :mes AND anio = :anio AND deleted_at IS NULL
        ");
        $stmtGastos->execute(['mes' => $mes, 'anio' => $anio]);
        $totalGastosMes = floatval($stmtGastos->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Egresos históricos globales
        $stmtGastosGlobal = $db->query("
            SELECT COALESCE(ROUND(SUM(monto_total), 2), 0) AS total_gastos
            FROM gastos_comunes
            WHERE deleted_at IS NULL
        ");
        $totalGastosHistorico = floatval($stmtGastosGlobal->fetch(PDO::FETCH_ASSOC)['total_gastos'] ?? 0);

        // 4. Balance operativo neto
        $balanceNetoMes = round($totalIngresosMes - $totalGastosMes, 2);
        $balanceHistorico = round($totalRecaudadoHistorico - $totalGastosHistorico, 2);

        // 5. Pagos pendientes de conciliar (indicador de alerta para módulo Conciliación)
        $stmtPendientes = $db->query("
            SELECT (
                (SELECT COUNT(*) FROM comprobantes_pago WHERE estado = 'pendiente')
                +
                (SELECT COUNT(*) FROM pagos WHERE estado = 'pendiente' AND deleted_at IS NULL)
            ) AS total_pendientes
        ");
        $totalPendientes = intval($stmtPendientes->fetch(PDO::FETCH_ASSOC)['total_pendientes'] ?? 0);

        return [
            'kpis_morosidad'            => $kpisMorosidad,
            'total_por_cobrar'          => $totalPorCobrar,
            'total_ingresos_mes'        => $totalIngresosMes,
            'total_recaudado_historico' => $totalRecaudadoHistorico,
            'total_gastos_mes'          => $totalGastosMes,
            'total_gastos_historico'    => $totalGastosHistorico,
            'balance_neto_mes'          => $balanceNetoMes,
            'balance_historico'         => $balanceHistorico,
            'total_pendientes'          => $totalPendientes
        ];
    }

    /**
     * Realiza exportación streaming directa en CSV con BOM UTF-8 para compatibilidad con Excel.
     */
    public function exportarCsvStreaming(array $filtros = []): void {
        $datos = $this->obtenerReporteMorosidadCompleto($filtros);

        $filename = 'balance_unidades_' . date('Y-m-d_H-i') . '.csv';

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
            'Estado Financiero',
            'Facturas Vencidas',
            'Días de Mora Máx.',
            'Total Deuda (Bs)'
        ], ';');

        foreach ($datos as $row) {
            fputcsv($output, [
                self::sanitizeCsvField($row['edificio_nombre']),
                self::sanitizeCsvField($row['unidad_numero']),
                self::sanitizeCsvField($row['propietario_nombre']),
                self::sanitizeCsvField($row['propietario_cedula']),
                self::sanitizeCsvField($row['propietario_telefono']),
                self::sanitizeCsvField($row['propietario_email']),
                ($row['estado_financiero'] === 'deudor' ? 'Con Deuda' : 'Solvente'),
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
