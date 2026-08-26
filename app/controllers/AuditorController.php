<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\UserRole;
use PDO;

class AuditorController extends Controller {

    /**
     * Dashboard general de fiscalización y solo lectura para el Auditor.
     */
    public function dashboard() {
        Auth::requireRole([UserRole::AUDITOR, UserRole::ADMIN]);

        $db = Database::getConnection();

        // 1. Métricas de auditoría
        $totalLogs = (int)$db->query("SELECT COUNT(*) FROM log_auditoria")->fetchColumn();
        $totalMovimientos = (int)$db->query("SELECT COUNT(*) FROM movimientos_cuenta")->fetchColumn();
        $totalConciliados = (int)$db->query("SELECT COUNT(*) FROM extractos_bancarios WHERE estado_conciliacion = 'conciliado'")->fetchColumn();
        $totalGastosMonto = (float)$db->query("SELECT COALESCE(SUM(monto_total), 0) FROM gastos_comunes WHERE activo = 1")->fetchColumn();

        // 2. Últimos 10 eventos críticos de auditoría
        $stmtLogs = $db->query("
            SELECT l.*, COALESCE(u.nombre_completo, 'Sistema') AS usuario_nombre
            FROM log_auditoria l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            ORDER BY l.id DESC
            LIMIT 10
        ");
        $ultimosLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        $this->render('auditor/dashboard', [
            'totalLogs'        => $totalLogs,
            'totalMovimientos' => $totalMovimientos,
            'totalConciliados' => $totalConciliados,
            'totalGastosMonto' => $totalGastosMonto,
            'ultimosLogs'      => $ultimosLogs,
            'layout'           => 'auditor',
            'title'            => 'Panel de Fiscalización y Auditoría'
        ]);
    }

    /**
     * Listado detallado y paginado del log de auditoría del sistema.
     */
    public function logTransacciones() {
        Auth::requireRole([UserRole::AUDITOR, UserRole::ADMIN]);

        $pagina = max(1, intval($_GET['page'] ?? 1));
        $porPagina = 25;
        $offset = ($pagina - 1) * $porPagina;

        $db = Database::getConnection();
        $total = (int)$db->query("SELECT COUNT(*) FROM log_auditoria")->fetchColumn();

        $stmt = $db->prepare("
            SELECT l.*, COALESCE(u.nombre_completo, 'Sistema') AS usuario_nombre, u.usuario AS usuario_username
            FROM log_auditoria l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            ORDER BY l.id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $paginacion = [
            'total'        => $total,
            'pagina'       => $pagina,
            'porPagina'    => $porPagina,
            'totalPaginas' => max(1, (int)ceil($total / $porPagina)),
        ];

        $this->render('auditor/log_transacciones', [
            'logs'       => $logs,
            'paginacion' => $paginacion,
            'layout'     => 'auditor',
            'title'      => 'Registro Inmutable de Auditoría'
        ]);
    }

    /**
     * Exporta el log de auditoría en formato CSV para fiscalización externa.
     */
    public function exportarLog() {
        Auth::requireRole([UserRole::AUDITOR, UserRole::ADMIN]);

        $db = Database::getConnection();

        // Streaming en lotes de 1000 filas para evitar agotamiento de memoria
        $batchSize = 1000;
        $limiteMaximo = 50000;
        $offset = 0;

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="log_auditoria_' . date('Ymd_His') . '.csv"');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Fecha y Hora', 'Usuario', 'Acción', 'Tabla Afectada', 'ID Registro', 'Detalles', 'Dirección IP'], ';');

        while ($offset < $limiteMaximo) {
            $stmt = $db->prepare("
                SELECT l.id, l.created_at AS fecha, COALESCE(u.nombre_completo, 'Sistema') AS usuario,
                       l.accion, l.tabla_afectada, l.registro_id, l.detalles, l.ip_address
                FROM log_auditoria l
                LEFT JOIN usuarios u ON l.usuario_id = u.id
                ORDER BY l.id DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $l) {
                fputcsv($out, [
                    $l['id'],
                    $l['fecha'],
                    $l['usuario'],
                    $l['accion'],
                    $l['tabla_afectada'],
                    $l['registro_id'],
                    $l['detalles'],
                    $l['ip_address']
                ], ';');
            }

            $offset += $batchSize;
        }

        fclose($out);
        exit;
    }
}
