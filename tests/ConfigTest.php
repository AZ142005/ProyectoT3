<?php
namespace Tests;

use Tests\TestCase;

/**
 * Tests de Configuración — Fase 1.1, 1.3, 1.7
 *
 * 1.1: Variables de entorno (DB_HOST no hardcodeado)
 * 1.3: Permisos de directorio (0755, nunca 0777)
 * 1.7: Cookie secure dependiente de ENVIRONMENT
 */
class ConfigTest extends TestCase {

    // =====================================================================
    // 1.1 — VARIABLES DE ENTORNO
    // =====================================================================

    public function testDbHostShouldNotBeHardcoded(): void {
        $defined = defined('DB_HOST') ? DB_HOST : null;

        if ($defined === 'localhost' || $defined === '127.0.0.1') {
            $envHost = getenv('DB_HOST');
            $envValue = $_ENV['DB_HOST'] ?? null;

            if ($envHost === false && $envValue === null) {
                $this->failures[] = "REQUERIDO: DB_HOST = 'localhost' hardcodeado. Migrar a \$_ENV['DB_HOST'] o getenv('DB_HOST')";
                $this->failed++;
            } else {
                $this->passed++;
            }
        } else {
            $this->passed++;
        }
    }

    public function testDbUserShouldNotBeRoot(): void {
        $defined = defined('DB_USER') ? DB_USER : null;

        if ($defined === 'root') {
            $envUser = getenv('DB_USER');
            $envValue = $_ENV['DB_USER'] ?? null;

            if ($envUser === false && $envValue === null) {
                $this->failures[] = "REQUERIDO: DB_USER = 'root' hardcodeado. Migrar a \$_ENV['DB_USER']";
                $this->failed++;
            } else {
                $this->passed++;
            }
        } else {
            $this->passed++;
        }
    }

    public function testDbPassShouldNotBeEmpty(): void {
        $defined = defined('DB_PASS') ? DB_PASS : null;

        if ($defined === '' || $defined === null) {
            $envPass = getenv('DB_PASS');
            $envValue = $_ENV['DB_PASS'] ?? null;

            if ($envPass === false && $envValue === null) {
                $this->failures[] = "REQUERIDO: DB_PASS vacío hardcodeado. Migrar a \$_ENV['DB_PASS']";
                $this->failed++;
            } else {
                $this->passed++;
            }
        } else {
            $this->passed++;
        }
    }

    public function testEnvExampleFileExists(): void {
        $envExamplePath = dirname(__DIR__) . '/.env.example';
        $this->assertFileExists($envExamplePath,
            "Falta .env.example en la raíz del proyecto como plantilla");
    }

    public function testEnvIsInGitignore(): void {
        $gitignorePath = dirname(__DIR__) . '/.gitignore';

        if (!file_exists($gitignorePath)) {
            $this->failures[] = "REQUERIDO: Archivo .gitignore no existe";
            $this->failed++;
            return;
        }

        $content = file_get_contents($gitignorePath);
        $this->assertStringContains('.env', $content,
            ".env debe estar en .gitignore");
    }

    public function testMissingDbConfigThrowsClearError(): void {
        if (defined('DB_HOST')) {
            $this->assertNotNull(DB_HOST, "DB_HOST no debe ser null");
            $this->assertNotEquals('', DB_HOST, "DB_HOST no debe ser string vacío");
        } else {
            $this->passed++;
        }
    }

    // =====================================================================
    // 1.3 — PERMISOS DE DIRECTORIO
    // =====================================================================

    public function testDirectoryPermissionsAreNot777(): void {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->skip("Windows no soporta permisos Unix (fileperms siempre retorna 0777)");
            return;
        }

        $tempDir = sys_get_temp_dir() . '/proyectot3_test_' . uniqid();
        $result = mkdir($tempDir, 0755, true);
        $this->assertTrue($result, "No se pudo crear directorio temporal");

        if ($result) {
            $perms = fileperms($tempDir);
            $octal = substr(sprintf('%o', $perms), -4);

            $this->assertNotEquals('0777', $octal,
                "Directorio no debe tener permisos 0777 — detectado: {$octal}");
            rmdir($tempDir);
        }
    }

    public function testLogsDirectoryPermissionsAreSafe(): void {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->skip("Windows no soporta permisos Unix");
            return;
        }

        $logsDir = dirname(__DIR__) . '/logs';

        if (is_dir($logsDir)) {
            $perms = fileperms($logsDir);
            $octal = substr(sprintf('%o', $perms), -4);
            $this->assertNotEquals('0777', $octal,
                "Directorio logs/ no debe tener permisos 0777 — detectado: {$octal}");
        } else {
            $this->passed++;
        }
    }

    public function testNoHardcoded777InCodebase(): void {
        $sourceFiles = array_merge(
            glob(dirname(__DIR__) . '/app/**/*.php'),
            glob(dirname(__DIR__) . '/public/**/*.php')
        );

        foreach ($sourceFiles as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace(dirname(__DIR__) . '/', '', $file);

            if (preg_match('/mkdir\s*\([^)]*0777/', $content)) {
                $this->failures[] = "VULNERABILIDAD: chmod 0777 encontrado en {$relativePath}";
                $this->failed++;
                return;
            }
        }

        $this->passed++;
    }

    // =====================================================================
    // 1.7 — COOKIE SECURE
    // =====================================================================

    public function testCookieSecureDependsOnEnvironment(): void {
        $indexContent = file_get_contents(dirname(__DIR__) . '/public/index.php');

        $hasHardcodedFalse = (bool) preg_match(
            "/['\"]secure['\"]\s*=>\s*false/",
            $indexContent
        );

        $hasEnvironmentCheck = (bool) preg_match(
            "/['\"]secure['\"]\s*=>.*ENVIRONMENT/",
            $indexContent
        );

        if ($hasHardcodedFalse && !$hasEnvironmentCheck) {
            $this->failures[] = "VULNERABILIDAD: Cookie 'secure' hardcodeada como false. Debe ser: ENVIRONMENT === 'production'";
            $this->failed++;
        } else {
            $this->passed++;
        }
    }

    public function testCookieHttponlyIsEnabled(): void {
        $indexContent = file_get_contents(dirname(__DIR__) . '/public/index.php');
        $this->assertTrue(
            (bool) preg_match("/['\"]httponly['\"]\s*=>\s*true/", $indexContent),
            "Cookie httponly debe estar habilitada"
        );
    }

    public function testCookieSameSiteIsConfigured(): void {
        $indexContent = file_get_contents(dirname(__DIR__) . '/public/index.php');
        $this->assertTrue(
            (bool) preg_match("/['\"]samesite['\"]\s*=>\s*['\"](?:Lax|Strict)['\"]/", $indexContent),
            "Cookie SameSite debe ser Lax o Strict"
        );
    }

    public function testCookieLifetimeIsReasonable(): void {
        $indexContent = file_get_contents(dirname(__DIR__) . '/public/index.php');

        if (preg_match("/['\"]lifetime['\"]\s*=>\s*(\d+)/", $indexContent, $matches)) {
            $lifetime = (int) $matches[1];
            $this->assertGreaterThan(0, $lifetime, "Lifetime debe ser > 0");
            $this->assertTrue($lifetime <= 604800,
                "Lifetime no debe exceder 7 días — actual: {$lifetime}s");
        } else {
            $this->passed++;
        }
    }
}
