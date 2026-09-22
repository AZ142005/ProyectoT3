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

        $numUnidad = $detalle['unidad']['unidad_numero'] ?? '';
        $torre = $detalle['unidad']['edificio_nombre'] ?: 'Sin Torre';
        $propietario = $detalle['unidad']['propietario_nombre'] ?: 'Propietario';
        $cedula = $detalle['unidad']['propietario_cedula'] ?: 'N/A';
        $codigoAviso = 'COB-' . date('Ym') . '-' . $numUnidad;
        $fechaEmision = date('d/m/Y');
        $totalBs = number_format(floatval($detalle['total_deuda']), 2);

        $lineasFacturas = "";
        if (!empty($detalle['facturas'])) {
            foreach ($detalle['facturas'] as $f) {
                $concepto = $f['descripcion'] ?? 'Cuota de Condominio';
                $venc = $f['fecha_vencimiento'] ?? '';
                $dias = isset($f['dias_vencido']) ? intval($f['dias_vencido']) : 0;
                $monto = number_format(floatval($f['saldo'] ?? 0), 2);
                $lineasFacturas .= "• " . $concepto . " (Venc: " . $venc . " | " . $dias . " d): Bs. " . $monto . "\n";
            }
        }

        $mensajeCarta = "CONJUNTO RESIDENCIAL \"LAS MESETAS DE MORÓN\"\n"
            . "Junta de Condominio & Administración General\n"
            . "RIF: J-30948572-0 | Morón, Estado Trujillo\n\n"
            . "AVISO OFICIAL: " . $codigoAviso . "\n"
            . "CARTA DE COBRO / RECORDATORIO DE MOROSIDAD\n\n"
            . "Destinatario: " . $propietario . " (C.I: " . $cedula . ")\n"
            . "Unidad: Apto/Unidad " . $numUnidad . " (" . $torre . ")\n"
            . "Fecha de Emisión: " . $fechaEmision . "\n\n"
            . "DETALLE DE CUOTAS Y OBLIGACIONES VENCIDAS:\n"
            . $lineasFacturas . "\n"
            . "TOTAL GENERAL ADEUDADO: Bs. " . $totalBs . "\n\n"
            . "Por medio de la presente se le notifica formalmente el saldo adeudado. Le solicitamos realizar el pago correspondiente a la brevedad y registrar su comprobante en el portal.\n\n"
            . "Atentamente,\n"
            . "ADMINISTRACIÓN GENERAL & JUNTA DE CONDOMINIO";

        $enlaceWhatsapp = \App\Services\NotificationService::generarEnlaceWhatsApp(
            $detalle['unidad']['propietario_telefono'] ?? '',
            $mensajeCarta
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
            'cedulaPropietario' => $detalle['unidad']['propietario_cedula'] ?? '',
            'numeroUnidad'      => $detalle['unidad']['unidad_numero'],
            'nombreEdificio'    => $detalle['unidad']['edificio_nombre'],
            'facturas'          => $detalle['facturas'],
            'totalDeuda'        => $detalle['total_deuda']
        ]);

        $asunto = "📄 Carta de Cobro Oficial - Unidad " . $detalle['unidad']['unidad_numero'];

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
                "Carta de Cobro Oficial Emitida",
                "Se ha emitido formalmente su Carta de Cobro por Bs. " . number_format($detalle['total_deuda'], 2) . " para su unidad.",
                "warning",
                "/residente/notificaciones"
            );
        }

        \App\Core\Flash::set('success', 'Carta de cobro encolada exitosamente para envío.');
        $this->redirect('/admin/reportes/carta-deuda/' . $unidadId);
    }
}
