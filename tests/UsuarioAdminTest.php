<?php
namespace Tests;

use App\Controllers\UsuarioAdminController;
use App\Models\UsuariosModel;
use App\Models\PersonasModel;
use App\Core\Database;
use PDO;

class UsuarioAdminTest extends TestCase {

    private function getDb(): PDO {
        return Database::getConnection();
    }

    public function testClassesAndMethodsExist(): void {
        $this->assertTrue(class_exists(UsuarioAdminController::class), "UsuarioAdminController debe existir");
        
        $this->assertTrue(
            method_exists(UsuariosModel::class, 'obtenerListadoUnificado'),
            "UsuariosModel debe tener el método obtenerListadoUnificado"
        );
        $this->assertTrue(
            method_exists(UsuariosModel::class, 'reiniciarPassword'),
            "UsuariosModel debe tener el método reiniciarPassword"
        );
        $this->assertTrue(
            method_exists(PersonasModel::class, 'reiniciarPassword'),
            "PersonasModel debe tener el método reiniciarPassword"
        );
    }

    public function testRoutesAndControllerRegisteredInIndex(): void {
        $indexContent = file_get_contents(dirname(__DIR__) . '/public/index.php');
        
        $this->assertStringContains('UsuarioAdminController', $indexContent, "index.php debe importar o referenciar UsuarioAdminController");
        $this->assertStringContains('/admin/usuarios', $indexContent, "index.php debe registrar la ruta /admin/usuarios");
        $this->assertStringContains('/admin/usuarios/reiniciar-password', $indexContent, "index.php debe registrar la ruta /admin/usuarios/reiniciar-password");
    }

    public function testSidebarContainsUsuariosMenu(): void {
        $sidebarContent = file_get_contents(dirname(__DIR__) . '/app/views/layouts/admin_sidebar.php');
        
        $this->assertStringContains("['route' => 'usuarios'", $sidebarContent, "admin_sidebar.php debe tener la ruta usuarios en la lista de items");
        $this->assertStringContains("'/admin/usuarios'", $sidebarContent, "admin_sidebar.php debe vincular a /admin/usuarios");
    }

    public function testObtenerListadoUnificadoStructure(): void {
        $usuariosModel = new UsuariosModel();
        $resultado = $usuariosModel->obtenerListadoUnificado('', '', 1, 10);

        $this->assertTrue(isset($resultado['datos']), "El resultado debe contener clave 'datos'");
        $this->assertTrue(isset($resultado['total']), "El resultado debe contener clave 'total'");
        $this->assertTrue(isset($resultado['pagina']), "El resultado debe contener clave 'pagina'");
        $this->assertTrue(isset($resultado['porPagina']), "El resultado debe contener clave 'porPagina'");
        $this->assertTrue(isset($resultado['totalPaginas']), "El resultado debe contener clave 'totalPaginas'");
        $this->assertTrue(is_array($resultado['datos']), "'datos' debe ser un array");
    }

    public function testObtenerListadoUnificadoFiltroRol(): void {
        $usuariosModel = new UsuariosModel();
        $resultadoAdmin = $usuariosModel->obtenerListadoUnificado('', 'admin', 1, 10);

        foreach ($resultadoAdmin['datos'] as $item) {
            $this->assertEquals('admin', $item['rol_clave'], "El filtro por rol 'admin' debe retornar únicamente cuentas con rol admin");
        }
    }

    public function testReiniciarPasswordPersona(): void {
        $db = $this->getDb();
        $personasModel = new PersonasModel();

        // Crear una persona temporal para prueba
        $cedulaTest = 'V-99988877';
        $emailTest = 'test_reset_' . time() . '@example.com';

        // Limpiar previo
        $db->exec("DELETE FROM personas WHERE cedula = '{$cedulaTest}'");

        $db->exec("INSERT INTO personas (cedula, nombre, apellido, email, tipo, estado, intentos_fallidos, bloqueado_hasta, password) 
                   VALUES ('{$cedulaTest}', 'Test', 'Persona', '{$emailTest}', 'propietario', 1, 4, NOW() + INTERVAL 30 MINUTE, 'antigua')");
        $personaId = (int)$db->lastInsertId();

        $this->assertTrue($personaId > 0, "La persona de prueba debe crearse");

        // Ejecutar reinicio de contraseña
        $nuevaClave = 'Temporal2026!';
        $nuevoHash = password_hash($nuevaClave, PASSWORD_BCRYPT);
        $ok = $personasModel->reiniciarPassword($personaId, $nuevoHash);

        $this->assertTrue($ok, "reiniciarPassword() debe retornar true");

        // Verificar datos en base de datos
        $stmt = $db->prepare("SELECT password, intentos_fallidos, bloqueado_hasta FROM personas WHERE id = :id");
        $stmt->execute(['id' => $personaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertTrue(password_verify($nuevaClave, $row['password']), "El nuevo hash debe verificar correctamente con password_verify");
        $this->assertEquals(0, (int)$row['intentos_fallidos'], "intentos_fallidos debe quedar en 0 tras reiniciar contraseña");
        $this->assertTrue(empty($row['bloqueado_hasta']), "bloqueado_hasta debe ser NULL tras reiniciar contraseña");

        // Limpiar persona temporal
        $db->exec("DELETE FROM personas WHERE id = {$personaId}");
    }

    public function testViewTemplateExistsAndContainsExpectedElements(): void {
        $viewPath = dirname(__DIR__) . '/app/views/admin/usuarios/index.php';
        $this->assertFileExists($viewPath, "La vista app/views/admin/usuarios/index.php debe existir");

        $viewContent = file_get_contents($viewPath);
        $this->assertStringContains('csrf_field()', $viewContent, "La vista debe incluir token CSRF");
        $this->assertStringContains('modalReiniciarPassword', $viewContent, "La vista debe contener el modal modalReiniciarPassword");
        $this->assertStringContains('pagination', $viewContent, "La vista debe contener soporte de paginación");
        $this->assertStringContains('reiniciar-password', $viewContent, "La vista debe apuntar al endpoint de reseteo");
    }
}
