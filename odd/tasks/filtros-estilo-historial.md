# Cajas de filtrado unificadas al estilo del Historial de Pagos

## Objetivo (instrucción del usuario)
- Revisar TODOS los apartados con cuadro de filtrado y alinearlos visualmente al que está en **Historial de Pagos** (`/admin/comprobantes`).
- Directo en `main` (sin ramas).

## Contexto
- Referencia de estilo: `app/views/admin/comprobantes.php` (Historial de Pagos) — tarjeta de filtros:
  - Card: `bg-white rounded-2xl border border-outline-variant p-6 shadow-sm mb-8`.
  - Header: `flex items-center gap-2 mb-4 pb-3 border-b border-slate-100` + ícono `filter_alt` (`text-primary text-sm`) + título `text-xs font-bold text-slate-700 uppercase tracking-wider`.
  - Campos: wrapper `flex flex-col gap-1.5`; label `text-xs font-semibold text-on-surface-variant uppercase tracking-wider`; select/input `w-full px-3 py-2.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary text-xs` (select además `cursor-pointer`); búsqueda con ícono `search` absoluto y input `pl-8`.
  - Botonera: `col-span-full flex justify-end gap-2 pt-2` — Limpiar `bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2 rounded-xl text-xs` + `clear_all`; Aplicar `bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2 rounded-xl shadow-sm text-xs` + `filter_alt`.
- Revisión de apartados:
  - `admin/comprobantes.php` (Historial) — referencia, sin cambios.
  - `admin/conciliacion/index.php` — ya quedó con tarjeta "Filtros de Coincidencia" estilo historial (sesión previa, commit `fe0001f`); sin cambios.
  - `pagos/admin/lista.php` — tarjeta Tailwind cercana pero con diferencias (header sin borde, labels sin uppercase, inputs py-2, botones embebidos en el grid).
  - `admin/usuarios/index.php` — card Bootstrap (`row g-3`, `input-group`, `btn-primary`).
  - `admin/gastos/index.php` — card Bootstrap (`row g-2`, `form-select-sm`, `btn-primary`).
  - `admin/reportes/morosidad.php` (Carta de Deuda) — card Bootstrap (`row g-3`) + buscador rápido `#buscadorBalance`.
  - Sin cuadro de filtrado (no se tocan): `admin/estructura.php` (controles inline en el header del tab), `admin/solicitudes_registro/index.php` (btn-group de estados), `admin/dashboard.php` (selector de período), respaldos/auditor/comunicados/cuentas/estacionamientos (sin filtros).

## Cambios (solo vistas; sin cambios de controlador)
1. `app/views/pagos/admin/lista.php` (líneas ~24-65): header al patrón de referencia (borde inferior + ícono primary + título uppercase slate-700); grid `grid-cols-1 md:grid-cols-3 gap-4 items-end`; labels/inputs al patrón; botonera `col-span-full flex justify-end gap-2 pt-2` con "Limpiar Filtros" + "Aplicar Filtros". Conservar: `method="GET" action="/pagos"`, names `estado`/`edificio`/`fecha`, links y valores.
2. `app/views/admin/usuarios/index.php` (líneas ~118-158): reemplazar card Bootstrap por tarjeta de referencia; campos Buscar Usuario (ícono search) + Tipo de Cuenta (select `rol`); grid `grid-cols-1 md:grid-cols-3 gap-4 items-end` (búsqueda `md:col-span-2`); botonera Limpiar/Aplicar. Conservar: hidden `name="tab"` value `usuarios`, condicional del link limpiar, `href="/admin/usuarios?tab=usuarios"`, ids `buscar`/`rol`, placeholders y valores.
3. `app/views/admin/gastos/index.php` (líneas ~52-96): reemplazar card Bootstrap por tarjeta de referencia; campos Mes/Año/Categoría/Tipo de Gasto; grid `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end`; botonera Limpiar (`/admin/gastos`)/Aplicar. Conservar names, valores y el título `>Gastos<` del resto de la vista.
4. `app/views/admin/reportes/morosidad.php` (líneas ~85-119): reemplazar card Bootstrap por tarjeta de referencia; campos Edificio/Torre + Antigüedad de Deuda; grid `grid-cols-1 md:grid-cols-2 gap-4 items-end`; botonera Limpiar (`/admin/reportes/morosidad`)/Aplicar. Alinear también el buscador rápido `#buscadorBalance` al estilo de campo de búsqueda de la referencia (conservar `id`, `onkeyup="filtrarBalance(this.value)"`, placeholder y la función JS).

## Restricciones funcionales (tests vigentes)
- `UsuariosSolicitudesTabsTest`: conservar `name="tab"`, `href="/admin/usuarios?tab=usuarios"` y el resto de ids de tabs.
- `UsuarioAdminTest`: no tocar `csrf_field()`, `modalReiniciarPassword`, `pagination`, `reiniciar-password`, `$esAdminCuenta`, etc.
- `BehaviorTest`: nada de `$_SESSION['admin_*']` legacy en `pagos/admin/lista.php`.
- `BalanceAgrupadoEdificiosTest`: conservar `name="edificio_id"`, `name="dias_mora"`, `id="buscadorBalance"`, `filtrarBalance`, `Carta de Deuda`, acordeón/tabla; no reintroducir `name="estado"`.
- `GastosTipologiaCalculoTest`: conservar `>Gastos<`.
- No editar tests salvo autorización del padre; no cambiar textos visibles existentes (labels/placeholders/opciones) más allá de la estructura visual.

## Criterios de aceptación
1. Las 4 tarjetas de filtrado siguen el patrón visual exacto de la referencia (card, header con borde, labels uppercase, inputs rounded-xl bg-background py-2.5, botonera derecha Limpiar/Aplicar).
2. Funcionalidad intacta: mismos methods/actions/names/ids/valores; GET sin JS nuevo; enlaces de limpiar conservan sus rutas.
3. `php -l` sin errores en los 4 archivos; suites filtradas relevantes verdes (mismos fallos de entorno preexistentes, si los hay).
4. Diff acotado a las tarjetas de filtrado (y buscador rápido de morosidad); cero cambios de lógica.

## Ruta de implementación
- Writer único (delegado); verificación del writer (`php -l` + suites filtradas) + verificador independiente del padre. Directo en `main`, commit del orquestador.

## Entrega
- Commit directo en `main`: `36e21e4` — feat(ui): unificar las cajas de filtrado con el estilo del historial de pagos (4 archivos, +147/−139, sin rama). Push: decisión del usuario.

## Verificación
- Writer: `php -l` 4/4 sin errores; suites `UsuarioAdminTest` (10/35✅), `UsuarioAdminAccionesTest` (7/33✅), `UsuariosSolicitudesTabsTest` (8/34✅), `GastosTipologiaCalculoTest` (5/24✅), `BalanceAgrupadoEdificiosTest` (9/45✅), `BehaviorTest` (111/253✅, 1❌ preexistente `htaccessFileExistsAndIsProtective` por `public/uploads/.htaccess` ausente e ignorado en git).
- Spot check del padre: `php -l` 4/4 + `BalanceAgrupadoEdificiosTest` 45✅/0❌/0⚠.
- Verificador independiente: VERIFICADO — patrón visual 4/4, funcionalidad preservada (names/actions/ids/valores, hidden tab, condicionales y links), diff acotado a las tarjetas (+ buscador rápido de morosidad), sin colisiones de id, adversarial sin hallazgos. Residual honesto: sin verificación renderizada en navegador.
- RDD: `review status` → `immutable_review_transport_unsupported` (OpenCode no elegible; solo claude-code/codex). `assess` con inventario declarado → risk `medium` / `under_budget`; ruta RDD-off: auto-verificación del writer + verificador independiente (modelo flash → bias de verificación).

## Iteración 2026-10-01 (feedback del usuario): compactado vertical
- Pedido: "reduce el tamaño vertical de los filtros, ocupan mucho espacio". Cambio autorizado; directo en main.
- Alcance: 6 tarjetas del patrón unificado — Historial (`comprobantes.php`), Pagos admin, Usuarios, Gastos, Carta de Deuda y Conciliación.
- Patrón compacto: card `p-6→p-4` (no aplica a Conciliación, ya `p-4`) y `mb-8→mb-6` en todas; header `mb-4 pb-3→mb-3 pb-2` (Conciliación `mb-3 pb-3→mb-2 pb-2`); form `gap-4→gap-3`; wrappers `gap-1.5→gap-1`; controles `py-2.5→py-2`; fila de botones sin `pt-2`; buscador rápido de Carta de Deuda `py-2.5→py-2`.
- Estado: [x] completada. Commit `e09bfd4` (6 archivos, 57 reemplazos de clase, +57/−57).
- Verificación: writer (`php -l` 6/6; suites HistorialPagos, UsuarioAdmin, UsuarioAdminAcciones, UsuariosSolicitudesTabs, GastosTipologia, BalanceAgrupado, Behavior [1 fallo preexistente de entorno], ConciliacionSimplificada, ConciliacionCentralizada — verdes). Spot check del padre: `php -l` 6/6 + HistorialPagosTest 20✅. Verificador independiente: VERIFICADO (57/57 solo clase; fuera de alcance intacto; adversarial sin hallazgos). Residual: sin render en navegador.
- RDD: assess (inventario declarado) → `medium` / `under_budget`; ruta RDD-off con verificador independiente (writer en modelo flash).
