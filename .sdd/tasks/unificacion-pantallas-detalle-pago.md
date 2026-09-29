# Tareas de Implementación: Unificación de Pantallas de Detalle de Pagos

- [x] **Tarea 1: Enriquecer Vista Canónica (`app/views/pagos/detalle.php`)**
  - [x] Soporte para datos de factura (`numero_factura`, `saldo_factura`, `saldo_restante`).
  - [x] Normalización de campos de residente (`residente_nombre`/`residente`, `residente_cedula`/`cedula`, `unidad_numero`/`unidad`).
  - [x] Panel de acciones contextual según `tipo_origen` (`pago` vs `comprobante`).
  - [x] Asegurar persistencia de visor multiformato (PDF/imagen) y botón "Descargar Comprobante".

- [x] **Tarea 2: Adaptador en `app/views/admin/verificar_comprobante.php`**
  - [x] Mapear variables de `$comprobante` al formato normalizado `$pago`.
  - [x] Delegar la presentación a la vista canónica `pagos/detalle.php`.
  - [x] Preservar compatibilidad con tests estáticos de cadenas clave.

- [x] **Tarea 3: Validación en `AdminController::verificarComprobante()`**
  - [x] Exigir motivo obligatorio (mínimo 5 caracteres) al rechazar comprobante de factura.

- [x] **Tarea 4: Verificación Automatizada y Auditorías**
  - [x] Ejecutar `php scripts/check_purity.php` (0 violaciones).
  - [x] Ejecutar `php scripts/audit_security.php` (0 vulnerabilidades).
  - [x] Ejecutar suite completa `php tests/run.php` (346 tests, 1106 aserciones exitosas).
