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
        Auth::requireRole(['admin', 'auditor']);

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
        Auth::requireRole(['admin', 'auditor']);

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
