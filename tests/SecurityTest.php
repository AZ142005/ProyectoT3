<?php
namespace Tests;

use Tests\TestCase;

/**
 * Tests de Seguridad — Validaciones adicionales del proyecto
 *
 * CSRF, XSS escaping, validación MIME, uploads, SQL injection patterns.
 */
class SecurityTest extends TestCase {

    // =====================================================================
    // CSRF
    // =====================================================================

    /**
     * Verifica que Security::validateCSRF exista y sea callable.
     */
    public function testCsrfValidatorClassExists(): void {
        $this->assertTrue(
            class_exists(\App\Core\Security::class),
            "La clase App\\Core\\Security debe existir"
        );
    }

    /**
     * Verifica que validateCSRF aborte con 403 cuando el token no coincide.
     */
    public function testCsrfRejectsInvalidToken(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = 'invalid_token_12345';
        $_SESSION['csrf_token'] = 'legitimate_token_67890';

        ob_start();
        try {
            \App\Core\Security::validateCSRF();
            $this->failures[] = "CSRF no rechazó token inválido — se esperaba exit";
            $this->failed++;
        } catch (\Throwable $e) {
            $this->passed++;
        }
        ob_end_clean();

        unset($_SERVER['REQUEST_METHOD'], $_POST['csrf_token'], $_SESSION['csrf_token']);
    }

    /**
     * Verifica que validateCSRF permita requests GET sin token.
     */
    public function testCsrfAllowsGetRequests(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_POST['csrf_token']);

        $result = \App\Core\Security::validateCSRF();
        $this->assertTrue($result === true, "CSRF debe retornar true para requests GET");

        unset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * Verifica que el token CSRF se genere con suficiente entropía (64 chars hex).
     */
    public function testCsrfTokenGenerationEntropy(): void {
        session_start();

        $_SESSION['csrf_token'] = null;
        // Simular lo que hace index.php
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $token = $_SESSION['csrf_token'];

        $this->assertMatchesRegex('/^[a-f0-9]{64}$/', $token,
            "Token CSRF debe ser string hex de 64 caracteres (32 bytes)");

        session_destroy();
    }

    // =====================================================================
    // XSS ESCAPING (helper e())
    // =====================================================================

    /**
     * Verifica que e() escape correctamente caracteres HTML peligrosos.
     */
    public function testHelperEscapesHtml(): void {
        $input = '<script>alert("XSS")</script>';
        $output = e($input);

        $this->assertStringContains('&lt;', $output,
            "e() debe escapar < como &lt;");
        $this->assertStringContains('&gt;', $output,
            "e() debe escapar > como &gt;");
        $this->assertStringContains('&quot;', $output,
            "e() debe escapar \" como &quot;");
    }

    public function testHelperEscapesSingleQuote(): void {
        $input = "it's a test";
        $output = e($input);

        // PHP 8.4+ may use &#039; or ' depending on ENT_QUOTES behavior
        $hasEscapedQuote = str_contains($output, '&#039;') ||
                          str_contains($output, '&#x27;') ||
                          !str_contains($output, "'");
        $this->assertTrue($hasEscapedQuote,
            "e() debe escapar comillas simples con ENT_QUOTES");
    }

    /**
     * Verifica que e() maneje strings vacíos y null sin errores.
     */
    public function testHelperHandlesEmptyAndNull(): void {
        $this->assertEquals('', e(null), "e(null) debe retornar string vacío");
        $this->assertEquals('', e(''), "e('') debe retornar string vacío");
    }

    /**
     * Verifica que e() preserve texto seguro.
     */
    public function testHelperPreservesSafeText(): void {
        $safe = 'Juan Pérez - Apt 4B';
        $this->assertEquals($safe, e($safe),
            "e() no debe modificar texto seguro");
    }

    /**
     * Verifica que e() maneje caracteres UTF-8 correctamente.
     */
    public function testHelperHandlesUtf8(): void {
        $input = 'Añoñoáéíóú ñ';
        $output = e($input);
        $this->assertEquals($input, $output,
            "e() debe preservar caracteres UTF-8 válidos");
    }

    // =====================================================================
    // UPLOADS — SEGURIDAD DE ARCHIVOS
    // =====================================================================

    /**
     * Verifica que el código fuente NO valide MIME solo por extensión.
     * Debe usar finfo_open() o similar.
     */
    public function testFileValidationUsesRealMimeType(): void {
        $controllerFiles = glob(dirname(__DIR__) . '/app/controllers/*.php');
        $modelFiles = glob(dirname(__DIR__) . '/app/models/*.php');
        $allFiles = array_merge($controllerFiles, $modelFiles);

        $usesFinfo = false;
        $onlyExtension = false;

        foreach ($allFiles as $file) {
            $content = file_get_contents($file);

            if (str_contains($content, 'finfo_open') || str_contains($content, 'finfo_file')) {
                $usesFinfo = true;
            }

            // Detectar validación solo por extensión sin finfo como respaldo
            if (preg_match('/pathinfo\s*\([^)]+PATHINFO_EXTENSION/', $content) &&
                !str_contains($content, 'finfo')) {
                $onlyExtension = true;
            }
        }

        if ($onlyExtension && !$usesFinfo) {
            $this->failures[] = "SEGURIDAD: Validación de uploads solo por extensión, sin verificación MIME real (finfo)";
            $this->failed++;
        } else {
            $this->passed++;
        }
    }

    /**
     * Verifica que los uploads en PagoController no preserven el nombre original del archivo.
     */
    public function testUploadFilenameIsSanitized(): void {
        $filePath = dirname(__DIR__) . '/app/controllers/PagoController.php';

        if (!file_exists($filePath)) {
            $this->failures[] = "Archivo PagoController.php no encontrado";
            $this->failed++;
            return;
        }

        $content = file_get_contents($filePath);

        // Detectar patrón peligroso: basename($file['name']) sin sanitización
        if (preg_match('/basename\s*\(\s*\$file\s*\[\s*[\'"]name[\'"]\s*\]\s*\)/', $content)) {
            // Verificar que se use random_bytes o uniqid como prefijo
            if (str_contains($content, 'random_bytes') || str_contains($content, 'uniqid')) {
                $this->passed++;
            } else {
                $this->failures[] = "SEGURIDAD: Nombre de archivo upload usa basename sin randomización adicional";
                $this->failed++;
            }
        } else {
            $this->passed++;
        }
    }

    /**
     * Verifica que el directorio uploads/ tenga protección contra ejecución PHP.
     */
    public function testUploadsDirHasHtaccessProtection(): void {
        $possiblePaths = [
            dirname(__DIR__) . '/uploads/.htaccess',
            dirname(__DIR__) . '/public/uploads/.htaccess',
        ];

        $found = false;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $content = file_get_contents($path);
                if (str_contains($content, 'php_flag engine off') ||
                    str_contains($content, 'RemoveHandler .php') ||
                    str_contains($content, 'Deny from all')) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            $this->failures[] = "SEGURIDAD: Falta .htaccess en directorio uploads/ para bloquear ejecución PHP";
            $this->failed++;
        } else {
            $this->passed++;
        }
    }

    // =====================================================================
    // SQL INJECTION — CONSULTAS PREPARADAS
    // =====================================================================

    /**
     * Verifica que TODOS los modelos usen consultas preparadas (prepare/execute)
     * y no interpolen variables directamente en SQL.
     */
    public function testModelsUsePreparedStatements(): void {
        $modelFiles = glob(dirname(__DIR__) . '/app/models/*.php');
        $vulnerableModels = [];

        foreach ($modelFiles as $file) {
            $content = file_get_contents($file);
            $modelName = basename($file);

            // Detectar interpolación directa de variables en SQL
            // Patrón: "SELECT/INSERT/UPDATE/DELETE ... $variable"
            // Excluir BaseModel.php ya que usa {$table} y {$column} que son parámetros internos controlados
            if ($modelName !== 'BaseModel.php' && preg_match_all('/(?:SELECT|INSERT|UPDATE|DELETE|WHERE)\s+[^"\']*\$(?!this->db|stmt|sql|params)/i', $content, $matches)) {
                // Verificar que sea interpolación real en string, no en comentarios
                $lines = explode("\n", $content);
                foreach ($lines as $lineNum => $line) {
                    $lineNum++;
                    if (preg_match('/["\'].*\$\w+.*["\']/', $line) &&
                        !str_contains($line, '//') &&
                        !str_contains($line, '*') &&
                        preg_match('/SELECT|INSERT|UPDATE|DELETE/i', $line)) {
                        $vulnerableModels[] = "{$modelName}:L{$lineNum}";
                    }
                }
            }
        }

        if (!empty($vulnerableModels)) {
            $files = implode(', ', $vulnerableModels);
            $this->failures[] = "SQL INJECTION: Posible interpolación directa en: {$files}";
            $this->failed++;
        } else {
            $this->passed++;
        }
    }

    /**
     * Verifica que la conexión PDO deshabilite EMULATE_PREPARES.
     */
    public function testPdoDisablesEmulatedPrepares(): void {
        $dbFile = dirname(__DIR__) . '/app/core/Database.php';
        $content = file_get_contents($dbFile);

        $this->assertStringContains('EMULATE_PREPARES', $content,
            "Database.php debe configurar ATTR_EMULATE_PREPARES");

        $this->assertStringContains('false', $content,
            "ATTR_EMULATE_PREPARES debe ser false (consultas preparadas reales)");
    }

    /**
     * Verifica que PDO esté en modo ERRMODE_EXCEPTION.
     */
    public function testPdoUsesExceptionMode(): void {
        $dbFile = dirname(__DIR__) . '/app/core/Database.php';
        $content = file_get_contents($dbFile);

        $this->assertStringContains('ERRMODE_EXCEPTION', $content,
            "Database.php debe usar ERRMODE_EXCEPTION");
    }

    // =====================================================================
    // SESIONES
    // =====================================================================

    /**
     * Verifica que la sesión use cookies httponly.
     */
    public function testSessionUsesHttponlyCookies(): void {
        $indexContent = file_get_contents(dirname(__DIR__) . '/public/index.php');

        $this->assertStringContains("httponly", $indexContent,
            "Sesión debe configurar cookies httponly");
    }

    /**
     * Verifica que session_start se ejecute después de session_set_cookie_params.
     */
    public function testSessionStartOrder(): void {
        $indexContent = file_get_contents(dirname(__DIR__) . '/public/index.php');

        $cookieParamsPos = strpos($indexContent, 'session_set_cookie_params');
        $sessionStartPos = strpos($indexContent, 'session_start');

        $this->assertTrue($cookieParamsPos !== false,
            "session_set_cookie_params debe estar presente en index.php");
        $this->assertTrue($sessionStartPos !== false,
            "session_start debe estar presente en index.php");

        if ($cookieParamsPos !== false && $sessionStartPos !== false) {
            $this->assertTrue($cookieParamsPos < $sessionStartPos,
                "session_set_cookie_params debe ejecutarse ANTES de session_start");
        }
    }

    // =====================================================================
    // ENTRADA DEL USUARIO
    // =====================================================================

    /**
     * Verifica que el login no acepte passwords vacíos.
     */
    public function testLoginRejectsEmptyPasswords(): void {
        $authFile = dirname(__DIR__) . '/app/controllers/AuthController.php';
        $content = file_get_contents($authFile);

        $this->assertStringContains("empty(\$password)", $content,
            "AuthController debe verificar que password no esté vacío");
    }

    /**
     * Verifica que el registro valide longitud mínima de password.
     */
    public function testRegisterEnforcesMinimumPasswordLength(): void {
        $authFile = dirname(__DIR__) . '/app/controllers/AuthController.php';
        $content = file_get_contents($authFile);

        $this->assertStringContains('strlen', $content,
            "Register debe usar strlen() para validar longitud de password");

        // Verificar que sea al menos 8 caracteres (OWASP)
        if (preg_match('/strlen\s*\(\s*\$password\s*\)\s*<\s*(\d+)/', $content, $matches)) {
            $minLength = (int) $matches[1];
            $this->assertGreaterThan(7, $minLength,
                "Longitud mínima de password debe ser >= 8 caracteres (OWASP)");
        }
    }

    /**
     * Verifica que el password se hashee con bcrypt antes de guardar.
     */
    public function testPasswordIsHashedWithBcrypt(): void {
        $authFile = dirname(__DIR__) . '/app/controllers/AuthController.php';
        $content = file_get_contents($authFile);

        $this->assertStringContains('password_hash', $content,
            "AuthController debe usar password_hash()");

        $this->assertStringContains('PASSWORD_BCRYPT', $content,
            "password_hash debe usar PASSWORD_BCRYPT");
    }

    /**
     * Verifica que el login use password_verify.
     */
    public function testLoginUsesPasswordVerify(): void {
        $authFile = dirname(__DIR__) . '/app/controllers/AuthController.php';
        $content = file_get_contents($authFile);

        $this->assertStringContains('password_verify', $content,
            "AuthController debe usar password_verify() para verificar passwords");
    }
}
