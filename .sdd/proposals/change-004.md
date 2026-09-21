# change-004: Refactorización del Flujo de Registro con Aprobación Administrativa y Asignación Dinámica de Apartamentos

## Status
COMPLETED

## Implementation Summary
- Migración `scripts/migrations_phase12.sql` ejecutada creando tabla `solicitudes_registro` con índices y agregando `personas.numero_residentes`.
- Modelo `UnidadesModel::getDisponibles()` implementado con filtros de vacancia y exclusión de solicitudes pendientes.
- Modelo `SolicitudesRegistroModel` implementado con transacciones atómicas `SELECT ... FOR UPDATE`, aprobación, rechazo y auditoría en `log_auditoria`.
- Controlador `SolicitudesRegistroController` y rutas registradas en `public/index.php`.
- Vista `app/views/auth/register.php` actualizada con selector dinámico y notificación de estado pendiente.
- Vista `app/views/admin/solicitudes_registro/index.php` implementada con Bootstrap 5 y modales de aprobación/rechazo.
- Menú lateral de administración actualizado en `app/views/layouts/admin_sidebar.php`.
- Suite de pruebas unitarias `tests/SolicitudesRegistroTest.php` completada y pasando al 100%.

## Description
Modificación del proceso de alta de usuarios en el sistema para introducir verificación y aprobación administrativa obligatoria. Los nuevos residentes podrán solicitar su registro proporcionando sus datos personales, número de residentes y seleccionando un apartamento disponible en tiempo real. La cuenta permanecerá en estado "Pendiente" sin permisos de acceso hasta que un administrador valide los datos y apruebe o rechace la solicitud en el nuevo módulo "Gestión de Solicitudes".

## Acceptance Criteria
1. **Formulario de Registro**: Captura nombre, apellido, cédula, teléfono, correo, contraseña robusta, número de residentes y selector dinámico de apartamentos disponibles (edificio + número).
2. **Estado Pendiente y Bloqueo de Acceso**: La cuenta queda en estado pendiente y se notifica al usuario. Se bloquea cualquier inicio de sesión y el backend rechaza peticiones autenticadas de cuentas no aprobadas.
3. **Módulo de Gestión de Solicitudes**: Interfaz administrativa protegida (`/admin/solicitudes` o `/admin/solicitudes-registro`) para auditar, aprobar o rechazar registros pendientes.
4. **Seguridad y Concurrencia (SDD)**:
   - Control estricto de concurrencia con `SELECT ... FOR UPDATE` sobre la unidad para evitar condiciones de carrera tanto en el momento del registro como en la aprobación.
   - Auditoría estricta con registro del `admin_id`, IP y detalles en `log_auditoria`.
   - Protección CSRF y validación estricta de roles.
