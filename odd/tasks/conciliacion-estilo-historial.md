# Conciliación: filtros fuera de la lista, acciones directas y estilo Historial de Pagos

## Objetivo (instrucción del usuario)
- QUITAR los botones de filtro (pills) que están DENTRO de la lista/bandeja (hoy en un `card-header` de la tarjeta).
- AGREGAR botones para Verificar/Conciliar y Rechazar DIRECTAMENTE por fila.
- ESTILIZAR todo el apartado para que se vea más parecido a **Historial de Pagos** (`app/views/admin/comprobantes.php`).
- Directo en `main` (sin ramas).

## Contexto
- Estado actual: commit `42c8e05` — bandeja unificada Bootstrap con pills de filtro dentro del card, botón único "Detalles" por fila, tabla `table`/`table-sm`.
- Referencia de estilo: `app/views/admin/comprobantes.php` (Historial de Pagos): tarjetas `bg-white rounded-2xl border border-outline-variant p-6 shadow-sm`; mini-header de filtros con ícono `filter_alt` + label uppercase; tabla `w-full text-left text-sm border-collapse` con thead `text-xs uppercase text-on-surface-variant font-bold border-b border-background`, td `py-4 px-4`, filas `hover:bg-background/40 transition-colors`; botón de acción tipo ícono `bg-slate-100 hover:bg-slate-200 text-slate-600 p-1.5 rounded-lg border border-slate-200` con `title="Ver Detalle"`.
- Referencia de botones directos: `app/views/pagos/admin/lista.php` — verdes `bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 px-3 py-1.5 rounded-lg text-xs font-bold`, rojos `bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 ...`.
- Restricciones: mantener ids/atributos funcionales (`filtro-*`, `tablaConciliacion`, `seccionConciliacion`, `formLoteExactas`, `modalDetallePago`, `modalRechazarConciliacion`, `filtroSinResultados`); POSTs existentes; el filtro sigue siendo client-side.

## Cambios (vista `app/views/admin/conciliacion/index.php`; sin cambios de controlador)
1. SACAR el bloque de pills (`card-header bg-light` con `ul#filtrosConciliacion`) de DENTRO de la tarjeta de la bandeja.
2. CREAR una tarjeta de filtros propia ENTRE las métricas y la bandeja (estilo del mini-header de filtros del historial):
   - `<div class="bg-white rounded-2xl border border-outline-variant p-4 shadow-sm mb-8">`
   - Header interno: ícono `filter_alt` + `Filtros de Coincidencia` (uppercase xs).
   - Pills con los mismos ids/`data-filtro-categoria`/contadores (`filtro-todas|exactas|sugeridas|inconsistencias|sin-coincidencia|sin-extracto`), re-estilizadas Tailwind: base `px-3 py-1.5 rounded-full border text-xs font-bold bg-slate-100 text-slate-700 border-slate-200`, activa `bg-primary text-white border-primary` (clase `active` togglada por el JS existente; agrega un `<style>` mínimo para `.filtro-pill.active` o usa clases utilitarias con JS).
3. RESTYLE general estilo historial:
   - Métricas: 4 tarjetas Tailwind (`grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8`) con el mismo contenido/conteos/onclicks (acento izquierdo por color, ícono, label uppercase, número grande, subtítulo).
   - Barra superior de acciones (lote + Importar Extracto): campos Tailwind (`px-3 py-2.5 bg-background border border-outline-variant rounded-xl text-xs`) y botón primario (`bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2 rounded-xl shadow-sm text-xs`); conservar `onchange` del select y `data-bs-target` del botón.
   - Tarjeta de la bandeja: `bg-white rounded-2xl border border-outline-variant p-6 shadow-sm`; header con título `text-lg font-bold text-on-surface` + badge `bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant` (Total) + botón 1-Clic Tailwind verde.
   - Tabla: `w-full text-left text-sm border-collapse`; thead como la del historial; th `py-3 px-4`; td `py-4 px-4`; filas `hover:bg-background/40 transition-colors`; SIN `table`/`table-sm`/`table-hover` de Bootstrap. Mismas columnas: Residente | Inmueble | Referencia | Monto (Bs.) | Fecha | Acción.
   - Estados vacíos estilo historial (`text-center py-12 text-on-surface-variant`).
4. ACCIONES DIRECTAS por fila (contenedor `flex items-center justify-end gap-1.5`; conservar `data-categoria`):
   - Con pago + extracto (exacta/sugerida): form POST `/admin/conciliacion/conciliar` (csrf + `extracto_id` + `pago_id` + `origen_tipo`, `onsubmit` con confirm) → botón verde **"Conciliar"**; botón rojo **"Rechazar"** → `abrirModalRechazo(...)`; botón **Detalles** (ícono `visibility`, `title="Ver Detalle"`, estilo historial).
   - Con pago sin extracto (`sin_extracto`): form POST `/admin/conciliacion/verificar` (csrf + `pago_id` + `origen_tipo`, confirm) → botón verde **"Verificar"**; "Rechazar"; "Detalles".
   - Solo extracto (inconsistencia/sin_coincidencia): SOLO botón "Detalles".
   - El botón "Detalles" pasa a ser de ícono (como el del historial); el modal sigue igual (3 formas, descarga interna, acciones ocultas sin pago).
5. El JS del filtro se mantiene (misma función, pills reubicadas); `#filtroSinResultados` se mantiene.

## Tests
- `tests/ConciliacionCentralizadaTest.php`: mantener headers/literales vigentes; ACTUALIZAR `testVistaRestauraColoresSemanticosExceptoNumeros` al nuevo contrato Tailwind (verde/rojo/gris de acciones; conservar la intención protectora sobre montos) — si el regex anterior ya no aplica, reemplazarlo por asserts equivalentes del nuevo estilo.
- `tests/ConciliacionSimplificadaTest.php`: `table-sm` → nuevas clases de tabla (`border-collapse`, `text-sm`); "Detalles" → `title="Ver Detalle"`; agregar: botones "Conciliar"/"Verificar"/"Rechazar" por fila presentes y `filtrosConciliacion` presente (fuera de la lista); conservar: tarjetas, pills, `Conciliar Todas las Exactas`, sin descarga por fila, sin `>Estado Cruce<`, sin emojis.

## Criterios de aceptación
1. No hay pills de filtro dentro de la tarjeta de la bandeja; existe tarjeta "Filtros de Coincidencia" aparte (mismos ids y filtrado client-side funcionando; las tarjetas de métricas siguen filtrando).
2. Filas con acciones directas: Conciliar/Verificar (verde) + Rechazar (rojo) + Detalles (ícono) según tipo; POSTs con csrf; solo-extracto únicamente Detalles.
3. Estilo general consistente con Historial de Pagos (tarjetas, filtros, tabla Tailwind, estados vacíos); sin clases Bootstrap de tabla.
4. Modal sin cambios funcionales (3 formas; acciones ocultas sin pago; descarga interna).
5. Suites Conciliación/Rbac verdes (3 asserts de entorno preexistentes se mantienen); smoke OK.

## Ruta de implementación
- Writer único; verificación del writer + verificador independiente del padre. Directo en `main`.

## Entrega
- Commit directo en `main`: `fe0001f` — feat(conciliacion): filtros fuera de la lista, acciones directas por fila y estilo del historial de pagos (3 archivos, +232/−182, sin rama). Push: decisión del usuario.

## Verificación
- `php tests/run.php --filter=Conciliacion`: 25 tests / ✅ 144 passed / 3 failed / 0 errors — mismos 3 fallos de entorno preexistentes (`rechazoPagoFlujoModel`, falta `.env`).
- `--filter=RbacAuthorization`: 7 / 154 / 0 / 0. `php -l` de los 3 archivos: sin errores.
- Smoke: RENDER OK (265097 bytes) + 8/8 checks.
- Verificador independiente: VERIFICADO. En vivo (87 filas: 1 exacta, 85 sin_coincidencia, 1 sin_extracto): pills fuera de la bandeja y antes de ella; forms directos correctos con csrf (1 `/conciliar`, 1 `/verificar`); solo-extracto únicamente Detalles; conteos de pills = filas; estilo Tailwind del historial sin clases Bootstrap de tabla en la principal; modal/JS del filtro **byte-idénticos** a `73e1e1c` (SHA256 de la cola del archivo).
- Review nativo (RDD): `assess` → `high_risk` / `unassessable` (OpenCode no elegible). Vía RDD-off con verificador independiente.
