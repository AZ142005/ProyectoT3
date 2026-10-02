<?php
namespace Tests;

use App\Models\UnidadesModel;
use App\Models\EstacionamientosModel;
use App\Controllers\EstructuraController;

/**
 * Suite de pruebas para la asignación automática de puestos de estacionamiento
 * al crear unidades y la migración/backfill de unidades existentes.
 */
class AsignacionEstacionamientoTest extends TestCase {

    /**
     * Verifica que los métodos requeridos existan en los modelos.
     */
    public function testMetodosYClasesExisten(): void {
        $this->assertTrue(class_exists(UnidadesModel::class), 'La clase UnidadesModel debe existir');
        $this->assertTrue(method_exists(UnidadesModel::class, 'createWithEstacionamiento'),
            'UnidadesModel debe implementar el método createWithEstacionamiento');
        $this->assertTrue(method_exists(UnidadesModel::class, 'tieneEstacionamientoAsignado'),
            'UnidadesModel debe implementar el método tieneEstacionamientoAsignado');
        $this->assertTrue(class_exists(EstacionamientosModel::class), 'La clase EstacionamientosModel debe existir');
    }

    /**
     * Verifica que la vista admin/estructura.php contenga el checkbox de asignación automática.
     */
    public function testVistaEstructuraContieneControlCheckbox(): void {
        $vistaPath = dirname(__DIR__) . '/app/views/admin/estructura.php';
        $this->assertFileExists($vistaPath, 'La vista app/views/admin/estructura.php debe existir');

        $content = file_get_contents($vistaPath);
        $this->assertStringContains('name="asignar_estacionamiento"', $content,
            'La vista debe contener el input con name="asignar_estacionamiento"');
        $this->assertStringContains('id="asignar_estacionamiento"', $content,
            'La vista debe contener el input con id="asignar_estacionamiento"');
        $this->assertStringContains('Asignar puesto de estacionamiento automáticamente', $content,
            'La vista debe contener la etiqueta explicativa para la asignación automática');
        $this->assertStringContains('contenedor_auto_estacionamiento', $content,
            'La vista debe contener el contenedor condicional para la asignación automática');
    }

    /**
     * Verifica que EstructuraController gestione la bandera de asignación de puesto de estacionamiento.
     */
    public function testControladorProcesaAsignacionEstacionamiento(): void {
        $controllerPath = dirname(__DIR__) . '/app/controllers/EstructuraController.php';
        $content = file_get_contents($controllerPath);

        $this->assertStringContains('asignar_estacionamiento', $content,
            'EstructuraController debe procesar el campo asignar_estacionamiento de la petición POST');
        $this->assertStringContains('createWithEstacionamiento', $content,
            'EstructuraController debe invocar createWithEstacionamiento');
    }

    /**
     * Verifica que el script de migración exista y cumpla con las reglas de negocio:
     * idempotencia, prevención de duplicados, nomenclatura automática y transaccionalidad.
     */
    public function testScriptMigracionExisteYCumpleRequisitos(): void {
        $scriptPath = dirname(__DIR__) . '/scripts/migrate_asignar_estacionamientos_unidades.php';
        $this->assertFileExists($scriptPath, 'El script scripts/migrate_asignar_estacionamientos_unidades.php debe existir');

        $content = file_get_contents($scriptPath);

        $this->assertStringContains('unidad_id', $content,
            'El script debe validar si la unidad ya tiene un puesto asignado');
        $this->assertStringContains('Puesto - ', $content,
            'El script debe utilizar la nomenclatura automática "Puesto - [Número]"');
        $this->assertStringContains('beginTransaction', $content,
            'El script debe utilizar transacciones atómicas de base de datos');
        $this->assertStringContains('commit', $content,
            'El script debe confirmar la transacción al finalizar con éxito');
        $this->assertStringContains('rollBack', $content,
            'El script debe revertir la transacción en caso de excepción');
    }

    /**
     * Verifica que el archivo de migración SQL de soporte exista y amplíe el tamaño de la columna.
     */
    public function testSqlPhase14Existe(): void {
        $sqlPath = dirname(__DIR__) . '/scripts/migrations_phase14.sql';
        $this->assertFileExists($sqlPath, 'El archivo scripts/migrations_phase14.sql debe existir');

        $content = file_get_contents($sqlPath);
        $this->assertStringContains('MODIFY COLUMN `numero` VARCHAR(50)', $content,
            'El script SQL debe modificar la columna numero para soportar hasta 50 caracteres');
    }
}
