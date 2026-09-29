# Tareas de Implementación: Gestión Integral de Saldos a Favor de Residentes

- [x] **Tarea 1: Servicio de Liquidación Contable (`App\Services\LiquidacionPagoService`)**
  - [x] Implementar `aplicarPagoAUnidad(PDO $db, int $unidadId, float $montoTotal, string $concepto, ?int $referenciaId = null): array`.
  - [x] Cascada de facturas pendientes con bloqueo `FOR UPDATE`.
  - [x] Acreditación de saldo a favor (`saldo < 0`) con identificador no colisionante.
  - [x] Registro inmutable de abono en `movimientos_cuenta`.

- [x] **Tarea 2: Consumo No Destructivo en Facturación Masiva (`FacturasModel`)**
  - [x] Refactorizar `crearFacturasMasivas()` para preservar remanentes de saldo a favor cuando la cuota mensual es inferior al crédito acumulado.
  - [x] Registrar cargos y abonos correspondientes en `movimientos_cuenta`.

- [x] **Tarea 3: Integración en Modelos de Pago y Conciliación**
  - [x] Integrar `LiquidacionPagoService` en `PagoModel::cambiarEstado()`.
  - [x] Integrar `LiquidacionPagoService` en `PagoModel::aprobarLote()`.
  - [x] Integrar `LiquidacionPagoService` en `ComprobantesModel::aprobar()`.
  - [x] Integrar `LiquidacionPagoService` en `ConciliacionBancariaService::procesarConciliacionPago()`.

- [x] **Tarea 4: Desbloqueo en Controladores y Formularios de Residentes**
  - [x] Eliminar restricción de sobrepago en `PagoController::subir()`.
  - [x] Enviar balances consolidados en `PagoController::nuevo()`.
  - [x] Permitir pagos anticipados en `app/views/pagos/residente/subir.php` y `app/views/residente/enviar_pago.php`.

- [x] **Tarea 5: Verificación Visual en Vistas de Residentes y Administración**
  - [x] Tarjeta de estado financiero en `/pagos/nuevo`.
  - [x] Comprobación de reflejo de saldo a favor en `/residente/dashboard` y `/residente/estado-cuenta`.

- [x] **Tarea 6: Pruebas Automatizadas y Auditoría de Calidad**
  - [x] Crear tests unitarios en `tests/SaldoFavorTest.php` cubriendo sobrepago, prepago y facturación masiva.
  - [x] Verificar pureza MVC (`php scripts/check_purity.php`).
  - [x] Verificar auditoría OWASP (`php scripts/audit_security.php`).
  - [x] Ejecutar suite completa (`php tests/run.php`).
