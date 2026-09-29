# Diseño Técnico: Gestión Integral de Saldos a Favor de Residentes

## 1. Arquitectura de la Solución

Para evitar duplicación y discrepancias contables en los 4 flujos de aprobación de pagos del sistema (`PagoModel::cambiarEstado`, `PagoModel::aprobarLote`, `ComprobantesModel::aprobar`, `ConciliacionBancariaService::procesarConciliacionPago`), se centraliza la mecánica de liquidación de facturas y acreditación de saldo a favor.

### Componente Central: `App\Services\LiquidacionPagoService`
Clase de servicio encargada de:
1. `aplicarPagoAUnidad(PDO $db, int $unidadId, float $montoTotal, string $origenReferencia, ?int $pagoId = null): array`
   - Realiza bloqueo pesimista `SELECT ... FOR UPDATE` de las facturas pendientes de la unidad (`estado = 'pendiente' AND deleted_at IS NULL ORDER BY anio ASC, mes ASC, fecha_vencimiento ASC`).
   - Itera deduciendo el saldo de cada factura y actualizando `monto_pagado` y `estado = 'pagada'` cuando corresponda.
   - Si tras cubrir todas las facturas (o si no había ninguna) existe un remanente `$excedente > 0.01`:
     - Inserta en `facturas` un registro de crédito con número `ABONO-{YYYYMM}-{UID}-{TIMESTAMP}-{RAND}` con `saldo = -$excedente`, `monto_total = 0`, `monto_pagado = $excedente`, `estado = 'pagada'`.
   - Registra en `movimientos_cuenta` el movimiento inmutable tipo `'abono_pago'` con el desglose correspondiente, asegurando que el saldo posterior refleje el estado contable real.
   - Retorna un arreglo resumen: `['facturas_afectadas' => [...], 'saldo_a_favor_generado' => float, 'total_aplicado' => float]`.

2. Refactor de `FacturasModel::crearFacturasMasivas`:
   - Bloquear facturas con saldo negativo (`saldo < 0 FOR UPDATE`).
   - Si `$saldo_favor_abs >= $monto_factura`:
     - Saldo a favor cubre toda la cuota: La nueva factura nace con `estado = 'pagada'`, `monto_pagado = $monto_factura`, `saldo = 0.00`.
     - El remanente `$remanente = $saldo_favor_abs - $monto_factura`.
     - Actualizar las facturas con saldo negativo previas a `saldo = 0`, y si `$remanente > 0.01`, dejar una factura de abono con `saldo = -$remanente` y `monto_pagado = $remanente`.
     - Registrar en `movimientos_cuenta` el cargo por la nueva factura (`cargo_factura`) y el abono por saldo a favor (`abono_pago`).
   - Si `$saldo_favor_abs < $monto_factura`:
     - La nueva factura nace con `monto_pagado = $saldo_favor_abs`, `saldo = round($monto_factura - $saldo_favor_abs, 2)`, `estado = 'pendiente'`.
     - Actualizar todas las facturas con saldo negativo a `saldo = 0`.
     - Registrar en `movimientos_cuenta` el cargo (`cargo_factura`) y el abono por el saldo a favor consumido (`abono_pago`).

---

## 2. Puntos de Integración

### A. `app/controllers/PagoController.php`
- `subir()`:
  - Eliminar el bloque que rechaza el pago cuando `$monto > $totalDeuda`.
  - Permitir envíos válidos con `$monto > 0`, incluso si `$totalDeuda == 0`.
  - Agregar mensaje informativo al usuario en caso de sobrepago / pago anticipado.
- `nuevo()`:
  - Pasar a la vista `totalDeuda` y `saldoFavor` para contexto visual del residente.

### B. `app/models/PagoModel.php`
- `cambiarEstado()`:
  - Al cambiar a `'APROBADO'`, invocar `LiquidacionPagoService::aplicarPagoAUnidad()` dentro de la transacción existente.
- `aprobarLote()`:
  - Usar la misma lógica unificada de liquidación garantizando que `numero_factura` sea único y no colisione.

### C. `app/models/ComprobantesModel.php`
- `aprobar()`:
  - En lugar de deducción rígida a 1 sola factura, aplicar el monto mediante la lógica unificada en cascada y crear el abono de saldo a favor si hay remanente.

### D. `app/services/ConciliacionBancariaService.php`
- `procesarConciliacionPago()`:
  - Aplicar liquidación en cascada y saldo a favor remanente mediante el servicio unificado.

### E. Vistas
- `app/views/pagos/residente/subir.php`:
  - Mostrar tarjeta resumen: Deuda Pendiente actual y Saldo a Favor disponible.
  - Mensaje aclaratorio de que pagos superiores generarán saldo a favor.
- `app/views/residente/enviar_pago.php`:
  - Permitir reportar pagos cuando no hay facturas pendientes (pago anticipado).
- `app/views/residente/dashboard.php`:
  - Asegurar que la tarjeta "Saldo a Favor" y la lista de facturas reflejen fielmente el saldo acumulado.

---

## 3. Seguridad y Concurrencia
- Todas las operaciones de deducción y acreditación se ejecutan dentro de transacciones PDO con `FOR UPDATE` en `facturas` y `movimientos_cuenta`.
- Se preserva la validación CSRF y sanitización `e()`.
- Cero inyecciones SQL (PDO con sentencias preparadas estandarizadas).
