<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\UserRole;
use App\Models\ComprobantesModel;
use App\Models\FacturasModel;
use App\Models\UnidadesModel;

class AdminController extends Controller {

    public function dashboard() {
        Auth::requireRole(UserRole::ADMIN);

        $comprobantesModel = new ComprobantesModel();

        $pendientes = $comprobantesModel->getPendientesVerificar(10);
        $procesados = $comprobantesModel->getProcesados(5);

        $this->render('admin/dashboard', [
            'pendientes' => $pendientes,
            'procesados' => $procesados,
            'showNav'    => false,
            'title'      => 'Panel de Control - Administrador'
        ]);
    }

    public function listarComprobantes() {
        Auth::requireRole(UserRole::ADMIN);

        $estado = $_GET['estado'] ?? '';
        $buscar = trim($_GET['buscar'] ?? '');

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
            'showNav'      => false,
            'title'        => 'Listado de Comprobantes - Administrador'
        ]);
    }

    public function verificarComprobante() {
        Auth::requireRole(UserRole::ADMIN);

        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/admin/comprobantes');
        }

        $comprobantesModel = new ComprobantesModel();
        $comprobante = $comprobantesModel->getById($id);

        if (!$comprobante) {
            Flash::error("El comprobante solicitado no existe.");
            $this->redirect('/admin/comprobantes');
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';
            $observaciones = trim($_POST['observaciones'] ?? '');

            if ($accion === 'aprobar') {
                if ($comprobantesModel->aprobar($id, $observaciones)) {
                    Flash::success("Comprobante aprobado exitosamente.");
                    $this->redirect('/admin/comprobantes');
                } else {
                    $error = "Error al intentar aprobar el comprobante.";
                }
            } elseif ($accion === 'rechazar') {
                if ($comprobantesModel->rechazar($id, $observaciones)) {
                    Flash::success("Comprobante rechazado exitosamente.");
                    $this->redirect('/admin/comprobantes');
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

    public function generarFacturas() {
        Auth::requireRole(UserRole::ADMIN);

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
                Flash::error($error);
            } else {
                $stats = $facturasModel->crearFacturasMasivas($unidades, $mes, $anio);
                if ($stats !== false) {
                    $msgText = "Se generaron {$stats['generadas']} facturas para el mes " . nombreMes($mes) . " de {$anio}.";
                    if ($stats['con_saldo_favor'] > 0) {
                        $msgText .= " Se aplicó saldo a favor en {$stats['con_saldo_favor']} unidades (Total usado: " . formatearMoneda($stats['total_saldo_favor_usado']) . ").";
                    }
                    Flash::success($msgText);
                    $mensaje = $msgText;
                    $facturas_existentes = $facturasModel->countByPeriod($mes, $anio);
                } else {
                    $error = "Ocurrió un error inesperado al generar las facturas masivas.";
                    Flash::error($error);
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
