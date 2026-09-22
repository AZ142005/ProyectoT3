# Tareas: Conciliación PDF (BDV) y CRUD de Cuentas Bancarias Autorizadas

- [x] **Tarea 1**: Crear migración de base de datos (`scripts/migrate_cuentas_bancarias.php` y `scripts/migrations_phase13.sql`) para crear la tabla `cuentas_bancarias`, agregar `cuenta_bancaria_id` a `pagos`, e insertar una cuenta semilla de Banco de Venezuela.
- [x] **Tarea 2**: Implementar el modelo `App\Models\CuentasBancariasModel` con métodos CRUD, listado de cuentas activas (`getActivas()`) y alternancia de estado (`toggleActiva()`).
- [x] **Tarea 3**: Crear el controlador administrativo `App\Controllers\CuentaBancariaController` y registrar sus rutas en `public/index.php` (`/admin/cuentas-bancarias`, `/admin/cuentas-bancarias/guardar`, `/admin/cuentas-bancarias/toggle`, `/admin/cuentas-bancarias/eliminar`).
- [x] **Tarea 4**: Diseñar la vista de administración `app/views/admin/cuentas_bancarias/index.php` con componentes Bootstrap 5, badges de métodos de pago, modal de creación/edición y protección CSRF.
- [x] **Tarea 5**: Actualizar la barra lateral de administración (`app/views/layouts/admin_sidebar.php`) agregando el acceso a "Cuentas Bancarias".
- [x] **Tarea 6**: Actualizar las pantallas de reporte de pagos de residentes (`app/views/pagos/residente/subir.php` y `app/views/residente/enviar_pago.php`) reemplazando el campo de texto de banco receptor con el dropdown dinámico de cuentas activas y tarjeta de datos de la cuenta.
- [x] **Tarea 7**: Adaptar `App\Controllers\PagoController` y `App\Controllers\ResidenteController` para cargar las cuentas activas y validar en backend que la cuenta seleccionada sea válida y esté activa.
- [x] **Tarea 8**: Implementar la extracción nativa de flujos PDF y el parser de Banco de Venezuela en `App\Services\ConciliacionBancariaService` y enlazarlo con `parsearArchivo()`.
- [x] **Tarea 9**: Adaptar `App\Controllers\ConciliacionController::importarExtracto()` para permitir `.pdf`, validar MIME y normalizar bancos permitidos.
- [x] **Tarea 10**: Actualizar el modal de conciliación en `app/views/admin/conciliacion/index.php` con soporte `.pdf` y etiqueta de Banco de Venezuela.
- [x] **Tarea 11**: Crear y actualizar pruebas automatizadas en `tests/ConciliacionTest.php` y `tests/CuentasBancariasTest.php`.
- [x] **Tarea 12**: Ejecutar suites de verificación (`php scripts/check_purity.php`, `php scripts/audit_security.php` y `php tests/run.php`).
- [x] **Tarea 13**: Actualizar estado en `.sdd/proposals/change-006.md` a `COMPLETED`.
