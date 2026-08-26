<?php
namespace App\Core;

class RoleMiddleware {

    /**
     * Valida permisos de rol y bloquea mutaciones (POST/PUT/PATCH/DELETE) para el rol Auditor.
     */
    public static function handle(): void {
        if (!Auth::check()) {
            return;
        }

        $rol = Auth::role();
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($rol === UserRole::AUDITOR && !in_array($metodo, ['GET', 'HEAD', 'OPTIONS'])) {
            http_response_code(403);

            if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error'   => 'Acceso denegado: El rol Auditor no tiene permisos de modificación ni mutación de datos.'
                ]);
                exit;
            }

            $forbiddenView = VIEWS_PATH . '/errors/403.php';
            if (file_exists($forbiddenView)) {
                require_once $forbiddenView;
            } else {
                echo "<h3>Error 403: El rol Auditor no tiene permisos de modificación ni mutación de datos.</h3>";
            }
            exit;
        }
    }
}
