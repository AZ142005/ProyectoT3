<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\PagoModel;
use App\Models\EdificiosModel;

class PagoController extends Controller {
    
    /**
     * Muestra la vista con la tabla de pagos del residente o de todos (según rol).
     */
    public function listar() {
        Auth::requireLogin();
        
        $rol = Auth::role();
        $pagoModel = new PagoModel();
        
        if ($rol === 'residente') {
            $residente = $this->getAuthenticatedResidente();
            $residenteId = Auth::id();
            
            $pagos = $pagoModel->obtenerPagosPorResidente($residenteId);
            
            $this->render('pagos/residente/lista', [
                'residente' => $residente,
                'pagos'     => $pagos,
                'showNav'   => true,
                'title'     => 'Mis Pagos - Portal Residente'
            ]);
        } else if ($rol === 'admin') {
            $filtros = [
                'estado'   => $_GET['estado'] ?? '',
                'edificio' => $_GET['edificio'] ?? '',
                'fecha'    => $_GET['fecha'] ?? ''
            ];
            
            $pagina = max(1, intval($_GET['page'] ?? 1));
            $resultado = $pagoModel->obtenerTodosPagos($filtros, $pagina, 20);
            $pagos = $resultado['datos'];
            $paginacion = [
                'total'       => $resultado['total'],
                'pagina'      => $resultado['pagina'],
                'porPagina'   => $resultado['porPagina'],
                'totalPaginas' => $resultado['totalPaginas'],
            ];
            
            $edificiosModel = new EdificiosModel();
            $edificios = $edificiosModel->getActivos();
            
            $this->render('pagos/admin/lista', [
                'pagos'      => $pagos,
                'edificios'  => $edificios,
                'filtros'    => $filtros,
                'paginacion' => $paginacion,
                'showNav'    => false,
                'title'      => 'Administración de Pagos'
            ]);
        } else {
            $this->render('errors/403', [
                'showNav' => false,
                'title'   => 'Acceso Denegado'
            ]);
            exit;
        }
    }

    /**
     * Muestra el formulario para registrar un nuevo pago (Solo residente)
     */
    public function nuevo() {
        $residente = $this->getAuthenticatedResidente();
        
        $this->render('pagos/residente/subir', [
            'residente' => $residente,
            'showNav'   => true,
            'title'     => 'Registrar Pago'
        ]);
    }

    /**
     * Recibe POST con archivo y datos del formulario. CSRF ya validado globalmente en index.php.
     */
    public function subir() {
        $residente = $this->getAuthenticatedResidente();
        $residenteId = Auth::id();
        
        if (empty($residente['unidad_id'])) {
            Flash::error("No se pudo determinar la unidad asociada a su cuenta de residente.");
            $this->redirect('/pagos/nuevo');
        }
        
        $unidadId = $residente['unidad_id'];
        
        // Validación de datos básicos, ahora con bancos
        $banco_pagador = trim($_POST['banco_pagador'] ?? '');
        $banco_receptor = trim($_POST['banco_receptor'] ?? '');
        $monto = floatval($_POST['monto'] ?? 0);
        $fecha_pago = $_POST['fecha_pago'] ?? '';
        $referencia = trim($_POST['referencia'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');
        
        if ($monto <= 0) {
            Flash::error("El monto del pago debe ser mayor a cero.");
            $this->redirect('/pagos/nuevo');
        }
        if (empty($fecha_pago)) {
            Flash::error("La fecha de realización del pago es requerida.");
            $this->redirect('/pagos/nuevo');
        }
        
        if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
            Flash::error("El archivo del comprobante es obligatorio y debe ser válido.");
            $this->redirect('/pagos/nuevo');
        }
        
        $uploader = new \App\Services\FileUploader();
        $uniqueName = $uploader->upload($_FILES['comprobante']);
        
        if (!$uniqueName) {
            Flash::error("Formato o tamaño de archivo no permitido. Solo se aceptan imágenes (JPEG, PNG) o PDF hasta 5MB.");
            $this->redirect('/pagos/nuevo');
        }
        
        $pagoModel = new PagoModel();
        $datos = [
            'monto'          => $monto,
            'fecha_pago'     => $fecha_pago,
            'referencia'     => $referencia,
            'observaciones'  => $observaciones,
            'banco_pagador'  => $banco_pagador,
            'banco_receptor' => $banco_receptor
        ];
        
        $result = $pagoModel->crearPago($residenteId, $unidadId, $datos, $uniqueName);
        
        if ($result) {
            Flash::success("Comprobante de pago subido correctamente. Está pendiente de verificación.");
            $this->redirect('/pagos');
        } else {
            Flash::error("No se pudo registrar la información de pago en la base de datos.");
            $this->redirect('/pagos/nuevo');
        }
    }

    /**
     * Endpoint dinámico para renderizar la vista de detalles y auditoría.
     */
    public function detalle($id) {
        Auth::requireLogin();
        
        $pagoModel = new PagoModel();
        $pago = $pagoModel->obtenerPagoPorId(intval($id));
        
        if (!$pago) {
            Flash::error('Pago no encontrado.');
            $this->redirect('/pagos');
        }
        
        // Seguridad: Los residentes solo pueden ver sus propios detalles de pago
        $rol = Auth::role();
        if ($rol === 'residente' && intval($pago['residente_id']) !== intval(Auth::id())) {
            Flash::error('Acceso denegado a este pago.');
            $this->redirect('/pagos');
        }
        
        $this->render('pagos/detalle', [
            'pago'    => $pago,
            'rol'     => $rol,
            'showNav' => ($rol === 'residente'), // Mostrar nav de residente si aplica
            'title'   => 'Detalle de Pago'
        ]);
    }

    /**
     * Endpoint de extracción simulada OCR de datos de comprobante (POST).
     */
    public function extraer() {
        Auth::requireLogin();
        $bancos = ['Banco de Venezuela', 'Banesco', 'Mercantil', 'Provincial', 'BNC', 'Bancaribe'];
        
        $this->json([
            'success'        => true,
            'banco_pagador'  => $bancos[array_rand($bancos)],
            'banco_receptor' => $bancos[array_rand($bancos)],
            'referencia'     => strval(rand(10000000, 99999999)),
            'monto'          => number_format(rand(30, 250) + (rand(0, 99) / 100), 2, '.', ''),
            'fecha_pago'     => date('Y-m-d')
        ]);
    }

    /**
     * Cambia el estado del pago (Admin). CSRF ya validado globalmente en index.php.
     */
    public function cambiarEstado() {
        Auth::requireRole('admin');
        
        $pagoId = intval($_POST['pago_id'] ?? 0);
        $nuevoEstado = trim($_POST['nuevo_estado'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');
        $adminId = Auth::id();
        
        if ($pagoId <= 0 || empty($nuevoEstado)) {
            Flash::error("Identificador o estado de pago inválido.");
            $this->redirect('/pagos');
        }
        
        // Se añade 'EN REVISIÓN' a los estados permitidos
        $estadosPermitidos = ['PENDIENTE', 'EN REVISIÓN', 'APROBADO', 'RECHAZADO'];
        if (!in_array($nuevoEstado, $estadosPermitidos)) {
            Flash::error("El estado seleccionado no es permitido.");
            $this->redirect('/pagos');
        }
        
        $pagoModel = new PagoModel();
        $exito = $pagoModel->cambiarEstado($pagoId, $nuevoEstado, $motivo, $adminId);
        
        if ($exito) {
            Flash::success("El pago fue actualizado a estado {$nuevoEstado} exitosamente.");
        } else {
            Flash::error("Hubo un error de base de datos al registrar el cambio de estado.");
        }
        
        $this->redirect('/pagos');
    }
}
