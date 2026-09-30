<?php
namespace Tests;

use App\Models\UsuariosModel;
use App\Core\Database;
use PDO;

class PerfilAdminTest extends TestCase {

    private function getDb(): PDO {
        return Database::getConnection();
    }

    public function testUsuariosModelMethodsExist(): void {
        $usuariosModel = new UsuariosModel();

        $this->assertTrue(
            method_exists($usuariosModel, 'actualizarPerfil'),
            "UsuariosModel debe tener el método actualizarPerfil"
        );

        $this->assertTrue(
            method_exists($usuariosModel, 'cedulaExisteEnOtroUsuario'),
            "UsuariosModel debe tener el método cedulaExisteEnOtroUsuario"
        );
    }

    public function testActualizarPerfilAdminPersistsCedulaYTelefono(): void {
        $db = $this->getDb();
        $usuariosModel = new UsuariosModel();

        // Crear o utilizar usuario admin de prueba
        $testUsuario = 'admin_test_' . time();
        $testEmail = 'admintest_' . time() . '@example.com';
        $testCedula = 'V-987' . rand(1000, 9999);
        $testTelefono = '0412' . rand(1000000, 9999999);
        $passHash = password_hash('Pass123456!', PASSWORD_BCRYPT);

        $stmt = $db->prepare("INSERT INTO usuarios (usuario, email, cedula, telefono, password, nombre_completo, rol, estado) 
                              VALUES (:usuario, :email, :cedula, :telefono, :password, 'Admin Test', 'admin', 1)");
        $stmt->execute([
            'usuario'   => $testUsuario,
            'email'     => $testEmail,
            'cedula'    => $testCedula,
            'telefono'  => $testTelefono,
            'password'  => $passHash
        ]);
        $testUserId = (int)$db->lastInsertId();

        $this->assertTrue($testUserId > 0, "Debe haberse insertado el usuario de prueba");

        // Actualizar cédula y teléfono de manera autónoma
        $nuevaCedula = 'V-988' . rand(1000, 9999);
        $nuevoTelefono = '0424' . rand(1000000, 9999999);
        $nuevoNombre = 'Admin Actualizado';

        $resultado = $usuariosModel->actualizarPerfil($testUserId, [
            'nombre_completo' => $nuevoNombre,
            'email'           => $testEmail,
            'cedula'          => $nuevaCedula,
            'telefono'        => $nuevoTelefono,
            'password'        => null
        ]);

        $this->assertTrue($resultado, "actualizarPerfil debe retornar true");

        // Verificar datos en la base de datos
        $adminActualizado = $usuariosModel->getById($testUserId);
        $this->assertEquals($nuevoNombre, $adminActualizado['nombre_completo'], "El nombre debe haberse actualizado");
        $this->assertEquals($nuevaCedula, $adminActualizado['cedula'], "La cédula debe haberse actualizado");
        $this->assertEquals($nuevoTelefono, $adminActualizado['telefono'], "El teléfono debe haberse actualizado");

        // Limpiar registro de prueba
        $db->exec("DELETE FROM usuarios WHERE id = {$testUserId}");
    }

    public function testCedulaExisteEnOtroUsuario(): void {
        $db = $this->getDb();
        $usuariosModel = new UsuariosModel();

        $cedula1 = 'V-111' . rand(1000, 9999);
        $email1 = 'user1_' . time() . '@example.com';
        $passHash = password_hash('Pass123456!', PASSWORD_BCRYPT);

        $stmt = $db->prepare("INSERT INTO usuarios (usuario, email, cedula, password, nombre_completo, rol, estado) 
                              VALUES (:usuario, :email, :cedula, :password, 'User 1', 'admin', 1)");
        $stmt->execute([
            'usuario'  => 'user1_' . time(),
            'email'    => $email1,
            'cedula'   => $cedula1,
            'password' => $passHash
        ]);
        $id1 = (int)$db->lastInsertId();

        // Para el mismo usuario id1, no debe considerarse duplicado
        $this->assertFalse(
            $usuariosModel->cedulaExisteEnOtroUsuario($cedula1, $id1),
            "No debe marcar como duplicada la cédula para el mismo usuario"
        );

        // Para un usuario diferente id2, debe detectar la colisión
        $this->assertTrue(
            $usuariosModel->cedulaExisteEnOtroUsuario($cedula1, $id1 + 9999),
            "Debe marcar como duplicada la cédula si ya pertenece a otro usuario"
        );

        // Limpiar
        $db->exec("DELETE FROM usuarios WHERE id = {$id1}");
    }

    public function testPerfilViewContainsAdminFieldsAndToggle(): void {
        $viewContent = file_get_contents(dirname(__DIR__) . '/app/views/perfil/index.php');

        // Campos de Cédula y Teléfono
        $this->assertStringContains('name="cedula"', $viewContent, "La vista debe contener un input con name='cedula'");
        $this->assertStringContains('id="admin_cedula"', $viewContent, "La vista debe contener id='admin_cedula'");
        $this->assertStringContains('name="telefono"', $viewContent, "La vista debe contener un input con name='telefono'");
        $this->assertStringContains('id="admin_telefono"', $viewContent, "La vista debe contener id='admin_telefono'");

        // Contraseña y botón de visibilidad (Toggle)
        $this->assertStringContains('id="admin_password"', $viewContent, "La vista debe contener id='admin_password'");
        $this->assertStringContains('id="btnTogglePassword"', $viewContent, "La vista debe contener id='btnTogglePassword'");
        $this->assertStringContains('togglePassword', $viewContent, "La vista debe incluir la función togglePassword");
        $this->assertStringContains('visibility', $viewContent, "La vista debe incluir el icono de visibilidad");
    }

    public function testValidacionesFormatoCedulaYTelefono(): void {
        // Validación de cédulas válidas e inválidas
        $this->assertTrue(validarCedula('V-12345678'), "V-12345678 debe ser válida");
        $this->assertTrue(validarCedula('12345678'), "12345678 debe ser válida");
        $this->assertFalse(validarCedula('abc'), "Cédula alfabética debe ser rechazada");
        $this->assertFalse(validarCedula('123'), "Cédula menor a 5 dígitos debe ser rechazada");

        // Validación de teléfonos válidos e inválidos
        $this->assertTrue(validarTelefono('04121234567'), "04121234567 debe ser válido");
        $this->assertTrue(validarTelefono('04249876543'), "04249876543 debe ser válido");
        $this->assertFalse(validarTelefono('02121234567'), "0212 (fijo) debe ser rechazado para móvil");
        $this->assertFalse(validarTelefono('12345'), "Teléfono corto debe ser rechazado");
    }
}
