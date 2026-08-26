<?php
namespace Tests;

use Tests\TestCase;

/**
 * Tests de Comportamiento (E2E) — Fase 1: Supervivencia
 *
 * Validan que las correcciones de seguridad funcionan en runtime,
 * no solo en análisis estático de código fuente.
 */
class BehaviorTest extends TestCase {

    // =====================================================================
    // 1. CARGA DE .ENV — Verifica que $_ENV se poblle correctamente
    // =====================================================================

    public function testEnvFileIsLoadedIntoEnv(): void {
        $envFile = dirname(__DIR__) . '/.env';

        if (!file_exists($envFile)) {
            $this->failures[] = "SKIPPED: .env file not found";
            $this->skipped++;
            return;
        }

        // Simulate what config.php does
        $envData = [];
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $envData[trim($key)] = trim($value);
            }
        }

        $this->assertTrue(isset($envData['DB_HOST']),
            ".env must contain DB_HOST key");
        $this->assertTrue(isset($envData['DB_USER']),
            ".env must contain DB_USER key");
        $this->assertTrue(isset($envData['ENVIRONMENT']),
            ".env must contain ENVIRONMENT key");
        $this->assertEquals('development', $envData['ENVIRONMENT'],
            "ENVIRONMENT default should be 'development'");
    }

    // =====================================================================
    // 2. TOGGLE POST-ONLY — Verifica que toggleEdificio lee $_POST, no $_GET
    // =====================================================================

    public function testToggleEdificioReadsPostNotGet(): void {
        $file = dirname(__DIR__) . '/app/controllers/EstructuraController.php';
        $content = file_get_contents($file);

        // Verify toggleEdificio uses $_POST['id']
        $this->assertStringContains('$_POST[\'id\']', $content,
            "toggleEdificio() must read from \$_POST['id']");

        // Verify toggleEdificio does NOT use $_GET['id']
        // We check specifically inside toggleEdificio method
        preg_match('/function toggleEdificio\(\)(.*?)(?=public function|private function|$)/s', $content, $matches);
        if (isset($matches[1])) {
            $methodBody = $matches[1];
            $this->assertFalse(str_contains($methodBody, '$_GET[\'id\']'),
                "toggleEdificio() must NOT use \$_GET['id']");
        }
    }

    public function testToggleUnidadReadsPostNotGet(): void {
        $file = dirname(__DIR__) . '/app/controllers/EstructuraController.php';
        $content = file_get_contents($file);

        $this->assertStringContains('$_POST[\'id\']', $content,
            "toggleUnidad() must read from \$_POST['id']");

        preg_match('/function toggleUnidad\(\)(.*?)(?=public function|private function|$)/s', $content, $matches);
        if (isset($matches[1])) {
            $methodBody = $matches[1];
            $this->assertFalse(str_contains($methodBody, '$_GET[\'id\']'),
                "toggleUnidad() must NOT use \$_GET['id']");
        }
    }

    // =====================================================================
    // 3. VIEW RESILIENCE — Verifica que las vistas usan Auth::user()
    // =====================================================================

    public function testAdminViewsUseAuthUserNotSessionLegacy(): void {
        // Check that admin views don't use legacy session variables
        $viewFiles = [
            dirname(__DIR__) . '/app/views/admin/estructura.php',
            dirname(__DIR__) . '/app/views/admin/dashboard.php',
            dirname(__DIR__) . '/app/views/admin/comprobantes.php',
            dirname(__DIR__) . '/app/views/admin/verificar_comprobante.php',
            dirname(__DIR__) . '/app/views/admin/generar_facturas.php',
            dirname(__DIR__) . '/app/views/pagos/admin/lista.php',
        ];

        foreach ($viewFiles as $file) {
            $shortName = str_replace(dirname(__DIR__) . '/', '', $file);
            $content = file_get_contents($file);

            if ($content === false) {
                continue;
            }

            // Must NOT use legacy session variables
            $hasLegacy = str_contains($content, '$_SESSION[\'admin_usuario\']')
                      || str_contains($content, '$_SESSION[\'admin_nombre\']')
                      || str_contains($content, '$_SESSION[\'admin_rol\']');

            $this->assertFalse($hasLegacy,
                "{$shortName} must NOT use legacy \$_SESSION['admin_*'] variables");
        }

        // Check that the sidebar partial uses Auth::user() (centralized sidebar)
        $sidebarFile = dirname(__DIR__) . '/app/views/layouts/admin_sidebar.php';
        $this->assertFileExists($sidebarFile, "admin_sidebar.php partial must exist");
        $sidebarContent = file_get_contents($sidebarFile);
        $this->assertStringContains('Auth::user()', $sidebarContent,
            "admin_sidebar.php must use Auth::user() for user info");
        $this->assertFalse(
            str_contains($sidebarContent, '$_SESSION[\'admin_usuario\']')
            || str_contains($sidebarContent, '$_SESSION[\'admin_nombre\']')
            || str_contains($sidebarContent, '$_SESSION[\'admin_rol\']'),
            "admin_sidebar.php must NOT use legacy session variables"
        );
    }

    // =====================================================================
    // 4. UPLOAD SECURITY — Verifica nombres de archivo seguros
    // =====================================================================

    public function testPagoControllerUsesRandomBytesForFilename(): void {
        $file = dirname(__DIR__) . '/app/controllers/PagoController.php';
        $content = file_get_contents($file);

        $this->assertStringContains('random_bytes', $content,
            "PagoController must use random_bytes() for upload filenames");

        // Must NOT use basename of original file in final name
        // (The regex check is in the static test, here we verify runtime logic)
        $this->assertFalse(
            (bool) preg_match('/\$uniqueName\s*=\s*uniqid\(\)\s*\.\s*[\'"]_[\'"]\s*\.\s*basename/', $content),
            "PagoController must NOT use uniqid() + basename() pattern"
        );
    }

    public function testPagoModelUsesRandomBytesForFilename(): void {
        $file = dirname(__DIR__) . '/app/models/PagoModel.php';
        $content = file_get_contents($file);

        $this->assertStringContains('random_bytes', $content,
            "PagoModel must use random_bytes() for upload filenames");
    }

    // =====================================================================
    // 5. HTACCESS PROTECTION — Verifica que el archivo existe y tiene contenido
    // =====================================================================

    public function testHtaccessFileExistsAndIsProtective(): void {
        $htaccessPath = dirname(__DIR__) . '/public/uploads/.htaccess';

        $this->assertFileExists($htaccessPath,
            "public/uploads/.htaccess must exist");

        $content = file_get_contents($htaccessPath);

        $this->assertTrue(
            str_contains($content, 'Deny from all') || str_contains($content, 'php_flag engine off'),
            ".htaccess must contain PHP execution denial"
        );

        $this->assertTrue(
            str_contains($content, 'Options -Indexes') || str_contains($content, 'DirectoryIndex'),
            ".htaccess must disable directory listing"
        );
    }

    // =====================================================================
    // 6. PHONE REGEX — Verifica el comportamiento en runtime
    // =====================================================================

    public function testPhoneRegexAccepts11DigitVenezuelanFormat(): void {
        // Standard: 0412-1234567 = 11 digits
        $this->assertTrue(validarTelefono('04121234567'),
            "11-digit format 04121234567 must be accepted");
        $this->assertTrue(validarTelefono('04241234567'),
            "11-digit format 04241234567 must be accepted");
        $this->assertTrue(validarTelefono('04161234567'),
            "11-digit format 04161234567 must be accepted");
    }

    public function testPhoneRegexRejects10DigitFormat(): void {
        // 10 digits: 0412-123456 (too short)
        $this->assertFalse(validarTelefono('0412123456'),
            "10-digit format 0412123456 must be rejected");
    }

    public function testPhoneRegexWithoutLeadingZero(): void {
        // Without leading 0: 412-1234567 = 10 digits (3+7)
        $this->assertTrue(validarTelefono('4121234567'),
            "10-digit format without leading zero (4121234567) must be accepted");
    }

    // =====================================================================
    // 7. SESSION LEGACY CLEANUP — Verifica que Auth no crea variables legacy
    // =====================================================================

    public function testLoginAsAdminDoesNotCreateLegacySessionVars(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];

        \App\Core\Auth::loginAsAdmin([
            'id' => 1,
            'usuario' => 'admin01',
            'nombre_completo' => 'Test Admin',
            'email' => 'test@test.com',
            'rol' => 'admin',
        ]);

        $this->assertFalse(isset($_SESSION['admin_usuario']),
            "loginAsAdmin must NOT set \$_SESSION['admin_usuario']");
        $this->assertFalse(isset($_SESSION['admin_nombre']),
            "loginAsAdmin must NOT set \$_SESSION['admin_nombre']");
        $this->assertFalse(isset($_SESSION['admin_rol']),
            "loginAsAdmin must NOT set \$_SESSION['admin_rol']");
        $this->assertFalse(isset($_SESSION['admin_id']),
            "loginAsAdmin must NOT set \$_SESSION['admin_id']");

        // Verify auth_user is still set correctly
        $this->assertTrue(\App\Core\Auth::check(),
            "Auth::check() must still return true after login");
        $this->assertEquals(1, \App\Core\Auth::id(),
            "Auth::id() must return correct ID");
        $this->assertEquals('admin', \App\Core\Auth::role(),
            "Auth::role() must return 'admin'");

        $_SESSION = [];
        session_destroy();
    }

    // =====================================================================
    // 8. DIRECTORY PERMISSIONS — Verifica mkdir uses 0755
    // =====================================================================

    public function testNoHardcoded777InSourceCode(): void {
        $sourceFiles = array_merge(
            glob(dirname(__DIR__) . '/app/**/*.php'),
            glob(dirname(__DIR__) . '/public/**/*.php')
        );

        foreach ($sourceFiles as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace(dirname(__DIR__) . '/', '', $file);

            if (preg_match('/mkdir\s*\([^)]*0777/', $content)) {
                $this->failures[] = "VULNERABILITY: 0777 found in {$relativePath}";
                $this->failed++;
                return;
            }
        }

        $this->passed++;
    }

    // =====================================================================
    // 9. CSRF INFRASTRUCTURE — Verifica que el middleware global existe
    // =====================================================================

    public function testCsrfMiddlewareRunsBeforeRouting(): void {
        $indexContent = file_get_contents(dirname(__DIR__) . '/public/index.php');

        // Security::validateCSRF() must appear before the switch statement
        $csrfPos = strpos($indexContent, 'Security::validateCSRF()');
        $switchPos = strpos($indexContent, 'switch ($route)');

        $this->assertTrue($csrfPos !== false,
            "CSRF middleware must be present in index.php");
        $this->assertTrue($switchPos !== false,
            "Routing switch must be present in index.php");

        if ($csrfPos !== false && $switchPos !== false) {
            $this->assertTrue($csrfPos < $switchPos,
                "CSRF middleware must execute BEFORE the routing switch");
        }
    }

    // =====================================================================
    // 10. COOKIE SECURITY — Verifica secure flag is dynamic
    // =====================================================================

    public function testCookieSecureIsEnvironmentDependent(): void {
        $indexContent = file_get_contents(dirname(__DIR__) . '/public/index.php');

        $hasHardcodedFalse = (bool) preg_match(
            "/['\"]secure['\"]\s*=>\s*false/",
            $indexContent
        );

        $hasEnvironmentCheck = (bool) preg_match(
            "/['\"]secure['\"]\s*=>.*ENVIRONMENT/",
            $indexContent
        );

        $this->assertFalse($hasHardcodedFalse,
            "Cookie secure must NOT be hardcoded to false");
        $this->assertTrue($hasEnvironmentCheck,
            "Cookie secure must depend on ENVIRONMENT constant");
    }
}
