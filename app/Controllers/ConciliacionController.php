<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\ConciliacionModel;
use App\Services\ConciliacionBancariaService;
use App\Services\ReporteConciliacionService;

class ConciliacionController extends Controller {

    /**
     * Muestra el panel interactivo del motor de conciliación bancaria.
     */
    public function index() {
        Auth::requireRole(['admin', 'auditor']);

        $conciliacionModel = new ConciliacionModel();
        $conciliacionService = new ConciliacionBancariaService();

        $lotes = $conciliacionModel->obtenerLotes();
        $lotesVentana = $conciliacionModel->obtenerLotesVentana(CONCILIACION_VENTANA_LOTES);
        $loteRecibido = $_GET['lote'] ?? null;
        $loteSeleccionado = (is_string($loteRecibido) && $loteRecibido !== '') ? $loteRecibido : null;
        $lotesPorNombre = array_column($lotes, 'lote_importacion');
        $loteExiste = $loteSeleccionado !== null && in_array($loteSeleccionado, $lotesPorNombre, true);

        $nombresVentana = array_column($lotesVentana, 'lote_importacion');
        $loteEsActivo = $loteExiste && in_array($loteSeleccionado, $nombresVentana, true);

        $reportesHistoricos = (new ReporteConciliacionService())->listarHistoricos();

        if ($loteSeleccionado === null) {
            $extractosPendientes = $conciliacionModel->obtenerExtractosPendientesVentana(CONCILIACION_VENTANA_LOTES);
        } elseif ($loteExiste) {
            $extractosPendientes = $conciliacionModel->obtenerExtractosPendientes($loteSeleccionado);
        } else {
            $extractosPendientes = [];
        }

        $resultadoCruce = $conciliacionService->ejecutarCruceInteligente($extractosPendientes);
        $pagosPendientes = $conciliacionModel->obtenerTodosPagosPendientes();

        // T3: la lista de pendientes solo muestra los pagos del mes actual (los cruces no se filtran).
        // El corte de mes se evalúa en la zona horaria de la app (America/Caracas), no en la del php.ini.
        $fechaMesActual = new \DateTimeImmutable('now', new \DateTimeZone('America/Caracas'));
        $prefijoMesActual = $fechaMesActual->format('Y-m');
        $pagosPendientes = array_values(array_filter(
            $pagosPendientes,
            static fn(array $pago): bool => !empty($pago['fecha_pago'])
                && str_starts_with((string) $pago['fecha_pago'], $prefijoMesActual)
        ));

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
            'lotesVentana'          => $lotesVentana,
            'limiteVentana'         => CONCILIACION_VENTANA_LOTES,
            'loteActual'            => $loteSeleccionado,
            'loteExiste'            => $loteExiste,
            'loteEsActivo'          => $loteEsActivo,
            'reportesHistoricos'    => $reportesHistoricos,
            'resultadoCruce'        => $resultadoCruce,
            'pagosPendientes'       => $pagosPendientes,
            'mesPagosActual'        => $fechaMesActual->format('m/Y'),
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
            $reporteService = new ReporteConciliacionService();
            $conciliacionModel = new ConciliacionModel();

            $movimientos = $conciliacionService->parsearArchivo($file['tmp_name'], $banco);

            // Ventana previa del banco: si ya alcanzó el límite configurado, el más antiguo
            // saldrá de la ventana al entrar el nuevo lote. El snapshot se toma
            // antes de insertar/adoptar para congelar su estado histórico.
            $ventanaPrevia = $conciliacionModel->obtenerLotesVentanaPorBanco($banco, CONCILIACION_VENTANA_LOTES);
            $loteExpulsado = count($ventanaPrevia) >= CONCILIACION_VENTANA_LOTES
                ? (string)($ventanaPrevia[count($ventanaPrevia) - 1]['lote_importacion'] ?? '')
                : '';
            if ($loteExpulsado === '') {
                $loteExpulsado = null;
            }

            $fechaCorte = date('Y-m-d H:i:s');
            $snapshotReporte = null;

            if ($loteExpulsado !== null) {
                try {
                    $snapshotReporte = $reporteService->construir($loteExpulsado, $fechaCorte);
                } catch (\Throwable $e) {
                    error_log("[CONCILIACION] No se pudo construir el reporte del lote expulsado: " . $e->getMessage());
                    $snapshotReporte = null;
                }
            }

            $lote = 'LOTE-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2));
            $stats = $conciliacionModel->insertarExtracto($movimientos, $banco, $lote);
            $insertados = (int)$stats['insertados'];
            $adoptados = (int)($stats['adoptados'] ?? 0);
            $nuevos = $insertados + $adoptados;

            $ventanaPosterior = $conciliacionModel->obtenerLotesVentanaPorBanco($banco, CONCILIACION_VENTANA_LOTES);
            $nombresVentanaPosterior = array_column($ventanaPosterior, 'lote_importacion');

            // Sólo se archiva el candidato si el extracto aportó movimientos
            // nuevos y el lote realmente salió de la ventana posterior. Una
            // reimportación que sólo adopta filas no rota la ventana.
            $loteArchivado = ($loteExpulsado !== null
                && $nuevos > 0
                && !in_array($loteExpulsado, $nombresVentanaPosterior, true))
                ? $loteExpulsado
                : null;

            $reporteGuardado = null;
            if ($loteArchivado !== null && $snapshotReporte !== null) {
                try {
                    $reporteGuardado = $reporteService->guardar($snapshotReporte);
                } catch (\Throwable $e) {
                    error_log("[CONCILIACION] No se pudo guardar el reporte del lote archivado: " . $e->getMessage());
                    $reporteGuardado = null;
                }
            }

            // Cruce automático sobre todos los disponibles de la ventana.
            $resultadoCruce = null;
            $cruceError = false;
            if ($nuevos > 0) {
                try {
                    $extractos = $conciliacionModel->obtenerExtractosPendientesVentana(CONCILIACION_VENTANA_LOTES);
                    $resultadoCruce = $conciliacionService->ejecutarCruceInteligente($extractos);
                } catch (\Throwable $e) {
                    error_log("[CONCILIACION] Error en cruce automático tras importar: " . $e->getMessage());
                    $cruceError = true;
                }
            }

            $mensajeLote = $nuevos > 0
                ? "Extracto importado con éxito. Lote: {$lote}."
                : "Extracto procesado sin movimientos nuevos. Lote: {$lote}.";

            $msg = $mensajeLote
                . " Insertados: {$stats['insertados']} (Créditos), Adoptados: {$adoptados},"
                . " Débitos descartados: {$stats['debitos']}, Duplicados omitidos: {$stats['duplicados']}."
                . ' Ventana activa: ' . count($ventanaPosterior) . ' lote(s) del banco.';

            if ($nuevos === 0) {
                $msg .= ' La ventana no rotó porque no hubo movimientos nuevos.';
            } elseif ($insertados === 0 && $loteArchivado === null) {
                $msg .= ' La ventana no rotó: el extracto se reimportó sobre lotes existentes.';
            }

            if ($loteArchivado !== null) {
                $msg .= " Lote archivado: {$loteArchivado}.";
                if ($reporteGuardado !== null) {
                    $msg .= " Reporte histórico generado: {$reporteGuardado['archivo_pdf']} y {$reporteGuardado['archivo_csv']}.";
                } elseif ($snapshotReporte === null) {
                    $msg .= ' No se pudo construir el reporte histórico del lote archivado.';
                } else {
                    $msg .= ' No se pudo guardar el reporte histórico del lote archivado.';
                }
            }

            if ($resultadoCruce !== null) {
                $msg .= ' Cruce automático: '
                    . count($resultadoCruce['coincidencias_exactas']) . ' exactas, '
                    . count($resultadoCruce['coincidencias_sugeridas']) . ' sugeridas, '
                    . count($resultadoCruce['inconsistencias']) . ' inconsistencias, '
                    . count($resultadoCruce['sin_coincidencia']) . ' sin coincidencia.';
            } elseif ($cruceError) {
                $msg .= ' El cruce automático no pudo completarse; los movimientos quedaron disponibles para revisión.';
            }

            Flash::set($nuevos > 0 ? 'success' : 'info', $msg);

            $destino = $nuevos > 0
                ? '/admin/conciliacion?lote=' . urlencode($lote)
                : '/admin/conciliacion';
            $this->redirect($destino);
        } catch (\Exception $e) {
            error_log("[CONCILIACION] Error importar extracto: " . $e->getMessage());
            Flash::set('danger', 'Error al procesar el archivo del extracto bancario. Verifique el formato e intente de nuevo.');
            $this->redirect('/admin/conciliacion');
        }
    }

    /**
     * Genera bajo demanda y descarga el reporte de conciliación del lote activo.
     */
    public function descargarReporteActual() {
        Auth::requireRole(['admin', 'auditor']);

        $formatoRecibido = $_GET['formato'] ?? '';
        $formato = is_string($formatoRecibido) ? strtolower(trim($formatoRecibido)) : '';
        if (!in_array($formato, ['pdf', 'csv'], true)) {
            Flash::set('danger', 'Formato de reporte no válido. Solicite PDF o CSV.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        $reporteService = new ReporteConciliacionService();
        $conciliacionModel = new ConciliacionModel();
        $lotesVentana = $conciliacionModel->obtenerLotesVentana(CONCILIACION_VENTANA_LOTES);

        if (empty($lotesVentana)) {
            Flash::set('danger', 'No hay lotes en la ventana activa para generar el reporte de conciliación.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        try {
            $datos = $reporteService->construirVentana($lotesVentana);
            $contenido = ($formato === 'pdf')
                ? $reporteService->generarPdf($datos)
                : $reporteService->generarCsv($datos);
        } catch (\Throwable $e) {
            error_log("[CONCILIACION] Error al generar reporte actual: " . $e->getMessage());
            Flash::set('danger', 'No se pudo generar el reporte de conciliación.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        if ($contenido === '') {
            Flash::set('danger', 'El reporte generado está vacío.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        $rutaTemporal = tempnam(sys_get_temp_dir(), 'concrep_');
        if ($rutaTemporal === false) {
            Flash::set('danger', 'No se pudo preparar el archivo temporal del reporte.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        register_shutdown_function(static function () use ($rutaTemporal): void {
            if (is_file($rutaTemporal)) {
                @unlink($rutaTemporal);
            }
        });

        if (@file_put_contents($rutaTemporal, $contenido, LOCK_EX) === false) {
            Flash::set('danger', 'No se pudo escribir el archivo temporal del reporte.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        $etiquetaReporte = (string)($datos['lote'] ?? 'ventana');
        $loteSeguro = preg_replace('/[^A-Za-z0-9_-]/', '_', $etiquetaReporte);
        $loteSeguro = is_string($loteSeguro) ? trim($loteSeguro, '._-') : '';
        if ($loteSeguro === '') {
            $loteSeguro = 'lote';
        }

        $nombre = 'reporte_conciliacion_' . $loteSeguro . '_' . date('Ymd-His') . '.' . $formato;
        $mime = ($formato === 'pdf') ? 'application/pdf' : 'text/csv; charset=UTF-8';

        $this->enviarArchivo($rutaTemporal, $nombre, $mime);
    }

    /**
     * Descarga un reporte histórico previamente guardado en disco.
     */
    public function descargarReporteHistorico() {
        Auth::requireRole(['admin', 'auditor']);

        $reporteService = new ReporteConciliacionService();
        $archivoRecibido = $_GET['archivo'] ?? '';
        $archivo = is_string($archivoRecibido) ? $archivoRecibido : '';
        $ruta = $reporteService->resolverArchivo($archivo);

        if ($ruta === null) {
            Flash::set('danger', 'El archivo de reporte solicitado no existe o no es válido.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $mimes = [
            'pdf' => 'application/pdf',
            'csv' => 'text/csv; charset=UTF-8',
        ];

        if (!isset($mimes[$extension])) {
            Flash::set('danger', 'Formato de archivo de reporte no permitido.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        $this->enviarArchivo($ruta, basename($ruta), $mimes[$extension]);
    }

    /**
     * Transmite un archivo de reporte con cabeceras seguras de descarga.
     */
    private function enviarArchivo(string $ruta, string $nombre, string $mime): void {
        if (!is_file($ruta) || !is_readable($ruta)) {
            Flash::set('danger', 'El archivo de reporte no está disponible para descarga.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        $nombreSeguro = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($nombre));
        if (!is_string($nombreSeguro) || $nombreSeguro === '') {
            $nombreSeguro = 'reporte';
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $nombreSeguro . '"');
        header('Content-Length: ' . (string)filesize($ruta));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');

        readfile($ruta);
        exit;
    }

    /**
     * Concilia y aprueba un pago individual de 1-clic.
     */
    public function conciliarPago() {
        Auth::requireRole('admin');

        $isAjax = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
               || (!empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
               || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');

        // T6a: aceptar cuerpo JSON cuando los ids no llegan por $_POST (el path form-encoded no cambia)
        $entrada = $_POST;
        if (!isset($_POST['extracto_id']) && !isset($_POST['movimiento_id']) && !isset($_POST['pago_id'])
            && !empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
            $cuerpoJson = json_decode(file_get_contents('php://input'), true);
            if (is_array($cuerpoJson)) {
                $entrada = $cuerpoJson;
            }
        }

        // Solo escalares numéricos: evita que intval(['x']) coaccione un id array a 1
        $idValido = static function ($valor): int {
            return (is_scalar($valor) && is_numeric($valor)) ? intval($valor) : 0;
        };

        $extractoId     = $idValido($entrada['extracto_id'] ?? ($entrada['movimiento_id'] ?? 0));
        $pagoId         = $idValido($entrada['pago_id'] ?? 0);
        $origenTipo     = (isset($entrada['origen_tipo']) && is_string($entrada['origen_tipo'])) ? trim($entrada['origen_tipo']) : 'auto';
        $idempotencyRaw = $entrada['idempotency_key'] ?? ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '');
        $idempotencyKey = is_string($idempotencyRaw) ? trim($idempotencyRaw) : '';
        $adminId        = intval(Auth::id() ?? 1);

        if ($extractoId <= 0 || $pagoId <= 0) {
            if ($isAjax) {
                $this->json([
                    'success' => false,
                    'codigo'  => 'PARAMETROS_INVALIDOS',
                    'error'   => 'Parámetros de conciliación inválidos.',
                    'csrf_token' => $_SESSION['csrf_token'] ?? ''
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
                    'mensaje'     => $resultado['mensaje'],
                    'csrf_token'  => $_SESSION['csrf_token'] ?? ''
                ], 200);
                return;
            }

            Flash::set('success', $resultado['mensaje']);
        } catch (\App\Core\ConciliacionException $ce) {
            if ($isAjax) {
                $this->json([
                    'success' => false,
                    'codigo'  => $ce->getCodigoNegocio(),
                    'error'   => $ce->getMessage(),
                    'csrf_token' => $_SESSION['csrf_token'] ?? ''
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
                    'error'   => 'Error al procesar la conciliación del pago.',
                    'csrf_token' => $_SESSION['csrf_token'] ?? ''
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
        Auth::requireRole('admin');

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

        // T6d: solo ids escalares numéricos > 0 (intval(array) coaccionaría a 1)
        $idValido = static function ($valor): bool {
            return is_scalar($valor) && is_numeric($valor) && intval($valor) > 0;
        };
        $validItems = array_filter($items, fn($it) => is_array($it) && $idValido($it['extracto_id'] ?? 0) && $idValido($it['pago_id'] ?? 0));
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
        Auth::requireRole('admin');

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
        Auth::requireRole('admin');

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

                if ($ok) {
                    if (!empty($compModel->ultimaAdvertencia)) {
                        Flash::set('info', $compModel->ultimaAdvertencia);
                    }
                    Flash::set('success', 'Pago verificado y aprobado exitosamente desde Conciliación.');
                } else {
                    Flash::set('danger', $compModel->ultimoError ?: 'No se pudo verificar el pago.');
                }
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

                if ($ok) {
                    if (!empty($resultado['warning'])) {
                        Flash::set('info', $resultado['warning']);
                    }
                    Flash::set('success', 'Pago verificado y aprobado exitosamente desde Conciliación.');
                } else {
                    Flash::set('danger', $resultado['message'] ?? 'No se pudo verificar el pago.');
                }
            }
        } catch (\Exception $e) {
            error_log("[CONCILIACION] Error al verificar pago directo: " . $e->getMessage());
            Flash::set('danger', 'Error al verificar el pago: ' . $e->getMessage());
        }

        $this->redirect('/admin/conciliacion');
    }
}
