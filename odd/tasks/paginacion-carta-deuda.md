# Paginación de 50 unidades en Carta de Deuda

## Objetivo

Paginar de 50 en 50 las unidades de la tabla de Carta de Deuda, conservando los
filtros activos y mostrando el rango visible.

## Decisiones

- Paginación server-side en `ReporteController::morosidad()`: `porPagina = 50`,
  `page` por GET, `array_slice` sobre la lista plana ya ordenada.
- Los enlaces de página conservan `edificio_id`, `dias_mora` y `estado`; los filtros
  vacíos no se propagan.
- El filtro y el botón Limpiar vuelven a la página 1.
- El buscador rápido original era client-side y solo filtraba la página actual; fue
  reemplazado por la búsqueda server-side descrita abajo.
- La exportación CSV y la impresión siguen incluyendo todas las unidades filtradas,
  no solo la página visible.

## Tareas

- [x] T1. Paginar la lista en el controlador (50 por página, con rango desde/hasta).
- [x] T2. Mostrar controles de paginación y rango en la vista.
- [x] T3. Ajustar el contador de unidades y el texto del buscador.
- [x] T4. Agregar test de paginación.
- [x] T5. Ejecutar tests, pureza y auditoría.

## Verificación

- `BalanceAgrupadoEdificiosTest`: 14 tests, 881 aserciones, 0 fallos.
- Suite completa: 783 tests, 4425 aserciones, 0 fallos, 1 error ajeno.
  El error es `EstacionamientosNumeroTest::puestoBorradoNoCuentaYLiberaNumero`:
  existe un residuo `estacionamientos.id=560` con `unidad_id=0` (borrado, número
  1-25) que choca con `uk_estacionamiento_unidad`. `softDelete` intenta
  `unidad_id=NULL` sobre una columna `NOT NULL`; en modo no estricto queda 0. Es un
  defecto preexistente de Estacionamientos, no de Carta de Deuda.
- `check_purity` ✅ y `audit_security` ✅.

## Próximo paso

Revisar el defecto de `EstacionamientosModel::softDelete` (columna `unidad_id` no
nullable + clave única) en una tarea aparte si se quiere dejar la suite 100% verde en
este entorno.

## Actualización 2026-10-29 — búsqueda server-side en todos los departamentos

- El buscador client-side (`buscadorBalance` / `filtrarBalance`) fue eliminado. Ahora
  hay un campo `name="buscar"` dentro del formulario GET de filtros.
- `ReporteController::filtrosBalance()` centraliza `edificio_id`, `dias_mora`, `estado`
  y `buscar`, y lo propaga a listado, impresión y exportación CSV.
- `ReportesModel::aplicarBusquedaBalance()` filtra **antes de paginar** por:
  edificio, número de unidad, nombre/apellido del propietario o cédula.
- La búsqueda aplica a todos los departamentos, no solo a las 50 filas de la página
  actual; los enlaces de paginación conservan el término.
- Se eliminaron el bloque `sinResultadosBalance` y el JS de filtrado en vivo; el estado
  vacío lo maneja la tabla cuando `$morosos` está vacío.
- `BalanceAgrupadoEdificiosTest`: 17 tests, 895 aserciones, 0 fallos.
- Suite completa: **804 tests, 4554 aserciones, 0 fallos, 0 errores, 5 omitidos**.

