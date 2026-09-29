# change-008: Gestion administrativa de usuarios y reinicio de contrasena

## Status
IMPLEMENTED

## Description
Modulo en el panel de administracion para listar usuarios registrados (residentes y personal administrativo) con buscador en tiempo real/servidor, paginacion y accion de reinicio de contrasena segura.

## Scope
- [x] Backend: Controlador `UsuarioAdminController`, métodos en `UsuariosModel` / `PersonasModel`, rutas en `public/index.php`.
- [x] Frontend: Vista `app/views/admin/usuarios/index.php` con Bootstrap 5, buscador, paginacion y modal de reinicio de clave.
- [x] Navegacion: Actualizacion de `app/views/layouts/admin_sidebar.php`.
- [x] Tests: Suite de pruebas unitarias y de autorizacion `tests/UsuarioAdminTest.php`.

## Acceptance Criteria
- [x] Entrada en el sidebar administrativo para "Usuarios".
- [x] Listado unificado de cuentas de usuario (`personas` y `usuarios`) con columnas: Cédula, Nombre Completo, Correo/Teléfono, Rol/Ubicación, Estado de Cuenta y Acciones.
- [x] Buscador con filtro por texto (cédula, nombre, correo) y selector de rol (Todos, Residente, Administrador, Auditor).
- [x] Paginación estándar de 15 registros por página con total de registros y navegación entre páginas.
- [x] Botón "Reiniciar Contraseña" por usuario que abre modal de confirmación con opción de generación automática segura o contraseña personalizada.
- [x] Reseteo atómico de la contraseña con bcrypt y desbloqueo de cuenta (reinicio de intentos fallidos y fecha de bloqueo).
- [x] Despliegue de la clave temporal generada con opción de copiado al portapapeles.
- [x] Verificación de seguridad OWASP (CSRF, control de acceso RBAC solo admin, prevención XSS con `e()`).
- [x] Validación estricta con scripts del proyecto (`check_purity.php`, `audit_security.php` y suite de tests).

## Created
2026-09-22
