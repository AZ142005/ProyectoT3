# Simplificación visual del apartado Conciliación

## Objetivo
Reducir la sobrecarga visual de `/admin/conciliacion` combinando información duplicada y quitando ruido, **sin perder datos ni utilidad operativa**. Todo ocurre en `app/views/admin/conciliacion/index.php` (+ tests).

## Contexto (del inventario)
- La vista (943 líneas, 73KB) apila: barra de acciones con h4 duplicado del h1, 4 tarjetas "Métricas Rápidas", Sección 1 "Pagos por Verificar", Sección 2 "Cruce Inteligente" con 4 tabs y 3 modales.
- Duplicaciones confirmadas: las 4 tarjetas KPI repiten exactamente los contadores del badge "Total" (S1) y de los 4 tabs; columnas "Estado Cruce" son constantes por tab; subtítulos repiten el h1/h5; el botón "ver" de comprobante duplica la previsualización del modal de detalle; los `json_encode` inline embeben PII (email/teléfono) que el modal no usa.
- Restricciones de tests (deben seguir verdes): literales `Pagos por Verificar`, `>Residente<`, `>Inmueble<`, `>Factura<`, `>Referencia<`, `>Monto (Bs.)<`, `>Fecha Pago<`, `>Comprobante<`, `>Acción<`, `download=1`, `/admin/conciliacion/verificar`; ids `exactas-tab`, `sugeridas-tab`, `inconsistencias-tab`, `sin-coincidencia-tab`, `exactas`, `sugeridas`, `inconsistencias`, `sin-coincidencia`; regex de colores semánticos (no combinar `font-monospace` con `text-*` en tds); prohibidos `100% Match`/`Fuzzy Match`.
- Decisiones conservadoras: se CONSERVAN tabs, tablas, acciones por fila (detalle/verificar/rechazar/conciliar), modales, selector de lote y botón 1-Clic; solo se quita duplicación y ruido.

## Cambios (vista)
1. Eliminar el bloque completo "Métricas Rápidas / Indicadores Clave" (4 tarjetas + comentario). Los conteos ya están en el badge "Total" de S1 y en los contadores de los tabs.
2. Barra de acciones: eliminar el h4 "Centro de Verificación y Conciliación" y su subtítulo (duplican el h1); conservar select "Lote activo" (id/onchange intactos) y botón "Importar Extracto" (data-bs-target intacto) en una fila compacta.
3. S1: eliminar su subtítulo (el h5, badge "Total" y tabla quedan). En "Comprobante": eliminar el botón "ver" inline (duplica la previsualización del modal); conservar SOLO la descarga directa (`download=1`). No tocar la columna "Acción" ni sus forms.
4. S2: conservar tabs/ids/contadores y el botón 1-Clic; quitar emojis 🟢🟡🔴⚪ de las etiquetas; conservar (o recortar a una línea) el subtítulo que explica el cruce.
5. Tab Exactas: eliminar la columna constante "Estado Cruce" (th + td). Ajustar colspan del vacío.
6. Tab Sin Coincidencia: eliminar la columna constante "Estado Cruce"; columna "Lote" condicional: visible solo cuando no hay lote específico seleccionado (`$loteActual` vacío = Todos); ajustar colspans.
7. Densidad: `table-sm` en las 5 tablas de datos (conservando clases actuales).
8. Código muerto: eliminar la rama del `auditor_sidebar` (el controlador exige admin): siempre `admin_sidebar`, conservando `$activeRoute='conciliacion'`.
9. PII/DOM: en los 3 call sites con `json_encode` inline (detalle pago directo, detalle cruce exactas, detalle cruce sugeridas), proyectar SOLO los campos que las funciones JS (`verDetallePagoDirecto`, `verDetalleConciliacion`) usan realmente (trazar cada acceso `.X` de cada función). Sin cambiar firmas ni lógica del modal.

## Tareas
- [x] T1 — Vista: cambios 1-9 implementados en el commit `2d51de7`.
- [x] T2 — Tests: nuevo `tests/ConciliacionSimplificadaTest.php` (7 métodos: ausencia de bloque de métricas, h4 duplicado, columnas Estado Cruce y emojis; presencia de tabs, 1-Clic, `download=1`, `table-sm`; condicionalidad de Lote y proyección de payloads) + suites existentes intactas. Mismo commit `2d51de7`.
- [x] T3 — Verificación: lint + `--filter=Conciliacion` + `--filter=RbacAuthorization` + smoke de render; resultados abajo.

## Criterios de aceptación
1. La vista no contiene el bloque de métricas, ni el h4 duplicado, ni subtítulos duplicados, ni columnas "Estado Cruce", ni emojis en tabs.
2. Se conservan: 4 tabs con sus ids, contadores, acciones por fila, modales, selector de lote (funcional), botón 1-Clic, y todos los literales que exigen los tests.
3. Columna "Lote" de Sin Coincidencia condicional correctamente (colspans coherentes).
4. Payloads `json_encode` solo con los campos usados por el JS (sin email/teléfono si no se usan).
5. Suites de Conciliación y Rbac en verde; smoke OK.

## Ruta de implementación
- Delegada a un único writer (vista + tests). Verificación del writer en primer plano; smoke del padre + verificador independiente.

## Entrega
- Rama `feat/conciliacion-simplificada` desde `main` @ `7cab77e`; commit `2d51de7` — refactor(conciliacion): simplificar la vista quitando duplicaciones y ruido visual.
- Diff: 2 archivos, +149/−129 (vista + test nuevo; <400). Push y merge: decisión del usuario.

## Verificación
- `php tests/run.php --filter=Conciliacion`: 25 tests / ✅ 133 passed / 3 failed / 0 errors. Los 3 fallos son PREEXISTENTES y de entorno: `rechazoPagoFlujoModel` (sin `.env` → `NOTIFICATION_ENCRYPT_KEY`/`APP_KEY`; el archivo de test no cambió y no lee la vista; mismo resultado en la baseline pre-cambio).
- `--filter=RbacAuthorization`: 7 / 154 / 0 / 0. `php -l` de vista y test: sin errores.
- Smoke: RENDER OK (188KB) + 6/6 checks. Verificador independiente: 40/40 checks en dos renderizados (lote por defecto y "Todos"), incluido el toggle dinámico de la columna Lote y ausencia de `residente_email`/`residente_telefono` en el HTML.
- Traza de payloads verificada: `pago` 17/17 campos y `extracto` 6/6 campos exactos a lo usado por el JS; `match` solo expone `extracto`+`pago`.
- Review nativo (RDD): `assess` → `high_risk` / `unassessable` (OpenCode no elegible; misma limitación de siempre). Vía RDD-off aplicada: verificación del writer + verificador independiente.
