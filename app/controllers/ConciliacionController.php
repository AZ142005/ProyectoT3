<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\ConciliacionModel;
use App\Services\ConciliacionBancariaService;

class ConciliacionController extends Controller {

    /**
     * Muestra el panel interactivo del motor de conciliación bancaria.
     */
    public function index() {
        Auth::requireRole(['admin', 'auditor']);

        $conciliacionModel = new ConciliacionModel();
        $conciliacionService = new ConciliacionBancariaService();

        $lotes = $conciliacionModel->obtenerLotes();
        $loteSeleccionado = $_GET['lote'] ?? ($lotes[0]['lote_importacion'] ?? null);

        $extractosPendientes = $conciliacionModel->obtenerExtractosPendientes($loteSeleccionado);
        $resultadoCruce = $conciliacionService->ejecutarCruceInteligente($extractosPendientes);
        $pagosPendientes = $conciliacionModel->obtenerTodosPagosPendientes();

        // Bandeja unificada: una fila por movimiento (pago y/o extracto) para el filtrado por categoría.
        $filasConciliacion = [];

        foreach ($resultadoCruce['coincidencias_exactas'] as $match) {
            $filasConciliacion[] = [
                'categoria' => 'exacta',
                'pago'      => $match['pago'],
                'extracto'  => $match['extracto'],
                'fecha'     => $match['pago']['fecha_pago'] ?: $match['extracto']['fecha_movimiento'],
            ];
        }

        foreach ($resultadoCruce['coincidencias_sugeridas'] as $match) {
            $filasConciliacion[] = [
                'categoria' => 'sugerida',
                'pago'      => $match['pago'],
                'extracto'  => $match['extracto'],
                'fecha'     => $match['pago']['fecha_pago'] ?: $match['extracto']['fecha_movimiento'],
            ];
        }

        foreach ($resultadoCruce['inconsistencias'] as $match) {
            $filasConciliacion[] = [
                'categoria' => 'inconsistencia',
                'pago'      => $match['pago'] ?? null,
                'extracto'  => $match['extracto'] ?? null,
                'motivo'    => $match['motivo'],
                'alerta'    => $match['alerta'] ?? null,
                'fecha'     => $match['extracto']['fecha_movimiento'] ?? $match['pago']['fecha_pago'] ?? null,
            ];
        }

        foreach ($resultadoCruce['sin_coincidencia'] as $match) {
            $filasConciliacion[] = [
                'categoria' => 'sin_coincidencia',
                'pago'      => null,
                'extracto'  => $match['extracto'],
                'fecha'     => $match['extracto']['fecha_movimiento'] ?? null,
            ];
        }

        // Pagos pendientes que no quedaron emparejados en ningún cruce (evita filas duplicadas).
        // La clave incluye el origen porque pagos y comprobantes_pago tienen secuencias de id independientes.
        $pagosEmparejados = [];
        foreach (['coincidencias_exactas', 'coincidencias_sugeridas', 'inconsistencias'] as $tipoCruce) {
            foreach ($resultadoCruce[$tipoCruce] as $match) {
                if (!empty($match['pago'])) {
                    $clavePago = ($match['pago']['origen_tabla'] ?? 'pago') . '_' . $match['pago']['id'];
                    $pagosEmparejados[$clavePago] = true;
                }
            }
        }

        foreach ($pagosPendientes as $pago) {
            $clavePago = ($pago['origen_tabla'] ?? 'pago') . '_' . $pago['id'];
            if (isset($pagosEmparejados[$clavePago])) {
                continue;
            }
            $filasConciliacion[] = [
                'categoria' => 'sin_extracto',
                'pago'      => $pago,
                'extracto'  => null,
                'fecha'     => $pago['fecha_pago'],
            ];
        }

        // Orden descendente por fecha (los movimientos más recientes primero).
        usort($filasConciliacion, fn(array $a, array $b) => strcmp((string)$b['fecha'], (string)$a['fecha']));

        // Conteos por categoría para las tarjetas y las pills de filtro.
        $conteosConciliacion = [
            'exacta'           => 0,
            'sugerida'         => 0,
            'inconsistencia'   => 0,
            'sin_coincidencia' => 0,
            'sin_extracto'     => 0,
            'total'            => count($filasConciliacion),
        ];
        foreach ($filasConciliacion as $fila) {
            $conteosConciliacion[$fila['categoria']]++;
        }

        // Paginación estandarizada a 25 registros por página
        $pagina = max(1, intval($_GET['page'] ?? 1));
        $porPagina = 25;
        $totalFilas = count($filasConciliacion);
        $totalPaginas = (int) ceil($totalFilas / $porPagina);
        $offset = ($pagina - 1) * $porPagina;
        $filasPaginadas = array_slice($filasConciliacion, $offset, $porPagina);

        $paginacion = [
            'total'        => $totalFilas,
            'pagina'       => $pagina,
            'porPagina'    => $porPagina,
            'totalPaginas' => $totalPaginas,
        ];

        $filtros = [
            'lote' => $loteSeleccionado ?? '',
        ];

        $this->render('admin/conciliacion/index', [
            'lotes'                 => $lotes,
            'loteActual'            => $loteSeleccionado,
            'resultadoCruce'        => $resultadoCruce,
            'pagosPendientes'       => $pagosPendientes,
            'filasConciliacion'     => $filasPaginadas,
            'conteosConciliacion'   => $conteosConciliacion,
            'paginacion'            => $paginacion,
            'filtros'               => $filtros,
            'layout'                => 'admin',
            'title'                 => 'Conciliación Bancaria y Verificación de Pagos'
        ]);
    }

    /**
     * Procesa la importación de un archivo de extracto bancario CSV / TXT.
     */
    public function importarExtracto() {
        Auth::requireRole(['admin', 'auditor']);

        $banco = trim($_POST['banco'] ?? 'mercantil');

        $bancosPermitidos = ['mercantil', 'provincial', 'venezuela', 'banco_de_venezuela', 'bdv', 'banesco', 'banco_plaza', 'banco_exterior', 'bbva', 'bancaribe', 'banco_nacional_de_crédito', 'banco_del_tesoro', 'banco_munivalle', 'generico_csv'];
        if (!in_array($banco, $bancosPermitidos, true)) {
            Flash::set('danger', 'Banco no reconocido. Seleccione un banco válido.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        if (empty($_FILES['archivo_extracto']) || $_FILES['archivo_extracto']['error'] !== UPLOAD_ERR_OK) {
            Flash::set('danger', 'Debe seleccionar un archivo de extracto bancario válido.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        $file = $_FILES['archivo_extracto'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'txt', 'pdf'], true)) {
            Flash::set('danger', 'Formato no permitido. Solo se aceptan archivos .CSV, .TXT o .PDF de extractos bancarios.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        // Validación MIME real del contenido
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            if (\PHP_VERSION_ID < 80500 && \is_resource($finfo)) {
                @finfo_close($finfo);
            }
            unset($finfo);
            if (!in_array($mime, ['text/csv', 'text/plain', 'text/comma-separated-values', 'application/octet-stream', 'text/x-csv', 'application/pdf', 'application/x-pdf'], true)) {
                Flash::set('danger', 'El contenido del archivo no corresponde a un extracto bancario válido.');
                $this->redirect('/admin/conciliacion');
                return;
            }
        }

        // Límite de tamaño: 10MB para extractos bancarios
        if ($file['size'] > 10 * 1024 * 1024) {
            Flash::set('danger', 'El archivo excede el tamaño máximo permitido de 10MB.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        try {
            $conciliacionService = new ConciliacionBancariaService();
            $movimientos = $conciliacionService->parsearArchivo($file['tmp_name'], $banco);

            $lote = 'LOTE-' . date('Ymd-His');
            $conciliacionModel = new ConciliacionModel();
            $stats = $conciliacionModel->insertarExtracto($movimientos, $banco, $lote);

            $msg = "Extracto importado con éxito. Lote: {$lote}. Insertados: {$stats['insertados']} (Créditos), Débitos descartados: {$stats['debitos']}, Duplicados omitidos: {$stats['duplicados']}.";
            Flash::set('success', $msg);
            $this->redirect('/admin/conciliacion?lote=' . urlencode($lote));
        } catch (\Exception $e) {
            error_log("[CONCILIACION] Error importar extracto: " . $e->getMessage());
            Flash::set('danger', 'Error al procesar el archivo del extracto bancario. Verifique el formato e intente de nuevo.');
            $this->redirect('/admin/conciliacion');
        }
    }

    /**
     * Concilia y aprueba un pago individual de 1-clic.
     */
    public function conciliarPago() {
        Auth::requireRole(['admin', 'auditor']);

        $isAjax = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
               || (!empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
               || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');

        $extractoId     = intval($_POST['extracto_id'] ?? ($_POST['movimiento_id'] ?? 0));
        $pagoId         = intval($_POST['pago_id'] ?? 0);
        $origenTipo     = trim($_POST['origen_tipo'] ?? 'auto');
        $idempotencyKey = trim($_POST['idempotency_key'] ?? ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $adminId        = intval(Auth::id() ?? 1);

        if ($extractoId <= 0 || $pagoId <= 0) {
            if ($isAjax) {
                $this->json([
                    'success' => false,
                    'codigo'  => 'PARAMETROS_INVALIDOS',
                    'error'   => 'Parámetros de conciliación inválidos.'
                ], 400);
                return;
            }
            Flash::set('danger', 'Parámetros de conciliación inválidos.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        try {
            $conciliacionService = new ConciliacionBancariaService();
            $resultado = $conciliacionService->conciliarYaprobar(
                $extractoId,
                $pagoId,
                $adminId,
                $origenTipo,
                $idempotencyKey ?: null
            );

            if ($isAjax) {
                $this->json([
                    'success'     => true,
                    'codigo'      => $resultado['codigo'] ?? 'CONCILIACION_EXITOSA',
                    'idempotente' => !empty($resultado['idempotente']),
                    'mensaje'     => $resultado['mensaje']
                ], 200);
                return;
            }

            Flash::set('success', $resultado['mensaje']);
        } catch (\App\Core\ConciliacionException $ce) {
            if ($isAjax) {
                $this->json([
                    'success' => false,
                    'codigo'  => $ce->getCodigoNegocio(),
                    'error'   => $ce->getMessage()
                ], $ce->getStatusHttp());
                return;
            }
            Flash::set('danger', $ce->getMessage());
        } catch (\Throwable $e) {
            error_log("[CONCILIACION] Error conciliar pago: " . $e->getMessage());
            if ($isAjax) {
                $this->json([
                    'success' => false,
                    'codigo'  => 'ERROR_INTERNO',
                    'error'   => 'Error al procesar la conciliación del pago.'
                ], 500);
                return;
            }
            Flash::set('danger', 'Error al procesar la conciliación del pago.');
        }

        $this->redirect('/admin/conciliacion');
    }

    /**
     * Concilia un lote de pagos seleccionados de forma masiva (máx. 100).
     */
    public function conciliarLote() {
        Auth::requireRole(['admin', 'auditor']);

        $itemsJson = trim($_POST['items_json'] ?? '');
        if (empty($itemsJson)) {
            Flash::set('danger', 'No se proporcionaron datos de conciliación.');
            $this->redirect('/admin/conciliacion');
            return;
        }
        $items = json_decode($itemsJson, true);
        if (!is_array($items) || empty($items)) {
            Flash::set('danger', 'Formato de datos inválido. Intente de nuevo.');
            $this->redirect('/admin/conciliacion');
            return;
        }
        // Limit array size to 100
        $items = array_slice($items, 0, 100);
        $adminId = Auth::id() ?? 1;

        // Validate items have required keys
        $validItems = array_filter($items, fn($it) => !empty($it['extracto_id']) && !empty($it['pago_id']));
        if (empty($validItems)) {
            Flash::set('danger', 'No se seleccionaron elementos válidos para conciliar.');
            $this->redirect('/admin/conciliacion');
            return;
        }
        $items = array_values($validItems);

        try {
            $conciliacionService = new ConciliacionBancariaService();
            $stats = $conciliacionService->conciliarLote($items, $adminId);

            $msg = "Conciliación masiva completada: {$stats['procesados']} procesados, {$stats['omitidos']} omitidos.";
            if (!empty($stats['errores'])) {
                $msg .= " Errores: " . implode(', ', array_slice($stats['errores'], 0, 3));
            }

            Flash::set('success', $msg);
        } catch (\Exception $e) {
            error_log("[CONCILIACION] Error conciliacion masiva: " . $e->getMessage());
            Flash::set('danger', 'Error durante la conciliación masiva de extractos.');
        }

        $this->redirect('/admin/conciliacion');
    }

    /**
     * Rechaza un pago reportado desde la pantalla de conciliación con motivo obligatorio.
     */
    public function rechazarPago() {
        Auth::requireRole(['admin', 'auditor']);

        $pagoId = intval($_POST['pago_id'] ?? 0);
        $origenTipo = trim($_POST['origen_tipo'] ?? 'pago');
        $motivo = trim($_POST['motivo'] ?? '');
        $adminId = Auth::id() ?? 1;

        if ($pagoId <= 0) {
            Flash::set('danger', 'Identificador de pago inválido.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        if (empty($motivo) || mb_strlen($motivo) < 5) {
            Flash::set('danger', 'Debe proporcionar un motivo de rechazo claro (mínimo 5 caracteres).');
            $this->redirect('/admin/conciliacion');
            return;
        }

        try {
            $mensajeError = 'No se pudo rechazar el pago.';

            if ($origenTipo === 'comprobante') {
                $compModel = new \App\Models\ComprobantesModel();
                $ok = $compModel->rechazar($pagoId, $motivo);
            } else {
                $pagoModel = new \App\Models\PagoModel();
                $resultado = $pagoModel->cambiarEstado($pagoId, \App\Core\EstadoPago::RECHAZADO, $motivo, $adminId, $_SERVER['REMOTE_ADDR'] ?? null);
                $ok = !empty($resultado['ok']);
                $mensajeError = $resultado['message'] ?? $mensajeError;
            }

            if ($ok) {
                Flash::set('success', 'Pago rechazado exitosamente.');
            } else {
                Flash::set('danger', $mensajeError);
            }
        } catch (\Exception $e) {
            error_log("[CONCILIACION] Error al rechazar pago: " . $e->getMessage());
            Flash::set('danger', 'Error al procesar el rechazo del pago: ' . $e->getMessage());
        }

        $this->redirect('/admin/conciliacion');
    }

    /**
     * Verifica y aprueba directamente un pago o comprobante desde el módulo de conciliación.
     */
    public function verificarPagoDirecto() {
        Auth::requireRole(['admin', 'auditor']);

        $pagoId = intval($_POST['pago_id'] ?? 0);
        $origenTipo = trim($_POST['origen_tipo'] ?? 'pago');
        $observaciones = trim($_POST['observaciones'] ?? 'Verificado y aprobado desde Conciliación');
        $adminId = Auth::id() ?? 1;

        if ($pagoId <= 0) {
            Flash::set('danger', 'Identificador de pago inválido.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        try {
            if ($origenTipo === 'comprobante') {
                $compModel = new \App\Models\ComprobantesModel();
                $ok = $compModel->aprobar($pagoId, $observaciones);
            } else {
                $pagoModel = new \App\Models\PagoModel();
                $resultado = $pagoModel->cambiarEstado(
                    $pagoId,
                    \App\Core\EstadoPago::APROBADO,
                    $observaciones,
                    $adminId,
                    $_SERVER['REMOTE_ADDR'] ?? null
                );
                $ok = !empty($resultado['ok']);
            }

            if ($ok) {
                Flash::set('success', 'Pago verificado y aprobado exitosamente desde Conciliación.');
            } else {
                Flash::set('danger', 'No se pudo verificar el pago.');
            }
        } catch (\Exception $e) {
            error_log("[CONCILIACION] Error al verificar pago directo: " . $e->getMessage());
            Flash::set('danger', 'Error al verificar el pago: ' . $e->getMessage());
        }

        $this->redirect('/admin/conciliacion');
    }
}
