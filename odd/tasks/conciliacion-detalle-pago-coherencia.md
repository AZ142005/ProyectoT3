# Conciliación: coherencia estética del modal "Detalle del Pago" (visualización emergente)

## Objetivo (instrucción del usuario)
"haz que la visualización emergente de detalles de pago tenga coherencia estética con el resto del sistema."
→ Modal `#modalDetallePago` de `app/views/admin/conciliacion/index.php` (L340-497) + su JS `verDetalleConciliacion` (L568+).

**Directo en main** (preferencia del usuario, sin rama).

## Contexto / inventario actual
- Header `bg-dark text-white` (fuera de paleta; único `bg-dark` del admin); icono `receipt_long` con `text-primary` sobre fondo oscuro.
- Badge de estado en el header: `badge bg-warning text-dark ms-2 fw-bold` — y el JS (L574) SOLO cambia `textContent`, nunca las clases ⇒ un pago APROBADO se muestra amarillo "PENDIENTE" (bug visual incluido en la coherencia).
- Body `bg-light` (idioma aceptado de los modales del sistema: se CONSERVA), cards internas ya `card border-0 shadow-sm rounded-3`; headers de card `text-muted`.
- Tabla comparativa Bootstrap pura: `table table-sm table-bordered align-middle`, thead `table-light`, celdas `text-muted` / `text-dark` / `font-monospace` / `fw-bolder` / `text-success`.
- Ficha residente: `text-muted` / `text-dark` / `font-monospace`; wrapper de factura `bg-primary-subtle border-primary-subtle`; observaciones `bg-light text-muted fst-italic`.
- Columna derecha: botón `btn btn-sm btn-primary fw-bold` (Bootstrap azul), labels `text-muted`, empty state `text-muted`.
- Footer: `btn btn-outline-secondary` (Pantalla Completa), `btn btn-light border` (Cerrar), `btn btn-success` (Aprobar y Conciliar), `btn btn-danger` (Rechazar Pago).
- Iconos con utilidades Bootstrap `fs-4` / `fs-6`.
- Tests: `ConciliacionSimplificadaTest` y `ConciliacionCentralizadaTest` escrapean la vista, pero SOLO la bandeja/filtros/payloads (región distinta). Grep confirmado: 0 tests referencian `modalDetallePago`, `bg-dark`, `table-bordered`, `font-monospace`. `title="Ver Detalle"` y los contratos de la bandeja NO se tocan.
- RDD: on; OpenCode unassessable → writer + verificador independiente + spot check. TDD no configurado.

## Alcance (4 tareas, 2 archivos)
- T1. Header + badge dinámico:
  - `modal-header bg-dark text-white py-3` → `modal-header bg-primary text-white py-3`; icono `fs-4 text-primary` → `text-2xl text-white`.
  - Badge base: `badge rounded-pill px-3 py-1 ms-2 fw-bold bg-warning text-on-surface` (texto inicial PENDIENTE; patrón de casa de badges warning: comunicados L98 / solicitudes L91 / estacionamientos L163).
  - JS `verDetalleConciliacion`: al setear el badge, asignar `className` completo = base + color por estado (mantener la lógica de `textContent` existente): APROBADO/VERIFICADO → `bg-success text-white`; RECHAZADO → `bg-danger text-white`; PENDIENTE → `bg-warning text-on-surface`; otro / SIN PAGO → `bg-secondary text-white`.
- T2. Cuerpo del modal — tokens de casa:
  - `text-muted` → `text-on-surface-variant`; `text-dark` → `text-on-surface`; `font-monospace` → `font-mono`; `fs-6` → `text-[16px]` (todo dentro del modal).
  - Tabla comparativa → patrón de casa: `<table class="w-full text-left text-sm border-collapse">` (quitar `table table-sm table-bordered align-middle mb-0`); fila thead `text-xs uppercase text-on-surface-variant font-bold border-b border-background` (quitar `table-light small`); `th` con `py-3 px-3`; columna "Extracto Bancario" `text-primary`, "Pago Reportado" `text-green-600` (hoy `text-success`); `tbody` `divide-y divide-background`; `td` `py-3 px-3`; montos `font-bold text-on-surface font-mono`; referencias `font-mono text-xs`; resto `text-sm`. CONSERVAR todos los ids `mdlExt*` / `mdlPago*`.
  - Ficha: labels `text-on-surface-variant`; valores `text-on-surface`; cédula `font-mono`; wrapper factura → `bg-primary/10 border border-primary/20 rounded p-2 text-primary small`; observaciones → `bg-background text-on-surface-variant fst-italic` (conservar ids).
- T3. Columna derecha + footer:
  - Descargar → R1 compacto: `bg-primary hover:bg-primary-hover text-white font-bold px-4 py-2 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5` + icono `download` `text-[16px]` (conservar `id="mdlBtnDescargar"`, `href`, `download`).
  - Empty state `text-muted` → `text-on-surface-variant`.
  - Footer: Pantalla Completa → R2 (`bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5`); Cerrar → `bg-white hover:bg-slate-100 text-slate-700 border border-outline-variant font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5`; Aprobar y Conciliar → `bg-green-600 hover:bg-green-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5 shadow-sm`; Rechazar Pago → misma forma con `bg-red-600 hover:bg-red-700`.
  - CONSERVAR: ids (`mdlLinkPantallaCompleta`, `mdlBtnConfirmarConciliacion`, `mdlBtnRechazarModal`), la estructura `span:last-child` del botón Aprobar (icono + span de texto) que usa el JS, `onclick` con confirm, form/`csrf_field()`/action/hidden inputs, `data-bs-dismiss`.
  - `bg-light` del body/footer/comparativas se CONSERVA (idioma aceptado de modales; solo desaparece `bg-dark`).
- T4. Test nuevo `tests/ConciliacionDetallePagoVistaTest.php`:
  - Extrae la región entre `Modal Ventana de Detalles de Pago` y `Modal para Rechazar Pago`.
  - Asserts: región no vacía; NO contiene `bg-dark`, `table-bordered`, `table-light`, `font-monospace`, `btn btn-`; SÍ contiene `mdlEstadoBadge`, `rounded-pill`, `text-on-surface-variant`; y el archivo contiene el mapeo JS (`bg-success` y `bg-danger`).

## Restricciones
- Solo `app/views/admin/conciliacion/index.php` + el test nuevo. NO tocar banda de métricas, filtros, bandeja (`tablaConciliacion`), paginación, otros modales (Importar/Rechazar), payloads/proyecciones JSON, ni el JS fuera del bloque del badge de estado del detalle.
- No cambiar textos visibles ni ids ni atributos funcionales.
- Writer no commitea.

## Verificación
- Writer: `php -l` de ambos archivos; `php tests/run.php` → exit 0; filtros: `--filter=Conciliacion` (cubre Simplificada/Centralizada/Test/Concurrencia/Twins), `--filter=PaginacionEstandarTest`, `--filter=RbacAuthorizationTest`, `--filter=ConciliacionDetallePagoVistaTest`; clases fuera del run completo: SolicitudesRegistro, UsuarioAdmin, UsuariosSolicitudesTabs → exit 0. Diff acotado a 2 archivos.
- Verificador independiente read-only: región del modal, mapeo JS del badge, preservación de hooks (`span:last-child`, ids, forms), suites, adversarial.
- Spot check del padre. Commit directo en `main`. Push: decisión del usuario.

## Evidencia (cierre 2026-10-03)
- T1-T4 aplicadas: header `bg-primary`; tokens de casa en todo el modal (`text-on-surface-variant`/`text-on-surface`/`font-mono`/`text-[16px]`); tabla comparativa al patrón del Historial (`border-collapse`/`divide-y`/`py-3 px-3`); botones R1/R2/verde sólido/rojo sólido; badge de estado con mapeo de clases por estado en JS (antes quedaba siempre amarillo).
- Hallazgo del verificador (falsificación real): el doc/checklist originales propagaban `bg-warning text-dark`; el patrón de casa es `bg-warning text-on-surface` (comunicados L98 / solicitudes L91 / estacionamientos L163). Corregido en la vista (L348) y el JS (L579), doc actualizado, y el test reforzado (prohibición de `text-dark` en la región + asserts del mapeo: token warning, ausencia de `text-dark`, fallback `secondary`).
- `php -l` 2/2; `--filter=ConciliacionDetallePagoVistaTest` exit 0 (3 tests / 18 asserts tras refuerzo); `--filter=Conciliacion` exit 0 (39 tests / 314 asserts); demás filtros verdes (Paginacion/Rbac/SolicitudesRegistro/UsuarioAdmin/UsuariosSolicitudesTabs). Early-exit preexistente del runner en SecurityTest re-confirmado (clases afectadas cubiertas por filtro).
- Alcance: 26 hunks en la vista, TODOS dentro del modal (L344-489) + la línea JS del badge; `bg-light` de modales conservado; `bg-dark` eliminado del archivo. `text-dark` restante solo fuera de la región (header de página y modal de Rechazo — fuera de alcance).
- Verificador independiente: veredicto inicial **NO PASA** por `text-dark` → corregido (vista/JS/test/doc) y re-chequeado por el padre (`php -l` + filtros + grep de la región: 0 `text-dark`).
- Commit: 18cae26 (directo en main) + commit docs de cierre. Push: pendiente (decisión del usuario).
- RDD: on; assess high/unassessable (untracked + runtime V2 sin elegibilidad inmutable), sin `next_transition` → ruta RDD-off aplicada (writer + verificador independiente + spot check).
