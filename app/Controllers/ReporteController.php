<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\ReportesModel;
use App\Models\EdificiosModel;

class ReporteController extends Controller {

    private function filtrosBalance(): array {
        return [
            'edificio_id' => $_GET['edificio_id'] ?? '',
            'dias_mora'   => $_GET['dias_mora'] ?? '',
            'estado'      => $_GET['estado'] ?? '',
            'buscar'      => trim((string)($_GET['buscar'] ?? '')),
        ];
    }

    /**
     * Muestra el panel interactivo del reporte de morosidad.
     */
    public function morosidad() {
        Auth::requireRole(['admin', 'auditor']);

        $filtros = $this->filtrosBalance();

        $reportesModel = new ReportesModel();
        $edificiosModel = new EdificiosModel();

        $edificiosConsolidados = $reportesModel->obtenerReporteBalanceAgrupadoPorEdificio($filtros);
        $kpis = $reportesModel->obtenerKpisMorosidad();
        $edificios = $edificiosModel->getActivos();

        // Retrocompatibilidad con componentes
        $todasUnidades = [];
        foreach ($edificiosConsolidados as $ed) {
            foreach ($ed['unidades'] as $u) {
                $todasUnidades[] = $u;
            }
        }

        // Lista plana orientada a unidades: mayor deuda (o mayor saldo a favor) primero.
        $ordenPorSaldoFavor = (($filtros['estado'] ?? '') === 'saldo_favor');
        usort($todasUnidades, static function (array $a, array $b) use ($ordenPorSaldoFavor): int {
            $principal = $ordenPorSaldoFavor
                ? ((float)($b['saldo_favor'] ?? 0) <=> (float)($a['saldo_favor'] ?? 0))
                : ((float)($b['total_deuda'] ?? 0) <=> (float)($a['total_deuda'] ?? 0));

            return $principal
                ?: strcmp((string)($a['edificio_nombre'] ?? ''), (string)($b['edificio_nombre'] ?? ''))
                ?: strnatcmp((string)($a['unidad_numero'] ?? ''), (string)($b['unidad_numero'] ?? ''));
        });

        $pagina = max(1, intval($_GET['page'] ?? 1));
        $porPagina = 50;
        $totalUnidades = count($todasUnidades);
        $totalPaginas = max(1, (int)ceil($totalUnidades / $porPagina));
        $pagina = min($pagina, $totalPaginas);
        $unidadesPagina = array_slice($todasUnidades, ($pagina - 1) * $porPagina, $porPagina);

        $paginacion = [
            'total'        => $totalUnidades,
            'pagina'       => $pagina,
            'porPagina'    => $porPagina,
            'totalPaginas' => $totalPaginas,
            'desde'        => $totalUnidades > 0 ? (($pagina - 1) * $porPagina) + 1 : 0,
            'hasta'        => min($totalUnidades, $pagina * $porPagina),
        ];

        $this->render('admin/reportes/morosidad', [
            'edificiosConsolidados' => $edificiosConsolidados,
            'morosos'               => $unidadesPagina,
            'unidades'              => $unidadesPagina,
            'kpis'                  => $kpis,
            'edificios'             => $edificios,
            'filtros'               => $filtros,
            'paginacion'            => $paginacion,
            'layout'                => 'admin',
            'title'                 => 'Carta de Deuda'
        ]);
    }

    /**
     * Muestra el reporte formateado exclusivamente para impresión o generación de PDF.
     */
    public function imprimirMorosidad() {
        Auth::requireRole(['admin', 'auditor']);

        $filtros = $this->filtrosBalance();

        $reportesModel = new ReportesModel();
        $morosos = $reportesModel->obtenerReporteMorosidadCompleto($filtros);
        $kpis = $reportesModel->obtenerKpisMorosidad();
        $truncado = count($morosos) >= 5000;

        $this->render('admin/reportes/imprimir', [
            'morosos' => $morosos,
            'truncado' => $truncado,
            'kpis'    => $kpis,
            'filtros' => $filtros,
            'title'   => 'Deuda de Unidades - Impresión Oficial'
        ]);
    }

    /**
     * Descarga el reporte en streaming CSV con codificación BOM UTF-8.
     */
    public function exportarCsv() {
        Auth::requireRole(['admin', 'auditor']);

        $filtros = $this->filtrosBalance();

        $reportesModel = new ReportesModel();
        $reportesModel->exportarCsvStreaming($filtros);
    }

    /**
     * Genera la vista formal de la carta de deuda para una unidad habitacional.
     *
     * @param mixed $unidadId
     */
    public function generarCartaDeuda($unidadId) {
        Auth::requireRole(['admin', 'auditor']);

        // T5: entradas no numéricas (p. ej. "abc") deben dar 404, no un 500 por TypeError
        $unidadId = intval($unidadId);
        if ($unidadId <= 0) {
            $this->render('errors/404', ['title' => 'Unidad no encontrada']);
            return;
        }

        $reportesModel = new ReportesModel();
        $detalle = $reportesModel->obtenerDetalleDeudaUnidad($unidadId);

        if (!$detalle) {
            $this->render('errors/404', ['title' => 'Unidad no encontrada']);
            return;
        }

        $numUnidad = $detalle['unidad']['unidad_numero'] ?? '';
        $torre = $detalle['unidad']['edificio_nombre'] ?: 'Sin Torre';
        $propietario = $detalle['unidad']['propietario_nombre'] ?: 'Propietario / Residente';

        // El mensaje refleja la tabla mensual de la carta: sin días por factura.
        $gruposFacturas = agruparFacturasPorMes($detalle['facturas']);
        $cantidadMeses = count($gruposFacturas);
        $textoMesesVencidos = ($cantidadMeses === 1) ? '1 mes vencido' : $cantidadMeses . ' meses vencidos';
        $montoEnLetrasTotal = montoEnLetras($detalle['total_deuda']);

        $detalleMensual = "";
        $esPrimeraFila = true;
        foreach ($gruposFacturas as $grupo) {
            $etiqueta = ($esPrimeraFila ? 'Saldo de ' : '') . nombreMes($grupo['mes']) . '-' . $grupo['anio'];
            $detalleMensual .= $etiqueta . ': ' . formatearMoneda($grupo['total']) . "\n";
            $esPrimeraFila = false;
        }
        $detalleMensual .= "TOTAL: " . formatearMoneda($detalle['total_deuda']);

        $mensajeCarta = "CONDOMINIO CONJUNTO LAS MESETAS\n"
            . "RIF. J-400577870\n"
            . "Av. Ppal. de Morón. Oficina Administrativa\n"
            . "Urb. Las Mesetas. Teléf. 0271-2259170 - Valera, Edo. Trujillo\n\n"
            . "Valera, " . date('j') . " de " . nombreMes(date('n')) . " de " . date('Y') . "\n\n"
            . "Ciudadana (o): " . mb_strtoupper($propietario, 'UTF-8') . "\n"
            . "Propietario(a) del Apto. " . $numUnidad . ". Del Edificio \"" . $torre . "\".\n"
            . "PRESENTE.-\n\n"
            . "Nos es grato dirigirnos a usted, para saludarle y a la vez notificarle que a la fecha, tiene una morosidad de " . $textoMesesVencidos . " de condominio, por un monto de " . $montoEnLetrasTotal . " (" . formatearMoneda($detalle['total_deuda']) . "). Monto total se detalla a continuación:\n\n"
            . $detalleMensual . "\n\n"
            . "Le solicitamos realizar el pago a la brevedad posible por los medios establecidos: Cuenta Corriente Banco de Venezuela Nº. 0102-0373-2200-0010-2432; Divisa (Tasa del BCV del día de cancelación); o al correo pagosjdclm@gmail.com.\n\n"
            . "Atentamente,\n"
            . "Ninoska Moncada — Administradora";

        $enlaceWhatsapp = \App\Services\NotificationService::generarEnlaceWhatsApp(
            $detalle['unidad']['propietario_telefono'] ?? '',
            $mensajeCarta
        );

        $analisisTel = \App\Services\NotificationService::analizarTelefono($detalle['unidad']['propietario_telefono'] ?? '');

        // Estado de envío por residente: cooldown por día calendario.
        $enviosModel = new \App\Models\CartasDeudaEnviosModel();
        $propietarioId = $detalle['unidad']['propietario_id'] !== null
            ? intval($detalle['unidad']['propietario_id'])
            : null;
        $ultimoEnvio = $enviosModel->obtenerUltimoEnvio($unidadId, $propietarioId);
        $enviadoHoy = $enviosModel->enviadoHoy($unidadId, $propietarioId);

        $this->render('admin/reportes/carta_deuda', [
            'unidad'         => $detalle['unidad'],
            'facturas'       => $detalle['facturas'],
            'totalDeuda'     => $detalle['total_deuda'],
            'enlaceWhatsapp' => $enlaceWhatsapp,
            'analisisTel'    => $analisisTel,
            'ultimoEnvio'    => $ultimoEnvio,
            'enviadoHoy'     => $enviadoHoy,
            'title'          => 'Carta Oficial de Deuda - Unidad ' . $detalle['unidad']['unidad_numero']
        ]);
    }

    /**
     * Encola el aviso de cobro por correo electrónico al residente.
     */
    public function enviarAvisoCobro() {
        Auth::requireRole(['admin', 'auditor']);

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

        // Cooldown: un envío por residente por día calendario (clave: propietario o unidad).
        $propietarioId = $detalle['unidad']['propietario_id'] !== null
            ? intval($detalle['unidad']['propietario_id'])
            : null;
        $enviosModel = new \App\Models\CartasDeudaEnviosModel();

        if ($enviosModel->enviadoHoy($unidadId, $propietarioId)) {
            \App\Core\Flash::set('warning', 'Ya se envió una carta de deuda a este residente hoy. Podrá reenviarse a partir de mañana.');
            $this->redirect('/admin/reportes/carta-deuda/' . $unidadId);
            return;
        }

        // Genera el PDF oficial antes de encolar: si falla, no se envía nada.
        try {
            $pdfCarta = (new \App\Services\CartaDeudaPdfService())->generar($detalle);
        } catch (\Throwable $e) {
            \App\Core\Flash::set('danger', 'No se pudo generar el PDF de la carta. Intente nuevamente.');
            $this->redirect('/admin/reportes/carta-deuda/' . $unidadId);
            return;
        }

        $emailService = new \App\Services\EmailService();
        $notifService = new \App\Services\NotificationService();

        $cuerpoHtml = $emailService->renderTemplate('aviso_cobro', [
            'nombrePropietario' => $detalle['unidad']['propietario_nombre'] ?: 'Propietario',
            'numeroUnidad'      => $detalle['unidad']['unidad_numero'],
            'nombreEdificio'    => $detalle['unidad']['edificio_nombre'],
            'totalDeuda'        => $detalle['total_deuda']
        ]);

        $asunto = "📄 Carta de Cobro Oficial - Unidad " . $detalle['unidad']['unidad_numero'];

        // El PDF viaja como adjunto cifrado en la cola; el worker solo lo descifra y despacha.
        $nombreAdjunto = 'Carta-Deuda-Unidad-' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string)$detalle['unidad']['unidad_numero']) . '.pdf';
        $adjuntos = [[
            'nombre'    => $nombreAdjunto,
            'mime'      => 'application/pdf',
            'contenido' => $pdfCarta,
        ]];

        $encolado = $notifService->encolarNotificacion(
            $detalle['unidad']['propietario_email'],
            $asunto,
            $cuerpoHtml,
            $detalle['unidad']['propietario_telefono'],
            'ambos',
            'alta',
            $adjuntos
        );

        // El envío solo se registra si el encolado del correo tuvo éxito.
        if ($encolado !== true) {
            \App\Core\Flash::set('danger', 'No se pudo encolar la carta. Intente nuevamente.');
            $this->redirect('/admin/reportes/carta-deuda/' . $unidadId);
            return;
        }

        $enviosModel->registrarEnvio($unidadId, $propietarioId, 'email', Auth::id());

        if (!empty($detalle['unidad']['propietario_id'])) {
            $notifService->registrarNotificacionResidente(
                $detalle['unidad']['propietario_id'],
                "Carta de Cobro Oficial Emitida",
                "Se ha emitido formalmente su Carta de Cobro por " . formatearMoneda($detalle['total_deuda']) . " para su unidad.",
                "warning",
                "/residente/notificaciones"
            );
        }

        \App\Core\Flash::set('success', 'Carta de deuda enviada al residente. No podrá reenviarse hasta mañana.');
        $this->redirect('/admin/reportes/carta-deuda/' . $unidadId);
    }
}
