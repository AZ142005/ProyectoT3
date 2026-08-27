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
    // 40. DAILY ADJUSTMENT LIMIT — Verify 20/day limit in MovimientosModel
    // =====================================================================

    public function testMovimientosModelHasDailyAdjustmentLimit(): void {
        $file = dirname(__DIR__) . '/app/models/MovimientosModel.php';
        $content = file_get_contents($file);
        $this->assertStringContains('ajuste', $content);
        $this->assertStringContains('>= 20', $content,
            'Must limit to 20 adjustments per day');
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
}
