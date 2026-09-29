# change-011: unificacion-pantallas-detalle-pago

## Status
COMPLETED

## Description
Unificación arquitectónica de las pantallas de detalle de pagos y comprobantes hacia una vista maestra canónica (`app/views/pagos/detalle.php`), eliminando la duplicidad de interfaces entre el módulo de pagos y el módulo de verificación de facturas, manteniendo compatibilidad transparente con ambas fuentes de datos (`pagos` y `comprobantes_pago`).

## Scope
- [x] Frontend: Estandarización de `app/views/pagos/detalle.php` para soportar tanto pagos con auditoría general como comprobantes vinculados a facturas (con desglose de saldo de factura, saldo restante/a favor, acciones de aprobación contextuales y visor multiformato con descarga).
- [x] Frontend: Refactorización de `app/views/admin/verificar_comprobante.php` para delegar en el componente maestro canónico mediante un adaptador normalizado, preservando contratos de testing.
- [x] Backend: Controlador `AdminController` para exigir motivo obligatorio (mínimo 5 caracteres) al rechazar comprobantes de factura, alineándose con las políticas de `PagoController`.
- [x] Tests: Suite automatizada de regresión y cobertura de la vista unificada.

## Acceptance Criteria
- [x] Una única vista canónica de presentación (`pagos/detalle.php`) resuelve la inspección de pagos independientemente de su origen (`pagos` o `comprobantes_pago`).
- [x] Si el pago está vinculado a una factura específica, la vista despliega los datos de facturación (número de factura, saldo actual, monto a aplicar y saldo restante/a favor).
- [x] Se conservan las acciones de aprobación y rechazo directas correspondientes al flujo originario, requiriendo motivo obligatorio en caso de rechazo.
- [x] La opción de descarga de comprobante físico (`download=1`) permanece activa y visible en todas las etapas del ciclo de vida.
- [x] 0 violaciones en pureza MVC (`scripts/check_purity.php`) y 0 vulnerabilidades OWASP (`scripts/audit_security.php`).
- [x] 100% de la suite de pruebas aprobada (346 tests, 1106 aserciones exitosas).

## Summary of Completed Work
1. **Componente Canónico Unificado**: Extendida `app/views/pagos/detalle.php` para admitir de forma polimórfica tanto pagos generales como comprobantes directos de facturas, mostrando datos de unidad, residente, factura relacionada, saldo previo y saldo restante.
2. **Patrón Adaptador de Compatibilidad**: Refactorizada `app/views/admin/verificar_comprobante.php` para normalizar los datos de `$comprobante` y delegar la renderización en `pagos/detalle.php`, eliminando código HTML duplicado.
3. **Validación de Rechazo Homogénea**: Añadida validación de motivo obligatorio en `AdminController::verificarComprobante()` al rechazar, homogeneizando las reglas del sistema.
4. **Verificación Integral**: Suite completa al 100% (346 tests, 1106 aserciones), pureza arquitectónica MVC impecable y 0 alertas de seguridad OWASP.
