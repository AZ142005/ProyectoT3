# ODD task: Modales al estilo de Comunicados (Carga y Emisión + Cuentas Bancarias) + inventario del resto

**Estado**: COMPLETADO (2026-10-17) — 5 commits en `main` local, sin push (2 iniciales + 3 de los modales restantes tras confirmación del usuario).
**Ruta**: delegated-direct — 1 writer (2 vistas) + 1 mapper de inventario en paralelo (sin solape de archivos).

## Objetivo

- [x] Modal de Carga y Emisión (`#modalNuevoGasto`, ambos formularios: deuda manual + gasto clásico) al estilo del modal de Comunicados.
- [x] Modal de Cuentas Bancarias (`#modalCuenta`) al mismo estilo.
- [x] Inventario de todas las demás ventanas emergentes del sistema comparadas con el estilo de referencia.

## Estilo de referencia (modal de Comunicados)

- Cascarón: `modal-content rounded-3 border-0 shadow-lg`; header `bg-primary text-white py-3` + icono material + título `fw-bold flex-fill d-flex align-items-center gap-2` + `btn-close btn-close-white`.
- Labels: `form-label fw-bold small text-on-surface-variant`.
- Controles: `w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors` (+ `cursor-pointer` en selects).
- Paneles internos y pickers: `bg-background rounded-xl border border-outline-variant`.
- Footer `bg-light` con botones pill: cancelar `bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs …`; primario `bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs …`.

## Cambios aplicados (SOLO clases; ids/names/atributos/JS/textos/lógica intactos)

- `8b8ea1b` — `ui(gastos): modal de carga y emision al estilo de comunicados` (51+/45−).
- `94f4c70` — `ui(cuentas-bancarias): modal al estilo de comunicados` (24+/24−).
- Cuentas: `rounded-4 overflow-hidden` → `rounded-3 border-0 shadow-lg`. Tamaños/posición de diálogo intactos (`modal-lg` / `modal-dialog-centered modal-lg`).
- Excepción documentada: `btnGuardarCuenta` sin icono material (su JS usa `textContent` y borraría el `<span>`).
- El cuadro azul del contador de deuda manual y los `form-check-input` se conservaron (consistente con el picker de comunicados).

## Inventario del resto (mapper read-only 2026-10-17)

**Coinciden con Comunicados**: estacionamientos (`modalAsignar_*`, `modalNuevoPuesto`, `modalNuevoVehiculo`); usuarios `modalCrearUsuario`; conciliación `modalDetallePago` (informativo; botones con variantes semánticas).

**Alineados (2026-10-17, confirmado por el usuario)** — commits `6cefd84` (conciliación), `9e29206` (solicitudes), `50fae52` (usuarios):
- conciliación `modalImportarExtracto` y `modalRechazarConciliacion` (header danger conservado; botón rojo reutilizado de `#mdlBtnRechazarModal`).
- solicitudes_registro `modalRechazarSolicitudRegistro` (`shadow-lg` + icono `block` añadidos).
- usuarios `modalReiniciarPassword` (botón ámbar reutilizado de `reportes/morosidad.php`), `modalActualizarDatos` (input-group tokenizado: addon `rounded-s-xl`/input `rounded-e-xl`), `modalEliminarUsuario` (dos pasos intactos; outline rojo `bg-red-50` + `disabled:*` preservados).

**Otro lenguaje de diseño (Tailwind puro, sin Bootstrap — no se propone cambiar sin pedido explícito)**: estructura `modalEdificio`/`modalUnidad`; logout global `modalCierreSesion`; pagos `modalRechazo` (×2); lightboxes de residente (`modalComprobante`, `modalVisorSoporte`).

## Verificación

- `php -l` OK en ambas vistas; grep sin restos de clases viejas DENTRO de los modales (en cuentas quedan `btn btn-*`/`text-muted` de las tarjetas y tabla de la página — fuera del modal, por diseño).
- Harness de render fuera del repo (~100 checks): clases nuevas aplicadas, ids/atributos preservados, sin errores.
- Tests (rerun del orquestador, exit 0): `GastosFacturacionTabs` 31✅ · `CuentasBancarias` 36✅ · `DeudaManual` 68✅ · `Gastos` 90✅.
- Commits aislados por vista (revert = `git revert <sha>` de un solo archivo).

## Cierre del orquestador (2026-10-17)

- **Spot check**: `git show --stat` de `6cefd84`/`9e29206`/`50fae52` (1 archivo cada uno); grep dentro de los 6 modales: 0 restos de `form-control`/`form-select`/`btn btn-` (en usuarios queda 1 `form-select` en la tabla — fuera de modales — y el toggle `text-muted` del JS de cooldown, justificados).
- **Tests re-ejecutados por el orquestador**: Conciliación, Rbac y Solicitudes — todos verdes (además de los del writer: UsuarioAdmin 36, Tabs 34, AuthEmail 47; harness de render 150 checks, 0 FAIL).
- QA visual en navegador pendiente (los harness de render no cargan el CDN de Tailwind).
