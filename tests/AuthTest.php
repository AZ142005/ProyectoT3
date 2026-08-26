<?php
namespace Tests;

use Tests\TestCase;

/**
 * Tests de Autenticación — Clase Auth
 *
 * Verifica login, logout, roles, sesiones y protección de rutas.
 */
class AuthTest extends TestCase {

    private function startTestSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    private function destroyTestSession(): void {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // =====================================================================
    // CLASE AUTH — MÉTODOS ESTÁTICOS
    // =====================================================================

    /**
     * Verifica que la clase Auth exista.
     */
    public function testAuthClassExists(): void {
        $this->assertTrue(
            class_exists(\App\Core\Auth::class),
            "La clase App\\Core\\Auth debe existir"
        );
    }

    /**
     * Verifica que Auth::check() retorne false sin sesión.
     */
    public function testAuthCheckReturnsFalseWithoutSession(): void {
        $this->startTestSession();
        $_SESSION = [];

        $this->assertFalse(\App\Core\Auth::check(),
            "Auth::check() debe retornar false sin sesión activa");

        $this->destroyTestSession();
    }

    /**
     * Verifica que Auth::check() retorne true con sesión válida.
     */
    public function testAuthCheckReturnsTrueWithSession(): void {
        $this->startTestSession();
        $_SESSION['auth_user'] = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@test.com',
            'role' => 'admin',
        ];

        $this->assertTrue(\App\Core\Auth::check(),
            "Auth::check() debe retornar true con sesión activa");

        $this->destroyTestSession();
    }

    /**
     * Verifica que Auth::user() retorne null sin sesión.
     */
    public function testAuthUserReturnsNullWithoutSession(): void {
        $this->startTestSession();
        $_SESSION = [];

        $this->assertNull(\App\Core\Auth::user(),
            "Auth::user() debe retornar null sin sesión");

        $this->destroyTestSession();
    }

    /**
     * Verifica que Auth::user() retorne los datos correctos.
     */
    public function testAuthUserReturnsUserData(): void {
        $this->startTestSession();
        $userData = [
            'id' => 42,
            'name' => 'María García',
            'email' => 'maria@test.com',
            'role' => 'residente',
        ];
        $_SESSION['auth_user'] = $userData;

        $user = \App\Core\Auth::user();
        $this->assertEquals(42, $user['id'], "Auth::user() debe retornar el ID correcto");
        $this->assertEquals('María García', $user['name'], "Auth::user() debe retornar el nombre correcto");
        $this->assertEquals('residente', $user['role'], "Auth::user() debe retornar el rol correcto");

        $this->destroyTestSession();
    }

    /**
     * Verifica que Auth::role() retorne el rol correcto.
     */
    public function testAuthRoleReturnsCorrectRole(): void {
        $this->startTestSession();
        $_SESSION['auth_user'] = ['role' => 'admin'];

        $this->assertEquals('admin', \App\Core\Auth::role(),
            "Auth::role() debe retornar 'admin'");

        $_SESSION['auth_user']['role'] = 'residente';
        $this->assertEquals('residente', \App\Core\Auth::role(),
            "Auth::role() debe retornar 'residente'");

        $this->destroyTestSession();
    }

    /**
     * Verifica que Auth::role() retorne null sin sesión.
     */
    public function testAuthRoleReturnsNullWithoutSession(): void {
        $this->startTestSession();
        $_SESSION = [];

        $this->assertNull(\App\Core\Auth::role(),
            "Auth::role() debe retornar null sin sesión");

        $this->destroyTestSession();
    }

    /**
     * Verifica que Auth::id() retorne el ID correcto.
     */
    public function testAuthIdReturnsCorrectId(): void {
        $this->startTestSession();
        $_SESSION['auth_user'] = ['id' => 99];

        $this->assertEquals(99, \App\Core\Auth::id(),
            "Auth::id() debe retornar el ID del usuario");

        $this->destroyTestSession();
    }

    /**
     * Verifica que Auth::hasRole() compare correctamente.
     */
    public function testHasRoleComparesCorrectly(): void {
        $this->startTestSession();

        $_SESSION['auth_user'] = ['role' => 'admin'];
        $this->assertTrue(\App\Core\Auth::hasRole('admin'),
            "hasRole('admin') debe retornar true cuando el rol es admin");
        $this->assertFalse(\App\Core\Auth::hasRole('residente'),
            "hasRole('residente') debe retornar false cuando el rol es admin");

        $_SESSION['auth_user']['role'] = 'residente';
        $this->assertTrue(\App\Core\Auth::hasRole('residente'),
            "hasRole('residente') debe retornar true cuando el rol es residente");
        $this->assertFalse(\App\Core\Auth::hasRole('admin'),
            "hasRole('admin') debe retornar false cuando el rol es residente");

        $this->destroyTestSession();
    }

    // =====================================================================
    // LOGIN / LOGOUT
    // =====================================================================

    /**
     * Verifica que loginAsAdmin establezca la sesión correctamente.
     */
    public function testLoginAsAdminSetsSession(): void {
        $this->startTestSession();

        $adminData = [
            'id' => 1,
            'usuario' => 'admin01',
            'nombre_completo' => 'Administrador Principal',
            'email' => 'admin@test.com',
            'rol' => 'admin',
        ];

        \App\Core\Auth::loginAsAdmin($adminData);

        $this->assertTrue(\App\Core\Auth::check(), "Debe haber sesión activa tras loginAsAdmin");
        $this->assertEquals('admin', \App\Core\Auth::role(), "El rol debe ser 'admin'");
        $this->assertEquals(1, \App\Core\Auth::id(), "El ID debe ser 1");

        $user = \App\Core\Auth::user();
        $this->assertEquals('Administrador Principal', $user['name']);
        $this->assertEquals('admin@test.com', $user['email']);

        $this->destroyTestSession();
    }

    /**
     * Verifica que loginAsResidente establezca la sesión correctamente.
     */
    public function testLoginAsResidenteSetsSession(): void {
        $this->startTestSession();

        $residenteData = [
            'id' => 42,
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'email' => 'juan@test.com',
        ];

        \App\Core\Auth::loginAsResidente($residenteData);

        $this->assertTrue(\App\Core\Auth::check(), "Debe haber sesión activa tras loginAsResidente");
        $this->assertEquals('residente', \App\Core\Auth::role(), "El rol debe ser 'residente'");
        $this->assertEquals(42, \App\Core\Auth::id(), "El ID debe ser 42");

        $user = \App\Core\Auth::user();
        $this->assertEquals('Juan Pérez', $user['name'], "El nombre debe ser la concatenación de nombre + apellido");

        $this->destroyTestSession();
    }

    /**
     * Verifica que logout limpie la sesión completamente.
     */
    public function testLogoutClearsSession(): void {
        $this->startTestSession();

        $_SESSION['auth_user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['admin_usuario'] = 'admin01';
        $_SESSION['csrf_token'] = 'some_token';

        \App\Core\Auth::logout();

        $this->assertFalse(\App\Core\Auth::check(),
            "Auth::check() debe retornar false después de logout");

        $this->destroyTestSession();
    }

    // =====================================================================
    // SEGURIDAD — VARIABLES DE SESIÓN LEGACY
    // =====================================================================

    /**
     * Verifica que loginAsAdmin NO cree variables heredadas innecesarias.
     */
    public function testLoginAsAdminNoLegacyVariables(): void {
        $this->startTestSession();

        $adminData = [
            'id' => 1,
            'usuario' => 'admin01',
            'nombre_completo' => 'Test Admin',
            'email' => 'test@test.com',
            'rol' => 'admin',
        ];

        \App\Core\Auth::loginAsAdmin($adminData);

        // Estas son las variables legacy que deberían eliminarse
        $hasLegacy = isset($_SESSION['admin_usuario']) ||
                     isset($_SESSION['admin_nombre']) ||
                     isset($_SESSION['admin_rol']) ||
                     isset($_SESSION['admin_id']);

        if ($hasLegacy) {
            $this->failures[] = "MEJORA: loginAsAdmin() crea variables de sesión heredadas (admin_usuario, admin_nombre, etc.)";
            $this->failed++;
        } else {
            $this->passed++;
        }

        $this->destroyTestSession();
    }

    /**
     * Verifica que Auth::requireRole() esté definido.
     */
    public function testRequireRoleMethodExists(): void {
        $this->assertTrue(
            method_exists(\App\Core\Auth::class, 'requireRole'),
            "Auth::requireRole() debe existir"
        );
    }

    /**
     * Verifica que Auth::requireLogin() esté definido.
     */
    public function testRequireLoginMethodExists(): void {
        $this->assertTrue(
            method_exists(\App\Core\Auth::class, 'requireLogin'),
            "Auth::requireLogin() debe existir"
        );
    }

    /**
     * Verifica que los controladores de rutas protegidas usen Auth::requireRole o Auth::requireLogin.
     */
    public function testProtectedRoutesUseAuth(): void {
        $protectedFiles = [
            dirname(__DIR__) . '/app/controllers/AdminController.php',
            dirname(__DIR__) . '/app/controllers/ResidenteController.php',
            dirname(__DIR__) . '/app/controllers/PagoController.php',
            dirname(__DIR__) . '/app/controllers/EstructuraController.php',
        ];

        $missingAuth = [];

        foreach ($protectedFiles as $file) {
            if (!file_exists($file)) continue;

            $content = file_get_contents($file);
            $shortName = basename($file);

            if (!str_contains($content, 'Auth::requireRole') && !str_contains($content, 'Auth::requireLogin') && !str_contains($content, 'getAuthenticatedResidente')) {
                $missingAuth[] = $shortName;
            }
        }

        if (!empty($missingAuth)) {
            $files = implode(', ', $missingAuth);
            $this->failures[] = "SEGURIDAD: Controladores sin protección de auth: {$files}";
            $this->failed++;
        } else {
            $this->passed++;
        }
    }
}
