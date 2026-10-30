# Filtro de unidades solventes y saldo a favor en Carta de Deuda

## Objetivo

Agregar a la sección Carta de Deuda un filtro por estado financiero que permita
visualizar:

- Unidades solventes (sin deuda vencida).
- Unidades con saldo a favor.
- Unidades con deuda.
- Todas las unidades.

Además, mostrar el monto de saldo a favor por unidad en la tabla, en la impresión y
en la exportación CSV.

## Contexto

- La sección es `ReporteController::morosidad()` → vista `admin/reportes/morosidad.php`.
- Hoy la tabla ya lista todas las unidades, pero no hay filtro por estado y no se
  muestra el saldo a favor.
- El saldo a favor se guarda en `facturas` con `saldo < 0` (series `ABONO-…`);
  `FacturasModel::getSaldoFavorByUnidad()` lo calcula como `ABS(SUM(saldo))`.
- Un test existente prohíbe el filtro `name="estado"` en esta vista; debe actualizarse
  porque el requerimiento cambió.

## Decisiones

- Filtro `estado` con valores: vacío (todas), `solvente`, `saldo_favor`, `deudor`.
- `solvente` = `total_deuda` vencida igual a 0 (incluye unidades con saldo a favor).
- `saldo_favor` = suma de saldos negativos mayor a 0.
- La tabla, la impresión y el CSV muestran una columna "Saldo a Favor (Bs)".
- Los KPIs incorporan `saldo_favor_total` y `unidades_con_saldo_favor`.
- Los edificios sin unidades que cumplen el filtro se omiten del consolidado.

## Tareas

- [x] T1. Actualizar los tests de `BalanceAgrupadoEdificiosTest` al nuevo
  comportamiento (filtro por estado y columna de saldo a favor).
- [x] T2. Extender `ReportesModel::obtenerReporteBalanceAgrupadoPorEdificio()` con
  `saldo_favor` y el filtro `estado`.
- [x] T3. Extender `ReportesModel::obtenerReporteMorosidadCompleto()` (impresión/CSV)
  con `saldo_favor` y el filtro `estado`.
- [x] T4. Extender `ReportesModel::obtenerKpisMorosidad()` con los KPIs de saldo a favor.
- [x] T5. Agregar la columna de saldo a favor al CSV.
- [x] T6. Pasar `estado` desde `ReporteController::morosidad()` y ordenar por saldo a
  favor cuando corresponda.
- [x] T7. Agregar el select de estado, la columna y el badge de saldo a favor en
  `morosidad.php`.
- [x] T8. Agregar la columna de saldo a favor en `imprimir.php`.
- [x] T9. Ejecutar tests focalizados, suite completa, pureza y auditoría.

## Criterios de aceptación

- El select de estado existe con las cuatro opciones.
- `estado=solvente` devuelve solo unidades con deuda vencida 0.
- `estado=saldo_favor` devuelve solo unidades con saldo a favor > 0.
- `estado=deudor` devuelve solo unidades con deuda vencida > 0.
- Solventes + deudores = total de unidades (partición).
- La tabla, la impresión y el CSV muestran el saldo a favor.
- Los tests nuevos y existentes pasan (salvo el fallo ajeno conocido de
  `LiquidacionPagoTest`).

## Verificación

- `php tests/run.php --filter=BalanceAgrupadoEdificiosTest`
- `php scripts/check_purity.php`
- `php scripts/audit_security.php`
- `php tests/run.php`

## Progreso

- Implementado en `ReportesModel`, `ReporteController` y las vistas `morosidad.php` e
  `imprimir.php`.
- El filtro `estado` acepta: vacío (todas), `solvente`, `saldo_favor`, `deudor`.
- El saldo a favor se calcula como `SUM(-saldo)` de las facturas con `saldo < 0` y
  `deleted_at IS NULL`, igual que `FacturasModel::getSaldoFavorPorUnidad`.
- La tabla muestra la columna "Saldo a Favor (Bs)", un badge "Saldo a Favor" y la
  tarjeta de unidades solventes muestra el total de crédito.
- CSV e impresión incluyen la columna de saldo a favor.
- Verificación real: 252 unidades, 36 solventes, 216 deudoras, partición correcta.
  No hay saldo a favor registrado hoy en la BD; el fixture transaccional del test
  demuestra que el filtro lo detecta.
- Tests: 13/13 en `BalanceAgrupadoEdificiosTest`; suite completa 782 tests, 4422
  aserciones, 0 fallos, 5 omitidos.
- `check_purity` ✅ y `audit_security` ✅.

## Próximo paso

Revisión funcional en navegador con un saldo a favor real; el filtro y la columna ya
quedan cubiertos por tests.
