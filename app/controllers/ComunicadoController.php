<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\ComunicadosModel;
use App\Models\EdificiosModel;
use App\Models\UnidadesModel;
use App\Services\NotificationService;
use App\Services\EmailService;

class ComunicadoController extends Controller {

    /**
     * Muestra la lista de comunicados para la administración.
     */
    public function index() {
        Auth::requireRole('admin');

        $pagina = max(1, intval($_GET['page'] ?? 1));

        $comunicadosModel = new ComunicadosModel();
        $edificiosModel = new EdificiosModel();
        $unidadesModel = new UnidadesModel();

        // Auto-eliminación oportunista: los comunicados vencidos se soft-deleted
        // al abrir el módulo (no hay scheduler en el proyecto).
        $comunicadosModel->eliminarExpirados();

        $resultado = $comunicadosModel->obtenerTodosAdmin($pagina, 15);
        $edificios = $edificiosModel->getActivos();
        $unidades = $unidadesModel->getActivas();

        $paginacion = [
            'total'        => $resultado['total'],
            'pagina'       => $resultado['pagina'],
            'porPagina'    => $resultado['porPagina'],
            'totalPaginas' => $resultado['totalPaginas'],
        ];

        $this->render('admin/comunicados/index', [
            'comunicados' => $resultado['datos'],
            'edificios'   => $edificios,
            'unidades'    => $unidades,
            'paginacion'  => $paginacion,
            'layout'      => 'admin',
            'title'       => 'Gestión de Comunicados y Cartelera'
        ]);
    }

    /**
     * Guarda un nuevo comunicado y opcionalmente lo encola para envío por correo.
     */
    public function guardar() {
        Auth::requireRole('admin');

        $adminId = Auth::id() ?? 1;

        $titulo = preg_replace('/[\r\n]/', '', strip_tags(trim($_POST['titulo'] ?? '')));
        $contenido = trim($_POST['contenido'] ?? '');
        $urgencia = strtolower($_POST['nivel_urgencia'] ?? 'normal');
        $edificioId = !empty($_POST['edificio_id']) ? intval($_POST['edificio_id']) : null;
        $unidadId = !empty($_POST['unidad_id']) ? intval($_POST['unidad_id']) : null;
        $enviarEmail = !empty($_POST['enviar_email']);

        // Duración en cartelera: whitelist de días; ausente o inválida => 7 días.
        // 0 = sin vencimiento (fecha_expiracion NULL).
        $duracionesValidas = [0, 1, 3, 7, 14, 30];
        $diasDuracion = (isset($_POST['duracion_dias']) && is_numeric($_POST['duracion_dias'])) ? intval($_POST['duracion_dias']) : 7;
        if (!in_array($diasDuracion, $duracionesValidas, true)) {
            $diasDuracion = 7;
        }
        $fechaExpiracion = $diasDuracion > 0 ? date('Y-m-d H:i:s', time() + ($diasDuracion * 86400)) : null;

        // Destinos avanzados: unidades específicas de uno o varios edificios.
        // Si hay selección, toma prioridad sobre el destino general (edificio/unidad).
        $unidadesDestino = [];
        if (isset($_POST['unidades']) && is_array($_POST['unidades'])) {
            $unidadesDestino = array_values(array_unique(array_filter(array_map('intval', array_filter($_POST['unidades'], 'is_scalar')), function ($id) {
                return $id > 0;
            })));
        }
        if (!empty($unidadesDestino)) {
            $edificioId = null;
            $unidadId = null;
        }

        if (empty($titulo) || empty($contenido)) {
            Flash::set('danger', 'El título y el contenido son obligatorios.');
            $this->redirect('/admin/comunicados');
            return;
        }

        if (mb_strlen($titulo) > 200) {
            Flash::set('danger', 'El título no puede exceder 200 caracteres.');
            $this->redirect('/admin/comunicados');
            return;
        }

        if (mb_strlen($contenido) > 10000) {
            Flash::set('danger', 'El contenido no puede exceder 10,000 caracteres.');
            $this->redirect('/admin/comunicados');
            return;
        }

        $urgenciasValidas = ['normal', 'importante', 'urgente'];
        if (!in_array($urgencia, $urgenciasValidas)) {
            Flash::set('danger', 'Nivel de urgencia no válido.');
            $this->redirect('/admin/comunicados');
            return;
        }

        // Rate limit de publicaciones: máximo 10 comunicados por hora por administrador.
        // Se evalúa tras las validaciones para que un envío inválido no consuma intentos.
        if (!\App\Core\RateLimiter::attempt('comunicado_' . $adminId, 10, 3600)) {
            Flash::set('danger', 'Ha excedido el límite de 10 comunicados por hora. Intente más tarde.');
            $this->redirect('/admin/comunicados');
            return;
        }

        try {
            $comunicadosModel = new ComunicadosModel();

            // Evitar doble publicación por doble envío del formulario
            if ($comunicadosModel->existeDuplicadoReciente($titulo, $contenido, $edificioId, $unidadId, $adminId)) {
                Flash::set('info', 'Este comunicado ya fue publicado hace instantes; se evitó un duplicado.');
                $this->redirect('/admin/comunicados');
                return;
            }

            $comunicadoId = $comunicadosModel->crearComunicado([
                'titulo'         => $titulo,
                'contenido'      => $contenido,
                'nivel_urgencia' => $urgencia,
                'edificio_id'    => $edificioId,
                'unidad_id'      => $unidadId,
                'admin_id'       => $adminId,
                'fecha_expiracion' => $fechaExpiracion
            ]);

            if (!empty($unidadesDestino)) {
                $comunicadosModel->asignarDestinosUnidades($comunicadoId, $unidadesDestino);
            }

            // Si se marcó "Enviar por correo", se encola el comunicado para los residentes elegibles
            if ($enviarEmail) {
                $this->encolarComunicadoCorreo($titulo, $contenido, $edificioId, $unidadId, $unidadesDestino);
            }

            Flash::set('success', 'Comunicado publicado exitosamente en la cartelera digital.');
        } catch (\Exception $e) {
            error_log("[COMUNICADO] Error publicar comunicado: " . $e->getMessage());
            Flash::set('danger', 'Error al publicar el comunicado. Intente de nuevo.');
        }

        $this->redirect('/admin/comunicados');
    }

    /**
     * Elimina lógicamente (Soft Delete) un comunicado.
     */
    public function eliminar() {
        Auth::requireRole('admin');

        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $comunicadosModel = new ComunicadosModel();
            $comunicadosModel->softDelete($id);
            Flash::set('success', 'Comunicado eliminado de la cartelera.');
        }

        $this->redirect('/admin/comunicados');
    }

    /**
     * Muestra la cartelera digital segmentada para el residente.
     */
    public function carteleraResidente() {
        Auth::requireRole('residente');

        // Resolución unificada del portal: personas.unidad_id / unidades.edificio_id
        $residente = $this->getAuthenticatedResidente();
        $unidadId = !empty($residente['unidad_id']) ? intval($residente['unidad_id']) : null;
        $edificioId = !empty($residente['edificio_id']) ? intval($residente['edificio_id']) : null;
        $pagina = max(1, intval($_GET['page'] ?? 1));

        $comunicadosModel = new ComunicadosModel();
        $resultado = $comunicadosModel->obtenerPorResidente($edificioId, $unidadId, $pagina, 10);

        $paginacion = [
            'total'        => $resultado['total'],
            'pagina'       => $resultado['pagina'],
            'porPagina'    => $resultado['porPagina'],
            'totalPaginas' => $resultado['totalPaginas'],
        ];

        $this->render('residente/cartelera', [
            'comunicados' => $resultado['datos'],
            'paginacion'  => $paginacion,
            'title'       => 'Cartelera Digital del Condominio'
        ]);
    }

    /**
     * Encola el comunicado por correo electrónico para los residentes filtrados.
     * Agrupa por persona_id para evitar notificaciones duplicadas.
     * Limita a 500 destinatarios máximo.
     */
    private function encolarComunicadoCorreo(string $titulo, string $contenido, ?int $edificioId, ?int $unidadId, array $unidadesDestino = []) {
        $db = \App\Core\Database::getConnection();
        $sql = "SELECT DISTINCT p.id AS persona_id, p.email, p.telefono 
                FROM personas p 
                INNER JOIN unidades u ON p.unidad_id = u.id
                WHERE p.email IS NOT NULL AND p.email != ''";

        $params = [];
        if (!empty($unidadesDestino)) {
            $placeholders = [];
            foreach (array_values($unidadesDestino) as $indice => $unidadDestinoId) {
                $placeholders[] = ':ud' . $indice;
                $params['ud' . $indice] = intval($unidadDestinoId);
            }
            $sql .= " AND u.id IN (" . implode(', ', $placeholders) . ")";
        } elseif ($unidadId) {
            $sql .= " AND u.id = :unidad_id";
            $params['unidad_id'] = $unidadId;
        } elseif ($edificioId) {
            $sql .= " AND u.edificio_id = :edificio_id";
            $params['edificio_id'] = $edificioId;
        }

        $sql .= " ORDER BY p.id ASC LIMIT 500";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $residentes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $emailService = new EmailService();
        $notifService = new NotificationService();

        $cuerpoHtml = $emailService->renderTemplate('comunicado', [
            'tituloComunicado'    => $titulo,
            'contenidoComunicado' => $contenido,
            'fechaPublicacion'    => date('d/m/Y H:i')
        ]);

        foreach ($residentes as $res) {
            $notifService->encolarNotificacion($res['email'], "📢 " . $titulo, $cuerpoHtml, $res['telefono'], 'email', 'normal');
            $notifService->registrarNotificacionResidente($res['persona_id'], "Nuevo Comunicado: " . $titulo, substr(strip_tags($contenido), 0, 120) . "...", "info", "/residente/cartelera");
        }
    }
}
