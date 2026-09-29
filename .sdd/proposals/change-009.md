# change-009: gestion-saldos-a-favor-residentes

## Status
COMPLETED

## Description
Gestión integral, persistencia y visualización de saldos a favor de residentes generados por pagos en exceso o pagos anticipados (sin deuda activa). Incluye acreditación en cascada sobre facturas pendientes, generación de crédito en facturas/movimientos, consumo no destructivo en la emisión masiva mensual, levantamiento de bloqueos de sobrepago en el portal de residentes y visualización transparente en los paneles administrativo y de residente.

## Scope
- [x] Backend: Modelos (`FacturasModel`, `PagoModel`, `ComprobantesModel`, `MovimientosModel`), Servicio de Liquidación (`LiquidacionPagoService`) y Controladores (`PagoController`, `ResidenteController`, `ConciliacionBancariaService`).
- [x] Frontend: Formulario de pagos (`subir.php`, `enviar_pago.php`), Dashboard de Residente (`dashboard.php`), Estado de Cuenta y Vista Administrativa de Verificación de Comprobantes.
- [ ] WPF: N/A
- [x] Tests: Suite automatizada cubriendo sobrepagos, pagos anticipados, cascada multi-factura, persistencia de saldo a favor remanente en facturación masiva y auditoría estática (`tests/SaldoFavorTest.php`).

## Acceptance Criteria
- [x] El residente puede reportar pagos superiores a su deuda pendiente y pagos anticipados con deuda cero.
- [x] Al aprobar un pago (individual, lote o conciliación), el monto se aplica en cascada contra todas las facturas pendientes de la unidad (`anio ASC, mes ASC`).
- [x] Todo remanente que supere la deuda se registra de forma inmutable como saldo a favor en `facturas` (saldo negativo) y en `movimientos_cuenta` (`abono_pago`).
- [x] La generación de facturas masivas mensuales (`crearFacturasMasivas`) deduce el saldo a favor existente sin destruir el remanente sobrante.
- [x] El saldo a favor se visualiza en tiempo real en el Dashboard del Residente, en su Estado de Cuenta y en las pantallas de registro de pago.
- [x] Purity check (`scripts/check_purity.php`) con 0 violaciones y auditoría OWASP (`scripts/audit_security.php`) limpia.
- [x] 100% de la suite de pruebas aprobada (`tests/run.php` con 334 tests y 1071 aserciones).

## Summary of Completed Work
1. **Servicio Contable Centralizado**: Creado `App\Services\LiquidacionPagoService` que gestiona liquidación en cascada pesimista (`FOR UPDATE`), generación no colisionante de facturas de abono (`ABONO-...`) con saldo negativo y registro inmutable en `movimientos_cuenta`.
2. **Consumo No Destructivo en Facturación Masiva**: Modificado `FacturasModel::crearFacturasMasivas()` para preservar el saldo a favor remanente (`ABONO-REM-...`) cuando la cuota mensual es inferior al crédito acumulado, registrando además el cargo en el libro mayor.
3. **Integración en Aprobaciones**: Conectados `PagoModel::cambiarEstado()`, `PagoModel::aprobarLote()`, `ComprobantesModel::aprobar()` y `ConciliacionBancariaService::procesarConciliacionPago()` con `LiquidacionPagoService`.
4. **Desbloqueo de Pagos para Residentes**: Eliminado el bloqueo `monto > totalDeuda` en `PagoController::subir()`, permitiendo pagos anticipados y sobrepagos. Añadida tarjeta informativa de Deuda vs. Saldo a Favor en `pagos/residente/subir.php` y opción de pago anticipado en `residente/enviar_pago.php`.
5. **Pruebas y Verificación**: Creado `tests/SaldoFavorTest.php` (5 tests, 29 aserciones). Suite completa pasando al 100% (334 tests, 1071 aserciones), 0 violaciones en pureza MVC y 0 vulnerabilidades en auditoría OWASP.
