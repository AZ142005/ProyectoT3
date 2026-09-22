<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\UserRole;
use App\Models\PagoModel;
use App\Models\EdificiosModel;
use App\Services\ComprobanteParserService;

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
            $pagina = max(1, intval($_GET['page'] ?? 1));
            $resultado = $pagoModel->obtenerPagosPorResidente($residenteId, $pagina, 20);
            
            $this->render('pagos/residente/lista', [
                'residente'  => $residente,
                'pagos'      => $resultado['datos'],
                'paginacion' => [
                    'total'        => $resultado['total'],
                    'pagina'       => $resultado['pagina'],
                    'porPagina'    => $resultado['porPagina'],
                    'totalPaginas' => $resultado['totalPaginas'],
                ],
                'showNav'    => true,
                'title'      => 'Mis Pagos - Portal Residente'
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
        $cuentasModel = new \App\Models\CuentasBancariasModel();
        $cuentasBancarias = $cuentasModel->getActivas();
        
        $this->render('pagos/residente/subir', [
            'residente'        => $residente,
            'cuentasBancarias' => $cuentasBancarias,
            'showNav'          => true,
            'title'            => 'Registrar Pago'
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
        
        // Validación de datos básicos con cuentas autorizadas
        $cuenta_bancaria_id = intval($_POST['cuenta_bancaria_id'] ?? 0);
        $banco_pagador = trim($_POST['banco_pagador'] ?? '');
        $monto = floatval($_POST['monto'] ?? 0);
        $fecha_pago = $_POST['fecha_pago'] ?? '';
        $referencia = trim($_POST['referencia'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        // Validar cuenta receptora autorizada y activa
        $cuentasModel = new \App\Models\CuentasBancariasModel();
        $cuentaReceptora = ($cuenta_bancaria_id > 0) ? $cuentasModel->getActivaById($cuenta_bancaria_id) : null;
        if (!$cuentaReceptora) {
            Flash::error("Debe seleccionar una cuenta bancaria receptora autorizada y activa.");
            $this->redirect('/pagos/nuevo');
            return;
        }
        $banco_receptor = $cuentaReceptora['banco'];
        
        if ($monto <= 0) {
            Flash::error("El monto del pago debe ser mayor a cero.");
            $this->redirect('/pagos/nuevo');
        }
        if (empty($fecha_pago)) {
            Flash::error("La fecha de realización del pago es requerida.");
            $this->redirect('/pagos/nuevo');
        }
        if (!DateTime::createFromFormat('Y-m-d', $fecha_pago) || date('Y-m-d', strtotime($fecha_pago)) !== $fecha_pago) {
            Flash::error("El formato de fecha no es válido. Use AAAA-MM-DD.");
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

        // Validar que el monto no exceda la deuda pendiente de la unidad
        $totalDeuda = $pagoModel->obtenerTotalDeuda($unidadId);
        if ($monto > $totalDeuda) {
            Flash::error("El monto del pago ({$monto}) excede la deuda pendiente de la unidad ({$totalDeuda}).");
            $this->redirect('/pagos/nuevo');
        }

        $datos = [
            'monto'              => $monto,
            'fecha_pago'         => $fecha_pago,
            'referencia'         => $referencia,
            'observaciones'      => $observaciones,
            'banco_pagador'      => $banco_pagador,
            'banco_receptor'     => $banco_receptor,
            'cuenta_bancaria_id' => $cuenta_bancaria_id
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
     * Endpoint de extracción y análisis de datos de comprobante (POST).
     * Soporta texto pre-extraído vía OCR en cliente o extracción nativa desde PDF.
     */
    public function extraer() {
        Auth::requireLogin();
        // G2-02: Rate limit OCR endpoint — max 20 requests/min/user
        if (!\App\Core\RateLimiter::attempt('ocr_' . Auth::id(), 20, 60)) {
            $this->json(['success' => false, 'error' => 'Demasiadas solicitudes de análisis.'], 429);
            return;
        }

        $parser = new ComprobanteParserService();
        $resultado = [
            'banco'      => null,
            'referencia' => null,
            'monto'      => null,
            'fecha'      => null,
            'detectado'  => false
        ];

        // 1. Caso A: Texto extraído vía OCR en cliente (Tesseract.js para imágenes)
        $textoExtraido = trim($_POST['texto_extraido'] ?? '');
        if (!empty($textoExtraido)) {
            if (mb_strlen($textoExtraido) > 50000) {
                $textoExtraido = mb_substr($textoExtraido, 0, 50000);
            }
            $resultado = $parser->analizarTexto($textoExtraido);
        }
        // 2. Caso B: Archivo PDF subido directamente para extracción nativa en backend
        elseif (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['comprobante']['tmp_name'];
            $nombreOriginal = $_FILES['comprobante']['name'] ?? '';
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

            if ($extension === 'pdf') {
                $resultado = $parser->procesarArchivo($tmpPath, 'pdf');
            } else {
                $this->json([
                    'success'   => false,
                    'detectado' => false,
                    'error'     => 'Para imágenes, la extracción se procesa mediante el motor de reconocimiento en el navegador.'
                ], 400);
                return;
            }
        } else {
            $this->json([
                'success' => false,
                'error'   => 'No se proporcionó texto de OCR ni archivo válido para analizar.'
            ], 400);
            return;
        }

        $nombresBancos = [
            'mercantil'  => 'Banco Mercantil',
            'banesco'    => 'Banesco',
            'venezuela'  => 'Banco de Venezuela',
            'provincial' => 'BBVA Provincial',
            'bancamiga'  => 'Bancamiga'
        ];

        $bancoPagador = $resultado['banco'] ? ($nombresBancos[$resultado['banco']] ?? ucfirst($resultado['banco'])) : '';
        $bancoReceptor = $bancoPagador ? 'Banco Mercantil' : '';

        $this->json([
            'success'        => true,
            'detectado'      => (bool)$resultado['detectado'],
            'banco_pagador'  => $bancoPagador,
            'banco_receptor' => $bancoReceptor,
            'referencia'     => $resultado['referencia'] ?? '',
            'monto'          => $resultado['monto'] !== null ? number_format($resultado['monto'], 2, '.', '') : '',
            'fecha_pago'     => $resultado['fecha'] ?? date('Y-m-d'),
            'mensaje'        => $resultado['detectado']
                ? 'Datos del comprobante detectados exitosamente.'
                : 'No se pudieron detectar todos los datos con certeza. Por favor verifique los campos.'
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
        
        // Máquina de estados: solo transiciones válidas permitidas
        $transicionesValidas = [
            'PENDIENTE'   => ['EN REVISIÓN', 'RECHAZADO'],
            'EN REVISIÓN' => ['APROBADO', 'RECHAZADO'],
            'RECHAZADO'   => [],  // Terminal
            'APROBADO'    => [],  // Terminal
        ];

        $pagoModel = new PagoModel();
        $pagoActual = $pagoModel->obtenerPagoPorId($pagoId);
        if (!$pagoActual) {
            Flash::error("Pago no encontrado.");
            $this->redirect('/pagos');
        }

        $estadoActual = strtoupper(trim($pagoActual['estado']));

        if (!array_key_exists($estadoActual, $transicionesValidas)) {
            Flash::error("Estado actual del pago desconocido: '{$estadoActual}'. Contacte al administrador.");
            $this->redirect('/pagos/detalle/' . $pagoId);
        }

        if (empty($nuevoEstado) || !in_array($nuevoEstado, $transicionesValidas[$estadoActual], true)) {
            Flash::error("No se puede cambiar de '{$estadoActual}' a '{$nuevoEstado}'. Transición no válida.");
            $this->redirect('/pagos/detalle/' . $pagoId);
        }

        // Exigir motivo obligatorio si el nuevo estado es RECHAZADO
        if ($nuevoEstado === \App\Core\EstadoPago::RECHAZADO && (empty($motivo) || mb_strlen($motivo) < 5)) {
            Flash::error("Debe proporcionar un motivo de rechazo claro y detallado (mínimo 5 caracteres).");
            $this->redirect('/pagos/detalle/' . $pagoId);
        }
        $exito = $pagoModel->cambiarEstado($pagoId, $nuevoEstado, $motivo, $adminId, $_SERVER['REMOTE_ADDR'] ?? null);
        
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
            $resultado = $pagoModel->aprobarLote($pagoIds, $adminId, $_SERVER['REMOTE_ADDR'] ?? null);

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
