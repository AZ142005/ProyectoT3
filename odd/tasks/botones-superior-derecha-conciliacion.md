# Botones de la parte superior derecha unificados al estilo de Conciliación

## Objetivo (instrucción del usuario)
- Asegurar que en TODOS los apartados donde hay un botón o botones en la parte superior derecha, estos coincidan visualmente con los de **Conciliación** (`/admin/conciliacion`).
- Directo en `main` (sin ramas). Commit del orquestador.

## Contexto / hallazgo raíz
- Referencia: `app/views/admin/conciliacion/index.php`.
  - Barra de acciones superior derecha (L47-65): select lote + botón **Importar Extracto** → patrón **R1** (Tailwind `bg-primary` = #27ae60 institucional, `rounded-xl`, `text-xs font-bold px-5 py-2.5 shadow-sm`, ícono `material-symbols-outlined text-[16px]`, `gap-1.5`).
  - Cabecera de la tarjeta bandeja (L175-183): badge `Total: N` + botón verde sólido **Conciliar Todas las Exactas** (`bg-green-600 hover:bg-green-700`).
  - Acciones por fila (L254/256/262/264): chips claros con borde (`bg-green-50 … text-green-700 border-green-200` / `bg-red-50 … text-red-700 border-red-200`, `rounded-lg px-3 py-1.5`).
- Problema raíz: los apartados con botones **Bootstrap** (`.btn.btn-primary`, `.btn-outline-*`) renderizan AZUL (#0d6efd del CDN de Bootstrap 5.3), fuera de la paleta institucional verde; `estructura.php` usa `bg-slate-900`. No existe ningún override de `.btn-primary` en el proyecto. Las páginas standalone (`carta_deuda.php`, `reportes/imprimir.php`, `residente/estado_cuenta_imprimir.php`) cargan SOLO Bootstrap (sin Tailwind ni header.php) → requieren overrides CSS scoped.

## Patrones objetivo (clases exactas; `inline-flex` es el equivalente visual seguro del `flex` de Conciliación)
- **R1 — CTA sólido primario:**
  `bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5`
  ícono: `<span class="material-symbols-outlined text-[16px]">{icono}</span>`
- **R2 — acción neutra/secundaria:**
  `bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5`
  ícono: `text-[16px]`.
- **R3 — chip claro semántico (barra superior):**
  verde: `bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 px-4 py-2.5 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5`
  rojo: `bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 px-4 py-2.5 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5`
  ícono: `text-[16px]`.
- **R4 — píldoras de filtro por estado (patrón `.filtro-pill` de Conciliación):**
  inactiva: `px-3 py-1.5 rounded-full border border-slate-200 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors`
  activa: `px-3 py-1.5 rounded-full border border-primary bg-primary text-white text-xs font-bold`
- **R5 — overrides para páginas standalone sin Tailwind (reproducen R1/R2 a nivel CSS):** agregar al `<style>` existente de cada página:
```css
/* Botones de barra superior alineados al estilo de Conciliación (R1/R2) */
.no-print .btn { border-radius: .75rem; font-size: .75rem; line-height: 1rem; font-weight: 700; padding: .625rem 1.25rem; display: inline-flex; align-items: center; gap: .375rem; transition: all .15s ease; }
.no-print .btn-primary { background-color: #27ae60; border-color: #27ae60; color: #fff; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
.no-print .btn-primary:hover { background-color: #1e8449; border-color: #1e8449; color: #fff; }
.no-print .btn-outline-secondary { background-color: #f1f5f9; border-color: #e2e8f0; color: #334155; }
.no-print .btn-outline-secondary:hover { background-color: #e2e8f0; border-color: #e2e8f0; color: #334155; }
.no-print .btn-warning { background-color: #fbbf24; border-color: #fbbf24; color: #451a03; }
.no-print .btn-warning:hover { background-color: #f59e0b; border-color: #f59e0b; color: #451a03; }
.no-print .btn-success { background-color: #16a34a; border-color: #16a34a; color: #fff; }
.no-print .btn-success:hover { background-color: #15803d; border-color: #15803d; color: #fff; }
.no-print .btn:disabled, .no-print .btn.disabled { background-color: #f1f5f9; border-color: #e2e8f0; color: #94a3b8; }
```

## Cambios (solo vistas; sin lógica; los números de línea son guía)
1. `app/views/admin/gastos/index.php` (~L33-50, barra superior): "Ingesta PDF Maestro" → **R2**; "Nuevo Gasto" → **R1**.
2. `app/views/admin/estacionamientos/index.php` (~L26-41, barra superior): "Nuevo Puesto" → **R2**; "Registrar Vehículo" → **R1**.
3. `app/views/admin/comunicados/index.php` (~L27-36, cabecera de tarjeta): "Nuevo Comunicado" → **R1**.
4. `app/views/admin/cuentas_bancarias/index.php` (~L30-46, cabecera de sección): "Nueva Cuenta Bancaria" → **R1**.
5. `app/views/admin/solicitudes_registro/index.php` (~L7-23): btn-group Bootstrap de estados → **R4** (píldoras). Conservar hrefs (`/admin/usuarios?tab=solicitudes…`), condicional `$estadoSolicitud`, total y contador `$pendientesCount`; el badge de pendientes pasa a `bg-amber-300 text-amber-950 ms-1 rounded-full px-1.5 text-[10px] font-bold`.
6. `app/views/admin/dashboard.php`: L70 "Ir a Conciliación" → **R1** (conservar ícono `arrow_forward`); L364 "Ver Historial" (cabecera de tarjeta) → **R2**.
7. `app/views/admin/reportes/morosidad.php` (~L33-48, barra superior): "Exportar CSV" → **R3 verde**; "Imprimir PDF" → **R3 rojo**.
8. `app/views/admin/gastos/cargar_maestro.php` (L45): "Volver al Historial" → **R2**.
9. `app/views/admin/estructura.php`: L352 "Agregar Edificio" (`bg-slate-900…`) → **R1**; L431/L436 "Crear Unidad": habilitado → **R1** exacto; deshabilitado → `bg-slate-200 text-slate-400 font-bold px-5 py-2.5 rounded-xl text-xs inline-flex items-center gap-1.5 cursor-not-allowed` (sin hover, sin sombra).
10. `app/views/admin/reportes/carta_deuda.php`: agregar bloque **R5** al `<style>` (toolbar `.no-print`, ~L21-48; NO cambiar las clases del markup `btn btn-*`).
11. `app/views/admin/reportes/imprimir.php`: agregar bloque **R5** al `<style>` (barra `.no-print`, ~L18-23).
12. `app/views/auditor/dashboard.php`: L31 "Exportar Log Completo" → **R2**; L104 "Ver Registro Completo" → **R1**.
13. `app/views/auditor/log_transacciones.php`: L29 "Exportar CSV" → **R1**.
14. `app/views/residente/estado_cuenta_imprimir.php`: agregar bloque **R5** al `<style>` (toolbar `.no-print`, ~L19-26).

Fuera de alcance (NO tocar): botones de fila de tablas, modales, footers de tarjetas/formularios, logout superior (ya consistente), selects/inputs, botoneras "Limpiar/Aplicar Filtros" ya Tailwind, pestañas, resto del portal residente (su lenguaje es intencionalmente distinto).

## Restricciones funcionales
- Conservar íntegros: textos visibles, iconos (`material-symbols` mismos nombres), `href`, `onclick`, `data-bs-*`, `target`, `confirm()`, `<form>`, `csrf_field()`, `name`, `id`, condicionales PHP y variables.
- Conservar strings verificados por tests: `$paginacionSolicitudes`, `$estadoSolicitud`, `$pendientesCount`, `'tab' => 'solicitudes'`, `action="/admin/estructura/unidad/toggle"`, `id="usuariosTabs"`, `Solicitudes de Registro`, `Carta de Deuda`, etc.
- Solo cambian atributos `class`/`style` de los botones del alcance + los 3 bloques CSS. Sin cambios de controladores, rutas ni lógica.
- `php -l` limpio en los 14 archivos.

## Criterios de aceptación
1. Cada botón listado usa exactamente su patrón objetivo (clases idénticas entre archivos).
2. Cero cambios de comportamiento (mismos enlaces/acciones/modales/confirmaciones).
3. `php -l` 14/14 sin errores; suites filtradas verdes (mismos fallos de entorno preexistentes si los hay).
4. Diff acotado a los botones del alcance y los 3 bloques CSS.

## Ruta de implementación
- Writer único (delegado); verificación del writer (`php -l` + suites filtradas); verificador independiente del padre (read-only) + spot check. Directo en `main`; commit del orquestador.
- Skills: `work-unit-commits` (organización; sin commits por parte del writer).

## Verificación
- Writer: `php -l` 14/14 sin errores. Suites filtradas (exit 0, 0 fallos): UsuariosSolicitudesTabsTest (8/34✅), DashboardFinancieroTest (3/31✅), BalanceAgrupadoEdificiosTest (9/44✅), EstructuraDirectorioEdificiosTest (4/28✅), AsignacionEstacionamientoTest (5/19✅), GastosTipologiaCalculoTest (5/24✅), GastosTest (1/4✅), CuentasBancariasTest (4/29✅), EstacionamientoTest (9/27✅), BehaviorTest (111/256✅, 1 skip), SolicitudesRegistroTest (7/47✅).
- Spot check del padre: `php -l` 14/14 + `UsuariosSolicitudesTabsTest` 34✅/0❌; revisión del diff completo (solo class/style + 3 bloques CSS).
- Verificador independiente (read-only): VERIFICADO — 24 hunks emparejados sin diferencias fuera de class/style (3 hunks son bloques CSS puros); 32/32 atributos class nuevos coinciden literalmente con los patrones R1-R5; scope acotado a los 14 archivos (sin controladores/tests/config); píldoras de solicitudes conservan hrefs/condicionales/badge; sin botones superiores Bootstrap remanentes en scope; overrides R5 con especificidad ganadora verificada contra el CSS real de Bootstrap 5.3.3 CDN; adversarial sin hallazgos. SUGGESTIONs cosméticas (no introducidas): Bootstrap mantiene `opacity .65` en disabled y focus rings grises.
- RDD: en evaluación tras el commit (OpenCode; registrar `mode status` + `assess`).
- Residual honesto: sin verificación renderizada en navegador (el navegador del desktop no está conectado en esta sesión; estilos verificados por igualdad de clases con la referencia + análisis de especificidad contra el CSS real).

## Entrega
- Commit `PENDING_HASH` directo en `main` (14 vistas + este doc). Push: decisión del usuario.
