# Consulta pública de deuda consistente (Pago Directo)

## Objetivo

Evitar que la consulta pública de deuda muestre facturas ya pagadas y, al mismo
tiempo, un saldo a favor. El estado de cuenta debe reflejar un único instante y
solo listar deuda realmente pendiente.

## Causa

- `PagoDirectoController::deuda()` hacía dos consultas separadas:
  `getResumenFinancieroUnidad()` y `getPendientesByUnidad()`. Entre ambas podía
  cambiar el estado de un pago recién aprobado.
- El listado de facturas no filtraba por estado: una factura marcada `pagada`
  con un saldo residual positivo podía seguir apareciendo como deuda.
- La respuesta JSON no enviaba cabeceras de no-caché y el `fetch` no usaba
  `cache: 'no-store'`, por lo que el navegador podía reutilizar una respuesta
  anterior.

## Solución

- `FacturasModel::getEstadoCuentaUnidad(int $unidadId)` resuelve en **una sola
  consulta**:
  - Facturas con `saldo > 0` y estado `pendiente`/`vencida` → deuda y listado.
  - Facturas con `saldo < 0` → saldo a favor (valor absoluto).
  - Facturas `pagada` o `anulada` con saldo positivo residual → se ignoran.
  - Facturas con `saldo = 0` → no se listan.
- `PagoDirectoController::deuda()` usa ese método y devuelve
  `facturas`, `total_deuda` y `saldo_favor` del mismo snapshot.
- La respuesta agrega `Cache-Control: no-store`, `Pragma: no-cache` y
  `Expires: 0`.
- El JS de la vista agrega `cache: 'no-store'` y un parámetro `_` con
  `Date.now()` para evitar respuestas cacheadas.

## Verificación de datos

- Unidad **Torre 6 - 6-66** (`unidades.id = 4616`):
  - Facturas `CART-6-66-202607` a `CART-6-66-202610`: pagadas, saldo 0.
  - Pago aprobado de Bs. 110.000,00 (2026-10-20).
  - `ABONO-202610-4616-...`: saldo `-3.631,71`.
  - Nuevo estado de cuenta: `total_deuda = 0`, `saldo_favor = 3.631,71`,
    `facturas = []`.
- No hay otras unidades con deuda positiva y saldo a favor simultáneos en la
  base actual.

## Verificación técnica

- `SaldoFavorTest`: 7 tests, 44 aserciones, 0 fallos.
- `PagoDirectoTest`: 14 tests, 108 aserciones, 0 fallos.
- Suite completa: **806 tests, 4570 aserciones, 0 fallos, 0 errores, 5 omitidos**.
- `check_purity` ✅ y `audit_security` ✅.
- `htdocs.zip` / `database/htdocs.zip`: 261 entradas, 2.301.328 bytes, hashes
  idénticos; los archivos del fix coinciden con el repo.

## Archivos

- `app/Models/FacturasModel.php`
- `app/Controllers/PagoDirectoController.php`
- `app/views/pago_directo/index.php`
- `tests/SaldoFavorTest.php`
- `tests/PagoDirectoTest.php`
- `htdocs.zip`
- `database/htdocs.zip`

## Despliegue

- El dump `database/condominio_cobranzas.sql` no cambió: el fix es solo de
  código.
- Subir el nuevo `htdocs.zip` al hosting para que la corrección tome efecto.
