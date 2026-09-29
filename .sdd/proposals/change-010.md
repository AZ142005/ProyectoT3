# change-010: visualizacion-descarga-comprobantes-flujo-aprobacion

## Status
COMPLETED

## Description
Visualización detallada de pagos con auditoría y controles de aprobación para administradores, junto con la descarga segura y funcional de archivos de comprobante tanto en la fase previa a la aprobación (estado pendiente o en revisión) como en la fase posterior (estado aprobado y rechazado).

## Scope
- [x] Backend: Controlador `PagoController` (soporte de transiciones directas PENDIENTE -> APROBADO, endpoints de detalle y acciones), `AdminController` (descarga y vista integral), y proxy seguro `public/comprobante-proxy.php` (soporte de `download=1`, cabeceras `Content-Disposition: attachment/inline` y validación de permisos por rol).
- [x] Frontend: Vistas de detalle administrativo `app/views/pagos/detalle.php` (tarjeta de acciones de aprobación/revisión/rechazo in situ, corrección de URL de comprobantes hacia proxy, botón de descarga activa en todos los estados) y `app/views/admin/verificar_comprobante.php` (soporte visual para PDF e imágenes, botón de descarga activo en pendientes y aprobados), y tablas de listado (`app/views/pagos/admin/lista.php`, `app/views/admin/comprobantes.php` y `app/views/admin/dashboard.php`).
- [ ] WPF: N/A
- [x] Tests: Suite automatizada `tests/ComprobanteFlujoAprobacionTest.php` cubriendo descarga, visualización, transiciones de estado y verificación estática sin violaciones.

## Acceptance Criteria
- [x] El administrador cuenta con una vista detallada completa donde puede inspeccionar todos los datos del pago (monto, fecha, referencia, bancos, observaciones, residente, unidad, historial de auditoría) antes de emitir la aprobación.
- [x] Desde la propia vista de detalle, el administrador puede ejecutar las acciones de aprobación, solicitud de revisión o rechazo con motivo obligatorio.
- [x] Se habilita un botón o enlace funcional de descarga de comprobante en la vista de detalle y tablas asociadas.
- [x] La opción de descarga del comprobante permanece activa y funcional en ambas etapas del ciclo de vida del pago: previo a la aprobación (pendiente/revisión) y posterior a la aprobación (aprobado/rechazado).
- [x] El servicio `comprobante-proxy.php` maneja la descarga segura forzada (`Content-Disposition: attachment`) cuando se solicita descarga, y visualización (`Content-Disposition: inline`) para previsualizaciones, restringido a usuarios autenticados.
- [x] Pureza arquitectónica MVC verificada con 0 violaciones (`scripts/check_purity.php`).
- [x] Auditoría de seguridad OWASP limpia con 0 vulnerabilidades (`scripts/audit_security.php`).
- [x] 100% de las pruebas automatizadas aprobadas (346 tests, 1106 aserciones exitosas).

## Summary of Completed Work
1. **Proxy Seguro de Descarga Forzada**: Actualizado `public/comprobante-proxy.php` para interpretar el parámetro `download=1`, emitiendo `Content-Disposition: attachment; filename="..."` para descarga física directa y `Content-Disposition: inline; filename="..."` para previsualización, con validación de sesión y autorización por rol (mitigación BOLA/IDOR).
2. **Acciones de Aprobación In Situ y Transiciones**: Actualizada la máquina de estados en `PagoController::cambiarEstado()` permitiendo la transición directa `PENDIENTE` -> `APROBADO`, preservando el retorno contextual a la vista de detalle.
3. **Vista de Detalle de Pagos Enriquecida**: Modificada `app/views/pagos/detalle.php` para corregir la ruta de archivos hacia el proxy seguro, agregar soporte integral para imágenes y documentos PDF, incorporar el botón "Descargar Comprobante" disponible en todas las etapas del ciclo de vida (pendiente, revisión, aprobado, rechazado) e integrar el panel de acciones de aprobación directa para administradores.
4. **Verificación y Listados Administrativos**: Actualizadas `app/views/admin/verificar_comprobante.php`, `app/views/pagos/admin/lista.php`, `app/views/admin/comprobantes.php` y `app/views/admin/dashboard.php` con opciones de visualización y descarga directa de comprobantes.
5. **Aseguramiento de Calidad**: Creada la suite `tests/ComprobanteFlujoAprobacionTest.php` (5 tests, 25 aserciones). Suite completa pasando al 100% (346 tests, 1106 aserciones), pureza MVC 100% y 0 vulnerabilidades OWASP.
