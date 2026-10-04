<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\EstadoPago;
use App\Core\Flash;
use App\Core\UserRole;
use App\Models\PagoModel;
use App\Models\EdificiosModel;
use App\Services\ComprobanteParserService;
use DateTime;

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
            $unidadId = intval($residente['unidad_id'] ?? 0);
            $pagina = max(1, intval($_GET['page'] ?? 1));

            // Lista unificada de la unidad: pagos (portal público / administración)
            // + comprobantes del formulario residente. Cualquier miembro activo de
            // la unidad ve todos los movimientos.
            $resultado = $unidadId > 0
                ? $pagoModel->obtenerTodosPagos(['unidad_id' => $unidadId], $pagina, 25)
                : ['datos' => [], 'total' => 0, 'pagina' => 1, 'porPagina' => 25, 'totalPaginas' => 1];

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
        } else if (in_array($rol, ['admin', 'auditor'], true)) {
            $filtros = [
                'estado'   => $_GET['estado'] ?? '',
                'edificio' => $_GET['edificio'] ?? '',
                'fecha'    => $_GET['fecha'] ?? ''
            ];
            
            $pagina = max(1, intval($_GET['page'] ?? 1));
            $resultado = $pagoModel->obtenerTodosPagos($filtros, $pagina, 25);
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
        
        $unidadId = intval($residente['unidad_id'] ?? 0);
        $totalDeuda = 0.00;
        $saldoFavor = 0.00;
        if ($unidadId > 0) {
            $facturasModel = new \App\Models\FacturasModel();
            $resumen = $facturasModel->getResumenFinancieroUnidad($unidadId);
            $totalDeuda = $resumen['total_deuda'];
            $saldoFavor = $resumen['saldo_favor'];
        }

        $this->render('pagos/residente/subir', [
            'residente'        => $residente,
            'cuentasBancarias' => $cuentasBancarias,
            'totalDeuda'       => $totalDeuda,
            'saldoFavor'       => $saldoFavor,
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
            return;
        }

        // Rate limiting: máximo 10 subidas por hora por residente
        if (!\App\Core\RateLimiter::attempt('pago_subir_' . $residenteId, 10, 3600)) {
            Flash::error("Ha excedido el límite de subida de pagos (máximo 10 por hora). Intente de nuevo más tarde.");
            $this->redirect('/pagos/nuevo');
            return;
        }
        
        $unidadId = $residente['unidad_id'];
        
        // Validación de datos básicos con cuentas autorizadas
        $cuenta_bancaria_id = intval($_POST['cuenta_bancaria_id'] ?? 0);
        $banco_pagador = trim($_POST['banco_pagador'] ?? '');
        if (strtoupper($banco_pagador) === 'OTRO' && !empty($_POST['banco_pagador_otro'])) {
            $banco_pagador = trim($_POST['banco_pagador_otro']);
        }
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
            return;
        }
        if (empty($fecha_pago)) {
            Flash::error("La fecha de realización del pago es requerida.");
            $this->redirect('/pagos/nuevo');
            return;
        }
        if (!\DateTime::createFromFormat('Y-m-d', $fecha_pago) || date('Y-m-d', strtotime($fecha_pago)) !== $fecha_pago) {
            Flash::error("El formato de fecha no es válido. Use AAAA-MM-DD.");
            $this->redirect('/pagos/nuevo');
            return;
        }
        
        if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
            Flash::error("El archivo del comprobante es obligatorio y debe ser válido.");
            $this->redirect('/pagos/nuevo');
            return;
        }
        
        $uploader = new \App\Services\FileUploader();
        $uniqueName = $uploader->upload($_FILES['comprobante']);
        
        if (!$uniqueName) {
            Flash::error("Formato o tamaño de archivo no permitido. Solo se aceptan imágenes (JPEG, PNG) o PDF hasta 5MB.");
            $this->redirect('/pagos/nuevo');
            return;
        }

        $archivoHash = $uploader->getLastFileHash();
        
        $pagoModel = new PagoModel();
        $totalDeuda = $pagoModel->obtenerTotalDeuda($unidadId);
        $esExcedente = ($monto > $totalDeuda);

        $datos = [
            'monto'              => $monto,
            'fecha_pago'         => $fecha_pago,
            'referencia'         => $referencia,
            'observaciones'      => $observaciones,
            'banco_pagador'      => $banco_pagador,
            'banco_receptor'     => $banco_receptor,
            'cuenta_bancaria_id' => $cuenta_bancaria_id,
            'archivo_hash'       => $archivoHash
        ];
        
        $result = $pagoModel->crearPago($residenteId, $unidadId, $datos, $uniqueName);
        
        if ($result) {
            if ($esExcedente) {
                Flash::success("Comprobante de pago subido correctamente. El excedente o pago anticipado se acreditará automáticamente como Saldo a Favor al ser aprobado.");
            } else {
                Flash::success("Comprobante de pago subido correctamente. Está pendiente de verificación.");
            }
            $this->redirect('/pagos');
        } else {
            Flash::error("No se pudo registrar el pago: es un duplicado o hubo un error de datos.");
            $this->redirect('/pagos/nuevo');
        }
    }

    /**
     * Endpoint dinámico para renderizar la vista de detalles y auditoría.
     */
    public function detalle($id) {
        Auth::requireLogin();

        $pagoModel = new PagoModel();
        $id = intval($id);
        $tipo = strtolower(trim($_GET['tipo'] ?? ''));

        // ?tipo=comprobante evita la colisión de IDs entre `pagos` y `comprobantes_pago`.
        $pago = ($tipo === 'comprobante')
            ? $pagoModel->obtenerComprobantePorId($id)
            : $pagoModel->obtenerPagoPorId($id);

        if (!$pago) {
            Flash::error('Pago no encontrado.');
            $rol = Auth::role();
            $redirectUrl = ($rol === 'admin' || $rol === 'auditor') ? '/admin/comprobantes' : '/pagos';
            $this->redirect($redirectUrl);
        }

        // Seguridad: el residente ve los movimientos de SU unidad (cualquier
        // miembro activo), no solo los que registró él mismo.
        $rol = Auth::role();
        if ($rol === 'residente') {
            $residente = $this->getAuthenticatedResidente();
            $unidadResidente = intval($residente['unidad_id'] ?? 0);
            $unidadMovimiento = intval($pago['unidad_id'] ?? 0);
            if ($unidadResidente <= 0 || $unidadMovimiento !== $unidadResidente) {
                Flash::error('Acceso denegado a este pago.');
                $this->redirect('/pagos');
            }
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
            $this->json([
                'success'    => false,
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
                'error'      => 'Demasiadas solicitudes de análisis.'
            ], 429);
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
                    'success'    => false,
                    'detectado'  => false,
                    'csrf_token' => $_SESSION['csrf_token'] ?? '',
                    'error'      => 'Para imágenes, la extracción se procesa mediante el motor de reconocimiento en el navegador.'
                ], 400);
                return;
            }
        } else {
            $this->json([
                'success'    => false,
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
                'error'      => 'No se proporcionó texto de OCR ni archivo válido para analizar.'
            ], 400);
            return;
        }

        $nombresBancos = [
            'mercantil'  => 'Banco Mercantil',
            'banesco'    => 'Banesco',
            'venezuela'  => 'Banco de Venezuela',
            'provincial' => 'BBVA Provincial',
            'bancamiga'  => 'Bancamiga',
            'bnc'        => 'Banco Nacional de Crédito',
            'bancaribe'  => 'Bancaribe',
            'tesoro'     => 'Banco del Tesoro',
            'exterior'   => 'Banco Exterior',
            'plaza'      => 'Banco Plaza',
            'activo'     => 'Banco Activo',
            'sofitasa'   => 'Banco Sofitasa',
            '100banco'   => '100% Banco',
            'bfc'        => 'Banco Fondo Común'
        ];

        $bancoPagador = $resultado['banco'] ? ($nombresBancos[$resultado['banco']] ?? ucfirst($resultado['banco'])) : '';
        
        // Resolver dinámicamente y validar la cuenta bancaria autorizada receptora
        $cuentasModel = new \App\Models\CuentasBancariasModel();
        $todasLasCuentas = $cuentasModel->getAll();
        $validacionCuenta = $parser->validarCuentaDestino($resultado, $todasLasCuentas);

        $confianza = $resultado['confianza'] ?? [];
        $confianza['cuenta_bancaria_id'] = $validacionCuenta['confianza'];

        $inconsistencias = $resultado['inconsistencias'] ?? [];
        $inconsistencias['cuenta_bancaria_id'] = $validacionCuenta['inconsistencia'];

        // Determinar mensaje de retroalimentación general
        $hayInconsistencias = !empty(array_filter($inconsistencias));
        if ($hayInconsistencias) {
            $mensaje = 'Datos detectados con observaciones. Por favor revise las advertencias antes de enviar.';
        } elseif ($resultado['detectado']) {
            $mensaje = 'Datos del comprobante detectados exitosamente.';
        } else {
            $mensaje = 'No se pudieron detectar los datos automáticamente. Por favor ingréselos manualmente.';
        }

        $this->json([
            'success'               => true,
            'csrf_token'            => $_SESSION['csrf_token'] ?? '',
            'detectado'             => (bool)$resultado['detectado'],
            'banco_pagador'         => $bancoPagador,
            'banco_receptor'        => $validacionCuenta['banco_receptor'],
            'cuenta_bancaria_id'    => $validacionCuenta['cuenta_bancaria_id'],
            'cuenta_destino_valida' => $validacionCuenta['cuenta_destino_valida'],
            'metodo_pago'           => $resultado['metodo_pago'] ?? '',
            'referencia'            => $resultado['referencia'] ?? '',
            'monto'                 => $resultado['monto'] !== null ? number_format($resultado['monto'], 2, '.', '') : '',
            'fecha_pago'            => $resultado['fecha'] ?? '',
            'confianza'             => $confianza,
            'inconsistencias'       => $inconsistencias,
            'mensaje'               => $mensaje
        ]);
    }

    /**
     * Cambia el estado del pago (Admin). CSRF ya validado globalmente en index.php.
     */
    public function cambiarEstado() {
        Auth::requireRole(['admin', 'auditor']);
        
        $pagoId = intval($_POST['pago_id'] ?? 0);
        $nuevoEstado = trim($_POST['nuevo_estado'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');
        $adminId = Auth::id();
        
        if ($pagoId <= 0 || empty($nuevoEstado)) {
            Flash::error("Identificador o estado de pago inválido.");
            $this->redirect('/pagos');
        }
        
        // Máquina de estados centralizada (fuente única: App\Core\EstadoPago).
        // Esta validación temprana da mensajes amigables; el modelo la re-valida
        // bajo el lock FOR UPDATE para bloquear carreras concurrentes.
        $transicionesValidas = EstadoPago::transicionesValidas();

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
        if ($nuevoEstado === EstadoPago::RECHAZADO && (empty($motivo) || mb_strlen($motivo) < 5)) {
            Flash::error("Debe proporcionar un motivo de rechazo claro y detallado (mínimo 5 caracteres).");
            $this->redirect('/pagos/detalle/' . $pagoId);
        }

        $resultado = $pagoModel->cambiarEstado($pagoId, $nuevoEstado, $motivo, $adminId, $_SERVER['REMOTE_ADDR'] ?? null);

        if (!empty($resultado['ok'])) {
            if (($resultado['code'] ?? '') === 'sin_cambios') {
                Flash::info($resultado['message'] ?? "El pago ya se encontraba en estado {$nuevoEstado}.");
            } else {
                Flash::success("El pago fue actualizado a estado {$nuevoEstado} exitosamente.");
            }
        } else {
            Flash::error($resultado['message'] ?? "Hubo un error de base de datos al registrar el cambio de estado.");
        }
        
        $origen = $_POST['origen'] ?? '';
        if ($origen === 'conciliacion') {
            $destino = '/admin/conciliacion';
        } elseif ($origen === 'dashboard') {
            $destino = '/admin/dashboard';
        } elseif ($origen === 'historial' || $origen === 'comprobantes') {
            $destino = '/admin/comprobantes';
        } else {
            $destino = (!empty($_POST['redirect_to_detalle']) || $origen === 'detalle')
                ? '/pagos/detalle/' . $pagoId
                : '/pagos';
        }
        $this->redirect($destino);
    }

    /**
     * Procesa la aprobación masiva en lote de hasta 50 pagos (Admin).
     */
    public function aprobarMasivo() {
        Auth::requireRole(['admin', 'auditor']);

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
                if (($resultado['duplicados'] ?? 0) > 0) {
                    $mensaje .= " {$resultado['duplicados']} pago(s) fueron bloqueados por duplicado económico.";
                }
                if ($resultado['omitidos'] > 0) {
                    $mensaje .= " ({$resultado['omitidos']} pago(s) fueron omitidos por estar previamente procesados).";
                }
                Flash::success($mensaje);
            } else {
                if (($resultado['duplicados'] ?? 0) > 0) {
                    Flash::error("Ninguno de los pagos seleccionados pudo ser aprobado: {$resultado['duplicados']} pago(s) están bloqueados por duplicado económico y el resto ya fue procesado o no es válido.");
                } else {
                    Flash::error("Ninguno de los pagos seleccionados pudo ser aprobado (ya procesados o no válidos).");
                }
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

        // Rate limit OCR endpoint — max 20 requests/min/user (mismo patrón que extraer())
        if (!\App\Core\RateLimiter::attempt('ocr_analisis_' . Auth::id(), 20, 60)) {
            $this->json([
                'success'    => false,
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
                'error'      => 'Demasiadas solicitudes de análisis.'
            ], 429);
            return;
        }

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
                if (\PHP_VERSION_ID < 80500 && \is_resource($finfo)) {
                    @finfo_close($finfo);
                }
                unset($finfo);
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
