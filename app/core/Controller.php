<?php
namespace App\Core;

use App\Models\PersonasModel;

class Controller {
    /**
     * Renderiza una vista inyectando datos y envolviéndola en el layout base.
     *
     * @param string $view Nombre de la vista (ej: 'residente/dashboard')
     * @param array $data Variables que se inyectarán en la vista
     * @return void
     */
    protected function render($view, $data = []): void {
        $layoutName = $data['layout'] ?? null;
        unset($data['layout']);

        // Extraer las variables del array
        extract($data);
        
        // Iniciar el búfer de salida para capturar la vista específica
        ob_start();
        
        $viewFile = VIEWS_PATH . '/' . $view . '.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            ob_end_clean();
            trigger_error("Error: La vista '{$view}' no se encuentra en " . VIEWS_PATH, E_USER_ERROR);
            return;
        }
        
        $content = ob_get_clean();
        
        // Si la vista solicita el layout de administración o auditoría, renderizar sidebar si corresponde
        $sidebar = '';
        if ($layoutName === 'admin') {
            $sidebarFile = VIEWS_PATH . '/layouts/admin_sidebar.php';
            if (file_exists($sidebarFile)) {
                ob_start();
                require $sidebarFile;
                $sidebar = ob_get_clean();
            }
        } elseif ($layoutName === 'auditor') {
            $sidebarFile = VIEWS_PATH . '/layouts/auditor_sidebar.php';
            if (file_exists($sidebarFile)) {
                ob_start();
                require $sidebarFile;
                $sidebar = ob_get_clean();
            }
        }

        // Requerir la plantilla base
        $layoutFile = VIEWS_PATH . '/layouts/base.php';
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }
    
    /**
     * Redirige a una URL específica y detiene la ejecución.
     *
     * @param string $url Dirección de redirección
     * @return void
     */
    protected function redirect($url): void {
        // Prevenir open redirect: solo permitir rutas relativas que comiencen con /
        // y no contengan // (que indicarían un dominio externo como https://evil.com)
        if (strpos($url, '://') !== false || (strpos($url, '//') === 0 && strpos($url, '://') !== 0)) {
            $url = '/';
        }
        header("Location: " . $url);
        exit;
    }

    /**
     * Obtiene el residente autenticado. Si no existe, cierra sesión y redirige.
     *
     * @return array Datos del residente (con unidad_id, etc.)
     */
    protected function getAuthenticatedResidente(): array {
        Auth::requireRole('residente');

        $residenteId = Auth::id();
        $personasModel = new PersonasModel();
        $residente = $personasModel->getResidenteDetails($residenteId);

        if (!$residente) {
            Auth::logout();
            $this->redirect('/auth/login');
        }

        return $residente;
    }

    /**
     * Responde con un payload JSON y detiene la ejecución.
     *
     * @param mixed $data Datos a serializar
     * @param int $status Código HTTP
     * @return void
     */
    protected function json($data, $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
