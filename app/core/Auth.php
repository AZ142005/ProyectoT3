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
    public static function requireLogin(): void {
        if (!self::check()) {
            header('Location: /auth/login');
            exit;
        }
    }

    /**
     * Verifica que el usuario autenticado tenga un rol específico (o esté dentro de una lista de roles permitidos).
     * Si no tiene sesión, redirige al login.
     * Si tiene sesión pero rol incorrecto, muestra 403.
     *
     * @param string|array $role Rol o array de roles permitidos ('admin', 'residente', 'auditor')
     * @return void
     */
    public static function requireRole($role): void {
        self::requireLogin();

        $rolesPermitidos = is_array($role) ? $role : [$role];
        if (!in_array(self::role(), $rolesPermitidos, true)) {
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
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['auth_user'] = [
            'id'    => (int) $user['id'],
            'name'  => $user['nombre_completo'],
            'email' => $user['email'] ?? $user['usuario'],
            'role'  => UserRole::ADMIN,
        ];
    }

    /**
     * Inicia sesión para un residente.
     *
     * @param array $persona Registro de la tabla `personas`
     * @return void
     */
    public static function loginAsResidente(array $persona): void {
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['auth_user'] = [
            'id'         => (int) $persona['id'],
            'persona_id' => (int) $persona['id'],
            'name'       => trim($persona['nombre'] . ' ' . $persona['apellido']),
            'email'      => $persona['email'],
            'role'       => UserRole::RESIDENTE,
        ];
    }

    /**
     * Inicia sesión para un auditor (fiscalizador de solo lectura).
     *
     * @param array $user Registro de la tabla `usuarios`
     * @return void
     */
    public static function loginAsAuditor(array $user): void {
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['auth_user'] = [
            'id'    => (int) $user['id'],
            'name'  => $user['nombre_completo'],
            'email' => $user['email'] ?? $user['usuario'],
            'role'  => UserRole::AUDITOR,
        ];
    }

    /**
     * Cierra la sesión activa completamente.
     *
     * @return void
     */
    public static function logout(): void {
        // Revocar todos los refresh tokens del usuario antes de destruir la sesión
        if (isset($_SESSION['auth_user']['id'])) {
            try {
                $db = Database::getConnection();
                $stmt = $db->prepare("UPDATE refresh_tokens SET revocado = 1 WHERE usuario_id = :uid AND revocado = 0");
                $stmt->execute(['uid' => $_SESSION['auth_user']['id']]);
            } catch (\Exception $e) {
                error_log("[AUTH] Error revocando refresh tokens: " . $e->getMessage());
            }
        }

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
