<?php
namespace Tests;

use App\Controllers\UsuarioAdminController;
use App\Core\Database;
use PDO;

class UsuarioCrearRolTest extends TestCase {

    private function getDb(): PDO {
        return Database::getConnection();
    }

    /**
     * Crea un controlador anónimo que captura el redirect y deja la sesión
     * autenticada como administrador, siguiendo el patrón de UsuarioAdminAccionesTest.
     */
    private function crearControlador(): UsuarioAdminController {
        $_SESSION['auth_user'] = [
            'id'    => 1,
            'role'  => 'admin',
            'name'  => 'Administrador Principal'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        return new class extends UsuarioAdminController {
            public string $redirectUrl = '';
            protected function redirect($url): void {
                $this->redirectUrl = $url;
            }
        };
    }

    /**
     * El alta de un auditor debe persistir el rol, el estado activo y el hash bcrypt.
     */
    public function testCrearUsuarioAuditorPersisteConHashYEstadoActivo(): void {
        $db = $this->getDb();
        $sufijo = time();
        $usuario = 'auditor_test_' . $sufijo;
        $email = 'auditor_test_' . $sufijo . '@example.com';
        $password = 'Clave12345';

        $db->exec("DELETE FROM usuarios WHERE usuario = '{$usuario}'");

        $ctrl = $this->crearControlador();
        $_POST = [
            'nombre_completo' => 'Auditor de Prueba',
            'usuario'         => $usuario,
            'email'           => $email,
            'cedula'          => 'V' . rand(10000000, 99999999),
            'telefono'        => '04121234567',
            'password'        => $password,
            'rol'             => 'auditor'
        ];

        $ctrl->crearUsuario();

        $this->assertEquals('/admin/usuarios', $ctrl->redirectUrl, 'Debe redirigir a /admin/usuarios tras el alta');
        $this->assertStringContains('creado correctamente con rol Auditor', \App\Core\Flash::get('success'),
            'Debe emitir un flash de éxito al crear el usuario');

        $stmt = $db->prepare("SELECT rol, estado, password, email FROM usuarios WHERE usuario = :usuario");
        $stmt->execute(['usuario' => $usuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertTrue(is_array($row), 'El usuario auditor debe persistirse en la base de datos');
        if (is_array($row)) {
            $this->assertEquals('auditor', $row['rol'], "El rol persistido debe ser 'auditor'");
            $this->assertEquals(1, (int)$row['estado'], 'El usuario debe crearse con estado activo (1)');
            $this->assertNotEquals($password, $row['password'], 'La contraseña no debe almacenarse en texto plano');
            $this->assertTrue(password_verify($password, $row['password']), 'La contraseña debe almacenarse con hash bcrypt verificable');
            $this->assertEquals($email, $row['email'], 'El correo debe persistirse normalizado en minúsculas');
        }

        $db->exec("DELETE FROM usuarios WHERE usuario = '{$usuario}'");
    }

    /**
     * El alta debe rechazar un correo repetido y un nombre de usuario repetido,
     * sin insertar registros nuevos.
     */
    public function testCrearUsuarioRechazaDuplicados(): void {
        $db = $this->getDb();
        $sufijo = time();
        $usuarioBase = 'dup_base_' . $sufijo;
        $usuarioNuevo = 'dup_nuevo_' . $sufijo;
        $emailBase = 'dup_base_' . $sufijo . '@example.com';

        $db->exec("DELETE FROM usuarios WHERE usuario IN ('{$usuarioBase}', '{$usuarioNuevo}')");
        $db->exec("INSERT INTO usuarios (usuario, email, password, nombre_completo, rol, estado)
                   VALUES ('{$usuarioBase}', '{$emailBase}', 'hash_dummy', 'Usuario Base', 'admin', 1)");
        $baseId = (int)$db->lastInsertId();

        // 1) Correo electrónico repetido
        $ctrl = $this->crearControlador();
        $_POST = [
            'nombre_completo' => 'Usuario Duplicado',
            'usuario'         => $usuarioNuevo,
            'email'           => $emailBase,
            'password'        => 'Clave12345',
            'rol'             => 'auditor'
        ];
        $ctrl->crearUsuario();

        $this->assertEquals('/admin/usuarios', $ctrl->redirectUrl, 'Debe redirigir al rechazar el correo duplicado');
        $this->assertStringContains('correo electrónico ya se encuentra registrado', \App\Core\Flash::get('error'),
            'Debe rechazar un correo electrónico ya registrado');

        // 2) Nombre de usuario repetido
        $_POST = [
            'nombre_completo' => 'Usuario Duplicado',
            'usuario'         => $usuarioBase,
            'email'           => 'dup_nuevo_' . $sufijo . '@example.com',
            'password'        => 'Clave12345',
            'rol'             => 'auditor'
        ];
        $ctrl->crearUsuario();

        $this->assertStringContains('nombre de usuario ya se encuentra registrado', \App\Core\Flash::get('error'),
            'Debe rechazar un nombre de usuario ya registrado');

        $stmtCount = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = :u1 OR usuario = :u2");
        $stmtCount->execute(['u1' => $usuarioBase, 'u2' => $usuarioNuevo]);
        $this->assertEquals(1, (int)$stmtCount->fetchColumn(),
            'Ningún intento duplicado debe insertar un usuario adicional');

        $db->exec("DELETE FROM usuarios WHERE id = {$baseId}");
        $db->exec("DELETE FROM usuarios WHERE usuario = '{$usuarioNuevo}'");
    }

    /**
     * El cambio de rol debe actualizar la fila del usuario destino.
     */
    public function testCambiarRolActualizaRol(): void {
        $db = $this->getDb();
        $sufijo = time();
        $usuario = 'rol_test_' . $sufijo;

        $db->exec("DELETE FROM usuarios WHERE usuario = '{$usuario}'");
        $db->exec("INSERT INTO usuarios (usuario, email, password, nombre_completo, rol, estado)
                   VALUES ('{$usuario}', 'rol_test_{$sufijo}@example.com', 'hash_dummy', 'Cambio Rol Test', 'auditor', 1)");
        $targetId = (int)$db->lastInsertId();

        $ctrl = $this->crearControlador();
        $_POST = ['id' => $targetId, 'nuevo_rol' => 'admin'];
        $ctrl->cambiarRol();

        $this->assertEquals('/admin/usuarios', $ctrl->redirectUrl, 'Debe redirigir a /admin/usuarios tras cambiar el rol');
        $this->assertStringContains('actualizado a Administrador', \App\Core\Flash::get('success'),
            'Debe emitir un flash de éxito al cambiar el rol');

        $stmt = $db->prepare("SELECT rol FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $targetId]);
        $this->assertEquals('admin', $stmt->fetchColumn(), 'El rol debe actualizarse a admin en la base de datos');

        $db->exec("DELETE FROM usuarios WHERE id = {$targetId}");
    }

    /**
     * Degradar al último administrador activo debe bloquearse y no modificar el rol.
     * Todo ocurre dentro de una transacción con rollback para restaurar el estado previo.
     */
    public function testCambiarRolBloqueaDegradarAlUltimoAdminActivo(): void {
        $db = $this->getDb();
        $sufijo = time();
        $usuario = 'rol_unico_' . $sufijo;

        $db->beginTransaction();
        try {
            $db->exec("INSERT INTO usuarios (usuario, email, password, nombre_completo, rol, estado)
                       VALUES ('{$usuario}', 'rol_unico_{$sufijo}@example.com', 'hash_dummy', 'Único Admin Activo', 'admin', 1)");
            $targetId = (int)$db->lastInsertId();

            // Desactivar temporalmente al resto de administradores activos para que
            // el destino sea el último; los cambios solo son visibles en esta transacción.
            $db->exec("UPDATE usuarios SET estado = 0 WHERE rol = 'admin' AND estado = 1 AND id != {$targetId}");

            $ctrl = $this->crearControlador();
            $_POST = ['id' => $targetId, 'nuevo_rol' => 'auditor'];
            $ctrl->cambiarRol();

            $this->assertEquals('/admin/usuarios', $ctrl->redirectUrl, 'Debe redirigir al bloquear la degradación');
            $this->assertStringContains('último administrador activo', \App\Core\Flash::get('error'),
                'Debe bloquear la degradación del último administrador activo');

            $stmt = $db->prepare("SELECT rol FROM usuarios WHERE id = :id");
            $stmt->execute(['id' => $targetId]);
            $this->assertEquals('admin', $stmt->fetchColumn(), 'El rol del último administrador activo no debe cambiar');
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }
    }

    /**
     * Un administrador no puede cambiar el rol de su propio usuario.
     */
    public function testCambiarRolBloqueaCambiarElPropioRol(): void {
        $db = $this->getDb();
        $sufijo = time();
        $usuario = 'rol_propio_' . $sufijo;

        $db->beginTransaction();
        try {
            $db->exec("INSERT INTO usuarios (usuario, email, password, nombre_completo, rol, estado)
                       VALUES ('{$usuario}', 'rol_propio_{$sufijo}@example.com', 'hash_dummy', 'Admin Propio', 'admin', 1)");
            $targetId = (int)$db->lastInsertId();

            $ctrl = new class extends UsuarioAdminController {
                public string $redirectUrl = '';
                protected function redirect($url): void {
                    $this->redirectUrl = $url;
                }
            };
            $_SESSION['auth_user'] = ['id' => $targetId, 'role' => 'admin', 'name' => 'Admin Propio'];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = ['id' => $targetId, 'nuevo_rol' => 'auditor'];

            $ctrl->cambiarRol();

            $this->assertEquals('/admin/usuarios', $ctrl->redirectUrl, 'Debe redirigir al bloquear el cambio de rol propio');
            $this->assertStringContains('propio usuario', \App\Core\Flash::get('error'),
                'Debe bloquear el cambio de rol de su propio usuario');

            $stmt = $db->prepare("SELECT rol FROM usuarios WHERE id = :id");
            $stmt->execute(['id' => $targetId]);
            $this->assertEquals('admin', $stmt->fetchColumn(), 'El rol propio no debe cambiar');
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }
    }

    /**
     * El alta de usuarios está limitada al rol Auditor: enviar "admin" debe rechazarse sin insertar.
     */
    public function testCrearUsuarioRechazaRolAdmin(): void {
        $db = $this->getDb();
        $sufijo = time();
        $usuario = 'rol_admin_' . $sufijo;

        $db->exec("DELETE FROM usuarios WHERE usuario = '{$usuario}'");

        $ctrl = $this->crearControlador();
        $_POST = [
            'nombre_completo' => 'Intento Admin',
            'usuario'         => $usuario,
            'email'           => $usuario . '@example.com',
            'password'        => 'Clave12345',
            'rol'             => 'admin'
        ];
        $ctrl->crearUsuario();

        $this->assertEquals('/admin/usuarios', $ctrl->redirectUrl, 'Debe redirigir al rechazar el rol admin');
        $this->assertStringContains('limitada al rol Auditor', \App\Core\Flash::get('error'),
            'Debe indicar que el alta está limitada al rol Auditor');

        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = :u");
        $stmt->execute(['u' => $usuario]);
        $this->assertEquals(0, (int)$stmt->fetchColumn(), 'No debe insertarse el usuario con rol admin');
    }
}
