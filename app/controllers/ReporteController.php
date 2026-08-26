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

        $this->render('admin/reportes/imprimir', [
            'morosos' => $morosos,
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
}
