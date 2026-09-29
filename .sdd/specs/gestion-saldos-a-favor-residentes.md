# Especificación: Gestión Integral de Saldos a Favor de Residentes

## 1. Requisitos Funcionales

### RF-SF-01: Desbloqueo de Pagos Superiores a la Deuda y Pagos Anticipados
- Eliminar la restricción en `PagoController::subir()` que rechaza pagos cuando `monto > totalDeuda`.
- Permitir registrar pagos cuando la deuda sea cero (`totalDeuda == 0`), tipificándolos como pagos anticipados que alimentarán el saldo a favor de la unidad.
- En la interfaz `/pagos/nuevo` (`subir.php`), mostrar de forma destacada el estado financiero de la unidad (Deuda Pendiente y Saldo a Favor Actual), informando que cualquier excedente o pago anticipado se acreditará como saldo a favor para futuras cuotas.
- En `/residente/enviar-pago` (`enviar_pago.php`), cuando no existan facturas pendientes, habilitar la opción de registrar un comprobante de abono / pago anticipado en lugar de bloquear el formulario.

### RF-SF-02: Aplicación en Cascada de Pagos sobre Facturas Pendientes
- Al aprobar un pago (individual desde `PagoModel::cambiarEstado`, en lote desde `PagoModel::aprobarLote`, vía comprobante en `ComprobantesModel::aprobar`, o vía conciliación en `ConciliacionBancariaService`):
  1. Identificar todas las facturas pendientes de la unidad ordenadas cronológicamente (`anio ASC, mes ASC, fecha_vencimiento ASC`).
  2. Aplicar el monto disponible factura por factura:
     - Deducir saldo: `nuevoSaldo = max(0, saldo - montoRestante)`.
     - Incrementar `monto_pagado`: `nuevoMontoPagado = monto_pagado + abono`.
     - Si `nuevoSaldo == 0`, actualizar estado a `'pagada'`.
     - Restar el monto aplicado del disponible.
  3. Registrar el abono correspondiente en el libro mayor (`movimientos_cuenta` con tipo `'abono_pago'`).

### RF-SF-03: Generación y Persistencia del Saldo a Favor Remanente
- Si tras liquidar todas las facturas pendientes (o si no existía ninguna) queda un remanente mayor a Bs. 0.01:
  1. Insertar una factura de crédito / abono en `facturas` con:
     - `numero_factura`: Formato único no colisionante `ABONO-YYYYMM-UID-TIMESTAMP-RAND`.
     - `unidad_id`: Unidad del residente.
     - `monto_total`: 0.00.
     - `monto_pagado`: Monto del excedente.
     - `saldo`: Valor negativo del excedente (`-$remanente`).
     - `estado`: `'pagada'`.
  2. Registrar movimiento en `movimientos_cuenta`:
     - Tipo: `'abono_pago'`.
     - Monto: Monto del excedente.
     - Descripción: "Saldo a favor generado por excedente / pago anticipado Ref. {referencia}".
     - Saldo posterior reflejado como saldo acreedor/favor.

### RF-SF-04: Consumo No Destructivo en Facturación Masiva Mensual
- En `FacturasModel::crearFacturasMasivas`:
  - Obtener la suma total de saldos a favor de la unidad (`SELECT SUM(saldo) ... WHERE saldo < 0`).
  - Si el saldo a favor absoluto (`abs(saldo_favor)`) cubre el monto de la cuota mensual (`cuota_mensual`):
    - La nueva factura nace con `estado = 'pagada'`, `monto_pagado = cuota_mensual`, `saldo = 0.00`.
    - El remanente de saldo a favor (`abs(saldo_favor) - cuota_mensual`) **NO se destruye**.
    - Se actualizan los registros de saldo negativo previos o se inserta la diferencia para conservar exactamente el crédito restante.
    - Se registra en `movimientos_cuenta` el cargo por la nueva factura (`cargo_factura`) y la aplicación del abono por saldo a favor (`abono_pago`).
  - Si el saldo a favor es menor a la cuota mensual:
    - La nueva factura nace con `monto_pagado = abs(saldo_favor)`, `saldo = cuota_mensual - abs(saldo_favor)`, `estado = 'pendiente'`.
    - Se liquidan a 0 los saldos a favor anteriores consumidos en su totalidad.

### RF-SF-05: Reflejo Transparente en la Interfaz de Usuario
- **Dashboard de Residente (`residente/dashboard.php`)**: La tarjeta "Saldo a Favor" refleja el valor consolidado positivo (`abs(saldo_favor)`).
- **Estado de Cuenta (`residente/estado_cuenta.php`)**: El libro mayor muestra el desglose cronológico de cargos y abonos con el saldo consolidado exacto.
- **Detalle y Verificación Administrativa (`admin/verificar_comprobante.php`, `pagos/detalle.php`)**: Mostrar el desglose exacto de lo aplicado a deuda y lo acreditado a saldo a favor.

---

## 2. Criterios de Aceptación
1. Registro de pagos superiores a la deuda sin excepción ni bloqueo.
2. Registro de pagos anticipados cuando la deuda es 0.00.
3. Pago en cascada exitoso cubriendo múltiples facturas y acreditando el sobrante.
4. Generación masiva mensual consume saldo a favor sin perder saldos residuales.
5. Sincronización exacta entre `facturas` (`saldo < 0`) y `movimientos_cuenta` (`saldo_posterior`).
6. Suite de pruebas unitarias y de comportamiento aprobada al 100%.
7. Validación de pureza MVC (`check_purity.php`) y seguridad estática (`audit_security.php`) exitosas.
