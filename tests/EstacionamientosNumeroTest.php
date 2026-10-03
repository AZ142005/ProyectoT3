<?php
namespace Tests;

use App\Core\Database;
use App\Models\EstacionamientosModel;
use PDO;

/**
 * Pruebas Fase 18 — número de puesto de estacionamiento único:
 * - numeroExists detecta duplicados activos.
 * - excluirId permite conservar el mismo número al editar.
 * - Un puesto borrado no cuenta y libera el número.
 * - El controlador valida en create y update con Flash de error.
 */
class EstacionamientosNumeroTest extends TestCase {

    private const PREFIJO = 'TEST-F18-EST-';

    private function crearUnidad(PDO $db): int {
        $stmt = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:numero, 100.00, 1)");
        $stmt->execute(['numero' => 'U-' . self::PREFIJO . bin2hex(random_bytes(4))]);
        return intval($db->lastInsertId());
    }

    private function crearPuesto(PDO $db, int $unidadId, string $numero): int {
        $stmt = $db->prepare("
            INSERT INTO estacionamientos (unidad_id, numero, tipo, estado)
            VALUES (:unidad_id, :numero, 'descubierto', 1)
        ");
        $stmt->execute(['unidad_id' => $unidadId, 'numero' => $numero]);
        return intval($db->lastInsertId());
    }

    private function limpiar(PDO $db): void {
        $db->exec("DELETE FROM estacionamientos WHERE numero LIKE '" . self::PREFIJO . "%'");
        $db->exec("DELETE FROM unidades WHERE numero LIKE 'U-" . self::PREFIJO . "%'");
    }

    public function testNumeroExistenteDetectadoYDistintoPasa(): void {
        $db = Database::getConnection();
        $model = new EstacionamientosModel();
        $sufijo = bin2hex(random_bytes(4));
        $numero = self::PREFIJO . $sufijo;
        $unidadId = 0;

        try {
            $unidadId = $this->crearUnidad($db);
            $puestoId = $this->crearPuesto($db, $unidadId, $numero);
            $this->assertTrue($puestoId > 0, 'El puesto de prueba debe crearse');

            $this->assertTrue($model->numeroExists($numero), 'Un número activo existente debe detectarse');

            // Flujo legítimo: un número distinto pasa
            $this->assertFalse($model->numeroExists($numero . '-B'), 'Un número distinto no debe bloquear');

            // Registro directo del segundo puesto con número distinto (unidad nueva)
            $unidadId2 = $this->crearUnidad($db);
            $this->assertTrue($this->crearPuesto($db, $unidadId2, $numero . '-B') > 0, 'Un número distinto debe poder registrarse');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testExcluirIdPermiteMismoNumeroEnEdicion(): void {
        $db = Database::getConnection();
        $model = new EstacionamientosModel();
        $sufijo = bin2hex(random_bytes(4));
        $numero = self::PREFIJO . $sufijo;
        $unidadId = 0;

        try {
            $unidadId = $this->crearUnidad($db);
            $puestoId = $this->crearPuesto($db, $unidadId, $numero);

            // Al editar el propio puesto, su número no debe bloquearse
            $this->assertFalse($model->numeroExists($numero, $puestoId), 'excluirId debe permitir conservar el mismo número al editar');
            // Sin excluir, sí bloquea
            $this->assertTrue($model->numeroExists($numero), 'Sin excluir, el número propio sigue existiendo');
            // Excluyendo otro puesto, el número sigue detectado
            $this->assertTrue($model->numeroExists($numero, $puestoId + 99999), 'Excluir un ID ajeno no debe ocultar el duplicado');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testPuestoBorradoNoCuentaYLiberaNumero(): void {
        $db = Database::getConnection();
        $model = new EstacionamientosModel();
        $sufijo = bin2hex(random_bytes(4));
        $numero = self::PREFIJO . $sufijo;
        $unidadId = 0;

        try {
            $unidadId = $this->crearUnidad($db);
            $puestoId = $this->crearPuesto($db, $unidadId, $numero);
            $this->assertTrue($model->numeroExists($numero), 'Antes del borrado el número existe');

            $this->assertTrue($model->softDelete($puestoId), 'El soft delete debe ejecutarse');

            $this->assertFalse($model->numeroExists($numero), 'Un puesto borrado no debe contar como número existente');

            // El número queda libre para otro puesto
            $unidadId2 = $this->crearUnidad($db);
            $this->assertTrue($this->crearPuesto($db, $unidadId2, $numero) > 0, 'El número liberado debe poder reutilizarse');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testControladorValidaNumeroEnCreateYUpdate(): void {
        $contenido = (string)file_get_contents(dirname(__DIR__) . '/app/controllers/EstacionamientoController.php');

        $this->assertStringContains('numeroExists($numero, $id)', $contenido,
            'EstacionamientoController::guardar debe validar el número (create y update) con numeroExists');
        $this->assertStringContains("Ya existe un puesto con el número", $contenido,
            'Debe mostrarse el Flash de error con el número duplicado');

        $posValidacion = strpos($contenido, 'numeroExists($numero, $id)');
        $posUpdate = strpos($contenido, '$estacionamientosModel->update(');
        $posCreate = strpos($contenido, '$estacionamientosModel->create(');
        $this->assertTrue($posValidacion !== false && $posUpdate !== false && $posCreate !== false && $posValidacion < $posUpdate && $posValidacion < $posCreate,
            'La validación debe ejecutarse antes de insertar o actualizar');
    }
}
