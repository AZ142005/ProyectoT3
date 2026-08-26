---
name: php-mvc-architect
description: >-
  Playbook para crear, refactorizar o extender módulos bajo la arquitectura MVC Pura en PHP Vanilla sin frameworks.
  Úsalo cuando el usuario pida añadir nuevas entidades, controladores, modelos o rutas al proyecto.
---

# Skill: Arquitecto PHP MVC Puro (Vanilla)

Esta habilidad guía la creación de nuevos módulos respetando la separación estricta Modelo-Vista-Controlador y el patrón Front Controller del proyecto.

---

## Flujo de Implementación de un Nuevo Módulo

### Paso 1: Definir la Capa de Datos (Modelo)
Crear la clase en `app/models/<Entidad>Model.php`:
```php
<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class EntidadModel {
    public function getAll() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM entidad WHERE estado = 1 ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM entidad WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create($data) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO entidad (campo1, campo2) VALUES (:campo1, :campo2)");
        return $stmt->execute([
            'campo1' => trim($data['campo1']),
            'campo2' => trim($data['campo2'])
        ]);
    }
}
```

### Paso 2: Definir la Lógica de Negocio y Guardias (Controlador)
Crear el controlador en `app/controllers/<Entidad>Controller.php`:
```php
<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\EntidadModel;

class EntidadController extends Controller {
    public function index() {
        Auth::requireRole('admin'); // O 'residente'

        $model = new EntidadModel();
        $items = $model->getAll();

        $this->render('admin/entidad_index', [
            'items'   => $items,
            'title'   => 'Gestión de Entidad',
            'showNav' => false
        ]);
    }

    public function guardar() {
        Auth::requireRole('admin');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF ya validado por Security::validateCSRF()
            $campo1 = trim($_POST['campo1'] ?? '');
            
            $model = new EntidadModel();
            $model->create(['campo1' => $campo1]);

            $this->redirect('/admin/entidad');
        }
    }
}
```

### Paso 3: Registrar las Rutas en el Front Controller
En `public/index.php`, añadir los casos en el `switch ($route)` o expresiones regulares:
```php
case '/admin/entidad':
    $controller = new \App\Controllers\EntidadController();
    $controller->index();
    break;

case '/admin/entidad/guardar':
    $controller = new \App\Controllers\EntidadController();
    $controller->guardar();
    break;
```

### Paso 4: Crear la Vista con Bootstrap 5
Crear `app/views/admin/entidad_index.php` utilizando maquetación HTML5 semántica y componentes de Bootstrap 5 (`.container-fluid`, `.card`, `.table`, `.modal`), escapando toda salida dinámica con `<?= e($item['campo']) ?>`.

### Paso 5: Validar Pureza MVC
Ejecutar:
```bash
php scripts/check_purity.php
```
Debe finalizar con código de salida 0.
