<?php
namespace Tests;

use Tests\TestCase;

/**
 * Tests de Autenticación por Correo Electrónico
 *
 * Valida el reemplazo de cédula por correo electrónico como credencial principal:
 * - Validación estricta y normalización de correo.
 * - Endpoint y vista de login adaptados a email.
 * - API REST login adaptada a email con respuesta genérica de error.
 * - Formularios de administración con campo de email obligatorio.
 * - Controladores administrativos y scripts de migración.
 */
class AuthEmailTest extends TestCase {

    /**
     * Valida que la función helper validarEmail() funcione correctamente con diversos formatos.
     */
    public function testValidarEmailHelper(): void {
        $validEmails = [
            'usuario@condominio.com',
            'admin.soporte@edificio.gob.ve',
            'residente+piso2@gmail.com',
            'persona_123@sub.dominio.org',
            'cedula@condominio.local'
        ];

        foreach ($validEmails as $email) {
            $this->assertTrue(
                validarEmail($email),
                "El correo '{$email}' debería ser considerado válido."
            );
        }

        $invalidEmails = [
            'invalido',
            'usuario@',
            '@dominio.com',
            'usuario@dominio',
            'usuario @dominio.com',
            'usuario@.com',
            '',
            'usuario@dominio..com'
        ];

        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                validarEmail($email),
                "El correo '{$email}' debería ser considerado inválido."
            );
        }
    }

    /**
     * Valida que la vista de login requiera correo electrónico y no pida cédula como credencial.
     */
    public function testLoginViewRequiresEmailAndNoCedula(): void {
        $loginViewPath = dirname(__DIR__) . '/app/views/auth/login.php';
        $this->assertFileExists($loginViewPath, "La vista de login debe existir.");

        $content = file_get_contents($loginViewPath);

        // Debe contener campo de tipo email y nombre email
        $this->assertStringContains('name="email"', $content, "La vista de login debe contener un input name='email'.");
        $this->assertStringContains('type="email"', $content, "El input de correo debe ser type='email'.");
        $this->assertStringContains('Correo Electrónico', $content, "La vista debe mostrar la etiqueta o texto de Correo Electrónico.");

        // No debe contener input para cédula en el formulario de login
        $this->assertFalse(
            str_contains($content, 'name="cedula"'),
            "La vista de login NO debe tener un input name='cedula'."
        );

        // Debe conservar enlaces requeridos por regresión
        $this->assertStringContains('/pago-directo', $content, "Debe conservar enlace a pago directo.");
        $this->assertStringContains('Pagar sin iniciar sesión', $content, "Debe conservar texto de pago sin iniciar sesión.");
        $this->assertStringContains('/auth/register', $content, "Debe conservar enlace a registro.");
    }

    /**
     * Valida que AuthController implemente autenticación por correo electrónico
     * con mensajes de error genéricos y normalización a minúsculas.
     */
    public function testAuthControllerEmailLoginImplementation(): void {
        $controllerPath = dirname(__DIR__) . '/app/controllers/AuthController.php';
        $this->assertFileExists($controllerPath, "AuthController debe existir.");

        $content = file_get_contents($controllerPath);

        $this->assertStringContains('strtolower(trim($_POST[\'email\']', $content,
            "AuthController debe normalizar el correo a minúsculas y sin espacios.");
        $this->assertStringContains('validarEmail($email)', $content,
            "AuthController debe validar el formato del correo con validarEmail.");
        $this->assertStringContains('getActiveByEmail($email)', $content,
            "AuthController debe consultar usuarios/residentes mediante getActiveByEmail.");
        $this->assertStringContains('Correo electrónico o contraseña incorrectos', $content,
            "AuthController debe emitir mensaje de error genérico ante credenciales inválidas.");
    }

    /**
     * Valida que ApiController acepte payload con email en lugar de cédula.
     */
    public function testApiControllerEmailLoginImplementation(): void {
        $apiControllerPath = dirname(__DIR__) . '/app/controllers/ApiController.php';
        $this->assertFileExists($apiControllerPath, "ApiController debe existir.");

        $content = file_get_contents($apiControllerPath);

        $this->assertStringContains('$input[\'email\']', $content,
            "ApiController debe leer 'email' del payload JSON o POST.");
        $this->assertStringContains('validarEmail($email)', $content,
            "ApiController debe validar el correo con la función validarEmail.");
        $this->assertStringContains('getActiveByEmail($email)', $content,
            "ApiController debe consultar con getActiveByEmail.");
        $this->assertStringContains('Correo electrónico o contraseña incorrectos', $content,
            "ApiController debe retornar error genérico ante fallo de credenciales.");
    }

    /**
     * Valida que la vista de gestión de usuarios en admin marque el correo como obligatorio.
     */
    public function testAdminUsuariosViewEmailRequired(): void {
        $viewPath = dirname(__DIR__) . '/app/views/admin/usuarios/index.php';
        $this->assertFileExists($viewPath, "La vista de usuarios admin debe existir.");

        $content = file_get_contents($viewPath);

        $this->assertStringContains('Correo Electrónico *', $content,
            "La vista de admin debe indicar que el Correo Electrónico es obligatorio con asterisco (*).");
        $this->assertStringContains('id="act_email"', $content,
            "El modal de actualizar datos debe contener el campo act_email.");
        $this->assertStringContains('name="email"', $content,
            "El modal debe enviar el campo email.");
    }

    /**
     * Valida que UsuarioAdminController exija correo electrónico obligatorio y único.
     */
    public function testUsuarioAdminControllerRequiresEmail(): void {
        $controllerPath = dirname(__DIR__) . '/app/controllers/UsuarioAdminController.php';
        $this->assertFileExists($controllerPath, "UsuarioAdminController debe existir.");

        $content = file_get_contents($controllerPath);

        $this->assertStringContains('El correo electrónico es obligatorio', $content,
            "UsuarioAdminController debe validar que el correo no esté vacío.");
        $this->assertStringContains('emailExistsActive($email, $id)', $content,
            "UsuarioAdminController debe validar unicidad de correo en residentes activos.");
    }

    /**
     * Valida que los modelos implementen métodos case-insensitive para búsqueda por email.
     */
    public function testModelsSupportCaseInsensitiveEmailLookup(): void {
        $usuariosModelPath = dirname(__DIR__) . '/app/models/UsuariosModel.php';
        $personasModelPath = dirname(__DIR__) . '/app/models/PersonasModel.php';

        $uContent = file_get_contents($usuariosModelPath);
        $pContent = file_get_contents($personasModelPath);

        $this->assertStringContains('LOWER(email) = LOWER(:email)', $uContent,
            "UsuariosModel debe realizar búsquedas case-insensitive de correo.");
        $this->assertStringContains('LOWER(email) = LOWER(:email)', $pContent,
            "PersonasModel debe realizar búsquedas case-insensitive de correo.");
        $this->assertStringContains('function emailExists', $uContent,
            "UsuariosModel debe tener método emailExists.");
        $this->assertStringContains('function emailExistsActive', $pContent,
            "PersonasModel debe tener método emailExistsActive.");
    }

    /**
     * Valida la existencia y estructura de los scripts de migración.
     */
    public function testMigrationScriptsExist(): void {
        $scriptPath = dirname(__DIR__) . '/scripts/migrate_auth_email.php';
        $sqlPath    = dirname(__DIR__) . '/scripts/migrations_phase15.sql';

        $this->assertFileExists($scriptPath, "El script de migración y backfill de auth email debe existir.");
        $this->assertFileExists($sqlPath, "El archivo SQL de migración fase 15 debe existir.");

        $scriptContent = file_get_contents($scriptPath);
        $this->assertStringContains('condominio.local', $scriptContent,
            "El script de migración debe asignar correos temporales con dominio de respaldo.");
        $this->assertStringContains('uq_usuarios_email', $scriptContent,
            "El script debe gestionar la restricción UNIQUE sobre email en usuarios.");
        $this->assertStringContains('uq_personas_email', $scriptContent,
            "El script debe gestionar la restricción UNIQUE sobre email en personas.");
    }
}
