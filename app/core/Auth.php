<?php
namespace App\Core;

/**
 * Clase estática de autorización y gestión de identidad.
 * Centraliza la verificación de sesiones y roles para
 * reemplazar los chequeos manuales en cada controlador.
 */
class Auth {
    /**
     * Verifica que el usuario tenga una sesión activa.
     * Si no la tiene, redirige al login unificado.
     *
     * @return void
     */
    public static function requireLogin() {
        if (!self::check()) {
            header('Location: /auth/login');
            exit;
        }
    }

    /**
     * Verifica que el usuario autenticado tenga un rol específico.
     * Si no tiene sesión, redirige al login.
     * Si tiene sesión pero rol incorrecto, muestra 403.
     *
     * @param string $role Rol requerido ('admin' o 'residente')
     * @return void
     */
    public static function requireRole(string $role) {
        self::requireLogin();

        if (self::role() !== $role) {
            http_response_code(403);
            $forbiddenView = VIEWS_PATH . '/errors/403.php';
            if (file_exists($forbiddenView)) {
                require_once $forbiddenView;
            } else {
                echo "<h3>Error 403: No tienes permiso para acceder a esta sección.</h3>";
            }
            exit;
        }
    }

    /**
     * Retorna true si hay un usuario autenticado (admin o residente).
     *
     * @return bool
     */
    public static function check(): bool {
        return isset($_SESSION['auth_user']);
    }

    /**
     * Retorna los datos del usuario autenticado o null.
     *
     * @return array|null
     */
    public static function user(): ?array {
        return $_SESSION['auth_user'] ?? null;
    }

    /**
     * Retorna el rol del usuario autenticado ('admin', 'residente') o null.
     *
     * @return string|null
     */
    public static function role(): ?string {
        return $_SESSION['auth_user']['role'] ?? null;
    }

    /**
     * Retorna el ID del usuario autenticado.
     *
     * @return int|null
     */
    public static function id(): ?int {
        return $_SESSION['auth_user']['id'] ?? null;
    }

    /**
     * Retorna true si el usuario tiene el rol dado.
     *
     * @param string $role
     * @return bool
     */
    public static function hasRole(string $role): bool {
        return self::role() === $role;
    }

    /**
     * Inicia sesión para un administrador.
     *
     * @param array $user Registro de la tabla `usuarios`
     * @return void
     */
    public static function loginAsAdmin(array $user): void {
        $_SESSION['auth_user'] = [
            'id'    => (int) $user['id'],
            'name'  => $user['nombre_completo'],
            'email' => $user['email'] ?? $user['usuario'],
            'role'  => 'admin',
        ];

        // Mantener compatibilidad con variables de sesión heredadas
        $_SESSION['admin_usuario'] = $user['usuario'];
        $_SESSION['admin_nombre']  = $user['nombre_completo'];
        $_SESSION['admin_rol']     = $user['rol'] ?? 'admin';
        $_SESSION['admin_id']      = (int) $user['id'];
    }

    /**
     * Inicia sesión para un residente.
     *
     * @param array $persona Registro de la tabla `personas`
     * @return void
     */
    public static function loginAsResidente(array $persona): void {
        $_SESSION['auth_user'] = [
            'id'    => (int) $persona['id'],
            'name'  => trim($persona['nombre'] . ' ' . $persona['apellido']),
            'email' => $persona['email'],
            'role'  => 'residente',
        ];

        // Mantener compatibilidad con variable de sesión heredada
        $_SESSION['residente_id'] = (int) $persona['id'];
    }

    /**
     * Cierra la sesión activa completamente.
     *
     * @return void
     */
    public static function logout(): void {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
