<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\GastosModel;
use App\Models\CategoriasGastosModel;
use App\Models\UnidadesModel;

class GastoController extends Controller {

    /**
     * Muestra el panel administrativo de gastos comunes.
     */
    public function index() {
        Auth::requireRole('admin');

        $pagina = max(1, intval($_GET['page'] ?? 1));
        $mes = !empty($_GET['mes']) ? intval($_GET['mes']) : intval(date('n'));
        $anio = !empty($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));
        $categoriaId = !empty($_GET['categoria_id']) ? intval($_GET['categoria_id']) : null;

        $gastosModel = new GastosModel();
        $categoriasModel = new CategoriasGastosModel();

        $filtros = ['mes' => $mes, 'anio' => $anio, 'categoria_id' => $categoriaId];
        $resultado = $gastosModel->obtenerGastosAdmin($pagina, 15, $filtros);
        $categorias = $categoriasModel->getActivas();
        $totalesPorCategoria = $gastosModel->obtenerTotalesPorCategoria($mes, $anio);
        $totalMes = $gastosModel->obtenerTotalGastoMes($mes, $anio);

        $paginacion = [
            'total'        => $resultado['total'],
            'pagina'       => $resultado['pagina'],
            'porPagina'    => $resultado['porPagina'],
            'totalPaginas' => $resultado['totalPaginas'],
        ];

        $this->render('admin/gastos/index', [
            'gastos'              => $resultado['datos'],
            'categorias'          => $categorias,
            'totalesPorCategoria' => $totalesPorCategoria,
            'totalMes'            => $totalMes,
            'filtros'             => $filtros,
            'paginacion'          => $paginacion,
            'layout'              => 'admin',
            'title'               => 'Gestión de Gastos Comunes y Soportes'
        ]);
    }

    /**
     * Guarda un nuevo gasto común con soporte digital adjunto.
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
        $adminId = Auth::id() ?? 1;

        if ($categoriaId <= 0 || empty($descripcion) || $montoTotal <= 0 || empty($proveedor)) {
            Flash::set('danger', 'Todos los campos marcados con (*) son obligatorios y el monto debe ser superior a 0.');
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
            $gastosModel->crearGasto([
                'categoria_id'          => $categoriaId,
                'mes'                   => $mes,
                'anio'                  => $anio,
                'descripcion'           => $descripcion,
                'monto_total'           => $montoTotal,
                'fecha_gasto'           => $fechaGasto,
                'proveedor'             => $proveedor,
                'nro_factura_proveedor' => $nroFactura,
                'soporte_digital'       => $nombreArchivoSoporte,
                'admin_id'              => $adminId
            ]);

            Flash::set('success', 'Gasto común registrado exitosamente con su soporte digital.');
        } catch (\Exception $e) {
            error_log("[GASTO] Error registrar gasto: " . $e->getMessage());
            Flash::set('danger', 'Error al registrar el gasto común. Verifique los datos e intente de nuevo.');
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

        $mes = !empty($_GET['mes']) ? intval($_GET['mes']) : intval(date('n'));
        $anio = !empty($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));

        $gastosModel = new GastosModel();
        $unidadesModel = new UnidadesModel();

        $gastos = $gastosModel->obtenerGastosPorPeriodo($mes, $anio);
        $totalesPorCategoria = $gastosModel->obtenerTotalesPorCategoria($mes, $anio);
        $totalMes = $gastosModel->obtenerTotalGastoMes($mes, $anio);

        // 4.5: Optimized count — direct SQL instead of loading full array
        $db = \App\Core\Database::getConnection();
        $stmtCount = $db->query("SELECT COUNT(*) as cnt FROM unidades WHERE estado = 1");
        $unidadesActivas = intval($stmtCount->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0);
        $alicuotaEstimada = ($unidadesActivas > 0) ? round($totalMes / $unidadesActivas, 2) : 0.00;

        $this->render('residente/gastos', [
            'gastos'              => $gastos,
            'totalesPorCategoria' => $totalesPorCategoria,
            'totalMes'            => $totalMes,
            'mes'                 => $mes,
            'anio'                => $anio,
            'unidadesActivas'     => $unidadesActivas,
            'alicuotaEstimada'    => $alicuotaEstimada,
            'title'               => 'Rendición de Cuentas y Justificación de Gastos'
        ]);
    }
}
