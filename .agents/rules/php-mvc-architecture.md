# Reglas del Proyecto: Arquitectura PHP MVC Pura

## Contexto del Proyecto
Este proyecto (`ProyectoT3`) implementa una arquitectura MVC pura y desacoplada en **PHP Vanilla** moderno (sin Laravel/Symfony) con enrutamiento centralizado (**Front Controller** en `public/index.php`) y carga automática de clases (**PSR-4** vía Composer en namespace `App\`).

---

## 1. Principios de Separación de Capas (Pureza MVC)

1. **Controladores (`app/controllers/`)**:
   - Deben ser delgados (Thin Controllers).
   - Su única responsabilidad es: recibir y sanitizar inputs de la petición, verificar autenticación y roles mediante `Auth::requireRole()`, invocar métodos de los Modelos correspondientes y llamar a `$this->render('vista', $data)` o `$this->redirect('/ruta')`.
   - **PROHIBIDO**: Escribir etiquetas HTML, `echo`, `print` o salidas directas dentro de métodos de los controladores.
   - **PROHIBIDO**: Ejecutar consultas SQL directas en controladores (deben delegarse siempre al Modelo).

2. **Modelos (`app/models/`)**:
   - Encapsulan toda la lógica de acceso a datos y reglas de negocio de la entidad.
   - Utilizan la conexión segura centralizada `App\Core\Database::getConnection()`.
   - **PROHIBIDO**: Escribir etiquetas HTML, emitir salidas directas (`echo`) o manipular variables globales de sesión/headers en los modelos.
   - **PROHIBIDO**: Escribir consultas SQL con interpolación de variables directas.

3. **Vistas (`app/views/`)**:
   - Su responsabilidad es puramente visual y de presentación.
   - Deben recibir los datos inyectados como variables por el controlador.
   - Toda salida de texto dinámico de usuario DEBE escaparse usando la función helper `e($variable)` para prevenir ataques XSS.
   - **PROHIBIDO**: Ejecutar consultas a base de datos (`PDO`, `mysqli`, `SELECT`, `INSERT`, etc.) dentro de archivos de vista.

4. **Front Controller (`public/index.php`)**:
   - Punto de entrada único para todas las peticiones web.
   - Configura cookies seguras de sesión antes de iniciar `session_start()`.
   - Ejecuta el middleware de validación CSRF (`Security::validateCSRF()`) para todas las peticiones `POST`.
   - Despacha las rutas hacia la acción del controlador correspondiente.

---

## 2. Convenciones de Nomenclatura y Código

- **PSR-4**: Los archivos PHP deben usar el namespace `App\` y coincidir exactamente con la estructura de carpetas:
  - `app/controllers/` -> `namespace App\Controllers;`
  - `app/models/` -> `namespace App\Models;`
  - `app/core/` -> `namespace App\Core;`
- **Nombres de Clases**: PascalCase (ej. `EstructuraController`, `EdificiosModel`, `Database`).
- **Nombres de Métodos**: camelCase (ej. `getActivos()`, `guardarEdificio()`, `verificarComprobante()`).
- **Nombres de Vistas**: snake_case o kebab-case dentro de su subdirectorio temático (ej. `admin/estructura.php`, `pagos/detalle.php`).

---

## 3. Validación de Pureza
Siempre que se agregue o modifique un Controlador o Modelo, se debe ejecutar el validador automático:
```bash
php scripts/check_purity.php
```
Debe retornar `0` violaciones de pureza antes de considerar completada la tarea.
