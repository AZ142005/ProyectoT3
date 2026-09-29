# Plan de Tareas: Gestión de Usuarios y Reinicio de Contraseña (change-008)

- [x] **Tarea 1: Capa de Datos (Modelos)**
  - [x] Implementar `obtenerListadoUnificado()` en `UsuariosModel.php` con paginación, filtros de texto y rol.
  - [x] Implementar `reiniciarPassword()` en `UsuariosModel.php`.
  - [x] Implementar `reiniciarPassword()` en `PersonasModel.php`.

- [x] **Tarea 2: Controlador Administrativo**
  - [x] Crear `app/controllers/UsuarioAdminController.php` con métodos `index()` y `reiniciarPassword()`.
  - [x] Implementar generador de contraseñas aleatorias seguras y validación de claves manuales.
  - [x] Manejo de feedback en sesión para mostrar la contraseña temporal tras el reinicio.

- [x] **Tarea 3: Interfaz de Usuario y Navegación**
  - [x] Agregar el ítem `'usuarios'` en `app/views/layouts/admin_sidebar.php`.
  - [x] Crear la vista `app/views/admin/usuarios/index.php` con maquetación Bootstrap 5, tabla responsive, paginador, buscador y modal de reinicio con copiado al portapapeles.

- [x] **Tarea 4: Enrutamiento**
  - [x] Registrar las rutas GET `/admin/usuarios` y POST `/admin/usuarios/reiniciar-password` en `public/index.php` con middleware de rol `UserRole::ADMIN`.

- [x] **Tarea 5: Suite de Pruebas y Auditoría de Seguridad**
  - [x] Crear `tests/UsuarioAdminTest.php` verificando acceso restringido, paginación, búsqueda y reseteo de claves con hash bcrypt y desbloqueo.
  - [x] Ejecutar `php scripts/check_purity.php` (pureza MVC - 0 violaciones).
  - [x] Ejecutar `php scripts/audit_security.php` (análisis estático OWASP - 0 vulnerabilidades).
  - [x] Ejecutar suite completa de tests `php tests/run.php` (1042 assertions passed, 0 failed).
