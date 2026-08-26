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

        $nombreArchivoSoporte = null;

        // Procesar subida de soporte digital (Factura / Recibo)
        if (!empty($_FILES['soporte_digital']) && $_FILES['soporte_digital']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['soporte_digital'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $extensionesPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];

            if (!in_array($ext, $extensionesPermitidas)) {
                Flash::set('danger', 'Formato de soporte no permitido. Solo se aceptan archivos PDF, JPG o PNG.');
                $this->redirect('/admin/gastos');
                return;
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                Flash::set('danger', 'El soporte digital excede el límite máximo de 5 MB.');
                $this->redirect('/admin/gastos');
                return;
            }

            $directorioDestino = UPLOADS_PATH . '/soportes';
            if (!is_dir($directorioDestino)) {
                mkdir($directorioDestino, 0755, true);
            }

            $nombreArchivoSoporte = 'soporte_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $directorioDestino . '/' . $nombreArchivoSoporte)) {
                Flash::set('danger', 'No se pudo guardar el archivo de soporte en el servidor.');
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
        if ($id > 0) {
            $gastosModel = new GastosModel();
            $gastosModel->eliminarGasto($id);
            Flash::set('success', 'Gasto común eliminado y soporte físico liberado del servidor.');
        }

        $this->redirect('/admin/gastos');
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

        // Cálculo de alícuota equitativa por unidad activa
        $unidadesActivas = count($unidadesModel->getActivas());
        $alicuotaEstimada = ($unidadesActivas > 0) ? ($totalMes / $unidadesActivas) : 0.00;

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
