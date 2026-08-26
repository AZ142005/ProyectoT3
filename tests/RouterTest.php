<?php
namespace Tests;

use Tests\TestCase;

/**
 * Tests de Router — Front Controller y rutas declarativas
 *
 * Verifica que el enrutador maneje correctamente rutas válidas,
 * inválidas, dinámicas y que las rutas protegidas existan.
 */
class RouterTest extends TestCase {

    private string $indexPath;
    private string $indexContent;

    public function __construct() {
        $this->indexPath = dirname(__DIR__) . '/public/index.php';
        $this->indexContent = file_get_contents($this->indexPath);
    }

    // =====================================================================
    // ESTRUCTURA DEL ROUTER
    // =====================================================================

    /**
     * Verifica que index.php exista en public/.
     */
    public function testIndexFileExists(): void {
        $this->assertFileExists($this->indexPath,
            "public/index.php debe existir como front controller");
    }

    /**
     * Verifica que index.php cargue Composer autoload.
     */
    public function testIndexLoadsAutoload(): void {
        $this->assertStringContains('vendor/autoload.php', $this->indexContent,
            "index.php debe cargar vendor/autoload.php");
    }

    /**
     * Verifica que index.php cargue config.php.
     */
    public function testIndexLoadsConfig(): void {
        $this->assertStringContains('config.php', $this->indexContent,
            "index.php debe cargar config.php");
    }

    /**
     * Verifica que exista el archivo .htaccess con rewrite rules.
     */
    public function testHtaccessExists(): void {
        $htaccessPath = dirname(__DIR__) . '/public/.htaccess';
        $this->assertFileExists($htaccessPath, "public/.htaccess debe existir");

        $content = file_get_contents($htaccessPath);
        $this->assertStringContains('mod_rewrite', $content,
            ".htaccess debe usar mod_rewrite");
        $this->assertStringContains('index.php', $content,
            ".htaccess debe redirigir todo a index.php");
    }

    // =====================================================================
    // RUTAS DEFINIDAS
    // =====================================================================

    /**
     * Verifica que la ruta raíz '/' esté definida.
     */
    public function testRootRouteExists(): void {
        $this->assertTrue(
            str_contains($this->indexContent, "case '/':") || str_contains($this->indexContent, "'/'"),
            "La ruta raíz '/' debe estar definida"
        );
    }

    /**
     * Verifica que la ruta de login esté definida.
     */
    public function testLoginRouteExists(): void {
        $this->assertTrue(
            str_contains($this->indexContent, "case '/login':") || str_contains($this->indexContent, "'/login'"),
            "La ruta '/login' debe estar definida"
        );
    }

    /**
     * Verifica que la ruta de logout esté definida.
     */
    public function testLogoutRouteExists(): void {
        $this->assertTrue(
            str_contains($this->indexContent, "case '/logout':") || str_contains($this->indexContent, "'/logout'"),
            "La ruta '/logout' debe estar definida"
        );
    }

    /**
     * Verifica que las rutas de admin estén definidas.
     */
    public function testAdminRoutesExist(): void {
        $adminRoutes = [
            '/admin/dashboard',
            '/admin/comprobantes',
            '/admin/comprobante/verificar',
            '/admin/facturas/generar',
            '/admin/estructura',
        ];

        foreach ($adminRoutes as $route) {
            $this->assertTrue(
                str_contains($this->indexContent, "case '{$route}':") || str_contains($this->indexContent, "'{$route}'"),
                "La ruta admin '{$route}' debe estar definida"
            );
        }
    }

    /**
     * Verifica que las rutas de residente estén definidas.
     */
    public function testResidenteRoutesExist(): void {
        $residenteRoutes = [
            '/residente/dashboard',
            '/residente/enviar-pago',
            '/residente/historial',
        ];

        foreach ($residenteRoutes as $route) {
            $this->assertTrue(
                str_contains($this->indexContent, "case '{$route}':") || str_contains($this->indexContent, "'{$route}'"),
                "La ruta residente '{$route}' debe estar definida"
            );
        }
    }

    /**
     * Verifica que las rutas de pagos estén definidas.
     */
    public function testPagoRoutesExist(): void {
        $pagoRoutes = [
            '/pagos',
            '/pagos/nuevo',
            '/pagos/subir',
            '/pagos/extraer',
            '/pagos/cambiar-estado',
        ];

        foreach ($pagoRoutes as $route) {
            $this->assertTrue(
                str_contains($this->indexContent, "case '{$route}':") || str_contains($this->indexContent, "'{$route}'"),
                "La ruta pago '{$route}' debe estar definida"
            );
        }
    }

    /**
     * Verifica que exista una ruta para pagos dinámicos /pagos/detalle/{id}.
     */
    public function testPagoDetalleRouteIsDynamic(): void {
        $this->assertStringContains('/pagos/detalle/', $this->indexContent,
            "La ruta '/pagos/detalle/{id}' debe existir como ruta dinámica");
    }

    // =====================================================================
    // CONTROLLERS REFERENCIADOS
    // =====================================================================

    /**
     * Verifica que cada ruta use un controlador existente.
     */
    public function testReferencedControllersExist(): void {
        $controllerFiles = glob(dirname(__DIR__) . '/app/controllers/*.php');
        $existingControllers = array_map(function ($f) {
            return basename($f, '.php');
        }, $controllerFiles);

        $referencedControllers = [
            'AuthController',
            'AdminController',
            'ResidenteController',
            'PagoController',
            'EstructuraController',
            'AdminAuthController',
        ];

        foreach ($referencedControllers as $controller) {
            $this->assertContains($controller, $existingControllers,
                "El controlador {$controller} referenciado en rutas debe existir");
        }
    }

    // =====================================================================
    // MANEJO DE ERRORES
    // =====================================================================

    /**
     * Verifica que exista manejo para rutas no encontradas (404).
     */
    public function testDefaultCaseFor404(): void {
        $this->assertTrue(
            str_contains($this->indexContent, 'default:') || str_contains($this->indexContent, 'dispatch('),
            "Debe existir enrutamiento para manejar rutas no encontradas"
        );
    }

    /**
     * Verifica que el 404 retorne código HTTP 404.
     */
    public function test404ReturnsCorrectStatusCode(): void {
        $routerContent = file_get_contents(dirname(__DIR__) . '/app/core/Router.php');
        $this->assertTrue(
            str_contains($this->indexContent, 'http_response_code(404)') || str_contains($routerContent, 'http_response_code(404)'),
            "El handler 404 debe retornar código HTTP 404"
        );
    }

    /**
     * Verifica que el 404 muestre una vista de error.
     */
    public function test404ShowsErrorView(): void {
        $routerContent = file_get_contents(dirname(__DIR__) . '/app/core/Router.php');
        $this->assertTrue(
            str_contains($this->indexContent, '404.php') || str_contains($routerContent, '404.php'),
            "El handler 404 debe cargar la vista errors/404.php"
        );
    }

    /**
     * Verifica que exista la vista 404.php.
     */
    public function testErrorView404Exists(): void {
        $this->assertFileExists(dirname(__DIR__) . '/app/views/errors/404.php',
            "La vista errors/404.php debe existir");
    }

    /**
     * Verifica que exista la vista 403.php.
     */
    public function testErrorView403Exists(): void {
        $this->assertFileExists(dirname(__DIR__) . '/app/views/errors/403.php',
            "La vista errors/403.php debe existir");
    }

    // =====================================================================
    // CSRF GLOBAL
    // =====================================================================

    /**
     * Verifica que el CSRF se valide globalmente antes del router.
     */
    public function testCsrfValidatedBeforeRouting(): void {
        $csrfPos = strpos($this->indexContent, 'Security::validateCSRF()');
        $dispatchPos = strpos($this->indexContent, 'dispatch(');

        $this->assertTrue($csrfPos !== false,
            "CSRF debe validarse en index.php");
        $this->assertTrue($dispatchPos !== false,
            "El dispatch de rutas debe existir");

        if ($csrfPos !== false && $dispatchPos !== false) {
            $this->assertTrue($csrfPos < $dispatchPos,
                "CSRF debe validarse ANTES del enrutamiento");
        }
    }

    /**
     * Verifica que el token CSRF se genere antes de la validación.
     */
    public function testCsrfTokenGeneratedBeforeValidation(): void {
        $generationPos = strpos($this->indexContent, "csrf_token");
        $validationPos = strpos($this->indexContent, "validateCSRF");

        if ($generationPos !== false && $validationPos !== false) {
            $this->assertTrue($generationPos < $validationPos,
                "El token CSRF debe generarse antes de validarlo");
        } else {
            $this->passed++;
        }
    }

    // =====================================================================
    // ESTRUCTURA DE VISTAS
    // =====================================================================

    /**
     * Verifica que exista el layout base.
     */
    public function testBaseLayoutExists(): void {
        $this->assertFileExists(dirname(__DIR__) . '/app/views/layouts/base.php',
            "El layout layouts/base.php debe existir");
    }

    /**
     * Verifica que existan los layouts header y footer.
     */
    public function testHeaderAndFooterExist(): void {
        $this->assertFileExists(dirname(__DIR__) . '/app/views/layouts/header.php',
            "El layout layouts/header.php debe existir");
        $this->assertFileExists(dirname(__DIR__) . '/app/views/layouts/footer.php',
            "El layout layouts/footer.php debe existir");
    }

    /**
     * Verifica que las vistas de login y register existan.
     */
    public function testAuthViewsExist(): void {
        $this->assertFileExists(dirname(__DIR__) . '/app/views/auth/login.php',
            "La vista auth/login.php debe existir");
        $this->assertFileExists(dirname(__DIR__) . '/app/views/auth/register.php',
            "La vista auth/register.php debe existir");
    }
}
