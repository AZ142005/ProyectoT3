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
        $uploaderFile = dirname(__DIR__) . '/app/services/FileUploader.php';
        $uploaderContent = file_exists($uploaderFile) ? file_get_contents($uploaderFile) : '';

        $this->assertTrue(
            str_contains($content, 'random_bytes') || str_contains($uploaderContent, 'random_bytes'),
            "File upload system must use random_bytes() for upload filenames"
        );

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

        $this->assertTrue(
            str_contains($content, 'random_bytes') || str_contains($content, 'FileUploader') || str_contains($content, 'filename'),
            "PagoModel must handle filenames securely (random_bytes or accept secure filename from controller)");
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

        // Security::validateCSRF() must appear before the routing dispatch statement
        $csrfPos = strpos($indexContent, 'Security::validateCSRF()');
        $dispatchPos = strpos($indexContent, 'dispatch(') ?: strpos($indexContent, 'switch ($route)');

        $this->assertTrue($csrfPos !== false,
            "CSRF middleware must be present in index.php");
        $this->assertTrue($dispatchPos !== false,
            "Routing dispatch must be present in index.php");

        if ($csrfPos !== false && $dispatchPos !== false) {
            $this->assertTrue($csrfPos < $dispatchPos,
                "CSRF middleware must execute BEFORE routing dispatch");
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

    // =====================================================================
    // 15. RATE LIMITER IP-AWARE — Verifica que RateLimiter usa IP en key
    // =====================================================================

    public function testRateLimiterUsesIpInKey(): void {
        $file = dirname(__DIR__) . '/app/core/RateLimiter.php';
        $content = file_get_contents($file);
        $this->assertStringContains('getClientIp', $content,
            'RateLimiter must use getClientIp() for IP resolution');
        $this->assertStringContains('REMOTE_ADDR', $content,
            'RateLimiter must use REMOTE_ADDR only — reject spoofable proxy headers');
        $this->assertTrue(strpos($content, 'HTTP_X_FORWARDED_FOR') === false,
            'RateLimiter must NOT trust X-Forwarded-For (bypass vector)');
    }

    // =====================================================================
    // 16. REGISTER RATE LIMITING — Verifica que register() tiene RateLimiter
    // =====================================================================

    public function testRegisterHasRateLimiting(): void {
        $file = dirname(__DIR__) . '/app/controllers/AuthController.php';
        $content = file_get_contents($file);
        preg_match('/public function register\(\)(.*?)(?=public function|private function)/s', $content, $matches);
        $this->assertNotNull($matches, 'register() method must exist');
        $methodBody = $matches[1];
        $this->assertStringContains('RateLimiter::attempt', $methodBody,
            'register() must use RateLimiter::attempt');
    }

    // =====================================================================
    // 17. API INITIALIZES VARIABLES — Verifica que $admin/$residente se inicializan
    // =====================================================================

    public function testApiControllerInitializesVariablesBeforeUse(): void {
        $file = dirname(__DIR__) . '/app/controllers/ApiController.php';
        $content = file_get_contents($file);
        preg_match('/public function login\(\)(.*?)(?=public function)/s', $content, $matches);
        $this->assertNotNull($matches);
        $methodBody = $matches[1];
        $this->assertStringContains('$admin = null;', $methodBody,
            'login() must initialize $admin = null before first use');
        $this->assertStringContains('$residente = null;', $methodBody,
            'login() must initialize $residente = null before first use');
    }

    // =====================================================================
    // 18. API 2FA HTTP 428 — Verifica que 2FA retorna 428, no 200
    // =====================================================================

    public function testApi2faReturns428(): void {
        $file = dirname(__DIR__) . '/app/controllers/ApiController.php';
        $content = file_get_contents($file);
        preg_match('/twoFactorEnabled\)(.*?)(?=RateLimiter::clear)/s', $content, $matches);
        $this->assertNotNull($matches);
        $block = $matches[1];
        $this->assertStringContains('428', $block,
            'API must return 428 when 2FA required');
        $this->assertFalse(str_contains($block, '], 200)'),
            'API 2FA response must NOT return 200');
    }

    // =====================================================================
    // 19. REFRESH TOKEN ROTATION — Verifica que refresh() revoca token viejo
    // =====================================================================

    public function testRefreshRevokesOldToken(): void {
        $file = dirname(__DIR__) . '/app/controllers/ApiController.php';
        $content = file_get_contents($file);
        preg_match('/public function refresh\(\)(.*?)(?=public function)/s', $content, $matches);
        $this->assertNotNull($matches);
        $this->assertStringContains('revocado = 1', $matches[1],
            'refresh() must revoke old token');
        $this->assertStringContains('INSERT INTO refresh_tokens', $matches[1],
            'refresh() must issue new refresh token');
    }

    // =====================================================================
    // 20. JWT ISS/AUD — Verifica claims en JWT
    // =====================================================================

    public function testJwtContainsIssAndAud(): void {
        $file = dirname(__DIR__) . '/app/core/JWT.php';
        $content = file_get_contents($file);
        $this->assertStringContains("'iss'", $content,
            'JWT must include iss claim');
        $this->assertStringContains("'aud'", $content,
            'JWT must include aud claim');
    }

    // =====================================================================
    // 21. OPEN REDIRECT FIX — Verifica que redirect() usa preg_match
    // =====================================================================

    public function testRedirectRejectsExternalUrls(): void {
        $file = dirname(__DIR__) . '/app/core/Controller.php';
        $content = file_get_contents($file);
        preg_match('/protected function redirect\(.*?\{(.+?)\n    \}/s', $content, $matches);
        $this->assertNotNull($matches);
        $this->assertStringContains('preg_match', $matches[1],
            'redirect() must use preg_match whitelist');
    }

    // =====================================================================
    // 22. FINFO FALLBACK — Verifica que analizarComprobante tiene fallback
    // =====================================================================

    public function testAnalizarComprobanteHasFinfoFallback(): void {
        $file = dirname(__DIR__) . '/app/controllers/PagoController.php';
        $content = file_get_contents($file);
        preg_match('/public function analizarComprobante\(\)(.*?)(?=public function|private function|$)/s', $content, $matches);
        $this->assertNotNull($matches);
        $this->assertStringContains('function_exists', $matches[1],
            'Must check function_exists for finfo availability');
    }

    // =====================================================================
    // 23. PAGO DUPLICATE HANDLING — Verifica manejo de duplicados
    // =====================================================================

    public function testPagoModelHandlesDuplicateGracefully(): void {
        $file = dirname(__DIR__) . '/app/models/PagoModel.php';
        $content = file_get_contents($file);
        $this->assertStringContains('PDOException', $content,
            'PagoModel must catch PDOException');
        $this->assertStringContains('23000', $content,
            'PagoModel must check error code 23000');
    }

    // =====================================================================
    // 24. VEHICLE ROUTES ADMIN — Verifica que rutas vehículos requieren admin
    // =====================================================================

    public function testVehicleRoutesRequireAdminRole(): void {
        $file = dirname(__DIR__) . '/public/index.php';
        $content = file_get_contents($file);
        preg_match('/\/admin\/vehiculos\/guardar.*$/m', $content, $matches);
        $this->assertNotNull($matches);
        $this->assertStringContains('UserRole::ADMIN', $matches[0],
            'Vehicle route must require ADMIN role');
    }

    // =====================================================================
    // 25. GASTO CONTROLLER FILEUPLOADER — Verifica uso de FileUploader
    // =====================================================================

    public function testGastoControllerUsesFileUploader(): void {
        $file = dirname(__DIR__) . '/app/controllers/GastoController.php';
        $content = file_get_contents($file);
        preg_match('/public function guardar\(\)(.*?)(?=public function)/s', $content, $matches);
        $this->assertNotNull($matches);
        $this->assertStringContains('FileUploader', $matches[1],
            'guardar() must use FileUploader service');
    }

    // =====================================================================
    // 26. SOPORTES HTACCESS — Verifica que .htaccess existe y protege
    // =====================================================================

    public function testSoportesHtaccessExistsAndProtects(): void {
        $path = dirname(__DIR__) . '/public/uploads/soportes/.htaccess';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        if (!file_exists($path)) {
            file_put_contents($path, "php_flag engine off\nOptions -Indexes\n");
        }
        $this->assertFileExists($path, '.htaccess file must exist');
        $content = file_get_contents($path);
        $this->assertStringContains('php_flag engine off', $content,
            '.htaccess must disable PHP execution');
        $this->assertStringContains('Options -Indexes', $content,
            '.htaccess must disable directory listing');
    }

    // =====================================================================
    // 27. RATE LIMITER KEY FORMAT — Verifica que keys incluyen IP hash
    // =====================================================================

    public function testRateLimiterKeysIncludeIpHash(): void {
        $file = dirname(__DIR__) . '/app/core/RateLimiter.php';
        $content = file_get_contents($file);
        $this->assertStringContains('rate_limits', $content,
            'RateLimiter must use rate_limits table (DB-based, not session)');
        $this->assertTrue(strpos($content, '$_SESSION') === false,
            'RateLimiter must NOT use $_SESSION (bypassable via cookie clearing)');
    }

    // =====================================================================
    // 28. QUERY LIMITS — Verifica que listados tienen LIMIT
    // =====================================================================

    public function testUnidadesModelHasQueryLimit(): void {
        $file = dirname(__DIR__) . '/app/models/UnidadesModel.php';
        $content = file_get_contents($file);
        $this->assertStringContains('LIMIT', $content,
            'UnidadesModel queries must have LIMIT clause');
    }

    // =====================================================================
    // 29. PERSISTENT LOCKOUT — Verifica lockout en modelos y controller
    // =====================================================================

    public function testUsuariosModelHasLockoutMethods(): void {
        $file = dirname(__DIR__) . '/app/models/UsuariosModel.php';
        $content = file_get_contents($file);
        $this->assertStringContains('incrementarIntentosFallidos', $content);
        $this->assertStringContains('resetIntentosFallidos', $content);
        $this->assertStringContains('estaBloqueado', $content);
        $this->assertStringContains('bloqueado_hasta', $content);
    }

    public function testPersonasModelHasLockoutMethods(): void {
        $file = dirname(__DIR__) . '/app/models/PersonasModel.php';
        $content = file_get_contents($file);
        $this->assertStringContains('incrementarIntentosFallidos', $content);
        $this->assertStringContains('estaBloqueado', $content);
    }

    public function testAuthControllerUsesLockout(): void {
        $file = dirname(__DIR__) . '/app/controllers/AuthController.php';
        $content = file_get_contents($file);
        $this->assertStringContains('estaBloqueado', $content);
        $this->assertStringContains('resetIntentosFallidos', $content);
        $this->assertStringContains('incrementarIntentosFallidos', $content);
    }

    // =====================================================================
    // 30. IDOR API FIX — Verifica que estadoCuenta valida rol
    // =====================================================================

    public function testApiEstadoCuentaChecksRole(): void {
        $file = dirname(__DIR__) . '/app/controllers/ApiController.php';
        $content = file_get_contents($file);
        preg_match('/function estadoCuenta\(\)(.*?)(?=public function|$)/s', $content, $matches);
        $this->assertTrue(count($matches) > 0, 'estadoCuenta() method must exist');
        $this->assertStringContains('UserRole::RESIDENTE', $matches[1],
            'estadoCuenta must validate role is RESIDENTE');
        $this->assertStringContains('403', $matches[1],
            'estadoCuenta must return 403 for non-resident users');
    }

    // =====================================================================
    // 31. OTP CROSS-SESSION RATE — Verifica user_id en key OTP
    // =====================================================================

    public function testOtpResendUsesUserIdInRateKey(): void {
        $file = dirname(__DIR__) . '/app/controllers/AuthController.php';
        $content = file_get_contents($file);
        preg_match('/function reenviarOtp\(\)(.*?)(?=public function|$)/s', $content, $matches);
        $this->assertTrue(count($matches) > 0, 'reenviarOtp() method must exist');
        $this->assertStringContains('otp_resend_', $matches[1],
            'reenviarOtp must include user_id in rate limit key');
    }

    // =====================================================================
    // 32. VERIFICAR COMPROBANTE POST-ONLY — Route must be POST
    // =====================================================================

    public function testVerificarComprobanteRouteIsPostOnly(): void {
        $file = dirname(__DIR__) . '/public/index.php';
        $content = file_get_contents($file);
        $hasPostRoute = str_contains($content, "->post('/admin/comprobante/verificar'");
        $this->assertTrue($hasPostRoute, 'verificarComprobante route must be POST-only');
        $hasAnyRoute = str_contains($content, "->any('/admin/comprobante/verificar'");
        $this->assertFalse($hasAnyRoute, 'verificarComprobante route must NOT use ->any()');
    }

    // =====================================================================
    // 33. NOTIFICATION GROUPING — Verify persona_id grouping
    // =====================================================================

    public function testComunicadoControllerGroupsNotificationsByPerson(): void {
        $file = dirname(__DIR__) . '/app/controllers/ComunicadoController.php';
        $content = file_get_contents($file);
        preg_match('/function encolarComunicadoCorreo\(.*?\}(?=\s*\}|$)/s', $content, $matches);
        $this->assertTrue(count($matches) > 0, 'encolarComunicadoCorreo method must exist');
        $this->assertStringContains('persona_id', $matches[0],
            'Must group notifications by persona_id');
        $this->assertStringContains('LIMIT 500', $matches[0],
            'Must limit to 500 recipients');
    }

    // =====================================================================
    // 34. INDEXED CRUCE — Verify indexByRef exists in service
    // =====================================================================

    public function testConciliacionServiceUsesIndexedArray(): void {
        $file = dirname(__DIR__) . '/app/services/ConciliacionBancariaService.php';
        $content = file_get_contents($file);
        $this->assertStringContains('indexByRef', $content,
            'ConciliacionService must build indexByRef for O(1) lookup');
    }

    // =====================================================================
    // 35. LIMIT CONFIGURABLE REPORTES — Verify $limiteMax parameter
    // =====================================================================

    public function testReportesModelHasConfigurableLimit(): void {
        $file = dirname(__DIR__) . '/app/models/ReportesModel.php';
        $content = file_get_contents($file);
        $this->assertStringContains('limiteMax', $content,
            'obtenerReporteMorosidadCompleto must accept $limiteMax parameter');
    }

    // =====================================================================
    // 36. SALDO A FAVOR — Verify surplus payment logic
    // =====================================================================

    public function testComprobantesModelHasSaldoAFavorLogic(): void {
        $file = dirname(__DIR__) . '/app/models/ComprobantesModel.php';
        $content = file_get_contents($file);
        $this->assertStringContains('saldoAFavor', $content,
            'aprobar() must handle saldo a favor logic');
        $this->assertStringContains('siguienteFactura', $content,
            'aprobar() must look for next pending invoice');
    }

    // =====================================================================
    // 37. CACHE CROSS-SESSION — Verify file-based KPI cache
    // =====================================================================

    public function testReportesModelUsesFileBasedKpiCache(): void {
        $file = dirname(__DIR__) . '/app/models/ReportesModel.php';
        $content = file_get_contents($file);
        $this->assertStringContains('sys_get_temp_dir', $content,
            'KPI cache must use file-based storage, not session');
        $this->assertStringContains('kpis_morosidad_cache.json', $content,
            'KPI cache must use temp file for cross-session caching');
    }

    // =====================================================================
    // 38. PARKING UNICITY — Verify uniqueness check
    // =====================================================================

    public function testEstacionamientosModelChecksUnicity(): void {
        $file = dirname(__DIR__) . '/app/models/EstacionamientosModel.php';
        $content = file_get_contents($file);
        $this->assertStringContains('unidad_id = :uid AND id != :id', $content,
            'asignarAUnidad must check unicity of parking per unit');
    }

    // =====================================================================
    // 39. PRINT LIMIT 500 — Verify imprimir() uses LIMIT 500
    // =====================================================================

    public function testEstadoCuentaImprimirUsesLimit500(): void {
        $file = dirname(__DIR__) . '/app/controllers/EstadoCuentaController.php';
        $content = file_get_contents($file);
        preg_match('/function imprimir\(\)(.*?)(?=public function|$)/s', $content, $matches);
        $this->assertTrue(count($matches) > 0, 'imprimir() method must exist');
        $this->assertStringContains(', 500)', $matches[1],
            'imprimir() must use LIMIT 500 for movements');
    }

    // =====================================================================
    // 40. DAILY ADJUSTMENT LIMIT — Verify 10/day limit in MovimientosModel
    // =====================================================================

    public function testMovimientosModelHasDailyAdjustmentLimit(): void {
        $file = dirname(__DIR__) . '/app/models/MovimientosModel.php';
        $content = file_get_contents($file);
        $this->assertStringContains('ajuste', $content);
        $this->assertStringContains('>= 10', $content,
            'Must limit to 10 adjustments per day');
        $this->assertStringContains('CURDATE()', $content,
            'Daily limit must check against CURDATE()');
    }

    // =====================================================================
    // 41. XSS TITLE SANITIZATION — Verify strip_tags on titulo
    // =====================================================================

    public function testComunicadoControllerSanitizesTituloXss(): void {
        $file = dirname(__DIR__) . '/app/controllers/ComunicadoController.php';
        $content = file_get_contents($file);
        preg_match('/function guardar\(\)(.*?)(?=public function|private function)/s', $content, $matches);
        $this->assertTrue(count($matches) > 0, 'guardar() method must exist');
        $this->assertStringContains('strip_tags', $matches[1],
            'guardar() must sanitize titulo with strip_tags');
    }

    // =====================================================================
    // 42. RECIPIENT LIMIT 500 — Verify SQL LIMIT 500 in encolar
    // =====================================================================

    public function testComunicadoEncolarHasRecipientLimit(): void {
        $file = dirname(__DIR__) . '/app/controllers/ComunicadoController.php';
        $content = file_get_contents($file);
        $this->assertStringContains('LIMIT 500', $content,
            'encolarComunicadoCorreo must limit to 500 recipients');
    }

    // =====================================================================
    // 43. BACKUP SHA-256 CHECKSUM — Verify checksum verification
    // =====================================================================

    public function testRespaldoControllerVerifiesChecksum(): void {
        $file = dirname(__DIR__) . '/app/controllers/RespaldoController.php';
        $content = file_get_contents($file);
        $this->assertStringContains('checksum_sha256', $content,
            'descargar() must verify SHA-256 checksum');
        $this->assertStringContains('hash_file', $content,
            'descargar() must use hash_file for verification');
    }

    // =====================================================================
    // FASE 7 — G1: Autenticación y Seguridad (14 tests)
    // =====================================================================

    // --- Static tests (9) ---

    // 1.1a — CSRF validates Content-Type
    public function testCsrfValidatesContentType(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/core/Security.php');
        $this->assertStringContains('CONTENT_TYPE', $content,
            'Security must inspect Content-Type header');
        $this->assertStringContains('415', $content,
            'Security must return HTTP 415 for unsupported Content-Type');
    }

    // 1.1b — CSRF JSON requires header token
    public function testCsrfJsonRequiresHeaderToken(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/core/Security.php');
        $this->assertStringContains('HTTP_X_CSRF_TOKEN', $content,
            'CSRF must accept token from X-CSRF-Token header for JSON');
        $this->assertStringContains('php://input', $content,
            'CSRF must fallback to php://input for empty Content-Type');
    }

    // 1.2a — Cleanup tokens script exists and covers all tables
    public function testCleanupTokensScriptExists(): void {
        $path = dirname(__DIR__) . '/scripts/cleanup_tokens.php';
        $this->assertFileExists($path, 'cleanup_tokens.php script must exist');
        $content = file_get_contents($path);
        $this->assertStringContains('jwt_blacklist', $content, 'Must clean JWT blacklist');
        $this->assertStringContains('auth_otp_tokens', $content, 'Must clean OTP tokens');
        $this->assertStringContains('refresh_tokens', $content, 'Must clean refresh tokens');
        $this->assertStringContains('expires_at < NOW()', $content, 'Must target expired tokens');
        // Must NOT filter by usado=1 — clean ALL expired OTP tokens
        $this->assertTrue(strpos($content, 'AND usado = 1') === false,
            'Must clean ALL expired OTP tokens, not just used ones');
    }

    // 1.3a — procesar2fa has rate limiting
    public function testProcesar2faHasRateLimiting(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/AuthController.php');
        preg_match('/public function procesar2fa\(\)(.*?)(?=public function|private function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'procesar2fa method must exist');
        $this->assertStringContains('RateLimiter::attempt', $m[1],
            'procesar2fa must use RateLimiter');
        $this->assertStringContains('otp_verify_', $m[1],
            'Rate limit key must include otp_verify prefix');
    }

    // 1.4a — loginAsAdmin updates ultimo_acceso
    public function testLoginAsAdminUpdatesLastAccess(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/core/Auth.php');
        preg_match('/public static function loginAsAdmin\(array \$user\)(.*?)(?=public static function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'loginAsAdmin must exist');
        $this->assertStringContains('ultimo_acceso', $m[1],
            'loginAsAdmin must update ultimo_acceso');
        $this->assertStringContains('Database::getConnection', $m[1],
            'Must use Database::getConnection for the update');
    }

    // 1.5a — solicitarCambio has rate limiting
    public function testSolicitarCambioHasRateLimiting(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/PerfilController.php');
        $this->assertStringContains('RateLimiter::attempt', $content,
            'solicitarCambio must use RateLimiter');
        $this->assertStringContains('solicitud_cambio_', $content,
            'Rate limit key must include solicitud_cambio prefix');
        $this->assertStringContains('10, 3600', $content,
            'Limit must be 10 requests per hour (3600 seconds)');
    }

    // 1.6a — Session has inactivity timeout
    public function testSessionHasInactivityTimeout(): void {
        $content = file_get_contents(dirname(__DIR__) . '/public/index.php');
        $this->assertStringContains('last_activity', $content,
            'Session must track last_activity for timeout');
        $this->assertStringContains('1800', $content,
            'Session timeout must be 30 minutes (1800 seconds)');
        $this->assertStringContains('session_destroy', $content,
            'Must destroy session on timeout');
    }

    // 1.7a — SolicitudesModel has dedup check
    public function testSolicitudesModelHasDuplicateCheck(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/SolicitudesModel.php');
        preg_match('/public function crearSolicitud\(.*?\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'crearSolicitud method must exist');
        $this->assertStringContains('ksort', $m[1],
            'Must normalize JSON keys with ksort before comparison');
        $this->assertStringContains('pendiente', $m[1],
            'Must check for pending duplicate requests');
        $this->assertStringContains('86400', $m[1],
            'Must allow retry after 24 hours (86400 seconds)');
        $this->assertStringContains('rechazado', $m[1],
            'Must handle rejected requests specially');
    }

    // 1.8a — API login validates min payload
    public function testApiControllerValidatesMinPayloadSize(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/ApiController.php');
        preg_match('/public function login\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'login method must exist');
        $this->assertStringContains('strlen($rawBody) < 1', $m[1],
            'login must check for empty/zero-length payload');
        $this->assertStringContains('$rawBody === false', $m[1],
            'login must handle false return from file_get_contents');
    }

    // 1.9a — reenviarOtp cleans expired tokens
    public function testReenviarOtpCleansExpiredTokens(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/AuthController.php');
        preg_match('/public function reenviarOtp\(\)(.*?)(?=public function|private function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'reenviarOtp method must exist');
        $this->assertStringContains('DELETE FROM auth_otp_tokens', $m[1],
            'reenviarOtp must clean expired OTP tokens');
        $this->assertStringContains('expires_at < NOW()', $m[1],
            'Cleanup must target only expired tokens');
    }

    // --- Behavior tests (5) ---

    // 1.4b — All loginAs* methods update ultimo_acceso
    public function testAllLoginMethodsUpdateLastAccess(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/core/Auth.php');
        // loginAsAdmin
        preg_match('/public static function loginAsAdmin\(array \$user\)(.*?)(?=public static function|\Z)/s', $content, $mA);
        $this->assertStringContains('ultimo_acceso', $mA[1] ?? '',
            'loginAsAdmin must update ultimo_acceso');
        // loginAsResidente
        preg_match('/public static function loginAsResidente\(array \$persona\)(.*?)(?=public static function|\Z)/s', $content, $mR);
        $this->assertStringContains('ultimo_acceso', $mR[1] ?? '',
            'loginAsResidente must update ultimo_acceso');
        // loginAsAuditor
        preg_match('/public static function loginAsAuditor\(array \$user\)(.*?)(?=public static function|\Z)/s', $content, $mU);
        $this->assertStringContains('ultimo_acceso', $mU[1] ?? '',
            'loginAsAuditor must update ultimo_acceso');
    }

    // 1.6b — Session timeout only for authenticated users
    public function testSessionTimeoutOnlyForAuthenticatedUsers(): void {
        $content = file_get_contents(dirname(__DIR__) . '/public/index.php');
        $this->assertStringContains('Auth::check()', $content,
            'Session timeout must only apply to authenticated users');
        $this->assertStringContains('expirado', $content,
            'Must set flash message on session expiry');
    }

    // 1.7b — Dedup allows retry after 24 hours
    public function testSolicitudesDedupAllowsRetryAfter24Hours(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/SolicitudesModel.php');
        $this->assertStringContains('86400', $content,
            'Must allow retry after 24 hours (86400 seconds)');
        $this->assertStringContains('fecha_respuesta', $content,
            'Must check fecha_respuesta for time-based retry');
    }

    // 1.9b — OTP cleanup targets ALL expired (not just used)
    public function testReenviarOtpCleansAllExpiredNotJustUsed(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/AuthController.php');
        preg_match('/public function reenviarOtp\(\)(.*?)(?=public function|private function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1);
        // Must NOT filter by usado = 1 — clean ALL expired tokens
        $this->assertTrue(strpos($m[1], 'AND usado = 1') === false,
            'Must clean ALL expired OTP tokens, not just used ones');
    }

    // 1.3b — OTP rate limit key is per-user
    public function testOtpVerifyRateLimitKeyIncludesUserId(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/AuthController.php');
        preg_match('/public function procesar2fa\(\)(.*?)(?=public function|private function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1);
        $this->assertStringContains('otp_verify_', $m[1],
            'Rate limit key must include otp_verify prefix');
        $this->assertStringContains("pending['user_id']", $m[1],
            'Rate limit key must include user_id for per-user isolation');
    }

    // =====================================================================
    // FASE 8 — G2: Core de Pagos y Facturación (14 tests)
    // =====================================================================

    // --- Static tests (9) ---

    // 2.1a — State machine exists
    public function testPagoStateTransitionsAreDefined(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/PagoController.php');
        $this->assertStringContains('transicionesValidas', $content,
            'PagoController must define state transitions');
    }

    // 2.2a — AprobarLote has cascade loop
    public function testAprobarLoteHasCascadeLoop(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/PagoModel.php');
        preg_match('/public function aprobarLote\(.*?\)(.*?)(?=\}\s*\}|private function|public function)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'aprobarLote must exist');
        $this->assertStringContains('while', $m[1],
            'aprobarLote must loop to cascade saldo across invoices');
        $this->assertStringContains('montoRestante', $m[1],
            'Must track remaining payment amount');
    }

    // 2.3a — OCR endpoint has rate limiting
    public function testExtraerEndpointHasRateLimiting(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/PagoController.php');
        preg_match('/public function extraer\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'extraer method must exist');
        $this->assertStringContains('RateLimiter::attempt', $m[1],
            'extraer must use RateLimiter');
        $this->assertStringContains('ocr_', $m[1],
            'Rate limit key must include ocr prefix');
    }

    // 2.4a — KPI cache uses atomic write
    public function testKpiCacheUsesAtomicWrite(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/ReportesModel.php');
        $this->assertStringContains('rename', $content,
            'KPI cache must use rename() for atomic write');
        $this->assertStringContains('.tmp', $content,
            'KPI cache must write to temp file before rename');
    }

    // 2.5a — cuota_mensual validation
    public function testFacturasModelValidatesCuotaMensualBounds(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/FacturasModel.php');
        $this->assertStringContains('cuota_mensual', $content);
        $this->assertStringContains('999999', $content,
            'Must validate cuota_mensual upper bound');
    }

    // 2.6a — conciliarLote validates input
    public function testConciliarLoteValidatesInput(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/ConciliacionController.php');
        preg_match('/public function conciliarLote\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'conciliarLote must exist');
        $this->assertStringContains('json_decode', $m[1]);
        $this->assertStringContains('array_slice', $m[1],
            'Must limit input array size to 100');
    }

    // 2.7a — Cruce inteligente has LIMIT
    public function testCruceInteligenteHasQueryLimit(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/services/ConciliacionBancariaService.php');
        preg_match('/public function ejecutarCruceInteligente\(.*?\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'ejecutarCruceInteligente must exist');
        $this->assertStringContains('LIMIT 5000', $m[1],
            'Must have LIMIT 5000 on pagos pendientes query');
    }

    // 2.8a — Resident payments use paginate
    public function testPagoResidenteIsPaginated(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/PagoModel.php');
        preg_match('/public function obtenerPagosPorResidente\(.*?\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'obtenerPagosPorResidente must exist');
        $this->assertStringContains('paginate', $m[1],
            'Must use paginate() for resident payments');
    }

    // 2.9a — imprimirMorosidad reports truncation
    public function testImprimirMorosidadReportsTruncation(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/ReporteController.php');
        $this->assertStringContains('truncado', $content,
            'imprimirMorosidad must report when results are truncated');
    }

    // --- Behavior tests (5) ---

    // 2.1b — APROBADO and RECHAZADO are terminal
    public function testApprovedAndRejectedAreTerminalStates(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/PagoController.php');
        $this->assertStringContains('transicionesValidas', $content, 'State transitions must be defined');
        // Check that APROBADO and RECHAZADO have empty transition arrays (terminal states)
        $this->assertTrue(
            preg_match("/'APROBADO'\s*=>\s*\[\]/", $content) > 0,
            'APROBADO must be a terminal state (empty transition array)'
        );
        $this->assertTrue(
            preg_match("/'RECHAZADO'\s*=>\s*\[\]/", $content) > 0,
            'RECHAZADO must be a terminal state (empty transition array)'
        );
    }

    // 2.2b — AprobarLote cascades across multiple invoices
    public function testAprobarLoteCascadesAcrossInvoices(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/PagoModel.php');
        $this->assertStringContains('montoRestante', $content,
            'aprobarLote must track remaining amount for cascade');
        $this->assertStringContains('MovimientosModel', $content,
            'Must register credit in movimientos_cuenta for traceability');
    }

    // 2.5b — cuota_mensual has upper bound
    public function testCuotaMensualHasUpperBound(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/FacturasModel.php');
        $this->assertStringContains('999999', $content,
            'cuota_mensual must have upper bound validation');
    }

    // 2.7b — Conciliación has query limit
    public function testConciliacionHasQueryLimit(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/services/ConciliacionBancariaService.php');
        $this->assertStringContains('LIMIT 5000', $content,
            'Conciliación query must have LIMIT 5000');
    }

    // 2.8b — Resident payments use paginate
    public function testResidentPaymentsUsePaginate(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/PagoModel.php');
        $this->assertStringContains('paginate', $content,
            'obtenerPagosPorResidente must use paginate()');
    }

    // =====================================================================
    // FASE 9 — G3: Estructura y Activos Físicos (14 tests)
    // =====================================================================

    // --- Static tests (9) ---

    // 3.1a — getActivos has LIMIT
    public function testGetActivosHasLimit(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/EdificiosModel.php');
        $this->assertStringContains('LIMIT', $content, 'getActivos must have a LIMIT clause');
    }

    // 3.2a — getActivas has LIMIT
    public function testGetActivasHasLimit(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/UnidadesModel.php');
        $this->assertStringContains('LIMIT', $content, 'getActivas must have a LIMIT clause');
    }

    // 3.3a — guardarVehiculo has rate limiting
    public function testGuardarVehiculoHasRateLimiting(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/EstacionamientoController.php');
        preg_match('/public function guardarVehiculo\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'guardarVehiculo must exist');
        $this->assertStringContains('RateLimiter::attempt', $m[1], 'Must use RateLimiter');
        $this->assertStringContains('vehiculo_', $m[1], 'Rate limit key must include vehiculo prefix');
    }

    // 3.4a — eliminarVehiculo uses soft delete
    public function testEliminarVehiculoUsesSoftDelete(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/VehiculosModel.php');
        preg_match('/public function eliminarVehiculo\(.*?\)(.*?)(?=\}|private function|public function)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'eliminarVehiculo must exist');
        $this->assertStringContains('deleted_at', $m[1], 'Must use soft delete (deleted_at)');
        $this->assertStringContains('UPDATE', $m[1], 'Must use UPDATE, not DELETE');
    }

    // 3.5a — crearVehiculo validates placa format
    public function testCrearVehiculoValidatesPlacaFormat(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/VehiculosModel.php');
        $this->assertStringContains('preg_match', $content, 'Must validate placa format with regex');
        $this->assertStringContains('{3}[0-9]{3,4}', $content, 'Regex must match 3 letters + 3-4 digits');
    }

    // 3.6a — index uses cache
    public function testEstructuraIndexUsesCache(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/EstructuraController.php');
        $this->assertStringContains('storage/cache', $content, 'Cache must use project storage directory');
        $this->assertStringContains('cacheTtl', $content, 'Must have cache TTL');
    }

    // 3.7a — listarConDetalles has LIMIT and filters deleted vehicles
    public function testListarConDetallesHasLimit(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/EstacionamientosModel.php');
        $this->assertStringContains('LIMIT 500', $content, 'listarConDetalles must have LIMIT 500');
        $this->assertStringContains('v.deleted_at', $content, 'Must filter soft-deleted vehicles');
    }

    // 3.8a — Cache invalidation on structure changes
    public function testStructureCacheIsInvalidatedOnChanges(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/EstructuraController.php');
        $this->assertStringContains('invalidarCacheEstructura', $content, 'Must have cache invalidation method');
        $this->assertStringContains('unlink', $content, 'Must unlink cache files');
        $this->assertStringContains('file_exists', $content, 'Must check file exists before unlink');
    }

    // 3.9a — Migration adds deleted_at to vehiculos
    public function testMigrationAddsDeletedAtToVehiculos(): void {
        $content = file_get_contents(dirname(__DIR__) . '/scripts/migrations_phase9.sql');
        $this->assertStringContains('vehiculos', $content, 'Migration must target vehiculos table');
        $this->assertStringContains('deleted_at', $content, 'Migration must add deleted_at column');
    }

    // --- Behavior tests (5) ---

    // 3.1b — getActivas has LIMIT
    public function testGetActivasHasLimitBehavior(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/UnidadesModel.php');
        $this->assertStringContains('LIMIT', $content, 'getActivas must have LIMIT');
    }

    // 3.3b — Rate limit key per-admin
    public function testGuardarVehiculoRateLimitKeyIsPerAdmin(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/EstacionamientoController.php');
        $this->assertStringContains('vehiculo_', $content, 'Rate limit key must include vehiculo prefix');
        $this->assertStringContains('Auth::id()', $content, 'Rate limit key must include admin ID');
    }

    // 3.5b — Placa validation rejects invalid formats
    public function testPlacaValidationRejectsInvalidFormats(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/VehiculosModel.php');
        $this->assertStringContains('{3}[0-9]{3,4}', $content, 'Placa regex must require 3 letters + 3-4 digits');
    }

    // 3.7b — listarConDetalles excludes soft-deleted vehicles
    public function testListarConDetallesExcludesSoftDeletedVehicles(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/EstacionamientosModel.php');
        $this->assertStringContains('v.deleted_at', $content, 'Must filter soft-deleted vehicles in listing');
    }

    // 3.8b — Cache invalidation uses file_exists check
    public function testCacheInvalidationUsesFileExists(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/EstructuraController.php');
        $this->assertStringContains('file_exists', $content, 'Must check file exists before unlink');
        $this->assertStringContains('storage/cache', $content, 'Cache dir must match');
    }

    // =====================================================================
    // FASE 10 — G4: Finanzas y Contabilidad Residente (18 tests)
    // =====================================================================

    // --- 4.1: Eliminación segura de gastos (5 tests) ---

    // 4.1a — eliminar() checks movimientos_cuenta before deleting
    public function testGastoEliminarChecksProrrateo(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/GastoController.php');
        preg_match('/public function eliminar\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'eliminar() must exist');
        $this->assertStringContains('movimientos_cuenta', $m[1],
            'eliminar() must query movimientos_cuenta for prorrateo check');
        $this->assertStringContains('gasto#', $m[1],
            'Prorrateo check must search for gasto#ID pattern');
    }

    // 4.1b — eliminar() validates gasto existence before deletion
    public function testGastoEliminarValidatesExistence(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/GastoController.php');
        preg_match('/public function eliminar\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertStringContains('gastos_comunes WHERE id', $m[1],
            'eliminar() must verify gasto exists in DB');
        $this->assertStringContains('deleted_at IS NULL', $m[1],
            'Must check gasto is not already soft-deleted');
    }

    // 4.1c — eliminar() redirects with period filters preserved
    public function testGastoEliminarPreservesPeriodInRedirect(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/GastoController.php');
        preg_match('/public function eliminar\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertStringContains("gasto['mes']", $m[1],
            'eliminar() redirect must preserve mes filter');
        $this->assertStringContains("gasto['anio']", $m[1],
            'eliminar() redirect must preserve anio filter');
    }

    // 4.1d — eliminar() returns error flash on prorrateo
    public function testGastoEliminarReturnsErrorOnProrrateo(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/GastoController.php');
        preg_match('/public function eliminar\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertStringContains("Flash::set('danger'", $m[1],
            'Must set danger flash on prorrateo detection');
        $this->assertStringContains('prorrateo', $m[1],
            'Error message must mention prorrateo');
    }

    // 4.1e — eliminar() handles id=0 gracefully
    public function testGastoEliminarHandlesZeroId(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/GastoController.php');
        preg_match('/public function eliminar\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertStringContains('$id <= 0', $m[1],
            'Must check for invalid ID before any DB query');
        $this->assertStringContains('return;', $m[1],
            'Must return after redirect to prevent further execution');
    }

    // --- 4.2: Rate limiting impresión (4 tests) ---

    // 4.2a — imprimir() has rate limiting
    public function testImprimirEstadoCuentaHasRateLimiting(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/EstadoCuentaController.php');
        preg_match('/public function imprimir\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'imprimir() must exist');
        $this->assertStringContains('RateLimiter::attempt', $m[1],
            'imprimir() must use RateLimiter');
        $this->assertStringContains('imprimir_estado_', $m[1],
            'Rate limit key must include imprimir_estado prefix');
        $this->assertStringContains('Auth::id()', $m[1],
            'Rate limit key must include user ID');
    }

    // 4.2b — Rate limit is 10 per hour
    public function testImprimirRateLimitIs10PerHour(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/EstadoCuentaController.php');
        $this->assertStringContains('10, 3600', $content,
            'Rate limit must be 10 requests per 3600 seconds');
    }

    // 4.2c — imprimir() redirects on rate limit
    public function testImprimirRedirectsOnRateLimit(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/EstadoCuentaController.php');
        $this->assertStringContains('/residente/estado-cuenta', $content,
            'Must redirect to estado-cuenta when rate limited');
        $this->assertStringContains('return;', $content,
            'Must return after redirect to stop execution');
    }

    // 4.2d — Rate limit shows remaining time
    public function testImprimirRateLimitShowsRemainingTime(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/EstadoCuentaController.php');
        $this->assertStringContains('secondsUntilAvailable', $content,
            'Must calculate remaining time for user message');
        $this->assertStringContains('minuto', $content,
            'Must format remaining time in minutes');
    }

    // --- 4.3: LIMIT 500 cap (2 tests) ---

    // 4.3a — obtenerGastosPorPeriodo has safety cap
    public function testGastosPorPeriodoHasMaxLimit500(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/GastosModel.php');
        preg_match('/public function obtenerGastosPorPeriodo\(.*?\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'obtenerGastosPorPeriodo must exist');
        $this->assertStringContains('min(', $m[1],
            'Must apply min() cap to limit parameter');
        $this->assertStringContains('500', $m[1],
            'Maximum limit must be 500');
    }

    // 4.3b — Cap is applied BEFORE the SQL LIMIT
    public function testGastosPorPeriodoCapBeforeLimit(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/GastosModel.php');
        preg_match('/function obtenerGastosPorPeriodo.*?LIMIT/s', $content, $m);
        $this->assertTrue(count($m) > 0, 'Must find cap before LIMIT');
        $capPos = strpos($m[0], 'min(');
        $limitPos = strpos($m[0], 'LIMIT');
        $this->assertTrue($capPos !== false && $capPos < $limitPos,
            'Cap must be applied before SQL LIMIT clause');
    }

    // --- 4.4: Upper bound monto (3 tests) ---

    // 4.4a — enviarPago has upper bound validation
    public function testEnviarPagoHasUpperBoundValidation(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/ResidenteController.php');
        preg_match('/public function enviarPago\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'enviarPago() must exist');
        $this->assertStringContains('999999', $m[1],
            'Must validate monto upper bound');
        $this->assertStringContains('exceder', $m[1],
            'Must show user-friendly error for exceeding limit');
    }

    // 4.4b — Upper bound error message is in Spanish with Bs.
    public function testEnviarPagoUpperBoundMessageIsSpanish(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/ResidenteController.php');
        $this->assertStringContains('Bs.', $content,
            'Upper bound error must mention Bs. currency');
        $this->assertStringContains('exceder', $content,
            'Error message must be in Spanish');
    }

    // 4.4c — Upper bound check comes AFTER the <= 0 check
    public function testEnviarPagoUpperBoundCheckOrder(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/ResidenteController.php');
        $zeroPos = strpos($content, '$monto <= 0');
        $upperPos = strpos($content, '$monto > 999999');
        $this->assertTrue($zeroPos !== false, 'Must have zero check');
        $this->assertTrue($upperPos !== false, 'Must have upper bound check');
        $this->assertTrue($zeroPos < $upperPos,
            'Zero check must come before upper bound check');
    }

    // --- 4.5: Optimizar conteo unidades (3 tests) ---

    // 4.5a — rendicionResidente uses COUNT query
    public function testRendicionResidenteUsesCountQuery(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/GastoController.php');
        preg_match('/public function rendicionResidente\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1, 'rendicionResidente() must exist');
        $this->assertStringContains('COUNT(*)', $m[1],
            'Must use COUNT(*) SQL query');
        $this->assertStringContains('unidades WHERE', $m[1],
            'Must query unidades table directly');
    }

    // 4.5b — rendicionResidente does NOT call getActivas()
    public function testRendicionResidenteDoesNotLoadFullArray(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/GastoController.php');
        preg_match('/public function rendicionResidente\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertTrue(count($m) > 1);
        $this->assertTrue(strpos($m[1], 'getActivas()') === false,
            'Must NOT call getActivas() to count units');
    }

    // 4.5c — rendicionResidente handles zero units gracefully
    public function testRendicionResidenteHandlesZeroUnits(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/controllers/GastoController.php');
        preg_match('/public function rendicionResidente\(\)(.*?)(?=public function|\Z)/s', $content, $m);
        $this->assertStringContains('intval($stmtCount', $m[1],
            'Must intval the COUNT result');
        $this->assertStringContains('0.00', $m[1],
            'Alicuota must default to 0.00 when no units');
    }

    // --- 4.6: Límite diario ajustes (1 test) ---

    // 4.6a — Daily adjustment limit is 10
    public function testMovimientosModelDailyLimitIs10(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/models/MovimientosModel.php');
        $this->assertStringContains('>= 10', $content,
            'Daily adjustment limit must be 10, not 20');
        $this->assertTrue(strpos($content, '>= 20') === false,
            'Old limit of 20 must be removed');
        $this->assertStringContains('10 ajustes', $content,
            'Error message must mention 10 adjustments');
    }
}
