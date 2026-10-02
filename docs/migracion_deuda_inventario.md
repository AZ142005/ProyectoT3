# Inventario de Referencias al Campo de Deuda y Saldo

Este documento contiene la auditoría completa de los campos y flujos vinculados al cálculo, almacenamiento, lectura y modificación de la deuda en **Condominio Digital (ProyectoT3)**.

---

## 1. Clasificación Metodológica

- **`ESCRITURA_MANUAL`**: Código que permite o facilitaría alterar o fijar el monto de deuda o saldo fuera del módulo de Gastos. Debe eliminarse o bloquearse con validación estricta (HTTP 400).
- **`LECTURA`**: Código que consulta el saldo o deuda para visualización en pantalla, estados de cuenta, reportes o comprobantes. Debe alimentarse de la fuente única de verdad.
- **`DERIVACION`**: Código que calcula o deduce la deuda a partir de gastos comunes, alícuotas o pagos aplicados.
- **`LEGACY`**: Columnas, métodos o fallbacks heredados de fases previas que deben ser deprecados o retirados.

---

## 2. Inventario Consolidado de Referencias

| Componente / Archivo | Elemento / Campo | Clasificación | Descripción y Acción Requerida |
| :--- | :--- | :--- | :--- |
| **BD: `unidades`** | Columna `cuota_mensual` | `LEGACY` | Columna previa donde se fijaba una cuota estática por apartamento. Debe marcarse como legacy (`cuota_mensual_legacy`) y prohibirse su edición manual. |
| **BD: `facturas`** | Columnas `monto`, `saldo` | `DERIVACION` | Representan el cargo y saldo pendiente por periodo. Actualmente generadas desde `FacturasModel::generarFacturasMes`. |
| **BD: `movimientos_cuenta`** | Columna `tipo = 'ajuste'` | `ESCRITURA_MANUAL` | Permite registrar ajustes manuales al saldo deudor. Debe regularse o vincularse a notas de crédito justificadas. |
| **Model: `UnidadesModel.php`** | `getActivas()`, `getDisponibles()` | `LECTURA` | Selecciona `u.cuota_mensual`. Debe eliminarse de la proyección o sustituirse por el estado de cuenta del periodo. |
| **Model: `FacturasModel.php` (L. 141)** | Fallback `$unidad['cuota_mensual']` | `LEGACY` | Si la distribución dinámica da 0, caía a `cuota_mensual`. Debe eliminarse el fallback manual: si no hay gastos aprobados, la cuota es 0. |
| **Model: `FacturasModel.php` (L. 28, 49)** | `getTotalDeudaByUnidad()`, `getResumenFinancieroUnidad()` | `LECTURA` | Calcula la deuda sumando facturas pendientes (`saldo > 0`). Se mantiene como lectura del saldo vivo de facturas o estados de cuenta. |
| **Model: `GastosModel.php` (L. 263)** | `calcularDistribucionCuotas()` | `DERIVACION` | **Fuente de Verdad Actual**: Calcula Fracción Global + Fracción Edificio para cada unidad activa. Base del nuevo servicio de estados de cuenta. |
| **Model: `MovimientosModel.php`** | `obtenerSaldoActualUnidad()` | `LECTURA` | Lee `saldo_posterior` del libro mayor. |
| **Controller: `EstructuraController.php`** | `guardarUnidad()` | `ESCRITURA_MANUAL` (Prevención) | Procesa la creación/edición de unidades. Aunque la vista no envía `monto_a_pagar`, debe agregarse filtro estricto que rechace con 400 cualquier parámetro prohibido (`monto_a_pagar`, `deuda`, `saldo`, `cuota_mensual`). |
| **Controller: `PagoDirectoController.php`** | `deuda()` (GET `/pago-directo/deuda`) | `LECTURA` | Retorna `total_deuda` y desglose de facturas al residente/pagador. |
| **Controller: `ApiController.php`** | `obtenerEstadoCuenta()` | `LECTURA` | Retorna `saldo_actual` y movimientos del libro mayor. |
| **Controller: `ResidenteController.php`** | `index()` | `LECTURA` | Consulta `total_deuda` para el dashboard del residente. |
| **Controller: `ReporteController.php`** | `morosidad()`, `cartaCobro()` | `LECTURA` | Genera reportes de deuda y cartas de cobro. |
| **View: `admin/estructura.php`** | Cabecera y celda "Cuota Mensual" | `LECTURA` / `LEGACY` | Muestra la cuota base de la tabla `unidades`. Debe eliminarse o reemplazarse por desglose de última cuota generada. |
| **View: `auth/register.php`** | Label de apartamento con cuota | `LEGACY` | Muestra `Cuota: Bs. ...` en selector. Debe limpiarse para no mostrar montos estáticos desactualizados. |
| **View: `pago_directo/index.php`** | Panel `#deudaContenido` | `LECTURA` | Muestra la deuda y desglose de facturas. |
| **View: `residente/dashboard.php`** | Tarjeta `total_deuda` | `LECTURA` | Tarjeta visual del saldo deudor. |

---

## 3. Lista de Campos Prohibidos en Entrada (Back-end)

Los siguientes campos deben ser interceptados y rechazados con `400 Bad Request` (`CAMPO_PROHIBIDO`) en todos los endpoints de creación/edición de unidades y administración:
- `monto_a_pagar`
- `deuda`
- `saldo`
- `saldo_deudor`
- `amount_due`
- `monto_manual`
- `deuda_manual`
- `ajuste_manual`
- `override_deuda`
- `cuota_mensual`
