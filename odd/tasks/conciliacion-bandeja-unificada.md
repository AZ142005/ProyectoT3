# Bandeja unificada de Conciliación (pagos + cruce) con filtro de coincidencias y Detalles emergentes

## Objetivo (instrucción del usuario)
- REVERTIR la eliminación de las 4 tarjetas ("cajas") de arriba: restaurarlas como estaban.
- COMBINAR "Pagos por Verificar" y "Cruce Inteligente" en UNA sola bandeja/listado.
- Poner APARTE el filtrado por coincidencias (control propio sobre la tabla).
- Dejar como datos: Residente, Inmueble, Referencia, Monto, Fecha (+ Acción).
- Quitar el botón de descargar comprobante por fila; agregar UN botón "Detalles" emergente (modal) con el estilo del de Historial de Pagos.
- Todo directo sobre `main` (sin ramas), por instrucción explícita del usuario.

## Contexto
- `main` ya contiene la simplificación `2d51de7` (sin tarjetas, secciones S1/S2 separadas, tabs, filas con varios botones, modal `modalDetallePago`, proyectores de payload).
- Patrón de referencia del botón: `app/views/admin/comprobantes.php:169` — botón compacto `bg-slate-100 hover:bg-slate-200 text-slate-700 ... rounded-lg` con ícono `visibility` y title "Ver Detalle". Replicar estilo con texto "Detalles".
- Datos: `$resultadoCruce` (exactas/sugeridas con extracto+pago; inconsistencias con motivo; sin_coincidencia), `$pagosPendientes` (pagos pendientes). Un pago ya emparejado NO debe repetirse como "sin extracto".

## Cambios
### Controlador — `ConciliacionController::index`
- Construir `$filasConciliacion`: una fila por movimiento con `categoria` ('exacta' | 'sugerida' | 'inconsistencia' | 'sin_coincidencia' | 'sin_extracto'), `pago` (o null), `extracto` (o null), `motivo` (si aplica) y `fecha` = `pago.fecha_pago ?: extracto.fecha_movimiento` para ordenar DESC (estable).
- `sin_extracto` = pagos de `$pagosPendientes` cuyo id no aparezca en exactas/sugeridas.
- `$conteosConciliacion` por categoría (para las pills y tarjetas).
- Mantener `$lotes`, `$loteActual`, `$resultadoCruce`, `$pagosPendientes` (tarjetas, 1-Clic y `formLoteExactas` los usan).

### Vista — `app/views/admin/conciliacion/index.php`
1. Restaurar las 4 tarjetas "Métricas Rápidas" EXACTAS desde `git show 2d51de7^:app/views/admin/conciliacion/index.php` (bloque `<!-- Métricas Rápidas / Indicadores Clave -->`), cambiando solo los `onclick`: click sobre la pill del filtro correspondiente (`filtro-exactas`, `filtro-sugeridas`, `filtro-sin-coincidencia`; la de "Pagos por Verificar" → `filtro-todas`) + `scrollIntoView` a `#seccionConciliacion`.
2. UNA sección (id `seccionConciliacion`): header con h5 "Pagos por Verificar y Conciliar" + badge "Total: N" (bandeja completa) + botón "Conciliar Todas las Exactas (1-Clic)" (form `formLoteExactas` intacto).
3. Barra de filtro APARTE (entre header y tabla): pills con ids `filtro-todas`, `filtro-exactas`, `filtro-sugeridas`, `filtro-inconsistencias`, `filtro-sin-coincidencia`, `filtro-sin-extracto`, cada una con contador; `filtro-todas` activa por defecto. Filtrado client-side por `data-categoria` en las filas.
4. Tabla única `id="tablaConciliacion"` con `table-sm`: `Residente | Inmueble | Referencia | Monto (Bs.) | Fecha | Acción`.
   - Pago presente: Residente = `residente_nombre`, Inmueble = `edificio_nombre` + `unidad_numero` ("Torre - Unidad X"), Referencia = `referencia`, Monto = `monto`, Fecha = `fecha_pago`.
   - Solo extracto: Residente/Inmueble = '—', Referencia = `referencia_bancaria ?: referencia`, Monto = `monto`, Fecha = `fecha_movimiento`.
   - Monto conserva clase permitida (`text-end font-monospace fw-bolder text-dark`).
   - Acción: UN botón "Detalles" (estilo historial de comprobantes, ícono `visibility`) → `verDetalleConciliacion(payload)` con payload proyectado: matched `{extracto, pago}`; `sin_extracto` → `{pago}`; extracto-only → `{extracto}`.
5. Eliminar: secciones S1/S2 viejas, tabs/panes (`cruceTabs`, ids `*-tab`), botones por fila (descarga/verificar/conciliar/rechazar inline), columnas `Comprobante`/`Factura`/`Estado Cruce`/`Lote`. Estado vacío único + mensaje `#filtroSinResultados` cuando el filtro no deja filas.
6. Modal: adaptar `verDetalleConciliacion` a las 3 formas; rama SIN PAGO: ocultar acciones (confirmar/conciliar, rechazar, pantalla completa) y mostrar '—' / "Sin pago registrado". Mantener preview y descarga INTERNA del modal (igual que el detalle del historial). Eliminar `verDetallePagoDirecto` (queda sin referencias).
7. JS: listener client-side de filtros; mantener `formLoteExactas` y `conciliarLoteExactas()`.

### Tests (actualizar al nuevo contrato)
- `tests/ConciliacionCentralizadaTest.php`: actualizar `testVistaContieneListaPagosPorVerificar` (headers Residente/Inmueble/Referencia/Monto (Bs.)/Fecha/Acción; se mantienen "Pagos por Verificar" y `/admin/conciliacion/verificar`); actualizar `testVistaMantieneCruceInteligenteYCuatroListas` → las 4 categorías + `sin_extracto` viven como pills `filtro-*` sobre tabla única. Mantener asserts de color-semántico y deprecaciones.
- `tests/ConciliacionSimplificadaTest.php`: quitar asserts obsoletos (métricas ausentes, ids de tabs, descarga, columna Lote); conservar los vigentes (sin `>Estado Cruce<`, sin emojis, `table-sm`) y agregar: tarjetas restauradas, botón `Detalles`, sin descarga por fila, pills de filtro presentes.

## Criterios de aceptación
1. 4 tarjetas restauradas con conteos correctos.
2. Bandeja única con las 5 columnas + botón Detalles; filtro por coincidencias en barra aparte funcionando (6 categorías con contadores).
3. Sin descarga por fila; el modal mantiene preview/descarga interna y las acciones por ítem; filas sin pago no ofrecen acciones.
4. Pagos emparejados no duplicados como "Sin extracto"; pagos sin extracto visibles.
5. Suites Conciliación/Rbac verdes (3 asserts de entorno preexistentes se mantienen); smoke OK.

## Ruta de implementación
- Writer único; verificación del writer + verificador independiente del padre. Directo en `main`.

## Entrega
- Commit directo en `main`: `42c8e05` — feat(conciliacion): bandeja unificada con filtro de coincidencias y detalles emergentes (4 archivos, +413/−481, sin rama). Push: decisión del usuario.

## Verificación
- `php tests/run.php --filter=Conciliacion`: 25 tests / ✅ 133 passed / 3 failed / 0 errors — los 3 fallos son preexistentes de entorno (`rechazoPagoFlujoModel`, falta `.env`).
- `--filter=RbacAuthorization`: 7 / 154 / 0 / 0. `php -l` de los 4 archivos: sin errores.
- Smoke: RENDER OK (260035 bytes) + 7/7 checks.
- Verificador independiente: VERIFICADO. En vivo: 87 filas renderizadas (1 exacta, 85 sin coincidencia, 1 sin extracto) con igualdad exacta de conjuntos contra modelo/servicio; dedup `origen_tabla+id` confirmado (el pago emparejado no se repite como sin_extracto); orden DESC en 87/87; 3 formas del modal (acciones ocultas sin pago); ausencias confirmadas (tabs viejos, Factura, Comprobante, Estado Cruce, descarga por fila).
- Review nativo (RDD): `assess` → `high_risk` / `unassessable` (OpenCode no elegible; misma limitación de siempre). Vía RDD-off aplicada con verificador independiente.
