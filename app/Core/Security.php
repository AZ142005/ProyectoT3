<?php
namespace App\Core;

class Security {
    /**
     * Valida el token CSRF en solicitudes POST.
     * Si falla, aborta la ejecución enviando un código HTTP 403.
     *
     * @return bool
     */
    public static function validateCSRF(): bool {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $sessionToken = $_SESSION['csrf_token'] ?? '';
            $postToken = '';

            if (str_starts_with($contentType, 'application/json')) {
                // JSON API: CSRF token from X-CSRF-Token header
                $postToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            } elseif (stripos($contentType, 'application/x-www-form-urlencoded') !== false
                   || stripos($contentType, 'multipart/form-data') !== false) {
                // Standard form POST
                $postToken = $_POST['csrf_token'] ?? '';
            } elseif (!empty($contentType)) {
                // Unsupported Content-Type → 415
                http_response_code(415);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Content-Type no soportado. Use application/x-www-form-urlencoded, multipart/form-data o application/json.'
                ]);
                exit;
            } else {
                // Empty Content-Type (legacy clients): try php://input as JSON fallback
                $rawInput = file_get_contents('php://input');
                if ($rawInput && strlen($rawInput) > 0) {
                    $decoded = json_decode($rawInput, true);
                    $postToken = $decoded['csrf_token'] ?? '';
                } else {
                    $postToken = $_POST['csrf_token'] ?? '';
                }
            }
            
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

            // Rotar token después de cada uso válido para limitar ventana de exposición
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return true;
    }
}
