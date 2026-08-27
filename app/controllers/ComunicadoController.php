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

        $titulo = preg_replace('/[\r\n]/', '', strip_tags(trim($_POST['titulo'] ?? '')));
        $contenido = trim($_POST['contenido'] ?? '');
        $urgencia = strtolower($_POST['nivel_urgencia'] ?? 'normal');
        $edificioId = !empty($_POST['edificio_id']) ? intval($_POST['edificio_id']) : null;
        $unidadId = !empty($_POST['unidad_id']) ? intval($_POST['unidad_id']) : null;
        $enviarEmail = !empty($_POST['enviar_email']);

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

        try {
            $comunicadosModel = new ComunicadosModel();
            $adminId = Auth::id() ?? 1;

            $comunicadoId = $comunicadosModel->crearComunicado([
                'titulo'         => $titulo,
                'contenido'      => $contenido,
                'nivel_urgencia' => $urgencia,
                'edificio_id'    => $edificioId,
                'unidad_id'      => $unidadId,
                'admin_id'       => $adminId
            ]);

            // Si se marcó "Enviar por correo", se encola el comunicado para los residentes elegibles
            if ($enviarEmail) {
                $this->encolarComunicadoCorreo($titulo, $contenido, $edificioId, $unidadId);
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

        $user = Auth::user();
        $personaId = $user['persona_id'] ?? null;
        $pagina = max(1, intval($_GET['page'] ?? 1));

        $edificioId = null;
        $unidadId = null;

        // Consultar la unidad asignada al residente
        if ($personaId) {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->prepare("SELECT id, edificio_id FROM unidades WHERE propietario_id = :pid LIMIT 1");
            $stmt->execute(['pid' => $personaId]);
            $u = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($u) {
                $unidadId = intval($u['id']);
                $edificioId = intval($u['edificio_id']);
            }
        }

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
    private function encolarComunicadoCorreo(string $titulo, string $contenido, ?int $edificioId, ?int $unidadId) {
        $db = \App\Core\Database::getConnection();
        $sql = "SELECT DISTINCT p.id AS persona_id, p.email, p.telefono 
                FROM personas p 
                INNER JOIN unidades u ON u.propietario_id = p.id 
                WHERE p.email IS NOT NULL AND p.email != ''";

        $params = [];
        if ($unidadId) {
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
