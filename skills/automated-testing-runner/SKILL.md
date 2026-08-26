---
name: automated-testing-runner
description: >-
  Playbook para ejecutar, escribir y mantener pruebas unitarias y de comportamiento en PHP sin dependencias pesadas.
  Úsalo cuando el usuario pida verificar el funcionamiento del sistema, crear tests o validar lógica tras un refactor.
---

# Skill: Ejecución y Creación de Pruebas Unitarias

Esta habilidad guía la ejecución y creación de tests dentro del directorio `tests/` del proyecto.

---

## 1. Ejecución del Test Suite Completo
Para correr toda la suite de pruebas automatizadas:
```bash
php tests/run.php
```
O mediante Composer:
```bash
composer test
```

---

## 2. Estructura de una Clase de Prueba
Todas las pruebas heredan de `Tests\TestCase` y se ubican en `tests/`:

```php
<?php
namespace Tests;

use App\Models\EdificiosModel;

class EdificiosModelTest extends TestCase {
    public function testCrearEdificioValido() {
        $model = new EdificiosModel();
        
        $nombre = 'Torre Test ' . rand(100, 999);
        $resultado = $model->create(['nombre' => $nombre, 'descripcion' => 'Prueba']);
        
        $this->assertTrue($resultado, "El edificio debería crearse exitosamente.");
        $this->assertTrue($model->nombreExists($nombre), "El nombre debe existir en la BD.");
    }
}
```

---

## 3. Aserciones Disponibles en `TestCase`
- `$this->assertTrue($condicion, $mensaje)`
- `$this->assertFalse($condicion, $mensaje)`
- `$this->assertEquals($esperado, $actual, $mensaje)`
- `$this->assertNull($valor, $mensaje)`
- `$this->assertNotNull($valor, $mensaje)`
- `$this->assertContains($aguja, $pajar, $mensaje)`
