<?php
namespace Tests;

use App\Controllers\UsuarioAdminController;
use App\Models\UsuariosModel;
use App\Models\PersonasModel;
use App\Core\Database;
use PDO;

class UsuarioAdminAccionesTest extends TestCase {

    private function getDb(): PDO {
        return Database::getConnection();
    }

    /**
     * Prueba que PersonasModel actualiza el teléfono correctamente.
     */
    public function testPersonasModelActualizarTelefono(): void {
        $db = $this->getDb();
        $personasModel = new PersonasModel();

        $cedula = 'V-88899911';
        $email = 'test_tel_' . time() . '@example.com';
        $db->exec("DELETE FROM personas WHERE cedula = '{$cedula}'");

        $db->exec("INSERT INTO personas (cedula, nombre, apellido, email, telefono, tipo, estado) 
                   VALUES ('{$cedula}', 'Juan', 'Perez', '{$email}', '04121112233', 'propietario', 1)");
        $personaId = (int)$db->lastInsertId();

        $this->assertTrue($personaId > 0, "El residente de prueba debe crearse");

        $nuevoTel = '04149998877';
        $ok = $personasModel->actualizarTelefono($personaId, $nuevoTel);
        $this->assertTrue($ok, "actualizarTelefono debe retornar true");

        $stmt = $db->prepare("SELECT telefono FROM personas WHERE id = :id");
        $stmt->execute(['id' => $personaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals($nuevoTel, $row['telefono'], "El teléfono debe actualizarse en la base de datos");

        // Limpiar
        $db->exec("DELETE FROM personas WHERE id = {$personaId}");
    }

    /**
     * Prueba que eliminarResidente realiza soft delete, desvincula la unidad y conserva pagos históricos.
     */
    public function testPersonasModelEliminarResidentePreservaHistorialContable(): void {
        $db = $this->getDb();
        $personasModel = new PersonasModel();

        // 1. Crear edificio y unidad temporal
        $db->exec("INSERT INTO edificios (nombre, descripcion, estado) VALUES ('Edif Elim Test', 'Desc Test', 1)");
        $edificioId = (int)$db->lastInsertId();

        $db->exec("INSERT INTO unidades (edificio_id, numero, estado) VALUES ({$edificioId}, 'E-999', 1)");
        $unidadId = (int)$db->lastInsertId();

        // 2. Crear persona vinculada a la unidad
        $cedula = 'V-77788899';
        $email = 'elim_test_' . time() . '@example.com';
        $db->exec("DELETE FROM personas WHERE cedula = '{$cedula}'");
        $db->exec("INSERT INTO personas (cedula, nombre, apellido, email, telefono, tipo, unidad_id, estado, password) 
                   VALUES ('{$cedula}', 'Carlos', 'Venta', '{$email}', '04125556677', 'propietario', {$unidadId}, 1, 'hash_pass')");
        $personaId = (int)$db->lastInsertId();

        // Asignar propietario en la unidad
        $db->exec("UPDATE unidades SET propietario_id = {$personaId} WHERE id = {$unidadId}");

        // 3. Crear pago histórico del residente para comprobar integridad referencial
        $db->exec("INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, archivo, estado) 
                   VALUES ({$personaId}, {$unidadId}, 50.00, '2026-09-01', 'transferencia', 'comprobante_dummy.jpg', 'APROBADO')");
        $pagoId = (int)$db->lastInsertId();
        $this->assertTrue($pagoId > 0, "El pago histórico debe haberse registrado");

        // 4. Ejecutar eliminación lógica
        $ok = $personasModel->eliminarResidente($personaId);
        $this->assertTrue($ok, "eliminarResidente debe retornar true");

        // 5. Verificaciones
        // a) Persona debe tener estado = 0, unidad_id = NULL, password = NULL
        $stmtP = $db->prepare("SELECT estado, unidad_id, password FROM personas WHERE id = :id");
        $stmtP->execute(['id' => $personaId]);
        $personaRow = $stmtP->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(0, (int)$personaRow['estado'], "El estado del residente debe ser 0 (inactivo/soft-delete)");
        $this->assertNull($personaRow['unidad_id'], "unidad_id en personas debe ser NULL tras eliminación");
        $this->assertNull($personaRow['password'], "password en personas debe ser NULL para revocar acceso");

        // b) Unidad debe tener propietario_id = NULL
        $stmtU = $db->prepare("SELECT propietario_id FROM unidades WHERE id = :id");
        $stmtU->execute(['id' => $unidadId]);
        $unidadRow = $stmtU->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($unidadRow['propietario_id'], "propietario_id en unidades debe ser NULL (unidad liberada)");

        // c) Pago histórico en tabla pagos debe permanecer INTACTO
        $stmtPago = $db->prepare("SELECT id, residente_id, monto, estado FROM pagos WHERE id = :id");
        $stmtPago->execute(['id' => $pagoId]);
        $pagoRow = $stmtPago->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($pagoRow, "El registro de pago histórico debe conservarse intacto");
        $this->assertEquals($personaId, (int)$pagoRow['residente_id'], "residente_id en pagos debe mantenerse para trazabilidad contable");

        // Limpieza de datos temporales
        $db->exec("DELETE FROM pagos WHERE id = {$pagoId}");
        $db->exec("DELETE FROM personas WHERE id = {$personaId}");
        $db->exec("DELETE FROM unidades WHERE id = {$unidadId}");
        $db->exec("DELETE FROM edificios WHERE id = {$edificioId}");
    }

    /**
     * Prueba que el backend restringe modificar datos a cuentas de administrador.
     */
    public function testBackendRestringeModificarDatosAAdministradores(): void {
        $db = $this->getDb();

        // Crear admin secundario temporal
        $db->exec("DELETE FROM usuarios WHERE usuario = 'admin_target_secundario'");
        $db->exec("INSERT INTO usuarios (usuario, email, cedula, password, nombre_completo, rol, estado) 
                   VALUES ('admin_target_secundario', 'secundario_mod@test.com', 'V-66655544', 'dummy_hash', 'Admin Secundario', 'admin', 1)");
        $admin2Id = (int)$db->lastInsertId();

        $ctrl = new class extends UsuarioAdminController {
            public string $redirectUrl = '';
            protected function redirect($url): void {
                $this->redirectUrl = $url;
            }
        };

        $_SESSION['auth_user'] = [
            'id'    => 1,
            'role'  => 'admin',
            'name'  => 'Administrador Principal'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['tipo_entidad']     = 'usuario';
        $_POST['id']               = $admin2Id;
        $_POST['telefono']         = '04141234567';

        $ctrl->actualizarDatos();

        $errorMsg = \App\Core\Flash::get('error');
        $this->assertEquals('/admin/usuarios', $ctrl->redirectUrl, "Debe redirigir a /admin/usuarios");
        $this->assertStringContains('No tienes permisos para modificar los datos de un usuario con rol de Administrador', $errorMsg,
            "El backend debe rechazar actualizar datos de un administrador");

        // Limpiar
        $db->exec("DELETE FROM usuarios WHERE id = {$admin2Id}");
    }

    /**
     * Prueba que el backend restringe eliminar a administradores.
     */
    public function testBackendRestringeEliminarAdministradores(): void {
        $ctrl = new class extends UsuarioAdminController {
            public string $redirectUrl = '';
            protected function redirect($url): void {
                $this->redirectUrl = $url;
            }
        };

        $_SESSION['auth_user'] = [
            'id'    => 1,
            'role'  => 'admin',
            'name'  => 'Administrador Principal'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['tipo_entidad']     = 'usuario';
        $_POST['id']               = 1;

        $ctrl->eliminar();

        $errorMsg = \App\Core\Flash::get('error');
        $this->assertEquals('/admin/usuarios', $ctrl->redirectUrl, "Debe redirigir a /admin/usuarios");
        $this->assertStringContains('No está permitido eliminar cuentas administrativas', $errorMsg,
            "El backend debe rechazar terminantemente la eliminación de cuentas del sistema o administradores");
    }

    /**
     * Prueba que el backend valida el formato del número telefónico.
     */
    public function testBackendValidaFormatoTelefono(): void {
        $db = $this->getDb();

        $cedula = 'V-55544433';
        $email = 'test_bad_tel_' . time() . '@example.com';
        $db->exec("DELETE FROM personas WHERE cedula = '{$cedula}'");
        $db->exec("INSERT INTO personas (cedula, nombre, apellido, email, telefono, tipo, estado) 
                   VALUES ('{$cedula}', 'Carlos', 'Test', '{$email}', '04121112233', 'propietario', 1)");
        $personaId = (int)$db->lastInsertId();

        $ctrl = new class extends UsuarioAdminController {
            public string $redirectUrl = '';
            protected function redirect($url): void {
                $this->redirectUrl = $url;
            }
        };

        $_SESSION['auth_user'] = [
            'id'    => 1,
            'role'  => 'admin',
            'name'  => 'Administrador Principal'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['tipo_entidad']     = 'persona';
        $_POST['id']               = $personaId;
        $_POST['telefono']         = '12345'; // Teléfono inválido

        $ctrl->actualizarDatos();

        $errorMsg = \App\Core\Flash::get('error');
        $this->assertEquals('/admin/usuarios', $ctrl->redirectUrl, "Debe redirigir a /admin/usuarios");
        $this->assertStringContains('El formato del teléfono no es válido', $errorMsg,
            "Debe arrojar error si el formato del teléfono no cumple con los prefijos venezolanos válidos");

        $db->exec("DELETE FROM personas WHERE id = {$personaId}");
    }

    /**
     * Verifica la deprecación total del módulo 'Solicitudes de datos' en sidebar, vistas y rutas.
     */
    public function testDeprecacionTotalModuloSolicitudesDatos(): void {
        // 1. Sidebar no debe incluir Solicitudes Datos
        $sidebarContent = file_get_contents(dirname(__DIR__) . '/app/views/layouts/admin_sidebar.php');
        $this->assertFalse(str_contains($sidebarContent, "['route' => 'solicitudes'"), "admin_sidebar.php no debe contener la ruta solicitudes");
        $this->assertFalse(str_contains($sidebarContent, "'/admin/solicitudes-datos'"), "admin_sidebar.php no debe vincular a /admin/solicitudes-datos");

        // 2. La vista antigua admin/solicitudes_datos/index.php no debe existir
        $viewPath = dirname(__DIR__) . '/app/views/admin/solicitudes_datos/index.php';
        $this->assertFalse(file_exists($viewPath), "La vista antigua admin/solicitudes_datos/index.php debe haber sido removida");
    }

    /**
     * Verifica que la vista de usuarios tenga el menú de tres puntos (kebab) y los modales con doble confirmación y cooldown de 10s.
     */
    public function testVistaUsuariosKebabMenuYModalesSeguridad(): void {
        $viewPath = dirname(__DIR__) . '/app/views/admin/usuarios/index.php';
        $this->assertFileExists($viewPath, "La vista app/views/admin/usuarios/index.php debe existir");

        $content = file_get_contents($viewPath);

        // Kebab menu
        $this->assertStringContains('more_vert', $content, "La vista debe incluir el icono more_vert para el menú kebab");
        $this->assertStringContains('Reiniciar clave', $content, "El menú debe incluir la opción Reiniciar clave");
        $this->assertStringContains('Actualizar datos', $content, "El menú debe incluir la opción Actualizar datos");
        $this->assertStringContains('Eliminar', $content, "El menú debe incluir la opción Eliminar");

        // Modal Actualizar Datos
        $this->assertStringContains('modalActualizarDatos', $content, "La vista debe incluir el modal modalActualizarDatos");
        $this->assertStringContains('/admin/usuarios/actualizar-datos', $content, "El modal debe apuntar a /admin/usuarios/actualizar-datos");

        // Modal Eliminar con Doble Confirmación y Temporizador
        $this->assertStringContains('modalEliminarUsuario', $content, "La vista debe incluir el modal modalEliminarUsuario");
        $this->assertStringContains('cuerpoEliminarPaso1', $content, "El modal de eliminación debe incluir el Paso 1 de confirmación");
        $this->assertStringContains('cuerpoEliminarPaso2', $content, "El modal de eliminación debe incluir el Paso 2 de confirmación final");
        $this->assertStringContains('contadorCooldown', $content, "El modal debe incluir el elemento contadorCooldown");
        $this->assertStringContains('10s', $content, "El temporizador debe inicializarse con 10s");
        $this->assertStringContains('/admin/usuarios/eliminar', $content, "El modal debe apuntar al endpoint /admin/usuarios/eliminar");
    }

    /**
     * Un administrador puede eliminar una cuenta de auditor (soft-delete),
     * y nadie puede eliminar su propia cuenta.
     */
    public function testEliminarUsuarioAuditorDesactivaCuenta(): void {
        $db = $this->getDb();
        $sufijo = time();
        $usuario = 'del_aud_' . $sufijo;

        $db->exec("DELETE FROM usuarios WHERE usuario = '{$usuario}'");
        $db->exec("INSERT INTO usuarios (usuario, email, password, nombre_completo, rol, estado)
                   VALUES ('{$usuario}', 'del_aud_{$sufijo}@example.com', 'hash_dummy', 'Auditor Borrar', 'auditor', 1)");
        $targetId = (int)$db->lastInsertId();

        $ctrl = new class extends UsuarioAdminController {
            public string $redirectUrl = '';
            protected function redirect($url): void {
                $this->redirectUrl = $url;
            }
        };

        // 1) Auto-eliminación bloqueada (sesión del propio auditor)
        $_SESSION['auth_user'] = ['id' => $targetId, 'role' => 'admin', 'name' => 'Auditor Borrar'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['tipo_entidad' => 'usuario', 'id' => $targetId];
        $ctrl->eliminar();
        $this->assertStringContains('propia cuenta', \App\Core\Flash::get('error'),
            'No debe poder eliminarse a sí mismo');

        // 2) Un administrador la elimina correctamente (soft-delete)
        $_SESSION['auth_user'] = ['id' => 1, 'role' => 'admin', 'name' => 'Administrador Principal'];
        $ctrl->eliminar();
        $this->assertEquals('/admin/usuarios', $ctrl->redirectUrl, 'Debe redirigir tras eliminar');
        $this->assertStringContains('eliminado', \App\Core\Flash::get('success'),
            'Debe confirmar la eliminación del usuario');

        $stmt = $db->prepare("SELECT estado FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $targetId]);
        $this->assertEquals(0, (int)$stmt->fetchColumn(), 'La cuenta debe quedar desactivada (soft-delete)');

        $db->exec("DELETE FROM usuarios WHERE id = {$targetId}");
    }

    public function testPersonasModelActualizarDatosPersonaYCedulaNormalizada(): void {
        $db = $this->getDb();
        $model = new PersonasModel();

        $cedulaVieja = 'V-90123456';
        $cedulaNueva = 'V-65432109';
        $emailViejo = 'test_datos_' . time() . '@example.com';
        $emailNuevo = 'test_datos_nuevo_' . time() . '@example.com';

        $db->exec("DELETE FROM personas WHERE cedula IN ('{$cedulaVieja}', '{$cedulaNueva}')");
        $db->exec("INSERT INTO personas (cedula, nombre, apellido, email, telefono, tipo, estado)
                   VALUES ('{$cedulaVieja}', 'Nombre', 'Viejo', '{$emailViejo}', '04121112233', 'propietario', 1)");
        $personaId = (int)$db->lastInsertId();

        try {
            $this->assertFalse($model->cedulaExistsNormalizada($cedulaVieja, $personaId),
                "La cédula propia no debe contar como duplicada al excluirse");

            $ok = $model->actualizarDatosPersona($personaId, 'Nuevo', 'Apellido', $cedulaNueva, '04149998877', $emailNuevo);
            $this->assertTrue($ok, "actualizarDatosPersona debe retornar true");

            $stmt = $db->prepare("SELECT nombre, apellido, cedula, telefono, email FROM personas WHERE id = :id");
            $stmt->execute(['id' => $personaId]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->assertEquals('Nuevo', $fila['nombre'], "El nombre debe actualizarse");
            $this->assertEquals('Apellido', $fila['apellido'], "El apellido debe actualizarse");
            $this->assertEquals($cedulaNueva, $fila['cedula'], "La cédula debe actualizarse");
            $this->assertEquals('04149998877', $fila['telefono'], "El teléfono debe actualizarse");
            $this->assertEquals($emailNuevo, $fila['email'], "El email debe actualizarse");

            $this->assertTrue($model->cedulaExistsNormalizada($cedulaNueva),
                "La cédula nueva debe detectarse como existente");
        } finally {
            $db->exec("DELETE FROM personas WHERE id = {$personaId}");
        }
    }

    public function testBackendActualizaNombreCedulaYContacto(): void {
        $db = $this->getDb();

        $cedulaVieja = 'V-90123457';
        $cedulaNueva = 'V-65432108';
        $emailViejo = 'test_upd_' . time() . '@example.com';
        $emailNuevo = 'test_upd_nuevo_' . time() . '@example.com';

        $db->exec("DELETE FROM personas WHERE cedula IN ('{$cedulaVieja}', '{$cedulaNueva}')");
        $db->exec("INSERT INTO personas (cedula, nombre, apellido, email, telefono, tipo, estado)
                   VALUES ('{$cedulaVieja}', 'Antiguo', 'Nombre', '{$emailViejo}', '04121112233', 'propietario', 1)");
        $personaId = (int)$db->lastInsertId();

        $ctrl = new class extends UsuarioAdminController {
            public string $redirectUrl = '';
            protected function redirect($url): void {
                $this->redirectUrl = $url;
            }
        };

        $_SESSION['auth_user'] = [
            'id'   => 1,
            'role' => 'admin',
            'name' => 'Administrador Principal',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'tipo_entidad' => 'persona',
            'id'           => $personaId,
            'nombre'       => 'Corregido',
            'apellido'     => 'Rápido',
            'cedula'       => $cedulaNueva,
            'telefono'     => '',
            'email'        => $emailNuevo,
        ];

        try {
            $ctrl->actualizarDatos();

            $this->assertEquals('/admin/usuarios', $ctrl->redirectUrl, "Debe redirigir tras actualizar");
            $this->assertStringContains('Datos actualizados', \App\Core\Flash::get('success'),
                "Debe confirmar la actualización");

            $stmt = $db->prepare("SELECT nombre, apellido, cedula, telefono, email FROM personas WHERE id = :id");
            $stmt->execute(['id' => $personaId]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->assertEquals('Corregido', $fila['nombre'], "El nombre debe actualizarse");
            $this->assertEquals('Rápido', $fila['apellido'], "El apellido debe actualizarse");
            $this->assertEquals(normalizarCedula($cedulaNueva), $fila['cedula'],
                "La cédula cambiada debe guardarse normalizada");
            $this->assertTrue($fila['telefono'] === null,
                "Sin teléfono debe guardarse NULL para no bloquear la corrección");
            $this->assertEquals($emailNuevo, $fila['email'], "El email debe actualizarse");
        } finally {
            $db->exec("DELETE FROM personas WHERE id = {$personaId}");
        }
    }

    public function testModalActualizarDatosPermiteEditarNombreYCedula(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/views/admin/usuarios/index.php');

        $this->assertStringContains('id="act_nombre"', $content, "El modal debe tener el input de nombre");
        $this->assertStringContains('name="nombre"', $content, "El nombre debe enviarse al backend");
        $this->assertStringContains('id="act_apellido"', $content, "El modal debe tener el input de apellido");
        $this->assertStringContains('name="apellido"', $content, "El apellido debe enviarse al backend");
        $this->assertStringContains('id="act_cedula"', $content, "El modal debe tener el input de cédula");
        $this->assertStringContains('name="cedula"', $content, "La cédula debe enviarse al backend");
        $this->assertStringContains('data-nombre-pila', $content, "El botón debe pasar el nombre de pila");
        $this->assertStringContains('data-apellido', $content, "El botón debe pasar el apellido");
        $this->assertStringContains("document.getElementById('act_nombre').value", $content,
            "El JS debe prellenar el nombre");
        $this->assertStringContains("document.getElementById('act_cedula').value", $content,
            "El JS debe prellenar la cédula");
    }

    public function testEdicionCedulaSinCambioNoBloqueaDuplicado(): void {
        $content = file_get_contents(dirname(__DIR__) . '/app/Controllers/UsuarioAdminController.php');

        $this->assertStringContains(
            '$cedulaNormalizada !== $cedulaActual && $personasModel->cedulaExistsNormalizada',
            $content,
            'Solo debe validar duplicado cuando la cédula cambia; guardar una cédula ya duplicada sin modificarla no debe bloquear'
        );
    }
}
