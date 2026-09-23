# change-007: extraccion-automatica-comprobantes-pago

## Status
IMPLEMENTED

## Description
Extraccion automatica asistida de datos de comprobantes de pago (monto, referencia, fecha, metodo de pago, banco pagador, cuenta receptora) para auto-llenado en tiempo real en los formularios de registro de pago de residentes.

## Scope
- [x] Backend
- [x] Frontend
- [ ] WPF
- [x] Tests

## Acceptance Criteria
- [x] Detección automática en tiempo real al seleccionar o arrastrar el archivo de comprobante (JPG, PNG, PDF).
- [x] Extracción precisa de monto, referencia, fecha, método de pago y banco.
- [x] Auto-llenado exclusivamente de campos vacíos (no sobreescribir valores ingresados por el usuario).
- [x] Muestra de advertencias de inconsistencia si un campo ya llenado difiere del detectado en el comprobante, con opción para aplicar el valor sugerido.
- [x] Vinculación dinámica con las cuentas bancarias autorizadas activas en el sistema.
- [x] Suite de pruebas unitarias cubriendo la lógica de extracción y concordancia bancaria.

## Created
6592.636435

