<?php
namespace App\Core;

class Security {
    /**
     * Valida el token CSRF en solicitudes POST.
     * Si falla, aborta la ejecución enviando un código HTTP 403.
     *
     * @return bool
     */
    public static function validateCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postToken = $_POST['csrf_token'] ?? '';
            $sessionToken = $_SESSION['csrf_token'] ?? '';
            
            if (empty($postToken) || empty($sessionToken) || !hash_equals($sessionToken, $postToken)) {
                http_response_code(403);
                $forbiddenView = VIEWS_PATH . '/errors/403.php';
                if (file_exists($forbiddenView)) {
                    require_once $forbiddenView;
                } else {
                    echo "<h3>Error 403: Acceso prohibido (Token CSRF inválido o ausente)</h3>";
                }
                exit;
            }
        }
        return true;
    }
}
