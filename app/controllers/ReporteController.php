<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\ReportesModel;
use App\Models\EdificiosModel;

class ReporteController extends Controller {

    /**
     * Muestra el panel interactivo del reporte de morosidad.
     */
    public function morosidad() {
        Auth::requireRole('admin');

        $filtros = [
            'edificio_id' => $_GET['edificio_id'] ?? '',
            'dias_mora'   => $_GET['dias_mora'] ?? ''
        ];

        $pagina = max(1, intval($_GET['page'] ?? 1));

        $reportesModel = new ReportesModel();
        $edificiosModel = new EdificiosModel();

        $resultado = $reportesModel->obtenerReporteMorosidad($filtros, $pagina, 50);
        $kpis = $reportesModel->obtenerKpisMorosidad();
        $edificios = $edificiosModel->getActivos();

        $paginacion = [
            'total'        => $resultado['total'],
            'pagina'       => $resultado['pagina'],
            'porPagina'    => $resultado['porPagina'],
            'totalPaginas' => $resultado['totalPaginas'],
        ];

        $this->render('admin/reportes/morosidad', [
            'morosos'    => $resultado['datos'],
            'kpis'       => $kpis,
            'edificios'  => $edificios,
            'filtros'    => $filtros,
            'paginacion' => $paginacion,
            'layout'     => 'admin',
            'title'      => 'Reporte de Morosidad en Tiempo Real'
        ]);
    }

    /**
     * Muestra el reporte formateado exclusivamente para impresión o generación de PDF.
     */
    public function imprimirMorosidad() {
        Auth::requireRole('admin');

        $filtros = [
            'edificio_id' => $_GET['edificio_id'] ?? '',
            'dias_mora'   => $_GET['dias_mora'] ?? ''
        ];

        $reportesModel = new ReportesModel();
        $morosos = $reportesModel->obtenerReporteMorosidadCompleto($filtros);
        $kpis = $reportesModel->obtenerKpisMorosidad();
        $truncado = count($morosos) >= 5000;

        $this->render('admin/reportes/imprimir', [
            'morosos' => $morosos,
            'truncado' => $truncado,
            'kpis'    => $kpis,
            'filtros' => $filtros,
            'title'   => 'Reporte de Morosidad - Impresión Oficial'
        ]);
    }

    /**
     * Descarga el reporte en streaming CSV con codificación BOM UTF-8.
     */
    public function exportarCsv() {
        Auth::requireRole('admin');

        $filtros = [
            'edificio_id' => $_GET['edificio_id'] ?? '',
            'dias_mora'   => $_GET['dias_mora'] ?? ''
        ];

        $reportesModel = new ReportesModel();
        $reportesModel->exportarCsvStreaming($filtros);
    }

    /**
     * Genera la vista formal de la carta de deuda para una unidad habitacional.
     *
     * @param int $unidadId
     */
    public function generarCartaDeuda(int $unidadId) {
        Auth::requireRole('admin');

        $reportesModel = new ReportesModel();
        $detalle = $reportesModel->obtenerDetalleDeudaUnidad($unidadId);

        if (!$detalle) {
            $this->render('errors/404', ['title' => 'Unidad no encontrada']);
            return;
        }

        $enlaceWhatsapp = \App\Services\NotificationService::generarEnlaceWhatsApp(
            $detalle['unidad']['propietario_telefono'] ?? '',
            "Estimado(a) " . $detalle['unidad']['propietario_nombre'] . ", le escribimos de la Administración del Condominio Las Mesetas de Morón para enviarle su aviso de cobro por la Unidad " . $detalle['unidad']['unidad_numero'] . " por un total de Bs. " . number_format($detalle['total_deuda'], 2)
        );

        $analisisTel = \App\Services\NotificationService::analizarTelefono($detalle['unidad']['propietario_telefono'] ?? '');

        $this->render('admin/reportes/carta_deuda', [
            'unidad'         => $detalle['unidad'],
            'facturas'       => $detalle['facturas'],
            'totalDeuda'     => $detalle['total_deuda'],
            'enlaceWhatsapp' => $enlaceWhatsapp,
            'analisisTel'    => $analisisTel,
            'title'          => 'Carta Oficial de Deuda - Unidad ' . $detalle['unidad']['unidad_numero']
        ]);
    }

    /**
     * Encola el aviso de cobro por correo electrónico al residente.
     */
    public function enviarAvisoCobro() {
        Auth::requireRole('admin');

        $unidadId = intval($_POST['unidad_id'] ?? 0);
        if ($unidadId <= 0) {
            \App\Core\Flash::set('danger', 'ID de unidad inválido.');
            $this->redirect('/admin/reportes/morosidad');
            return;
        }

        $reportesModel = new ReportesModel();
        $detalle = $reportesModel->obtenerDetalleDeudaUnidad($unidadId);

        if (!$detalle || empty($detalle['unidad']['propietario_email'])) {
            \App\Core\Flash::set('danger', 'La unidad no posee un propietario con email registrado.');
            $this->redirect('/admin/reportes/morosidad');
            return;
        }

        $emailService = new \App\Services\EmailService();
        $notifService = new \App\Services\NotificationService();

        $cuerpoHtml = $emailService->renderTemplate('aviso_cobro', [
            'nombrePropietario' => $detalle['unidad']['propietario_nombre'],
            'numeroUnidad'      => $detalle['unidad']['unidad_numero'],
            'nombreEdificio'    => $detalle['unidad']['edificio_nombre'],
            'facturas'          => $detalle['facturas'],
            'totalDeuda'        => $detalle['total_deuda']
        ]);

        $asunto = "⚠️ Aviso Oficial de Cobro - Unidad " . $detalle['unidad']['unidad_numero'];

        $notifService->encolarNotificacion(
            $detalle['unidad']['propietario_email'],
            $asunto,
            $cuerpoHtml,
            $detalle['unidad']['propietario_telefono'],
            'ambos',
            'alta'
        );

        if (!empty($detalle['unidad']['propietario_id'])) {
            $notifService->registrarNotificacionResidente(
                $detalle['unidad']['propietario_id'],
                "Aviso de Deuda Vencida",
                "Se ha emitido un aviso de cobro por Bs. " . number_format($detalle['total_deuda'], 2) . " correspondiente a su unidad.",
                "warning",
                "/residente/notificaciones"
            );
        }

        \App\Core\Flash::set('success', 'Aviso de cobro encolado exitosamente para envío.');
        $this->redirect('/admin/reportes/carta-deuda/' . $unidadId);
    }
}
