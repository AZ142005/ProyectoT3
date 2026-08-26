<?php
namespace App\Core;

class AuthMiddleware {

    /**
     * Valida el token JWT en las cabeceras HTTP de peticiones API.
     * Retorna el payload del token o termina con HTTP 401.
     *
     * @return array Payload verificado del JWT
     */
    public static function validarJWT(): array {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
        }

        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            self::responderNoAutorizado('Token de autorización no proporcionado o formato Bearer inválido.');
        }

        $token = trim($matches[1]);

        try {
            return JWT::decode($token);
        } catch (\InvalidArgumentException $e) {
            self::responderNoAutorizado($e->getMessage());
        } catch (\Exception $e) {
            self::responderNoAutorizado('Error de autenticación: Token JWT no válido.');
        }
        return [];
    }

    /**
     * Emite respuesta JSON 401 Unauthorized y finaliza ejecución.
     */
    private static function responderNoAutorizado(string $mensaje): void {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => $mensaje
        ]);
        exit;
    }
}
