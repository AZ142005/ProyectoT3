# Reversión de depósito de prueba - Unidad 1-25

## Objetivo

Deshacer el depósito de Bs. 50.000,00 registrado como prueba en la unidad 1-25 y
restaurar la deuda que había quedado cubierta por ese pago.

## Hallazgo

- Pago `#3973`, unidad `1-25` (id 4413), Bs. 50.000,00, referencia `1234`,
  estado `APROBADO`, sin comprobante en `comprobantes_pago`.
- Aplicación original:
  - `CART-1-25-202609`: Bs. 26.650,49 pagados (saldo 0).
  - `CART-1-25-202610`: Bs. 23.349,51 pagados (saldo 2.417,97).
- Movimiento `#2393` `abono_pago` por Bs. 50.000,00.

## Acciones aplicadas

- Facturas restauradas:
  - Septiembre: pagado 0,00 / saldo 26.650,49 / vencida.
  - Octubre: pagado 0,00 / saldo 25.767,48 / vencida.
- Contra-asiento en `movimientos_cuenta`: `#3194`, tipo `ajuste`, +Bs. 50.000,00,
  saldo anterior -50.000,00 → saldo posterior 0,00. El movimiento original se conserva.
- Pago `#3973` marcado `RECHAZADO` y soft-deleted; `dup_guard` queda NULL y la
  referencia 1234 vuelve a estar disponible.
- Auditoría `#5455`: APROBADO → RECHAZADO, acción `anular_pago_prueba`, admin 1.

## Verificación

- Deuda total de la unidad 1-25: **Bs. 52.417,97**.
- Pago: `RECHAZADO`, `deleted_at` 2026-10-29 13:42, `dup_guard` NULL.
- Suite completa: 799 tests, 4536 aserciones, 0 fallos, 0 errores, 5 omitidos.
- `check_purity` ✅ y `audit_security` ✅.

## Script

`scripts/revertir_pago_prueba_1_25.php`:
- Sin `--apply` ejecuta una simulación transaccional.
- Con `--apply` aplica la reversión.
- Valida que el pago siga APROBADO, que lo aplicado sume 50.000 y que los saldos
  sean consistentes antes de tocar nada.

## Nota

El archivo del comprobante (`e85ceefebeb50ecdb6446d02c6e2e598.jpeg`) se conserva como
evidencia del pago anulado. Si se desea eliminarlo, hacerlo de forma explícita.
