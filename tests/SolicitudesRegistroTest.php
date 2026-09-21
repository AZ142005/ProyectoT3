<?php
namespace Tests;

use App\Core\Database;
use App\Models\SolicitudesRegistroModel;
use App\Models\UnidadesModel;
use PDO;

class SolicitudesRegistroTest extends TestCase {

    private function getDb(): PDO {
        return Database::getConnection();
    }

    /**
     * Limpia datos de prueba creados durante los tests.
     */
    private function cleanup(string $cedula, string $email, int $unidadId = 0): void {
        $db = $this->getDb();
        $db->exec("DELETE FROM log_auditoria WHERE detalles LIKE '%{$cedula}%'");
        $db->exec("DELETE FROM solicitudes_registro WHERE cedula = '{$cedula}' OR email = '{$email}'");
        $db->exec("DELETE FROM personas WHERE cedula = '{$cedula}' OR email = '{$email}'");
        if ($unidadId > 0) {
            $db->exec("UPDATE unidades SET propietario_id = NULL WHERE id = {$unidadId}");
        }
    }

    public function testClassesExist(): void {
        $this->assertTrue(class_exists(\App\Models\SolicitudesRegistroModel::class), "SolicitudesRegistroModel debe existir");
        $this->assertTrue(class_exists(\App\Controllers\SolicitudesRegistroController::class), "SolicitudesRegistroController debe existir");
    }

    public function testTableStructure(): void {
        $db = $this->getDb();
        $stmt = $db->query("SHOW TABLES LIKE 'solicitudes_registro'");
        $this->assertEquals(1, $stmt->rowCount(), "La tabla solicitudes_registro debe existir");

        $stmtCols = $db->query("SHOW COLUMNS FROM solicitudes_registro LIKE 'estado'");
        $this->assertEquals(1, $stmtCols->rowCount(), "La columna estado debe existir en solicitudes_registro");

        $stmtNumRes = $db->query("SHOW COLUMNS FROM personas LIKE 'numero_residentes'");
        $this->assertEquals(1, $stmtNumRes->rowCount(), "La columna numero_residentes debe existir en personas");
    }

    public function testGetDisponiblesReturnsArray(): void {
        $unidadesModel = new UnidadesModel();
        $disponibles = $unidadesModel->getDisponibles();
        $this->assertTrue(is_array($disponibles), "getDisponibles() debe retornar un array");
    }

    public function testCrearSolicitudValidationAndConcurrency(): void {
        $db = $this->getDb();
        $model = new SolicitudesRegistroModel();

        // 1. Unidad inexistente
        $threw = false;
        try {
            $model->crearSolicitud([
                'unidad_id'         => 9999999,
                'cedula'            => 'V-99990001',
                'nombre'            => 'Test',
                'apellido'          => 'Unit',
                'email'             => 'test_nonexistent@example.com',
                'telefono'          => '04121112233',
                'numero_residentes' => 2,
                'password_hash'     => password_hash('Pass12345', PASSWORD_BCRYPT),
            ]);
        } catch (\RuntimeException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, "Crear solicitud en unidad inexistente debe lanzar RuntimeException");

        // 2. Buscar una unidad disponible o crear un edificio y unidad temporal para pruebas
        $stmtEd = $db->query("SELECT id FROM edificios WHERE estado = 1 LIMIT 1");
        $edificioId = $stmtEd->fetchColumn();
        if (!$edificioId) {
            $db->exec("INSERT INTO edificios (nombre, direccion, estado) VALUES ('Edif Test Solicitud', 'Calle Test', 1)");
            $edificioId = (int)$db->lastInsertId();
        }

        $testNumero = 'TEST-' . rand(1000, 9999);
        $stmtInsU = $db->prepare("
            INSERT INTO unidades (edificio_id, numero, cuota_mensual, estado, propietario_id)
            VALUES (:eid, :num, 50.00, 1, NULL)
        ");
        $stmtInsU->execute(['eid' => $edificioId, 'num' => $testNumero]);
        $testUnidadId = (int)$db->lastInsertId();

        $testCedula = 'V-' . rand(80000000, 89999999);
        $testEmail = 'solicitante_' . rand(1000, 9999) . '@test.com';

        try {
            // Verificar que la unidad aparece en disponibles
            $unidadesModel = new UnidadesModel();
            $disponiblesAntes = $unidadesModel->getDisponibles();
            $encontrada = false;
            foreach ($disponiblesAntes as $u) {
                if ((int)$u['id'] === $testUnidadId) {
                    $encontrada = true;
                    break;
                }
            }
            $this->assertTrue($encontrada, "La unidad recién creada debe figurar en getDisponibles()");

            // Crear solicitud legítima
            $solicitudId = $model->crearSolicitud([
                'unidad_id'         => $testUnidadId,
                'cedula'            => $testCedula,
                'nombre'            => 'Pedro',
                'apellido'          => 'Pérez',
                'email'             => $testEmail,
                'telefono'          => '04149876543',
                'numero_residentes' => 3,
                'password_hash'     => password_hash('Segura123', PASSWORD_BCRYPT),
            ]);
            $this->assertGreaterThan(0, $solicitudId, "crearSolicitud debe retornar un ID mayor a cero");

            // Verificar que la solicitud está en estado 'pendiente'
            $detalle = $model->getDetalleById($solicitudId);
            $this->assertNotNull($detalle, "El detalle de la solicitud debe ser consultable");
            $this->assertEquals('pendiente', $detalle['estado'], "El estado inicial de la solicitud debe ser 'pendiente'");
            $this->assertEquals(3, (int)$detalle['numero_residentes'], "numero_residentes debe coincidir");

            // Verificar que la unidad ya NO aparece en getDisponibles()
            $disponiblesDespues = $unidadesModel->getDisponibles();
            $aunDisponible = false;
            foreach ($disponiblesDespues as $u) {
                if ((int)$u['id'] === $testUnidadId) {
                    $aunDisponible = true;
                    break;
                }
            }
            $this->assertFalse($aunDisponible, "La unidad con solicitud pendiente debe excluirse de getDisponibles()");

            // Intentar crear otra solicitud para la MISMA unidad debe fallar
            $threwMismaUnidad = false;
            try {
                $model->crearSolicitud([
                    'unidad_id'         => $testUnidadId,
                    'cedula'            => 'V-' . rand(70000000, 79999999),
                    'nombre'            => 'Otro',
                    'apellido'          => 'Usuario',
                    'email'             => 'otro_' . rand(1000, 9999) . '@test.com',
                    'telefono'          => '04161234567',
                    'numero_residentes' => 1,
                    'password_hash'     => password_hash('Segura123', PASSWORD_BCRYPT),
                ]);
            } catch (\RuntimeException $e) {
                $threwMismaUnidad = true;
            }
            $this->assertTrue($threwMismaUnidad, "No debe permitirse una segunda solicitud pendiente para la misma unidad");

            // Intentar crear otra solicitud con la MISMA cédula debe fallar
            $threwMismaCedula = false;
            try {
                $model->crearSolicitud([
                    'unidad_id'         => $testUnidadId,
                    'cedula'            => $testCedula,
                    'nombre'            => 'Pedro',
                    'apellido'          => 'Pérez',
                    'email'             => 'distinto_' . rand(1000, 9999) . '@test.com',
                    'telefono'          => '04161234567',
                    'numero_residentes' => 1,
                    'password_hash'     => password_hash('Segura123', PASSWORD_BCRYPT),
                ]);
            } catch (\RuntimeException $e) {
                $threwMismaCedula = true;
            }
            $this->assertTrue($threwMismaCedula, "No debe permitirse solicitud con cédula duplicada en estado pendiente");

            // Test de Aprobación
            $adminId = 1;
            $aprobado = $model->aprobar($solicitudId, $adminId, '127.0.0.1');
            $this->assertTrue($aprobado, "aprobar() debe retornar true");

            // Verificar que la solicitud ahora tiene estado 'aprobada'
            $detalleAprobado = $model->getDetalleById($solicitudId);
            $this->assertEquals('aprobada', $detalleAprobado['estado'], "El estado debe cambiar a 'aprobada'");

            // Verificar que la persona se creó y está activa en 'personas'
            $stmtPersona = $db->prepare("SELECT * FROM personas WHERE cedula = :cedula");
            $stmtPersona->execute(['cedula' => $testCedula]);
            $persona = $stmtPersona->fetch(PDO::FETCH_ASSOC);
            $this->assertNotNull($persona, "La persona debe haber sido creada en personas");
            $this->assertEquals(1, (int)$persona['estado'], "El estado de la persona debe ser 1 (activo)");
            $this->assertEquals($testUnidadId, (int)$persona['unidad_id'], "La unidad asignada debe coincidir");
            $this->assertEquals(3, (int)$persona['numero_residentes'], "numero_residentes debe coincidir en personas");

            // Verificar que la unidad tiene propietario_id asignado a la persona
            $stmtUnidadCheck = $db->prepare("SELECT propietario_id FROM unidades WHERE id = :id");
            $stmtUnidadCheck->execute(['id' => $testUnidadId]);
            $propietarioId = (int)$stmtUnidadCheck->fetchColumn();
            $this->assertEquals((int)$persona['id'], $propietarioId, "La unidad debe tener asignado como propietario_id el ID de la persona");

            // Verificar que se registró la auditoría
            $stmtAudit = $db->prepare("SELECT COUNT(*) FROM log_auditoria WHERE accion = 'APROBACION_REGISTRO' AND registro_id = :id");
            $stmtAudit->execute(['id' => $solicitudId]);
            $this->assertGreaterThan(0, (int)$stmtAudit->fetchColumn(), "Debe haberse registrado auditoría APROBACION_REGISTRO");

        } finally {
            // Limpieza
            $this->cleanup($testCedula, $testEmail, $testUnidadId);
            $db->exec("DELETE FROM unidades WHERE id = {$testUnidadId}");
        }
    }

    public function testRechazarSolicitudWorkflow(): void {
        $db = $this->getDb();
        $model = new SolicitudesRegistroModel();

        $stmtEd = $db->query("SELECT id FROM edificios WHERE estado = 1 LIMIT 1");
        $edificioId = $stmtEd->fetchColumn();
        if (!$edificioId) {
            $db->exec("INSERT INTO edificios (nombre, direccion, estado) VALUES ('Edif Test Solicitud 2', 'Calle Test', 1)");
            $edificioId = (int)$db->lastInsertId();
        }

        $testNumero = 'TEST-RECH-' . rand(1000, 9999);
        $stmtInsU = $db->prepare("
            INSERT INTO unidades (edificio_id, numero, cuota_mensual, estado, propietario_id)
            VALUES (:eid, :num, 45.00, 1, NULL)
        ");
        $stmtInsU->execute(['eid' => $edificioId, 'num' => $testNumero]);
        $testUnidadId = (int)$db->lastInsertId();

        $testCedula = 'V-' . rand(70000000, 79999999);
        $testEmail = 'solicitante_rech_' . rand(1000, 9999) . '@test.com';

        try {
            $solicitudId = $model->crearSolicitud([
                'unidad_id'         => $testUnidadId,
                'cedula'            => $testCedula,
                'nombre'            => 'María',
                'apellido'          => 'Gómez',
                'email'             => $testEmail,
                'telefono'          => '04245556677',
                'numero_residentes' => 2,
                'password_hash'     => password_hash('Clave1234', PASSWORD_BCRYPT),
            ]);

            $rechazado = $model->rechazar($solicitudId, 1, 'Datos incompletos en el registro', '127.0.0.1');
            $this->assertTrue($rechazado, "rechazar() debe retornar true");

            $detalle = $model->getDetalleById($solicitudId);
            $this->assertEquals('rechazada', $detalle['estado'], "El estado debe ser 'rechazada'");
            $this->assertEquals('Datos incompletos en el registro', $detalle['motivo_rechazo'], "El motivo de rechazo debe guardarse");

            // La unidad debe volver a figurar en disponibles
            $unidadesModel = new UnidadesModel();
            $disponibles = $unidadesModel->getDisponibles();
            $encontrada = false;
            foreach ($disponibles as $u) {
                if ((int)$u['id'] === $testUnidadId) {
                    $encontrada = true;
                    break;
                }
            }
            $this->assertTrue($encontrada, "Tras rechazo, la unidad debe volver a estar disponible");

            // Verificar auditoría de rechazo
            $stmtAudit = $db->prepare("SELECT COUNT(*) FROM log_auditoria WHERE accion = 'RECHAZO_REGISTRO' AND registro_id = :id");
            $stmtAudit->execute(['id' => $solicitudId]);
            $this->assertGreaterThan(0, (int)$stmtAudit->fetchColumn(), "Debe haberse registrado auditoría RECHAZO_REGISTRO");

        } finally {
            $this->cleanup($testCedula, $testEmail, $testUnidadId);
            $db->exec("DELETE FROM unidades WHERE id = {$testUnidadId}");
        }
    }
}
