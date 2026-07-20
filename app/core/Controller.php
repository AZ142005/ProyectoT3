<?php
namespace App\Core;

class Controller {
    /**
     * Renderiza una vista inyectando datos y envolviéndola en el layout base.
     *
     * @param string $view Nombre de la vista (ej: 'residente/dashboard')
     * @param array $data Variables que se inyectarán en la vista
     * @return void
     */
    protected function render($view, $data = []) {
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
        
        // Guardar el contenido procesado en $content para ser usado en layouts/base.php
        $content = ob_get_clean();
        
        // Requerir la plantilla base
        $layoutFile = VIEWS_PATH . '/layouts/base.php';
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            // Fallback en caso de que no exista el layout base
            echo $content;
        }
    }
    
    /**
     * Redirige a una URL específica y detiene la ejecución.
     *
     * @param string $url Dirección de redirección
     * @return void
     */
    protected function redirect($url) {
        header("Location: " . $url);
        exit;
    }
}
