<?php
namespace Tests;

use Tests\TestCase;
use App\Core\Auth;
use App\Core\UserRole;
use App\Core\Router;

/**
 * Tests de Control de Acceso Basado en Roles (RBAC)
 *
 * Valida de forma estricta que un usuario residente o no autenticado
 * no pueda acceder bajo ninguna circunstancia a rutas, controladores,
 * endpoints o elementos de interfaz administrativos.
 */
class RbacAuthorizationTest extends TestCase {

    private string $indexPath;
    private string $indexContent;

    public function __construct() {
        $this->indexPath = dirname(__DIR__) . '/public/index.php';
        $this->indexContent = file_get_contents($this->indexPath);
    }

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
    // 1. AUDITORÍA DE DECLARACIÓN DE RUTAS EN EL FRONT CONTROLLER
    // =====================================================================

    /**
     * Verifica que todas las rutas bajo el prefijo /admin/ tengan protección de rol estricta.
     */
    public function testAllAdminRoutesRequireAdminRole(): void {
        $lines = explode("\n", $this->indexContent);
        $adminRouteRegex = '/\$router->(get|post|any)\s*\(\s*[\'"](\/admin\/[^\'"]+)[\'"]\s*,\s*\[([^\]]+)\]\s*(?:,\s*\[([^\]]+)\])?\s*\)/i';

        $checkedRoutes = 0;
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '//') || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match($adminRouteRegex, $line, $matches)) {
                $routeUri = $matches[2];
                $middlewares = $matches[4] ?? '';

                // Excepciones conocidas: /admin/login (pública)
                if ($routeUri === '/admin/login') {
                    continue;
                }

                // Excepciones: /admin/logout (requiere 'auth')
                if ($routeUri === '/admin/logout') {
                    $this->assertStringContains('auth', $middlewares,
                        "La ruta {$routeUri} debe requerir auth");
                    continue;
                }

                $checkedRoutes++;
                $hasAdminRole = str_contains($middlewares, 'UserRole::ADMIN') || str_contains($middlewares, "'admin'");
                $this->assertTrue(
                    $hasAdminRole,
                    "SEGURIDAD CRÍTICA: La ruta administrativa '{$routeUri}' en la línea " . ($lineNum + 1) . " no exige UserRole::ADMIN en el Router."
                );
            }
        }

        $this->assertGreaterThan(20, $checkedRoutes,
            "Se deben auditar al menos 20 rutas administrativas en public/index.php");
    }

    /**
     * Verifica que las mutaciones de pagos (/pagos/cambiar-estado, /admin/pagos/aprobar-masivo) exijan UserRole::ADMIN.
     */
    public function testPaymentMutationsRequireAdminRole(): void {
        $criticalRoutes = [
            '/pagos/cambiar-estado',
            '/admin/pagos/aprobar-masivo'
        ];

        foreach ($criticalRoutes as $route) {
            $pattern = '/\$router->(get|post|any)\s*\(\s*[\'"]' . preg_quote($route, '/') . '[\'"]\s*,\s*\[([^\]]+)\]\s*,\s*\[([^\]]+)\]\s*\)/i';
            $matched = preg_match($pattern, $this->indexContent, $matches);

            $this->assertTrue((bool)$matched, "La ruta '{$route}' debe estar registrada con middlewares");
            if ($matched) {
                $middlewares = $matches[3] ?? '';
                $hasAdmin = str_contains($middlewares, 'UserRole::ADMIN') || str_contains($middlewares, "'admin'");
                $this->assertTrue($hasAdmin, "La ruta '{$route}' debe exigir estrictamente UserRole::ADMIN");
            }
        }
    }

    // =====================================================================
    // 2. AUDITORÍA DE DEFENSA EN PROFUNDIDAD EN CONTROLADORES ADMIN
    // =====================================================================

    /**
     * Verifica que todos los controladores administrativos contengan Auth::requireRole en sus acciones.
     */
    public function testAdminControllersEnforceRoleInternally(): void {
        $adminControllers = [
            'AdminController.php'              => ['dashboard', 'listarComprobantes', 'verificarComprobante', 'generarFacturas'],
            'SolicitudesRegistroController.php' => ['index', 'aprobar', 'rechazar'],
            'ConciliacionController.php'       => ['index', 'importarExtracto', 'conciliarPago', 'conciliarLote'],
            'EstructuraController.php'         => ['index', 'guardarEdificio', 'toggleEdificio', 'guardarUnidad', 'toggleUnidad', 'guardarResidente', 'desvincularResidente'],
            'EstacionamientoController.php'    => ['index', 'guardar', 'asignar', 'eliminar', 'guardarVehiculo', 'eliminarVehiculo'],
            'ReporteController.php'            => ['morosidad', 'imprimirMorosidad', 'exportarCsv', 'generarCartaDeuda', 'enviarAvisoCobro'],
            'ComunicadoController.php'         => ['index', 'guardar', 'eliminar'],
            'RespaldoController.php'           => ['index', 'generarManual', 'descargar'],
        ];

        $controllersDir = dirname(__DIR__) . '/app/controllers';

        foreach ($adminControllers as $filename => $methods) {
            $filePath = $controllersDir . '/' . $filename;
            $this->assertFileExists($filePath, "El controlador {$filename} debe existir");

            $content = file_get_contents($filePath);

            foreach ($methods as $method) {
                // Verificar que el método exista en el archivo
                $methodPattern = '/public\s+function\s+' . preg_quote($method, '/') . '\s*\(/i';
                $this->assertTrue(
                    (bool)preg_match($methodPattern, $content),
                    "El método {$method} debe existir en {$filename}"
                );

                // Encontrar el cuerpo de la función y verificar Auth::requireRole
                $pos = strpos($content, "function {$method}");
                if ($pos !== false) {
                    $snippet = substr($content, $pos, 400);
                    $hasRoleCheck = str_contains($snippet, 'Auth::requireRole');
                    $this->assertTrue(
                        $hasRoleCheck,
                        "SEGURIDAD CRÍTICA: {$filename}::{$method}() carece de Auth::requireRole en sus primeras líneas."
                    );
                }
            }
        }
    }

    /**
     * Verifica que las acciones administrativas en PerfilController y PagoController
     * también requieran rol de administrador internamente.
     */
    public function testSharedControllersProtectAdminActions(): void {
        $controllersDir = dirname(__DIR__) . '/app/controllers';

        // PerfilController: listarSolicitudes y procesarSolicitud
        $perfilContent = file_get_contents($controllersDir . '/PerfilController.php');
        foreach (['listarSolicitudes', 'procesarSolicitud'] as $method) {
            $pos = strpos($perfilContent, "function {$method}");
            $this->assertTrue($pos !== false, "PerfilController::{$method} debe existir");
            if ($pos !== false) {
                $snippet = substr($perfilContent, $pos, 400);
                $this->assertTrue(
                    str_contains($snippet, "Auth::requireRole('admin')") || str_contains($snippet, "Auth::requireRole(UserRole::ADMIN)"),
                    "PerfilController::{$method} debe requerir rol admin"
                );
            }
        }

        // PagoController: cambiarEstado y aprobarMasivo
        $pagoContent = file_get_contents($controllersDir . '/PagoController.php');
        foreach (['cambiarEstado', 'aprobarMasivo'] as $method) {
            $pos = strpos($pagoContent, "function {$method}");
            $this->assertTrue($pos !== false, "PagoController::{$method} debe existir");
            if ($pos !== false) {
                $snippet = substr($pagoContent, $pos, 400);
                $this->assertTrue(
                    str_contains($snippet, "Auth::requireRole('admin')") || str_contains($snippet, "Auth::requireRole(UserRole::ADMIN)"),
                    "PagoController::{$method} debe requerir rol admin"
                );
            }
        }
    }

    // =====================================================================
    // 3. IDENTIDAD Y SESIÓN DE RESIDENTE vs ADMINISTRADOR
    // =====================================================================

    /**
     * Verifica que una sesión iniciada como residente retorne falso para verificación de rol admin.
     */
    public function testResidentSessionCannotClaimAdminRole(): void {
        $this->startTestSession();

        $personaMock = [
            'id'       => 10,
            'nombre'   => 'Carlos',
            'apellido' => 'Pérez',
            'email'    => 'carlos@condominio.com',
            'rol'      => 'residente'
        ];

        Auth::loginAsResidente($personaMock);

        $this->assertEquals(UserRole::RESIDENTE, Auth::role(), "El rol en sesión debe ser 'residente'");
        $this->assertTrue(Auth::hasRole(UserRole::RESIDENTE), "hasRole(RESIDENTE) debe ser true");
        $this->assertFalse(Auth::hasRole(UserRole::ADMIN), "hasRole(ADMIN) DEBE ser false para un residente");
        $this->assertFalse(Auth::hasRole(UserRole::AUDITOR), "hasRole(AUDITOR) DEBE ser false para un residente");

        $this->destroyTestSession();
    }

    /**
     * Verifica que una sesión iniciada como admin retorne falso para verificación de rol residente.
     */
    public function testAdminSessionCannotClaimResidentRole(): void {
        $this->startTestSession();

        $adminMock = [
            'id'              => 1,
            'nombre_completo' => 'Administrador Principal',
            'email'           => 'admin@condominio.com',
            'rol'             => 'admin'
        ];

        Auth::loginAsAdmin($adminMock);

        $this->assertEquals(UserRole::ADMIN, Auth::role(), "El rol en sesión debe ser 'admin'");
        $this->assertTrue(Auth::hasRole(UserRole::ADMIN), "hasRole(ADMIN) debe ser true");
        $this->assertFalse(Auth::hasRole(UserRole::RESIDENTE), "hasRole(RESIDENTE) DEBE ser false para un admin");

        $this->destroyTestSession();
    }

    // =====================================================================
    // 4. AISLAMIENTO DE VISTAS (CERO FUGAS DE UI HACIA RESIDENTES)
    // =====================================================================

    /**
     * Verifica que las vistas del portal de residentes no contengan enlaces ni formularios dirigidos a /admin/*.
     */
    public function testResidentViewsDoNotLeakAdminLinksOrForms(): void {
        $residenteViewsDir = dirname(__DIR__) . '/app/views/residente';
        $viewFiles = glob($residenteViewsDir . '/*.php');

        // Agregar también vistas de pagos para residentes
        $pagoResidenteDir = dirname(__DIR__) . '/app/views/pagos/residente';
        $viewFiles = array_merge($viewFiles, glob($pagoResidenteDir . '/*.php'));

        $leaksFound = [];

        foreach ($viewFiles as $file) {
            $content = file_get_contents($file);
            $baseName = basename($file);

            // Buscar href="/admin/..." o action="/admin/..."
            if (preg_match_all('/(href|action)\s*=\s*[\'"](\/admin\/[^\'"]+)[\'"]/i', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $uri = $match[2];
                    // Excepción: /admin/logout
                    if ($uri === '/admin/logout') {
                        continue;
                    }
                    $leaksFound[] = "{$baseName}: {$match[1]}=\"{$uri}\"";
                }
            }
        }

        $this->assertTrue(
            empty($leaksFound),
            "FUGAS DE INTERFAZ DETECTADAS en vistas de residente: " . implode(', ', $leaksFound)
        );
    }
}
