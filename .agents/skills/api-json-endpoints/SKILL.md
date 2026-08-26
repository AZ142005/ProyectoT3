---
name: api-json-endpoints
description: >-
  Playbook para construir y consumir endpoints REST / AJAX en PHP Vanilla con respuestas JSON seguras.
  Úsalo cuando el usuario pida añadir funcionalidades asíncronas, validación en tiempo real o integración JS con Bootstrap.
---

# Skill: Endpoints API y AJAX con Respuestas JSON

Esta habilidad guía la creación de endpoints asíncronos en PHP Vanilla integrados con interfaces de Bootstrap 5 mediante `fetch()`.

---

## 1. Patrón del Controlador para Endpoint JSON

En `app/controllers/<Nombre>Controller.php`:
```php
public function miEndpointAjax() {
    Auth::requireRole('admin'); // Validar rol o requireLogin()

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $this->json(['error' => 'Método no permitido'], 405);
    }

    // CSRF ya validado por Security::validateCSRF()
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        $this->json(['success' => false, 'error' => 'ID inválido'], 400);
    }

    $model = new MiModelo();
    $datos = $model->obtenerDetalle($id);

    $this->json([
        'success' => true,
        'data'    => $datos
    ], 200);
}
```

---

## 2. Consumo desde JavaScript Nativo en Vistas Bootstrap

```javascript
async function cargarDatosModal(id) {
    const formData = new FormData();
    formData.append('id', id);
    // Inyectar el token CSRF si la petición es POST
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput) {
        formData.append('csrf_token', csrfInput.value);
    }

    try {
        const res = await fetch('/admin/mi-endpoint-ajax', {
            method: 'POST',
            body: formData
        });

        const json = await res.json();
        if (json.success) {
            document.getElementById('campoModal').value = json.data.nombre;
            const modal = new bootstrap.Modal(document.getElementById('miModal'));
            modal.show();
        } else {
            alert('Error: ' + json.error);
        }
    } catch (err) {
        console.error('Error en petición AJAX:', err);
    }
}
```
