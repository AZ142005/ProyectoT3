<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\GastosModel;
use App\Models\CategoriasGastosModel;
use App\Models\UnidadesModel;
use App\Models\FacturasModel;
use App\Models\CarteleraModel;
use App\Models\DeudasManualesModel;

class GastoController extends Controller {

    /**
     * Muestra el panel administrativo unificado de Carga y Emisión.
     */
    public function index() {
        Auth::requireRole(['admin', 'auditor']);

        $tabActual = ($_GET['tab'] ?? 'gastos') === 'facturacion' ? 'facturacion' : 'gastos';

        $pagina = max(1, intval($_GET['page'] ?? 1));
        $mes = !empty($_GET['mes']) ? intval($_GET['mes']) : intval(date('n'));
        $anio = !empty($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));

        $gastosModel = new GastosModel();
        $categoriasModel = new CategoriasGastosModel();
        $edificiosModel = new \App\Models\EdificiosModel();
        $unidadesModel = new UnidadesModel();
        $facturasModel = new FacturasModel();

        $categorias = $categoriasModel->getActivas();
        $edificios = $edificiosModel->getAll();
        $totalesPorCategoria = $gastosModel->obtenerTotalesPorCategoria($mes, $anio);
        $totalMes = $gastosModel->obtenerTotalGastoMes($mes, $anio);

        // Historial de reportes cargados (último mes)
        $historial = (new CarteleraModel())->obtenerHistorialUltimoMes($pagina, 25);
        foreach ($historial['datos'] as &$filaReporte) {
            $filaReporte['archivo_existe'] = !empty($filaReporte['archivo'])
                && is_file(UPLOADS_PATH . '/soportes/' . $filaReporte['archivo']);
        }
        unset($filaReporte);

        // Datos para la sección de Emisión de Deuda
        $unidades = $unidadesModel->getActivas();
        $facturas_existentes = $facturasModel->countByPeriod($mes, $anio);
        $cartelera_existentes = $facturasModel->countCarteleraByPeriod($mes, $anio);
        $distribucion = $gastosModel->calcularDistribucionCuotas($mes, $anio);
        $deudasManuales = (new DeudasManualesModel())->obtenerPorPeriodo($mes, $anio);

        $paginacion = [
            'total'        => $historial['total'],
            'pagina'       => $historial['pagina'],
            'porPagina'    => $historial['porPagina'],
            'totalPaginas' => $historial['totalPaginas'],
        ];

        $this->render('admin/gastos/index', [
            'tabActual'           => $tabActual,
            'historial'           => $historial['datos'],
            'categorias'          => $categorias,
            'edificios'           => $edificios,
            'totalesPorCategoria' => $totalesPorCategoria,
            'totalMes'            => $totalMes,
            'paginacion'          => $paginacion,
            'unidades'            => $unidades,
            'mes'                 => $mes,
            'anio'                => $anio,
            'facturas_existentes' => $facturas_existentes,
            'cartelera_existentes' => $cartelera_existentes,
            'distribucion'        => $distribucion,
            'deudasManuales'      => $deudasManuales,
            'totalGastosMes'      => $totalMes,
            'layout'              => 'admin',
            'title'               => 'Carga y Emisión - Administrador'
        ]);
    }

    /**
     * Procesa la generación de facturas delegando en el controlador de facturación.
     */
    public function generarFacturas() {
        (new AdminController())->generarFacturas();
    }

    /**
     * Guarda un nuevo gasto (común o individual) con soporte digital adjunto.
     */
    public function guardar() {
        Auth::requireRole('admin');

        $categoriaId = intval($_POST['categoria_id'] ?? 0);
        $mes = $this->periodoDesdePost('mes', intval(date('n')));
        $anio = $this->periodoDesdePost('anio', intval(date('Y')));
        $descripcion = trim($_POST['descripcion'] ?? '');
        $montoTotal = floatval($_POST['monto_total'] ?? 0);
        $fechaGasto = trim($_POST['fecha_gasto'] ?? date('Y-m-d'));
        $proveedor = trim($_POST['proveedor'] ?? '');
        $nroFactura = trim($_POST['nro_factura_proveedor'] ?? '');
        $tipoGasto = ($_POST['tipo_gasto'] ?? 'comun') === 'individual' ? 'individual' : 'comun';
        $edificioId = intval($_POST['edificio_id'] ?? 0);
        $adminId = Auth::id() ?? 1;

        if ($categoriaId <= 0 || empty($descripcion) || $montoTotal <= 0 || empty($proveedor)) {
            Flash::set('danger', 'Todos los campos marcados con (*) son obligatorios y el monto debe ser superior a 0.');
            $this->redirect('/admin/gastos');
            return;
        }

        if ($tipoGasto === 'individual' && $edificioId <= 0) {
            Flash::set('danger', 'Debe seleccionar un edificio para registrar un gasto individual.');
            $this->redirect('/admin/gastos');
            return;
        }

        if (mb_strlen($proveedor) > 150 || mb_strlen($descripcion) > 500) {
            Flash::set('danger', 'El proveedor o la descripción exceden la longitud máxima permitida.');
            $this->redirect('/admin/gastos');
            return;
        }

        $nombreArchivoSoporte = null;

        // T6e: si se adjuntó un soporte y la subida falló, no guardar el gasto sin soporte.
        // UPLOAD_ERR_NO_FILE se exceptúa porque el soporte es opcional en el formulario.
        if (isset($_FILES['soporte_digital']) && $_FILES['soporte_digital']['error'] !== UPLOAD_ERR_NO_FILE
            && $_FILES['soporte_digital']['error'] !== UPLOAD_ERR_OK) {
            Flash::error('No se pudo recibir el archivo de soporte. Verifique el tamaño máximo de 5MB e intente de nuevo.');
            $this->redirect('/admin/gastos');
            return;
        }

        try {
            // Procesar subida de soporte digital con MIME validation real.
            // Dentro del try: una RuntimeException del uploader se reporta con flash, no como 500.
            if (isset($_FILES['soporte_digital']) && $_FILES['soporte_digital']['error'] === UPLOAD_ERR_OK) {
                $uploader = new \App\Services\FileUploader(
                    UPLOADS_PATH . '/soportes',
                    ['image/jpeg', 'image/png', 'application/pdf'],
                    ['jpg', 'jpeg', 'png', 'pdf'],
                    5242880
                );
                $nombreArchivoSoporte = $uploader->upload($_FILES['soporte_digital']);

                if (!$nombreArchivoSoporte) {
                    Flash::set('danger', 'Formato o tamaño de soporte no permitido. Solo se aceptan archivos PDF, JPG o PNG hasta 5MB.');
                    $this->redirect('/admin/gastos');
                    return;
                }
            }

            $gastosModel = new GastosModel();
            $gastoId = $gastosModel->crearGasto([
                'categoria_id'          => $categoriaId,
                'mes'                   => $mes,
                'anio'                  => $anio,
                'descripcion'           => $descripcion,
                'monto_total'           => $montoTotal,
                'fecha_gasto'           => $fechaGasto,
                'proveedor'             => $proveedor,
                'nro_factura_proveedor' => $nroFactura,
                'soporte_digital'       => $nombreArchivoSoporte,
                'admin_id'              => $adminId,
                'tipo_gasto'            => $tipoGasto,
                'edificio_id'           => ($tipoGasto === 'individual') ? $edificioId : null,
            ]);

            if (!$gastoId) {
                // Carrera detectada por la guardia única (23000/1062) durante el INSERT
                Flash::set('danger', 'Ya existe un gasto con los mismos datos en el período (posible envío duplicado).');
            } else {
                Flash::set('success', 'Gasto registrado exitosamente con su soporte digital.');
            }
        } catch (\Exception $e) {
            if (str_starts_with($e->getMessage(), 'Ya existe un gasto')) {
                // Duplicado detectado por el pre-chequeo (factura F o guarda S)
                Flash::set('danger', 'Ya existe un gasto con los mismos datos en el período (posible envío duplicado).');
            } else {
                error_log("[GASTO] Error registrar gasto: " . $e->getMessage());
                Flash::set('danger', 'Error al registrar el gasto. Verifique los datos e intente de nuevo.');
            }
        }

        $this->redirect('/admin/gastos?mes=' . $mes . '&anio=' . $anio);
    }

    /**
     * Elimina un gasto común y su archivo físico de soporte.
     */
    public function eliminar() {
        Auth::requireRole('admin');

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('danger', 'ID de gasto no válido.');
            $this->redirect('/admin/gastos');
            return;
        }

        $gastosModel = new GastosModel();
        $db = \App\Core\Database::getConnection();

        // Fetch gasto data for existence check, prorrateo check, and redirect context
        $stmtGasto = $db->prepare("SELECT * FROM gastos_comunes WHERE id = :id AND deleted_at IS NULL");
        $stmtGasto->execute(['id' => $id]);
        $gasto = $stmtGasto->fetch(\PDO::FETCH_ASSOC);

        if (!$gasto) {
            Flash::set('danger', 'Gasto no encontrado o ya fue eliminado.');
            $this->redirect('/admin/gastos');
            return;
        }

        // 4.1: Check if gasto has been prorated into movimientos_cuenta
        // Prorated movements reference the gasto by ID in the description field
        $stmtProrrateo = $db->prepare(""
            . "SELECT COUNT(*) as cnt FROM movimientos_cuenta "
            . "WHERE tipo = 'cargo_factura' AND descripcion LIKE :patron"
        );
        $stmtProrrateo->execute(['patron' => '%gasto#' . $id . '%']);
        $tieneProrrateo = intval($stmtProrrateo->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;

        if ($tieneProrrateo) {
            Flash::set('danger', 'No se puede eliminar este gasto: ya tiene prorrateo aplicado en el libro mayor de unidades. Contacte al administrador de sistema.');
            $this->redirect('/admin/gastos?mes=' . intval($gasto['mes']) . '&anio=' . intval($gasto['anio']));
            return;
        }

        $gastosModel->eliminarGasto($id);
        Flash::set('success', 'Gasto común eliminado y soporte físico liberado del servidor.');
        $this->redirect('/admin/gastos?mes=' . intval($gasto['mes']) . '&anio=' . intval($gasto['anio']));
    }

    /**
     * Guarda deuda manual (monto exacto por unidad) para un período.
     *
     * Alcance "todo": todas las unidades activas. Alcance "seleccion": unión sin
     * duplicados de las unidades de los edificios elegidos más las unidades
     * elegidas directamente. El monto se aplica completo a cada unidad (no se
     * reparte) y reemplaza el monto anterior de esas unidades en el período.
     * Lo guardado se materializa al enviar/re-enviar la deuda del período.
     */
    public function guardarDeudaManual() {
        Auth::requireRole('admin');

        $alcance = ($_POST['alcance'] ?? '') === 'todo' ? 'todo' : 'seleccion';
        $mes = $this->periodoDesdePost('mes', intval(date('n')));
        $anio = $this->periodoDesdePost('anio', intval(date('Y')));
        $montoRaw = $_POST['monto_unidad'] ?? null;
        $monto = (is_scalar($montoRaw) && is_numeric($montoRaw)) ? floatval($montoRaw) : 0.0;
        $adminId = Auth::id() ?? 1;

        if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            Flash::set('danger', 'Período inválido para la deuda manual.');
            $this->redirect('/admin/gastos');
            return;
        }

        if ($monto < 0.01 || $monto > 999999.99) {
            Flash::set('danger', 'El monto por unidad debe estar entre Bs. 0,01 y Bs. 999.999,99.');
            $this->redirect('/admin/gastos?mes=' . $mes . '&anio=' . $anio);
            return;
        }

        // Resolución de unidades: solo unidades activas, con deduplicación
        $unidadesActivas = (new UnidadesModel())->getActivas();
        $activasPorId = [];
        $activasPorEdificio = [];
        foreach ($unidadesActivas as $u) {
            $uid = intval($u['id']);
            $activasPorId[$uid] = $u;
            $activasPorEdificio[intval($u['edificio_id'] ?? 0)][] = $uid;
        }

        $seleccion = [];
        if ($alcance === 'todo') {
            foreach (array_keys($activasPorId) as $uid) {
                $seleccion[$uid] = true;
            }
        } else {
            $edificiosRaw = $_POST['edificios'] ?? [];
            foreach ((is_array($edificiosRaw) ? $edificiosRaw : []) as $edificioRaw) {
                if (!is_scalar($edificioRaw) || !is_numeric($edificioRaw)) {
                    continue;
                }
                foreach ($activasPorEdificio[intval($edificioRaw)] ?? [] as $uid) {
                    $seleccion[$uid] = true;
                }
            }

            $unidadesRaw = $_POST['unidades'] ?? [];
            foreach ((is_array($unidadesRaw) ? $unidadesRaw : []) as $unidadRaw) {
                if (!is_scalar($unidadRaw) || !is_numeric($unidadRaw)) {
                    continue;
                }
                $uid = intval($unidadRaw);
                if (isset($activasPorId[$uid])) {
                    $seleccion[$uid] = true;
                }
            }
        }

        $unidadIds = array_keys($seleccion);
        if (empty($unidadIds)) {
            Flash::set('danger', 'Seleccione al menos una unidad o edificio con unidades activas.');
            $this->redirect('/admin/gastos?mes=' . $mes . '&anio=' . $anio);
            return;
        }

        try {
            $guardadas = (new DeudasManualesModel())->guardarLote($mes, $anio, $unidadIds, $monto, $adminId);
            Flash::set('success', 'Deuda manual guardada: ' . $guardadas . ' unidades a ' . formatearMoneda($monto) . ' c/u. Se aplicará al enviar/re-enviar la deuda del período.');
        } catch (\Throwable $e) {
            error_log('[GASTO] Error al guardar deuda manual: ' . $e->getMessage());
            Flash::set('danger', 'Ocurrió un error al guardar la deuda manual. Intente de nuevo.');
        }

        $this->redirect('/admin/gastos?mes=' . $mes . '&anio=' . $anio);
    }

    /**
     * Elimina (baja lógica) la deuda manual de una unidad en un período.
     */
    public function eliminarDeudaManual() {
        Auth::requireRole('admin');

        $unidadId = intval($_POST['unidad_id'] ?? 0);
        $mes = $this->periodoDesdePost('mes', intval(date('n')));
        $anio = $this->periodoDesdePost('anio', intval(date('Y')));

        if ($unidadId <= 0 || $mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            Flash::set('danger', 'Datos inválidos para eliminar la deuda manual.');
            $this->redirect('/admin/gastos');
            return;
        }

        try {
            if ((new DeudasManualesModel())->eliminar($unidadId, $mes, $anio)) {
                Flash::set('success', 'Deuda manual eliminada. Se aplicará al enviar/re-enviar la deuda del período.');
            } else {
                Flash::set('danger', 'No se encontró una deuda manual activa para esa unidad en el período.');
            }
        } catch (\Throwable $e) {
            error_log('[GASTO] Error al eliminar deuda manual: ' . $e->getMessage());
            Flash::set('danger', 'Ocurrió un error al eliminar la deuda manual.');
        }

        $this->redirect('/admin/gastos?mes=' . $mes . '&anio=' . $anio);
    }

    /**
     * Elimina (baja lógica) todas las deudas manuales de un período.
     */
    public function eliminarDeudasManualesPeriodo() {
        Auth::requireRole('admin');

        $mes = $this->periodoDesdePost('mes', intval(date('n')));
        $anio = $this->periodoDesdePost('anio', intval(date('Y')));

        if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            Flash::set('danger', 'Período inválido para limpiar las deudas manuales.');
            $this->redirect('/admin/gastos');
            return;
        }

        try {
            $eliminadas = (new DeudasManualesModel())->eliminarPeriodo($mes, $anio);
            if ($eliminadas > 0) {
                Flash::set('success', 'Se eliminaron ' . $eliminadas . ' deudas manuales del período. Se aplicará al enviar/re-enviar la deuda del período.');
            } else {
                Flash::set('info', 'No había deudas manuales guardadas para el período.');
            }
        } catch (\Throwable $e) {
            error_log('[GASTO] Error al limpiar deudas manuales del período: ' . $e->getMessage());
            Flash::set('danger', 'Ocurrió un error al limpiar las deudas manuales del período.');
        }

        $this->redirect('/admin/gastos?mes=' . $mes . '&anio=' . $anio);
    }

    /**
     * Muestra la pantalla de Rendición de Cuentas y Justificación de Gastos para Residentes.
     */
    public function rendicionResidente() {
        Auth::requireRole('residente');
        $residente = $this->getAuthenticatedResidente();
        $unidadId = intval($residente['unidad_id'] ?? 0);
        $edificioId = intval($residente['edificio_id'] ?? 0);

        $mes = !empty($_GET['mes']) ? intval($_GET['mes']) : intval(date('n'));
        $anio = !empty($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));

        $gastosModel = new GastosModel();

        // Obtener gastos del período: comunes globales + individuales de su edificio (o todos si no tiene edificio)
        $gastos = $gastosModel->obtenerGastosPorPeriodo($mes, $anio, 200, $edificioId ?: null);
        $totalesPorCategoria = $gastosModel->obtenerTotalesPorCategoria($mes, $anio);
        $totalMes = $gastosModel->obtenerTotalGastoMes($mes, $anio);

        // Optimización de conteo directo de unidades activas
        $db = \App\Core\Database::getConnection();
        $stmtCount = $db->query("SELECT COUNT(*) as cnt FROM unidades WHERE estado = 1");
        $unidadesActivas = intval($stmtCount->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0);
        $alicuotaEstimada = ($unidadesActivas > 0) ? round($totalMes / $unidadesActivas, 2) : 0.00;

        // Distribución dinámica de cuotas (Global + Edificio)
        $distribucion = $gastosModel->calcularDistribucionCuotas($mes, $anio);
        $infoUnidad = $distribucion['unidades'][$unidadId] ?? null;

        $totalGlobal = $distribucion['total_global'] ?? 0.0;
        $cuotaGlobal = $distribucion['cuota_global_unidad'] ?? 0.0;

        $infoEdificio = $distribucion['edificios'][$edificioId] ?? null;
        $totalEdificio = $infoEdificio['total_gastos_individual'] ?? 0.0;
        $unidadesEdificio = $infoEdificio['total_unidades'] ?? 0;
        $cuotaEdificio = $infoEdificio['cuota_individual_unidad'] ?? 0.0;

        $cuotaTotalUnidad = $infoUnidad['cuota_total'] ?? round($cuotaGlobal + $cuotaEdificio, 2);

        $this->render('residente/gastos', [
            'gastos'              => $gastos,
            'totalesPorCategoria' => $totalesPorCategoria,
            'totalMes'            => $totalMes,
            'totalGlobal'         => $totalGlobal,
            'totalEdificio'       => $totalEdificio,
            'unidadesActivas'     => $unidadesActivas,
            'unidadesEdificio'    => $unidadesEdificio,
            'cuotaGlobal'         => $cuotaGlobal,
            'cuotaEdificio'       => $cuotaEdificio,
            'cuotaTotalUnidad'    => $cuotaTotalUnidad,
            'alicuotaEstimada'    => $cuotaTotalUnidad ?: $alicuotaEstimada,
            'residente'           => $residente,
            'mes'                 => $mes,
            'anio'                => $anio,
            'title'               => 'Rendición de Cuentas y Gastos'
        ]);
    }

    /**
     * Muestra la interfaz de ingesta y carga del PDF Maestro de Gastos (RF 30, RF 31).
     */
    public function cargarMaestro() {
        Auth::requireRole(['admin', 'auditor']);

        $mes = !empty($_GET['mes']) ? intval($_GET['mes']) : intval(date('n'));
        $anio = !empty($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));

        $categoriasModel = new CategoriasGastosModel();
        $categorias = $categoriasModel->getActivas();

        $renglones = $_SESSION['maestro_renglones_preview'] ?? [];
        $archivo = $_SESSION['maestro_archivo_preview'] ?? null;

        $this->render('admin/gastos/cargar_maestro', [
            'mes'        => $mes,
            'anio'       => $anio,
            'categorias' => $categorias,
            'renglones'  => $renglones,
            'archivo'    => $archivo,
            'layout'     => 'admin',
            'title'      => 'Ingesta de PDF Maestro de Gastos'
        ]);
    }

    /**
     * Procesa la extracción estructurada del PDF Maestro (RF 30, RF 31, RF 32).
     */
    public function parsearMaestro() {
        Auth::requireRole(['admin', 'auditor']);

        $mes = $this->periodoDesdePost('mes', intval(date('n')));
        $anio = $this->periodoDesdePost('anio', intval(date('Y')));
        $textoManual = trim($_POST['texto_manual'] ?? '');
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
               || !empty($_POST['ajax']);

        $categoriasModel = new CategoriasGastosModel();
        $categorias = $categoriasModel->getActivas();
        $parserService = new \App\Services\GastoParserService();

        $nombreArchivoSoporte = null;
        $renglonesExtraidos = [];

        // 1. Carga de archivo PDF Maestro
        if (!empty($_FILES['pdf_maestro']) && $_FILES['pdf_maestro']['error'] === UPLOAD_ERR_OK) {
            try {
                $uploader = new \App\Services\FileUploader(
                    UPLOADS_PATH . '/soportes',
                    ['application/pdf'],
                    ['pdf'],
                    10485760 // 10MB
                );
                $nombreArchivoSoporte = $uploader->upload($_FILES['pdf_maestro']);
            } catch (\Throwable $e) {
                error_log('[GASTO] Error al procesar PDF maestro: ' . $e->getMessage());
                $nombreArchivoSoporte = null;
            }

            if (!$nombreArchivoSoporte) {
                if ($isAjax) {
                    $this->json(['success' => false, 'message' => 'Archivo no válido. Solo se aceptan PDFs de hasta 10MB.']);
                    return;
                }
                Flash::set('danger', 'Archivo no válido. Solo se aceptan PDFs de hasta 10MB.');
                $this->redirect("/admin/gastos/maestro?mes={$mes}&anio={$anio}");
                return;
            }

            $rutaCompleta = UPLOADS_PATH . '/soportes/' . $nombreArchivoSoporte;
            $resultado = $parserService->procesarPdfMaestro($rutaCompleta, $mes, $anio, $categorias);
            $renglonesExtraidos = $resultado['renglones'];
        } elseif (!empty($textoManual)) {
            // 2. Extracción de texto manual/pegado
            $renglonesExtraidos = $parserService->analizarLineasGastos($textoManual, 1, $categorias, $mes, $anio);
        } else {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'Debe adjuntar un archivo PDF maestro o ingresar el texto de los gastos.']);
                return;
            }
            Flash::set('danger', 'Debe adjuntar un archivo PDF maestro o ingresar el texto de los gastos.');
            $this->redirect("/admin/gastos/maestro?mes={$mes}&anio={$anio}");
            return;
        }

        if (empty($renglonesExtraidos)) {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'No se detectaron renglones estructurados en el documento. Puede agregarlos manualmente.']);
                return;
            }
            Flash::set('warning', 'No se pudieron extraer renglones de gastos automáticamente. Puede ingresarlos manualmente en la tabla.');
        }

        if ($isAjax) {
            $this->json([
                'success'   => true,
                'archivo'   => $nombreArchivoSoporte,
                'renglones' => $renglonesExtraidos,
                'total'     => count($renglonesExtraidos),
                'suma'      => round(array_sum(array_column($renglonesExtraidos, 'monto_total')), 2)
            ]);
            return;
        }

        $_SESSION['maestro_renglones_preview'] = $renglonesExtraidos;
        $_SESSION['maestro_archivo_preview'] = $nombreArchivoSoporte;

        Flash::set('info', 'Se detectaron ' . count($renglonesExtraidos) . ' renglones en el PDF Maestro. Revise y confirme antes de guardar.');
        $this->redirect("/admin/gastos/maestro?mes={$mes}&anio={$anio}");
    }

    /**
     * Guarda el lote confirmado de gastos comunes desde el PDF Maestro (RF 31, RF 33, RF 34).
     */
    public function importarMaestro() {
        Auth::requireRole('admin');

        $mes = $this->periodoDesdePost('mes', intval(date('n')));
        $anio = $this->periodoDesdePost('anio', intval(date('Y')));
        // Período inválido (incluye coerción de arrays) no debe llegar a la importación
        if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            Flash::set('danger', 'Período inválido.');
            $this->redirect('/admin/gastos/maestro');
            return;
        }

        // T2: saneamiento estricto del nombre del archivo maestro (anti path traversal)
        $archivoMaestroRaw = $_POST['archivo_maestro'] ?? '';
        $archivoMaestro = is_string($archivoMaestroRaw) ? trim($archivoMaestroRaw) : '';
        $archivoMaestro = str_replace("\0", '', $archivoMaestro);
        $archivoMaestro = str_replace('\\', '/', $archivoMaestro);
        $archivoMaestro = basename($archivoMaestro);
        $gastosRaw = $_POST['gastos'] ?? [];
        $adminId = Auth::id() ?? 1;

        // Hash del PDF ya almacenado (server-side) para idempotencia de re-ingesta
        $soporteHash = null;
        if ($archivoMaestro !== '') {
            $rutaSoporte = UPLOADS_PATH . '/soportes/' . basename($archivoMaestro);
            if (is_file($rutaSoporte)) {
                $hashCalculado = @hash_file('sha256', $rutaSoporte);
                if ($hashCalculado !== false) {
                    $soporteHash = $hashCalculado;
                }
            }
        }

        if (empty($gastosRaw) || !is_array($gastosRaw)) {
            Flash::set('danger', 'No hay gastos especificados para importar.');
            $this->redirect("/admin/gastos/maestro?mes={$mes}&anio={$anio}");
            return;
        }

        $itemsAImportar = [];
        foreach ($gastosRaw as $g) {
            if (!is_array($g)) {
                continue;
            }

            // T6d: guardas de tipo antes de trim/floatval/intval (un array provocaría TypeError o coerción a 1)
            $montoRaw = $g['monto_total'] ?? 0;
            $monto = (is_scalar($montoRaw) && is_numeric($montoRaw)) ? floatval($montoRaw) : 0.0;
            $proveedor = (isset($g['proveedor']) && is_string($g['proveedor'])) ? trim($g['proveedor']) : '';
            $descripcion = (isset($g['descripcion']) && is_string($g['descripcion'])) ? trim($g['descripcion']) : '';

            if ($monto > 0 && !empty($proveedor) && !empty($descripcion)) {
                $categoriaRaw = $g['categoria_id'] ?? 1;
                $fechaRaw = $g['fecha_gasto'] ?? '';
                $nroFacturaRaw = $g['nro_factura_proveedor'] ?? '';
                $paginaRaw = $g['pagina_soporte'] ?? 1;
                $extractoRaw = $g['extracto_texto'] ?? '';

                $itemsAImportar[] = [
                    'categoria_id'          => (is_scalar($categoriaRaw) && is_numeric($categoriaRaw)) ? intval($categoriaRaw) : 1,
                    'descripcion'           => $descripcion,
                    'monto_total'           => $monto,
                    'fecha_gasto'           => (is_string($fechaRaw) && trim($fechaRaw) !== '') ? trim($fechaRaw) : sprintf('%04d-%02d-01', $anio, $mes),
                    'proveedor'             => $proveedor,
                    'nro_factura_proveedor' => (is_string($nroFacturaRaw) && trim($nroFacturaRaw) !== '') ? trim($nroFacturaRaw) : null,
                    'pagina_soporte'        => (is_scalar($paginaRaw) && is_numeric($paginaRaw)) ? max(1, intval($paginaRaw)) : 1,
                    'extracto_texto'        => (is_string($extractoRaw) && trim($extractoRaw) !== '') ? trim($extractoRaw) : null
                ];
            }
        }

        if (empty($itemsAImportar)) {
            Flash::set('danger', 'Ningún renglón contiene datos válidos (monto > 0, proveedor y descripción requeridos).');
            $this->redirect("/admin/gastos/maestro?mes={$mes}&anio={$anio}");
            return;
        }

        try {
            $gastosModel = new GastosModel();
            $resultado = $gastosModel->importarGastosMaestro($itemsAImportar, $adminId, $archivoMaestro, $mes, $anio, $soporteHash);

            unset($_SESSION['maestro_renglones_preview'], $_SESSION['maestro_archivo_preview']);

            if (!empty($resultado['archivo_ya_importado'])) {
                Flash::set('info', "Este PDF ya fue importado para el período; se omitieron {$resultado['omitidos']} renglones.");
                $this->redirect("/admin/gastos?mes={$mes}&anio={$anio}");
                return;
            }

            $msg = "Se importaron exitosamente {$resultado['procesados']} gastos comunes desde el PDF Maestro.";
            if ($resultado['omitidos'] > 0) {
                $msg .= " ({$resultado['omitidos']} fueron omitidos por duplicidad o datos incompletos).";
            }
            Flash::set('success', $msg);
            $this->redirect("/admin/gastos?mes={$mes}&anio={$anio}");
        } catch (\Exception $e) {
            error_log("[GASTO] Error al importar lote maestro: " . $e->getMessage());
            Flash::set('danger', 'Ocurrió un error al guardar los gastos en la base de datos.');
            $this->redirect("/admin/gastos/maestro?mes={$mes}&anio={$anio}");
        }
    }

    /**
     * Normaliza un período (mes/año) recibido por POST: solo escalares numéricos.
     * Ausente o vacío => valor por defecto; array o texto inválido => 0 (será rechazado).
     */
    private function periodoDesdePost(string $clave, int $default): int {
        $raw = $_POST[$clave] ?? null;
        if ($raw === null || $raw === '') {
            return $default;
        }
        return (is_scalar($raw) && is_numeric($raw)) ? intval($raw) : 0;
    }
}
