<?php
namespace Tests;

/**
 * Botón "Eliminar edificio" (solo admin) en la pestaña Configuración de Estructura.
 * Cubre ruta, vista (form + confirm), controlador y las reglas de rechazo/limpieza del modelo.
 */
class EstructuraEliminarEdificioTest extends TestCase {

    /**
     * Extrae el cuerpo de un método público de un archivo fuente.
     */
    private function extraerMetodo(string $rutaArchivo, string $metodo): string {
        $contenido = file_get_contents($rutaArchivo);
        preg_match('/public function ' . preg_quote($metodo, '/') . '\(.*?\)(.*?)(?=public function|private function|protected function|\Z)/s', $contenido, $m);
        return $m[1] ?? '';
    }

    /**
     * Devuelve el primer edificio con unidades que tengan propietario o residentes,
     * o 0 si no existe ninguno para validar el rechazo.
     */
    private function buscarEdificioConDependencias(): int {
        $db = \App\Core\Database::getConnection();
        return (int)($db->query(
            "SELECT u.edificio_id FROM unidades u
             LEFT JOIN personas p ON p.unidad_id = u.id
             WHERE u.propietario_id IS NOT NULL OR p.id IS NOT NULL
             LIMIT 1"
        )->fetchColumn() ?: 0);
    }

    public function testRutaEliminarEdificioRegistradaSoloParaAdmin(): void {
        $index = file_get_contents(BASE_PATH . '/public/index.php');

        $this->assertStringContains("'/admin/estructura/eliminar-edificio'", $index,
            'public/index.php debe registrar la ruta /admin/estructura/eliminar-edificio');

        $linea = '';
        foreach (explode("\n", $index) as $l) {
            if (str_contains($l, '/admin/estructura/eliminar-edificio')) {
                $linea = $l;
                break;
            }
        }
        $this->assertStringContains("EstructuraController::class, 'eliminarEdificio'", $linea,
            'La ruta debe apuntar a EstructuraController::eliminarEdificio');
        $this->assertStringContains('[UserRole::ADMIN]', $linea,
            'La ruta debe exigir únicamente UserRole::ADMIN');
        $this->assertFalse(str_contains($linea, 'UserRole::AUDITOR'),
            'La ruta de eliminación no debe permitir el rol AUDITOR');
    }

    public function testVistaEstructuraContieneFormularioEliminarConConfirmacion(): void {
        $vista = file_get_contents(BASE_PATH . '/app/views/admin/estructura.php');

        $posForm = strpos($vista, 'action="/admin/estructura/eliminar-edificio"');
        $this->assertTrue($posForm !== false,
            'La vista debe contener el formulario POST hacia /admin/estructura/eliminar-edificio');

        // La etiqueta <form ...> empieza antes del atributo action.
        $posFormTag = strrpos(substr($vista, 0, $posForm), '<form');
        $this->assertTrue($posFormTag !== false, 'El action de eliminación debe vivir dentro de un <form>');

        $fragmento = substr($vista, $posFormTag, 1200);
        $this->assertStringContains('method="POST"', $fragmento, 'El formulario debe ser POST');
        $this->assertStringContains('csrf_field()', $fragmento, 'El formulario debe incluir token CSRF');
        $this->assertStringContains('name="id"', $fragmento, 'El formulario debe enviar el id del edificio');
        $this->assertStringContains('name="tab"', $fragmento, 'El formulario debe preservar el tab');
        $this->assertStringContains('return confirm(', $fragmento, 'El formulario debe pedir confirmación');
        $this->assertStringContains('¿Seguro que deseas eliminar el edificio «', $fragmento,
            'El confirm debe interpolar el nombre del edificio');
        $this->assertStringContains('Se eliminarán también sus unidades sin propietario ni deudas.', $fragmento,
            'El confirm debe advertir que se eliminarán las unidades limpias');
        $this->assertStringContains('Esta acción no se puede deshacer.', $fragmento,
            'El confirm debe advertir que la acción es irreversible');
        $this->assertStringContains('btn-outline-danger', $fragmento, 'El botón debe usar estilo danger outline');
        $this->assertStringContains('delete', $fragmento, 'El botón debe usar el icono delete');

        // El formulario debe quedar bajo el guard de rol que excluye al auditor.
        preg_match_all("/Auth::role\(\) !== 'auditor'/", $vista, $guards, PREG_OFFSET_CAPTURE);
        $posGuard = -1;
        foreach ($guards[0] as $g) {
            if ($g[1] < $posForm && $g[1] > $posGuard) {
                $posGuard = $g[1];
            }
        }
        $this->assertTrue($posGuard >= 0 && ($posForm - $posGuard) < 1200,
            'El formulario de eliminación debe quedar bajo el guard de rol (el auditor no debe verlo)');
        $this->assertFalse(str_contains(substr($vista, $posGuard, $posForm - $posGuard), '<?php endif; ?>'),
            'El guard de rol no debe cerrarse antes del formulario de eliminación');
    }

    public function testControladorEliminarEdificioSoloAdminYDelegaEnModelo(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/EstructuraController.php', 'eliminarEdificio');

        $this->assertTrue($metodo !== '', 'EstructuraController debe tener el método eliminarEdificio');
        $this->assertStringContains("Auth::requireRole(['admin'])", $metodo,
            'eliminarEdificio debe exigir SOLO el rol admin');
        $this->assertFalse(str_contains($metodo, "requireRole(['admin', 'auditor'])"),
            'eliminarEdificio no debe permitir el rol auditor');
        $this->assertStringContains('eliminarSiLimpio', $metodo,
            'Debe delegar la transacción en EdificiosModel::eliminarSiLimpio');
        $this->assertStringContains('No se puede eliminar', $metodo,
            'Debe mostrar el rechazo por unidades con propietario o deudas');
        $this->assertStringContains('invalidarCacheEstructura', $metodo,
            'Debe invalidar la caché de estructura tras eliminar');
    }

    public function testModeloContieneReglaDeRechazoYTransaccion(): void {
        $modelo = file_get_contents(BASE_PATH . '/app/models/EdificiosModel.php');

        $this->assertStringContains('function eliminarSiLimpio(int $id): array', $modelo,
            'El modelo debe exponer eliminarSiLimpio(int $id): array');
        $this->assertStringContains('unidades con propietario o deudas asociadas', $modelo,
            'El motivo de rechazo debe mencionar unidades con propietario o deudas');
        $this->assertStringContains('FROM facturas', $modelo, 'Debe bloquear si hay facturas');
        $this->assertStringContains('FROM movimientos_cuenta', $modelo, 'Debe bloquear si hay movimientos de cuenta');
        $this->assertStringContains('FROM pagos', $modelo, 'Debe bloquear si hay pagos');
        $this->assertStringContains("'eliminar_edificio'", $modelo, 'Debe registrar la acción en log_auditoria');
        $this->assertStringContains('beginTransaction', $modelo, 'La eliminación debe ser transaccional');
    }

    public function testModeloRechazaIdInexistente(): void {
        $modelo = new \App\Models\EdificiosModel();
        $res = $modelo->eliminarSiLimpio(999999999);

        $this->assertIsArray($res, 'eliminarSiLimpio debe devolver un array');
        $this->assertFalse($res['ok'] ?? true, 'Un id inexistente no debe eliminar nada');
        $this->assertStringContains('no encontrado', (string)($res['motivo'] ?? ''),
            'Debe indicar que el edificio no existe');
    }

    public function testModeloRechazaEdificioConDependencias(): void {
        $idBloqueado = $this->buscarEdificioConDependencias();
        if ($idBloqueado <= 0) {
            $this->skip('No hay edificios con propietarios o residentes para validar el rechazo');
            return;
        }

        $modelo = new \App\Models\EdificiosModel();
        $res = $modelo->eliminarSiLimpio($idBloqueado);

        $this->assertFalse($res['ok'] ?? true, 'El edificio con propietario o residentes no debe eliminarse');
        $this->assertStringContains('deudas asociadas', (string)($res['motivo'] ?? ''),
            'El motivo debe mencionar unidades con propietario o deudas asociadas');
        $this->assertNotEquals(false, $modelo->getById($idBloqueado),
            'El edificio debe seguir existiendo tras el rechazo');
    }

    public function testControladorRechazaEdificioConDependenciasYRedirigeAlTab(): void {
        $idBloqueado = $this->buscarEdificioConDependencias();
        if ($idBloqueado <= 0) {
            $this->skip('No hay edificios con propietarios o residentes para validar el rechazo');
            return;
        }

        $_SESSION['auth_user'] = ['id' => 1, 'role' => 'admin', 'name' => 'Admin de Prueba'];
        $_POST = ['id' => $idBloqueado, 'tab' => 'configuracion'];

        $ctrl = new class extends \App\Controllers\EstructuraController {
            public string $redirectUrl = '';
            protected function redirect($url): void {
                $this->redirectUrl = $url;
            }
        };

        $ctrl->eliminarEdificio();

        $this->assertEquals('/admin/estructura?tab=configuracion', $ctrl->redirectUrl,
            'Debe redirigir a la pestaña de configuración');
        $error = \App\Core\Flash::get('error');
        $this->assertStringContains('No se puede eliminar', $error, 'Debe mostrar un Flash de error');
        $this->assertStringContains('deudas asociadas', $error, 'El mensaje debe indicar propietario o deudas asociadas');
        $this->assertNotEquals(false, (new \App\Models\EdificiosModel())->getById($idBloqueado),
            'El edificio bloqueado debe seguir existiendo');
    }

    public function testModeloEliminaEdificioLimpioConRegistrosHijosYAuditoria(): void {
        $db = \App\Core\Database::getConnection();
        $sufijo = bin2hex(random_bytes(4));
        $nombreEdificio = 'Edificio Test ' . $sufijo;
        $numeroUnidad = 'U-' . $sufijo;
        $numeroPuesto = 'P-' . $sufijo;
        $cedula = 'V-' . $sufijo;
        $email = 'zzdel_' . $sufijo . '@test.local';
        $edificioId = 0;

        try {
            $db->prepare("INSERT INTO edificios (nombre, descripcion, estado) VALUES (:nombre, 'Temporal para test', 1)")
               ->execute(['nombre' => $nombreEdificio]);
            $edificioId = (int)$db->lastInsertId();

            $db->prepare("INSERT INTO unidades (edificio_id, propietario_id, numero, cuota_mensual, estado) VALUES (:edificio_id, NULL, :numero, 0, 1)")
               ->execute(['edificio_id' => $edificioId, 'numero' => $numeroUnidad]);
            $unidadId = (int)$db->lastInsertId();

            $db->prepare("INSERT INTO estacionamientos (unidad_id, numero, tipo, edificio_id, estado) VALUES (:unidad_id, :numero, 'regular', :edificio_id, 1)")
               ->execute(['unidad_id' => $unidadId, 'numero' => $numeroPuesto, 'edificio_id' => $edificioId]);

            $db->prepare("INSERT INTO solicitudes_registro (cedula, nombre, apellido, telefono, email, unidad_id, numero_residentes, password_hash, estado) VALUES (:cedula, 'Test', 'Temporal', '0000000000', :email, :unidad_id, 1, 'hash', 'pendiente')")
               ->execute(['cedula' => $cedula, 'email' => $email, 'unidad_id' => $unidadId]);

            $modelo = new \App\Models\EdificiosModel();
            $res = $modelo->eliminarSiLimpio($edificioId);

            $this->assertTrue($res['ok'] ?? false,
                'El edificio limpio debe poder eliminarse: ' . ($res['motivo'] ?? ''));

            $stmt = $db->prepare("SELECT COUNT(*) FROM edificios WHERE id = :id");
            $stmt->execute(['id' => $edificioId]);
            $this->assertEquals(0, (int)$stmt->fetchColumn(), 'El edificio debe quedar eliminado');

            $stmt = $db->prepare("SELECT COUNT(*) FROM unidades WHERE numero = :numero");
            $stmt->execute(['numero' => $numeroUnidad]);
            $this->assertEquals(0, (int)$stmt->fetchColumn(), 'Sus unidades deben quedar eliminadas');

            $stmt = $db->prepare("SELECT COUNT(*) FROM estacionamientos WHERE numero = :numero");
            $stmt->execute(['numero' => $numeroPuesto]);
            $this->assertEquals(0, (int)$stmt->fetchColumn(), 'Sus estacionamientos deben quedar eliminados');

            $stmt = $db->prepare("SELECT COUNT(*) FROM solicitudes_registro WHERE cedula = :cedula");
            $stmt->execute(['cedula' => $cedula]);
            $this->assertEquals(0, (int)$stmt->fetchColumn(), 'Sus solicitudes de registro deben quedar eliminadas');

            $stmt = $db->prepare("SELECT COUNT(*) FROM log_auditoria WHERE accion = 'eliminar_edificio' AND registro_id = :id");
            $stmt->execute(['id' => $edificioId]);
            $this->assertEquals(1, (int)$stmt->fetchColumn(),
                'Debe existir exactamente un registro de auditoría de la eliminación');
        } finally {
            // Limpieza defensiva (no-op si la eliminación ya ocurrió).
            $db->prepare("DELETE FROM log_auditoria WHERE accion = 'eliminar_edificio' AND registro_id = :id")
               ->execute(['id' => $edificioId]);
            $db->prepare("DELETE FROM solicitudes_registro WHERE cedula = :cedula")->execute(['cedula' => $cedula]);
            $db->prepare("DELETE FROM estacionamientos WHERE numero = :numero")->execute(['numero' => $numeroPuesto]);
            $db->prepare("DELETE FROM unidades WHERE numero = :numero")->execute(['numero' => $numeroUnidad]);
            $db->prepare("DELETE FROM edificios WHERE nombre = :nombre")->execute(['nombre' => $nombreEdificio]);
        }
    }
}
