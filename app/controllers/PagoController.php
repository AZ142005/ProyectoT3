<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\UserRole;
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

        // Exigir motivo obligatorio si el nuevo estado es RECHAZADO
        if ($nuevoEstado === \App\Core\EstadoPago::RECHAZADO && (empty($motivo) || mb_strlen($motivo) < 5)) {
            Flash::error("Debe proporcionar un motivo de rechazo claro y detallado (mínimo 5 caracteres).");
            $this->redirect('/pagos/detalle/' . $pagoId);
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

    /**
     * Procesa la aprobación masiva en lote de hasta 50 pagos (Admin).
     */
    public function aprobarMasivo() {
        Auth::requireRole('admin');

        $pagoIds = $_POST['pago_ids'] ?? [];
        if (!is_array($pagoIds) || empty($pagoIds)) {
            Flash::error("No se seleccionó ningún pago para aprobar.");
            $this->redirect('/pagos');
        }

        $adminId = Auth::id();
        $pagoModel = new PagoModel();

        try {
            $resultado = $pagoModel->aprobarLote($pagoIds, $adminId);

            if ($resultado['procesados'] > 0) {
                $mensaje = "Se aprobaron {$resultado['procesados']} pago(s) exitosamente.";
                if ($resultado['omitidos'] > 0) {
                    $mensaje .= " ({$resultado['omitidos']} pago(s) fueron omitidos por estar previamente procesados).";
                }
                Flash::success($mensaje);
            } else {
                Flash::error("Ninguno de los pagos seleccionados pudo ser aprobado (ya procesados o no válidos).");
            }
        } catch (\Exception $e) {
            error_log("[PAGO] Error aprobacion masiva: " . $e->getMessage());
            Flash::error('Error durante la aprobación masiva de pagos.');
        }

        $this->redirect('/pagos');
    }

    /**
     * Endpoint AJAX para análisis asistido de comprobantes (RF 15).
     */
    public function analizarComprobante() {
        Auth::requireRole(UserRole::RESIDENTE);

        $textoPegado = trim($_POST['texto_comprobante'] ?? '');

        // Si se envió texto directo
        if (!empty($textoPegado)) {
            $service = new \App\Services\ComprobanteParserService();
            $datos = $service->analizarTexto($textoPegado);
            $this->json(['success' => true, 'datos' => $datos]);
            return;
        }

        // Si se cargó un archivo PDF/Imagen temporal
        if (!empty($_FILES['comprobante_archivo']) && $_FILES['comprobante_archivo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['comprobante_archivo'];

            // Límite de tamaño: 5MB
            if ($file['size'] > 5 * 1024 * 1024) {
                $this->json(['success' => false, 'error' => 'El archivo excede el tamaño máximo de 5MB.'], 400);
                return;
            }

            // Verificación MIME real con fallback si extensión fileinfo no disponible
            $mimeType = 'application/octet-stream';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $extToMime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','pdf'=>'application/pdf'];
                $mimeType = $extToMime[$ext] ?? 'application/octet-stream';
            }
            $mimePermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
            if (!in_array($mimeType, $mimePermitidos, true)) {
                $this->json(['success' => false, 'error' => 'Tipo de archivo no permitido. Solo PDF o imágenes.'], 400);
                return;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            $service = new \App\Services\ComprobanteParserService();
            $datos = $service->procesarArchivo($file['tmp_name'], $ext);
            $this->json(['success' => true, 'datos' => $datos]);
            return;
        }

        $this->json([
            'success' => false,
            'error'   => 'No se proporcionó texto ni archivo de comprobante válido.'
        ], 400);
    }
}
