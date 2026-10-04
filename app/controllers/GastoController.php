<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\GastosModel;
use App\Models\CategoriasGastosModel;
use App\Models\UnidadesModel;
use App\Models\FacturasModel;

class GastoController extends Controller {

    /**
     * Muestra el panel administrativo unificado de Gastos y Facturación.
     */
    public function index() {
        Auth::requireRole(['admin', 'auditor']);

        $tabActual = ($_GET['tab'] ?? 'gastos') === 'facturacion' ? 'facturacion' : 'gastos';

        $pagina = max(1, intval($_GET['page'] ?? 1));
        $mes = !empty($_GET['mes']) ? intval($_GET['mes']) : intval(date('n'));
        $anio = !empty($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));
        $categoriaId = !empty($_GET['categoria_id']) ? intval($_GET['categoria_id']) : null;
        $tipoGasto = !empty($_GET['tipo_gasto']) ? trim($_GET['tipo_gasto']) : null;
        $edificioId = !empty($_GET['edificio_id']) ? intval($_GET['edificio_id']) : null;

        $gastosModel = new GastosModel();
        $categoriasModel = new CategoriasGastosModel();
        $edificiosModel = new \App\Models\EdificiosModel();
        $unidadesModel = new UnidadesModel();
        $facturasModel = new FacturasModel();

        $filtros = [
            'mes'          => $mes,
            'anio'         => $anio,
            'categoria_id' => $categoriaId,
            'tipo_gasto'   => $tipoGasto,
            'edificio_id'  => $edificioId,
        ];
        $resultado = $gastosModel->obtenerGastosAdmin($pagina, 25, $filtros);
        $categorias = $categoriasModel->getActivas();
        $edificios = $edificiosModel->getAll();
        $totalesPorCategoria = $gastosModel->obtenerTotalesPorCategoria($mes, $anio);
        $totalMes = $gastosModel->obtenerTotalGastoMes($mes, $anio);

        // Datos para la pestaña de Emisión de Facturas
        $unidades = $unidadesModel->getActivas();
        $facturas_existentes = $facturasModel->countByPeriod($mes, $anio);
        $distribucion = $gastosModel->calcularDistribucionCuotas($mes, $anio);

        $paginacion = [
            'total'        => $resultado['total'],
            'pagina'       => $resultado['pagina'],
            'porPagina'    => $resultado['porPagina'],
            'totalPaginas' => $resultado['totalPaginas'],
        ];

        $this->render('admin/gastos/index', [
            'tabActual'           => $tabActual,
            'gastos'              => $resultado['datos'],
            'categorias'          => $categorias,
            'edificios'           => $edificios,
            'totalesPorCategoria' => $totalesPorCategoria,
            'totalMes'            => $totalMes,
            'filtros'             => $filtros,
            'paginacion'          => $paginacion,
            'unidades'            => $unidades,
            'mes'                 => $mes,
            'anio'                => $anio,
            'facturas_existentes' => $facturas_existentes,
            'distribucion'        => $distribucion,
            'totalGastosMes'      => $totalMes,
            'layout'              => 'admin',
            'title'               => 'Gastos y Facturación - Administrador'
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
        $mes = intval($_POST['mes'] ?? date('n'));
        $anio = intval($_POST['anio'] ?? date('Y'));
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

        // Procesar subida de soporte digital con MIME validation real
        if (!empty($_FILES['soporte_digital']) && $_FILES['soporte_digital']['error'] === UPLOAD_ERR_OK) {
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

        try {
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
        Auth::requireRole('admin');

        $mes = intval($_POST['mes'] ?? date('n'));
        $anio = intval($_POST['anio'] ?? date('Y'));
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
            $uploader = new \App\Services\FileUploader(
                UPLOADS_PATH . '/soportes',
                ['application/pdf'],
                ['pdf'],
                10485760 // 10MB
            );
            $nombreArchivoSoporte = $uploader->upload($_FILES['pdf_maestro']);

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

        $mes = intval($_POST['mes'] ?? date('n'));
        $anio = intval($_POST['anio'] ?? date('Y'));
        $archivoMaestro = trim($_POST['archivo_maestro'] ?? '');
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
            $monto = floatval($g['monto_total'] ?? 0);
            $proveedor = trim($g['proveedor'] ?? '');
            $descripcion = trim($g['descripcion'] ?? '');

            if ($monto > 0 && !empty($proveedor) && !empty($descripcion)) {
                $itemsAImportar[] = [
                    'categoria_id'          => intval($g['categoria_id'] ?? 1),
                    'descripcion'           => $descripcion,
                    'monto_total'           => $monto,
                    'fecha_gasto'           => !empty($g['fecha_gasto']) ? trim($g['fecha_gasto']) : sprintf('%04d-%02d-01', $anio, $mes),
                    'proveedor'             => $proveedor,
                    'nro_factura_proveedor' => !empty($g['nro_factura_proveedor']) ? trim($g['nro_factura_proveedor']) : null,
                    'pagina_soporte'        => max(1, intval($g['pagina_soporte'] ?? 1)),
                    'extracto_texto'        => !empty($g['extracto_texto']) ? trim($g['extracto_texto']) : null
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
}
