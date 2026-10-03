# Estética de textos: listas admin al estilo Historial de Pagos / Conciliación

## Objetivo
Aplicar la estética tipográfica de las listas de referencia — Historial de Pagos (`app/views/admin/comprobantes.php`) y Conciliación (`app/views/admin/conciliacion/index.php`) — a todas las demás listas de tablas de los apartados admin.

## Referencia (patrón exacto)
- Título de tarjeta/sección: `text-lg font-bold text-on-surface` + subtítulo `text-xs text-on-surface-variant`.
- Badge de conteo: `bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant`.
- Tabla: `w-full text-left text-sm border-collapse` (sin clases Bootstrap de tabla).
- Fila de encabezado: `text-xs uppercase text-on-surface-variant font-bold border-b border-background`; `th` con `py-3 px-4`.
- `tbody`: `divide-y divide-background`; filas con `hover:bg-background/40 transition-colors`.
- Celdas: `py-4 px-4` + semántica: primario `font-semibold text-on-surface`, secundario `text-xs text-on-surface-variant`, referencias `font-mono text-xs`, montos `font-bold text-on-surface`, fechas `text-xs`.
- Estado vacío: `text-center py-12 text-on-surface-variant` + icono grande.
- Colores de texto Bootstrap (`text-dark`, `text-muted`, `text-secondary`) se traducen a `text-on-surface` / `text-on-surface-variant`.

## Alcance
Solo admin. Excluidos: vistas de impresión (`reportes/imprimir.php`), carta individual (`reportes/carta_deuda.php`), tablas internas de modales, formularios sin lista, y el portal residente (otra área).

## Checklist

### Lote A — sin cobertura de tests (5)
- [ ] `admin/cuentas_bancarias/index.php`
- [ ] `admin/estacionamientos/index.php`
- [ ] `admin/respaldos/index.php`
- [ ] `admin/comunicados/index.php`
- [ ] `admin/solicitudes_registro/index.php` (partial dentro del tab de usuarios)

### Lote B — parciales (5)
- [ ] `pagos/admin/lista.php` (thead `bg-slate-50`/`text-slate-500`, th `py-4`, hover `hover:bg-slate-50`, badge sin borde)
- [ ] `admin/gastos/index.php` (filtros ya en referencia; tarjeta/tabla pendientes)
- [ ] `admin/gastos/cargar_maestro.php` (grilla editable; solo textos/encabezados, no tocar inputs)
- [ ] `admin/usuarios/index.php` (h1 y filtros ya en referencia; tarjeta/tabla pendientes)
- [ ] `admin/dashboard.php` (título + tabla de últimos movimientos; prohibido generar la cadena exacta `class="py-4 px-4 font-mono text-xs">` — test la prohíbe)

### Lote C — delicadas con tests (2)
- [ ] `admin/reportes/morosidad.php` (conservar `id="tablaBalanceUnidades"`, `class="fila-unidad ` con espacio final, paleta roja suave y botón ámbar del commit b04386b, scroll `max-h-[calc(100vh_-_33rem)]`, CSS sticky — actualizar fondo sticky a `#ffffff` si el thead deja de ser `table-light`)
- [ ] `admin/estructura.php` (conservar ids `tablaDirectorioEdificios`/`tablaConfiguracionEdificios`, `class="fila-edificio`, atributos collapse y acciones; tablas anidadas mantienen `text-xs`)

## Restricciones
- No tocar: modales, formularios/inputs, JS, ids, rutas, data-attributes, valores de options.
- No romper textos asertados por tests (`>Gastos<`, `Carta de Deuda`, `Paso 1: Edificios / Torres`, etc.).
- Cada lote cierra con `php -l`, suites afectadas y un commit work-unit.

## Verificación
- Suites: Conciliacion, BalanceAgrupado, EstructuraDirectorioEdificios, GastosTipologia, UsuariosSolicitudesTabs, DashboardFinanciero, Rbac.
- Verificador independiente al final sobre todos los commits del cambio.
- RDD: no evaluable en OpenCode (unassessable); verificación por suite + verificador.

## Entrega
DIRECTO EN MAIN (convención del repo), commits work-unit por lote, sin push.
