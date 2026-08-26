<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\ComprobantesModel;
use App\Models\FacturasModel;
use App\Models\UnidadesModel;

class AdminController extends Controller {
    /**
     * Muestra la consola principal del administrador.
     */
    public function dashboard() {
        Auth::requireRole('admin');

        $comprobantesModel = new ComprobantesModel();

        $comprobantes_pendientes = $comprobantesModel->getPendientesVerificar(10);
        $ultimos_comprobantes = $comprobantesModel->getProcesados(5);

        $this->render('admin/dashboard', [
            'comprobantes_pendientes' => $comprobantes_pendientes,
            'ultimos_comprobantes'    => $ultimos_comprobantes,
            'showNav'                 => false,
            'title'                   => 'Panel de Control - Administrador'
        ]);
    }

    /**
     * Lista todos los comprobantes recibidos con filtros.
     */
    public function listarComprobantes() {
        Auth::requireRole('admin');

        $estado = $_GET['estado'] ?? '';
        $buscar = trim($_GET['buscar'] ?? '');
        $mensaje = $_GET['mensaje'] ?? '';

        $pagina = max(1, intval($_GET['page'] ?? 1));
        $comprobantesModel = new ComprobantesModel();
        $resultado = $comprobantesModel->getAllFiltered($estado, $buscar, $pagina, 20);
        $comprobantes = $resultado['datos'];
        $paginacion = [
            'total'       => $resultado['total'],
            'pagina'      => $resultado['pagina'],
            'porPagina'   => $resultado['porPagina'],
            'totalPaginas' => $resultado['totalPaginas'],
        ];

        $this->render('admin/comprobantes', [
            'comprobantes' => $comprobantes,
            'paginacion'   => $paginacion,
            'estado'       => $estado,
            'buscar'       => $buscar,
            'mensaje'      => $mensaje,
            'showNav'      => false,
            'title'        => 'Listado de Comprobantes - Administrador'
        ]);
    }

    /**
     * Muestra la verificación detallada y procesa la aprobación/rechazo de un comprobante.
     */
    public function verificarComprobante() {
        Auth::requireRole('admin');

        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/admin/comprobantes');
        }

        $comprobantesModel = new ComprobantesModel();
        $comprobante = $comprobantesModel->getById($id);

        if (!$comprobante) {
            $this->redirect('/admin/comprobantes');
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF ya validado por index.php
            $accion = $_POST['accion'] ?? '';
            $observaciones = trim($_POST['observaciones'] ?? '');

            if ($accion === 'aprobar') {
                if ($comprobantesModel->aprobar($id, $observaciones)) {
                    $this->redirect('/admin/comprobantes?mensaje=' . urlencode("Comprobante aprobado exitosamente."));
                } else {
                    $error = "Error al intentar aprobar el comprobante.";
                }
            } elseif ($accion === 'rechazar') {
                if ($comprobantesModel->rechazar($id, $observaciones)) {
                    $this->redirect('/admin/comprobantes?mensaje=' . urlencode("Comprobante rechazado exitosamente."));
                } else {
                    $error = "Error al intentar rechazar el comprobante.";
                }
            } else {
                $error = "Acción de verificación no válida.";
            }
        }

        $saldo_restante = $comprobante['saldo'] - $comprobante['monto'];

        $this->render('admin/verificar_comprobante', [
            'comprobante'    => $comprobante,
            'saldo_restante' => $saldo_restante,
            'error'          => $error,
            'showNav'        => false,
            'title'          => 'Verificar Comprobante - Administrador'
        ]);
    }

    /**
     * Generación masiva de facturas.
     */
    public function generarFacturas() {
        Auth::requireRole('admin');

        $unidadesModel = new UnidadesModel();
        $facturasModel = new FacturasModel();

        $unidades = $unidadesModel->getActivas();
        $mes = date('n');
        $anio = date('Y');

        $facturas_existentes = $facturasModel->countByPeriod($mes, $anio);

        $mensaje = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar'])) {
            if ($facturas_existentes > 0) {
                $error = "Ya existen facturas generadas para el mes " . nombreMes($mes) . " de " . $anio;
            } else {
                $stats = $facturasModel->crearFacturasMasivas($unidades, $mes, $anio);
                if ($stats !== false) {
                    $mensaje = "Se generaron {$stats['generadas']} facturas para el mes " . nombreMes($mes) . " de {$anio}.";
                    if ($stats['con_saldo_favor'] > 0) {
                        $mensaje .= " Se aplicó saldo a favor en {$stats['con_saldo_favor']} unidades (Total usado: " . formatearMoneda($stats['total_saldo_favor_usado']) . ").";
                    }
                    // Actualizar contador
                    $facturas_existentes = $facturasModel->countByPeriod($mes, $anio);
                } else {
                    $error = "Ocurrió un error inesperado al generar las facturas masivas.";
                }
            }
        }

        $this->render('admin/generar_facturas', [
            'unidades'            => $unidades,
            'mes'                 => $mes,
            'anio'                => $anio,
            'facturas_existentes' => $facturas_existentes,
            'mensaje'             => $mensaje,
            'error'               => $error,
            'showNav'             => false,
            'title'               => 'Generar Facturas - Administrador'
        ]);
    }
}
