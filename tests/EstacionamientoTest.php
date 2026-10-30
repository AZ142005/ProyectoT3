<?php
namespace Tests;

use Tests\TestCase;
use App\Core\Database;
use App\Models\EstacionamientosModel;
use App\Models\UnidadesModel;
use App\Models\VehiculosModel;
use PDO;

/**
 * Tests para EstacionamientosModel y VehiculosModel (RF 12)
 */
class EstacionamientoTest extends TestCase {

    public function testEstacionamientosModelClassExists(): void {
        $this->assertTrue(class_exists(EstacionamientosModel::class));
    }

    public function testVehiculosModelClassExists(): void {
        $this->assertTrue(class_exists(VehiculosModel::class));
    }

    public function testEstacionamientoTiposConstanteEsValida(): void {
        $tipos = EstacionamientosModel::TIPOS_VALIDOS;
        $this->assertIsArray($tipos);
        $this->assertContains('techado', $tipos);
        $this->assertContains('descubierto', $tipos);
        $this->assertContains('visitante', $tipos);
    }

    public function testNormalizarPlacaConvierteAMayusculasYSinEspacios(): void {
        $model = new VehiculosModel();
        $this->assertEquals('ABC1234', $model->normalizarPlaca(' abc 1234 '));
        $this->assertEquals('AB-123-CD', $model->normalizarPlaca(' ab-123-cd '));
    }

    public function testAsignarYLiberarPuestoConNull(): void {
        $db = Database::getConnection();
        $model = new EstacionamientosModel();
        $sufijo = bin2hex(random_bytes(4));
        $numUnidad = 'U-T-' . $sufijo;
        $numPuesto = 'TEST-EST-' . $sufijo;
        $unidadIds = [];
        $puestoId = 0;

        try {
            $stmt = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:numero, 100.00, 1)");
            $stmt->execute(['numero' => $numUnidad]);
            $unidadId = (int)$db->lastInsertId();
            $unidadIds[] = $unidadId;

            $stmt = $db->prepare("INSERT INTO estacionamientos (unidad_id, numero, tipo, estado) VALUES (:unidad_id, :numero, 'descubierto', 1)");
            $stmt->execute(['unidad_id' => $unidadId, 'numero' => $numPuesto]);
            $puestoId = (int)$db->lastInsertId();

            $this->assertTrue($model->asignarAUnidad($puestoId, $unidadId), "Debe asignar el puesto a la unidad");

            $this->assertTrue($model->asignarAUnidad($puestoId, null), "Debe permitir liberar el puesto con NULL");

            $stmt = $db->prepare("SELECT unidad_id FROM estacionamientos WHERE id = :id");
            $stmt->execute(['id' => $puestoId]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->assertTrue(
                $fila !== false && $fila['unidad_id'] === null,
                "El puesto liberado debe quedar con unidad_id NULL"
            );

            $stmt = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:numero, 100.00, 1)");
            $stmt->execute(['numero' => $numUnidad . '-2']);
            $unidadId2 = (int)$db->lastInsertId();
            $unidadIds[] = $unidadId2;

            $this->assertTrue($model->asignarAUnidad($puestoId, $unidadId2), "Debe permitir reasignar el puesto liberado");
        } finally {
            if ($puestoId > 0) {
                $db->prepare("DELETE FROM estacionamientos WHERE id = :id")->execute(['id' => $puestoId]);
            }
            foreach ($unidadIds as $uid) {
                $db->prepare("DELETE FROM unidades WHERE id = :id")->execute(['id' => $uid]);
            }
        }
    }

    public function testGetActivasIncluyeEdificioYPropietario(): void {
        $model = new UnidadesModel();
        $unidades = $model->getActivas(5);

        $this->assertTrue(is_array($unidades), "getActivas debe devolver un array");
        if (!empty($unidades)) {
            $this->assertTrue(array_key_exists('edificio_nombre', $unidades[0]), "getActivas debe incluir edificio_nombre");
            $this->assertTrue(array_key_exists('propietario_nombre', $unidades[0]), "getActivas debe incluir propietario_nombre");
        }
    }

    public function testModalAsignacionUsaCascadaEdificioUnidadSinResidente(): void {
        $view = file_get_contents(VIEWS_PATH . '/admin/estacionamientos/index.php');
        $controller = file_get_contents(dirname(__DIR__) . '/app/Controllers/EstacionamientoController.php');
        $routes = file_get_contents(dirname(__DIR__) . '/public/index.php');

        $this->assertStringContains('js-edificio-asignar', $view,
            "El modal debe tener el selector de edificio");
        $this->assertStringContains('js-unidad-asignar', $view,
            "El modal debe tener el selector de unidad");
        $this->assertStringContains('cargarUnidadesAsignacion', $view,
            "Debe existir la carga en cascada edificio -> unidad");
        $this->assertStringContains("'Apto. ' + unidad.numero", $view,
            "La opción de unidad debe mostrar solo el identificador, sin el residente");
        $this->assertStringContains('persona_nombre', $view,
            "La tabla general debe seguir mostrando el propietario/residente");
        $this->assertFalse(str_contains($view, "['piso']"),
            "El modal no debe usar la clave inexistente piso");
        $this->assertStringContains('function unidades', $controller,
            "El controlador debe exponer el endpoint de unidades por edificio");
        $this->assertStringContains('/admin/estacionamientos/unidades', $routes,
            "La ruta del endpoint de unidades debe estar registrada");
    }
}
