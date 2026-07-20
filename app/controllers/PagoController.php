<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Security;
use App\Models\PagoModel;
use App\Models\PersonasModel;
use App\Models\EdificiosModel;

class PagoController extends Controller {
    
    /**
     * Muestra la vista con la tabla de pagos del residente o de todos (según rol).
     */
    public function listar() {
        Auth::requireLogin();
        
        $rol = $_SESSION['rol'] ?? Auth::role();
        $pagoModel = new PagoModel();
        
        $mensaje = $_SESSION['pago_mensaje'] ?? '';
        $error = $_SESSION['pago_error'] ?? '';
        unset($_SESSION['pago_mensaje'], $_SESSION['pago_error']);
        
        if ($rol === 'residente') {
            $residenteId = Auth::id();
            $personasModel = new PersonasModel();
            $residente = $personasModel->getResidenteDetails($residenteId);
            
            if (!$residente) {
                Auth::logout();
                $this->redirect('/auth/login');
            }
            
            $pagos = $pagoModel->obtenerPagosPorResidente($residenteId);
            
            $this->render('pagos/residente/lista', [
                'residente' => $residente,
                'pagos'     => $pagos,
                'mensaje'   => $mensaje,
                'error'     => $error,
                'showNav'   => true,
                'title'     => 'Mis Pagos - Portal Residente'
            ]);
        } else if ($rol === 'admin') {
            $filtros = [
                'estado'   => $_GET['estado'] ?? '',
                'edificio' => $_GET['edificio'] ?? '',
                'fecha'    => $_GET['fecha'] ?? ''
            ];
            
            $pagos = $pagoModel->obtenerTodosPagos($filtros);
            
            $edificiosModel = new EdificiosModel();
            $edificios = $edificiosModel->getActivos();
            
            $this->render('pagos/admin/lista', [
                'pagos'     => $pagos,
                'edificios' => $edificios,
                'filtros'   => $filtros,
                'mensaje'   => $mensaje,
                'error'     => $error,
                'showNav'   => false,
                'title'     => 'Administración de Pagos'
            ]);
        } else {
            http_response_code(403);
            echo "Acceso prohibido: Rol no válido.";
            exit;
        }
    }

    /**
     * Muestra el formulario para registrar un nuevo pago (Solo residente)
     */
    public function nuevo() {
        Auth::requireRole('residente');
        
        $residenteId = Auth::id();
        $personasModel = new PersonasModel();
        $residente = $personasModel->getResidenteDetails($residenteId);
        
        if (!$residente) {
            Auth::logout();
            $this->redirect('/auth/login');
        }
        
        $mensaje = $_SESSION['pago_mensaje'] ?? '';
        $error = $_SESSION['pago_error'] ?? '';
        unset($_SESSION['pago_mensaje'], $_SESSION['pago_error']);
        
        $this->render('pagos/residente/subir', [
            'residente' => $residente,
            'mensaje'   => $mensaje,
            'error'     => $error,
            'showNav'   => true,
            'title'     => 'Registrar Pago'
        ]);
    }

    /**
     * Recibe POST con archivo y datos del formulario. Valida CSRF y guarda el pago.
     */
    public function subir() {
        Auth::requireRole('residente');
        Security::validateCSRF();
        
        $residenteId = Auth::id();
        $personasModel = new PersonasModel();
        $residente = $personasModel->getResidenteDetails($residenteId);
        
        if (!$residente || empty($residente['unidad_id'])) {
            $_SESSION['pago_error'] = "No se pudo determinar la unidad asociada a su cuenta de residente.";
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
            $_SESSION['pago_error'] = "El monto del pago debe ser mayor a cero.";
            $this->redirect('/pagos/nuevo');
        }
        if (empty($fecha_pago)) {
            $_SESSION['pago_error'] = "La fecha de realización del pago es requerida.";
            $this->redirect('/pagos/nuevo');
        }
        
        if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['pago_error'] = "El archivo del comprobante es obligatorio y debe ser válido.";
            $this->redirect('/pagos/nuevo');
        }
        
        $file = $_FILES['comprobante'];
        
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowedMimes)) {
            $_SESSION['pago_error'] = "Formato de archivo no permitido. Solo se aceptan imágenes (JPEG, PNG) o archivos PDF.";
            $this->redirect('/pagos/nuevo');
        }
        
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $_SESSION['pago_error'] = "El comprobante excede el tamaño máximo permitido de 5 MB.";
            $this->redirect('/pagos/nuevo');
        }
        
        $uniqueName = uniqid() . '_' . basename($file['name']);
        
        $pagoModel = new PagoModel();
        $datos = [
            'monto'          => $monto,
            'fecha_pago'     => $fecha_pago,
            'referencia'     => $referencia,
            'observaciones'  => $observaciones,
            'banco_pagador'  => $banco_pagador,
            'banco_receptor' => $banco_receptor
        ];
        
        $result = $pagoModel->crearPago($residenteId, $unidadId, $datos, [
            'tmp_name' => $file['tmp_name'],
            'name'     => $uniqueName
        ]);
        
        if ($result) {
            $_SESSION['pago_mensaje'] = "Comprobante de pago subido correctamente. Está pendiente de verificación.";
            $this->redirect('/pagos');
        } else {
            $_SESSION['pago_error'] = "No se pudo registrar la información de pago en la base de datos.";
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
            $_SESSION['pago_error'] = 'Pago no encontrado.';
            $this->redirect('/pagos');
        }
        
        // Seguridad: Los residentes solo pueden ver sus propios detalles de pago
        $rol = $_SESSION['rol'] ?? Auth::role();
        if ($rol === 'residente' && intval($pago['residente_id']) !== intval(Auth::id())) {
            $_SESSION['pago_error'] = 'Acceso denegado a este pago.';
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
        sleep(1);
        
        $bancos = ['Banco de Venezuela', 'Banesco', 'Mercantil', 'Provincial', 'BNC', 'Bancaribe'];
        
        header('Content-Type: application/json');
        echo json_encode([
            'success'        => true,
            'banco_pagador'  => $bancos[array_rand($bancos)],
            'banco_receptor' => $bancos[array_rand($bancos)],
            'referencia'     => strval(rand(10000000, 99999999)),
            'monto'          => number_format(rand(30, 250) + (rand(0, 99) / 100), 2, '.', ''),
            'fecha_pago'     => date('Y-m-d')
        ]);
        exit;
    }

    /**
     * Cambia el estado del pago (Admin). Añadido soporte para "EN REVISIÓN".
     */
    public function cambiarEstado() {
        Auth::requireRole('admin');
        Security::validateCSRF();
        
        $pagoId = intval($_POST['pago_id'] ?? 0);
        $nuevoEstado = trim($_POST['nuevo_estado'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');
        $adminId = Auth::id();
        
        if ($pagoId <= 0 || empty($nuevoEstado)) {
            $_SESSION['pago_error'] = "Identificador o estado de pago inválido.";
            $this->redirect('/pagos');
        }
        
        // Se añade 'EN REVISIÓN' a los estados permitidos
        $estadosPermitidos = ['PENDIENTE', 'EN REVISIÓN', 'APROBADO', 'RECHAZADO'];
        if (!in_array($nuevoEstado, $estadosPermitidos)) {
            $_SESSION['pago_error'] = "El estado seleccionado no es permitido.";
            $this->redirect('/pagos');
        }
        
        $pagoModel = new PagoModel();
        $exito = $pagoModel->cambiarEstado($pagoId, $nuevoEstado, $motivo, $adminId);
        
        if ($exito) {
            $_SESSION['pago_mensaje'] = "El pago fue actualizado a estado {$nuevoEstado} exitosamente.";
        } else {
            $_SESSION['pago_error'] = "Hubo un error de base de datos al registrar el cambio de estado.";
        }
        
        $this->redirect('/pagos');
    }
}
