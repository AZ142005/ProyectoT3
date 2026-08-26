<?php
namespace Tests;

use Tests\TestCase;
use App\Models\EstacionamientosModel;
use App\Models\VehiculosModel;

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
}
