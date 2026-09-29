# Tareas de Implementación: Visualización y Descarga de Comprobantes

- [x] **Tarea 1: Servicio Proxy Seguro de Archivos (`public/comprobante-proxy.php`)**
  - [x] Implementar soporte para `download=1` con cabecera `Content-Disposition: attachment; filename="..."`.
  - [x] Implementar cabecera `Content-Disposition: inline; filename="..."` para visualización directa.
  - [x] Añadir validación de permisos de descarga por rol (Administrador, Auditor y Residente propietario del comprobante).

- [x] **Tarea 2: Lógica de Negocio y Transiciones de Aprobación (`PagoController.php`)**
  - [x] Permitir transición válida directa de `PENDIENTE` a `APROBADO`.
  - [x] Asegurar redirecciones limpias tras acciones ejecutadas desde la vista de detalle.

- [x] **Tarea 3: Vista de Detalle y Aprobación Administrativa (`app/views/pagos/detalle.php`)**
  - [x] Corregir la URL base de los archivos hacia `/comprobante-proxy.php?file=...`.
  - [x] Agregar bloque de visualización con soporte completo para imágenes y PDFs.
  - [x] Integrar botón prominente de "Descargar Comprobante" activo en estado previo (`PENDIENTE`, `EN REVISIÓN`) y posterior (`APROBADO`, `RECHAZADO`).
  - [x] Integrar panel de acciones de aprobación directa para administradores (Aprobar, Poner en Revisión, Rechazar con modal de motivo).
  - [x] Renderizar tarjeta de estado final cuando el pago ya está aprobado o rechazado.

- [x] **Tarea 4: Vista de Verificación y Listados Administrativos**
  - [x] En `app/views/admin/verificar_comprobante.php`: añadir soporte PDF y botón de descarga de comprobante en estados pendiente y procesado.
  - [x] En `app/views/pagos/admin/lista.php`: añadir acceso directo de previsualización y descarga en la tabla.
  - [x] En `app/views/admin/comprobantes.php`: añadir botón de descarga directa en la columna de comprobante.
  - [x] En `app/views/admin/dashboard.php`: añadir botón de descarga directa en tablas de comprobantes.

- [x] **Tarea 5: Pruebas Automatizadas y Auditorías de Calidad**
  - [x] Crear suite de pruebas `tests/ComprobanteFlujoAprobacionTest.php`.
  - [x] Ejecutar y verificar `php scripts/check_purity.php` (0 violaciones).
  - [x] Ejecutar y verificar `php scripts/audit_security.php` (0 vulnerabilidades).
  - [x] Ejecutar suite completa `php tests/run.php` (346 tests, 1106 aserciones exitosas).
