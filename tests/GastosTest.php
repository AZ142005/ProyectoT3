<?php
namespace Tests;

use App\Models\CategoriasGastosModel;
use App\Models\GastosModel;
use App\Models\ConciliacionModel;
use App\Models\MovimientosModel;

class GastosTest extends TestCase {

    public function testModelClassesExist() {
        $this->assertTrue(class_exists(CategoriasGastosModel::class));
        $this->assertTrue(class_exists(GastosModel::class));
        $this->assertTrue(class_exists(ConciliacionModel::class));
        $this->assertTrue(class_exists(MovimientosModel::class));
    }
}
