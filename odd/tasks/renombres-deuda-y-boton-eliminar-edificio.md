# ODD task: Renombres visuales "deuda" + botón admin para eliminar edificio en Estructura

**Estado**: EN PROGRESO
**Fecha**: 2026-10-10
**Ruta**: delegated-direct — un writer (renombres de texto + feature de botón). Commit por unidad.

## Objetivo

1. **Renombres SOLO visuales** (pedido del usuario):
   - "Emisión de Facturas" → **"Emisión de Deuda"** (pestaña).
   - "Emisión de Facturación Mensual" (encabezado de la pestaña) → **"Emisión de Deuda Mensual"**.
   - "Gastos y Facturación" → **"Carga y Emisión de Deuda"** (sidebar admin L10/L25, sidebar auditor L7, encabezado de `admin/gastos/index.php` L21).
   - NO renombrar: rutas, clases, métodos, ids, claves de BD, archivos, ni botones "Generar Facturas"/"Re-generar Facturas".
2. **Botón "Eliminar edificio" SOLO para admin** en Estructura, con mensaje de confirmación al intentar.

## Contexto

- Textos actuales: `app/views/layouts/admin_sidebar.php` L10 y L25 (`'label' => 'Gastos y Facturación'`); `app/views/layouts/auditor_sidebar.php` L7; `app/views/admin/gastos/index.php` L21 (span de encabezado), L82 (span de pestaña), L312 (h4 "Emisión de Facturación Mensual").
- Tests que asertan esos textos (actualizar SOLO aserciones de texto): `tests/GastosFacturacionTabsTest.php` (L17-18, L25-26, L32) y cualquier otro que aparezca con grep (`Gastos y Facturación|Emisión de Facturas|Emisión de Facturación`).
- Estructura: `EstructuraController` (index/guardarEdificio/guardarUnidad/toggleEdificio/toggleUnidad; patrón de Flash + `invalidarCacheEstructura()` + redirect con `tab`). Vista `app/views/admin/estructura.php` (tabs configuración/visualización; formularios existentes p.ej. `/admin/estructura/unidad/toggle`). Rutas de estructura en `public/index.php`. NO existe método de eliminación todavía.
- Datos: ya se ejecutó una limpieza puntual de edificios de prueba (ver `odd/tasks/` si aparece doc; quedaron "Torre A" + "Torre 1..6"). El botón nuevo es una capacidad permanente del panel.
- Repo: commits locales en `main`, sin push; convencionales en español; sin atribución IA. Entorno PHP `C:/xampp/php/php.exe`; suite completa con falla preexistente `SecurityTest` (no tocar).

## Tareas

- [x] **T1 — Renombres visuales**: aplicar los 6 cambios de texto + actualizar aserciones de tests afectadas. Grep de verificación: 0 ocurrencias visibles de "Gastos y Facturación"/"Emisión de Facturas" en `app/views/` (excepto comentarios si el writer decide dejarlos; preferible actualizarlos también para evitar confusiones, sin efecto visual).
- [x] **T2 — Botón eliminar edificio (solo admin)**:
  - Vista `admin/estructura.php`: botón "Eliminar" (danger outline, icono delete) por edificio en la pestaña Configuración, **visible solo para admin** (patrón de rol ya usado en la vista; el auditor no lo ve). Form POST a `/admin/estructura/eliminar-edificio` con `id`, `tab` y CSRF; confirmación JS: `confirm('¿Seguro que deseas eliminar el edificio «{NOMBRE}»? Se eliminarán también sus unidades sin propietario ni deudas. Esta acción no se puede deshacer.')`.
  - Controlador `EstructuraController::eliminarEdificio()`: `Auth::requireRole(['admin'])` (SOLO admin). Reglas: **rechazar** (Flash::error) si el edificio tiene unidades con propietario asignado (`unidades.propietario_id` NOT NULL o `personas.unidad_id` en sus unidades) o registros financieros (facturas / movimientos_cuenta / pagos de sus unidades): "No se puede eliminar «X»: tiene unidades con propietario o deudas asociadas." Si está limpio: transacción que elimina sus registros no financieros (estacionamientos, solicitudes_registro) y sus unidades, luego el edificio; registrar en `log_auditoria` (`accion='eliminar_edificio'`); `invalidarCacheEstructura()`; Flash::success; redirect al tab.
  - Modelo: método de eliminación transaccional en `EdificiosModel` (p.ej. `eliminarSiLimpio(int $id): array` devolviendo `['ok'=>bool,'motivo'=>?string]`) usando `Database::getConnection()`; sin FK colgantes.
  - Ruta en `public/index.php`: POST `/admin/estructura/eliminar-edificio` → `[UserRole::ADMIN]` (junto al bloque de estructura).
  - Tests (patrón del repo): ruta registrada con rol ADMIN; vista contiene el form/confirm; método existe; regla de rechazo presente (assert de texto de código como en `ArreglosAuditoriaTest`). Correr `--filter=Estructura`, `--filter=Gastos`, `--filter=Filtros`, `--filter=Router`.

## Checks

- `php -l` de tocados; filtros de tests relevantes; verificación visual por HTTP si el writer puede (hay server en localhost:8000 y credenciales admin en `scripts/seed_admin.php` — opcional).
- Suite completa: falla preexistente conocida (no tocar).

## Progreso y evidencia

### T1 — Renombres visuales (COMPLETADO)
- Cambios visibles aplicados:
  - `app/views/layouts/admin_sidebar.php` L10/L25: label → `Carga y Emisión de Deuda`.
  - `app/views/layouts/auditor_sidebar.php` L7: idem.
  - `app/views/admin/gastos/index.php`: encabezado (L21) → `Carga y Emisión de Deuda`; pestaña (L82) → `Emisión de Deuda`; h4 (L312) → `Emisión de Deuda Mensual`; comentarios HTML L55/L307 actualizados (sin efecto visual).
- Textos no visibles actualizados por consistencia (evitan confusión y dejan grep repo-wide en 0): comentarios de `GastoController` (L15/L48) y `AdminController` (L223), y `<title>` de `GastoController` L76 → `Carga y Emisión de Deuda - Administrador` (sí visible en la pestaña del navegador).
- Tests: `tests/GastosFacturacionTabsTest.php` (aserciones y mensajes), `tests/GastosTipologiaCalculoTest.php` (L208/212/216), `tests/FiltrosCompactosTest.php` (comentario). Sin renombrar clases ni métodos de test.
- NO tocado: rutas, clases, métodos, ids (`tab-facturacion`, claves `'facturacion'`), archivos; botones "Generar Facturas del Mes"/"Re-generar Facturas" intactos; `app/views/admin/generar_facturas.php` intacta (vista huérfana sin ruta registrada en `public/index.php`).
- Verificación:
  - `grep -i "emisi[oó]n de factur|gastos y facturaci[oó]n"` en `app/ tests/ public/` → **0 coincidencias** (repo-wide).
  - `php -l` de todos los archivos tocados → sin errores.
  - `php tests/run.php --filter=Gastos` → 18 tests, 83 asserts, 0 fallos.
  - `php tests/run.php --filter=Filtros` → 6 tests, 39 asserts, 0 fallos.

### T2 — Botón "Eliminar edificio" solo admin (COMPLETADO)
- **Vista** `app/views/admin/estructura.php`: en la pestaña Configuración, junto a Editar/toggle de cada tarjeta de edificio, se agregó un form POST a `/admin/estructura/eliminar-edificio` con `csrf_field()`, `tab` e `id`, botón danger outline + icono delete ("Eliminar"), y `onsubmit="return confirm(...)"` con el nombre interpolado y doblemente escapado (HTML + JS: se duplican `\`, se escapan `'` y se quitan saltos de línea). El form queda bajo el guard de rol existente (`Auth::role() !== 'auditor'`), por lo que el auditor no lo ve.
- **Controlador** `EstructuraController::eliminarEdificio()`: `Auth::requireRole(['admin'])` (solo admin), sanea `id`/`tab` como los métodos existentes, rechaza con `Flash::error('No se puede eliminar «X»: <motivo>')` y redirige al tab; en éxito invalida caché, `Flash::success('Edificio eliminado.')` y redirige.
- **Modelo** `EdificiosModel::eliminarSiLimpio(int $id): array` (`['ok'=>bool,'motivo'=>?string]`): valida y elimina dentro de una única transacción, registra en `log_auditoria` (`accion='eliminar_edificio'`, `tabla_afectada='edificios'`, `registro_id`, `estado_nuevo='eliminado'`, `detalles`, `ip_address`) y hace rollback ante cualquier excepción.
- **Ruta** `public/index.php`: `POST /admin/estructura/eliminar-edificio` con `[UserRole::ADMIN]`.
- **Rechazo**: unidades con propietario o personas (residentes) vinculadas; registros financieros de sus unidades (facturas, movimientos_cuenta, pagos, incluyendo soft-deleted porque el FK igual apunta); además gastos del condominio imputados al edificio (FK `gastos_comunes.edificio_id` es SET NULL y los desvincularía en silencio).
- **Limpieza**: elimina hijos no financieros de sus unidades (estacionamientos y solicitudes_registro según spec, más vehículos y comunicados_destinos por ser registros hijos sin FK), luego las unidades y el edificio. Los `comunicados` con `unidad_id`/`edificio_id` NO se tocan (contenido publicado sin FK).
- **Tests** `tests/EstructuraEliminarEdificioTest.php` (nuevo): ruta solo-admin; vista con form/confirm/guard de rol; método del controlador con `requireRole(['admin'])`; regla de rechazo y transacción en el modelo; rechazo de id inexistente; rechazo de edificio con dependencias (modelo y controlador, no destructivo); y eliminación real de un edificio temporal con sus registros hijos + auditoría (con limpieza defensiva).
- Desviación controlada: `tests/RbacAuthorizationTest.php` documentaba que toda ruta admin permite auditor salvo exclusiones; se agregó esta ruta a la lista de exclusiones documentadas (es solo-admin por requerimiento).
- Verificación:
  - `php tests/run.php --filter=Estructura` → 13 tests, 81 asserts, 0 fallos.
  - `php tests/run.php --filter=Rbac` → 9 tests, 234 asserts, 0 fallos.
  - `php tests/run.php --filter=Router` → 23 tests, 44 asserts, 0 fallos.
  - Render CLI real (admin vs auditor): admin ve el form con el confirm exacto; auditor NO ve el form; csrf/tab/id presentes.
  - Escapado JS del nombre verificado empíricamente con nombres que contienen `'`, `\`, `"`, saltos de línea y `&`/`<`.
  - `php -l` de todos los archivos tocados → sin errores.
  - Suite completa: 0 fallos de aserción; solo persiste la salida preexistente de `SecurityTest` (no tocado).

## Verificación independiente (orquestador, 2026-10-10)

- **Spot-check del orquestador (HTTP en vivo, sesión admin real)**: `/admin/gastos` → sidebar "Carga y Emisión de Deuda" ✓ y pestaña "Emisión de Deuda" ✓; `/admin/estructura` → botón + confirm presentes; `POST eliminar-edificio id=234 (Torre 1)` → rechazo correcto ("No se puede eliminar «Torre 1»: tiene unidades con propietario o deudas asociadas."); edificio temporal vacío → "Edificio eliminado" + auditoría + limpieza del artefacto de prueba.
- **Riesgo del diff** (`gentle-ai review assess`): `high`/`unassessable` (OpenCode no elegible; esperado) → verificador independiente por el gate.
- **Verificador independiente: APROBADO CON OBSERVACIONES — 5/5 PASS**: diff íntegro (solo textos visibles + feature; rutas/ids/claves intactos); renombres 0 pendientes en código; seguridad triple del botón (ruta ADMIN → controlador `requireRole(['admin'])` → vista con guard auditor) + rechazo por propietarios/deudas/gastos + transacción con auditoría y SQL preparado; tests (Estructura 13/13, Gastos 18/18, Filtros 6/6, Router 23/23, Rbac 9/9); HTTP de rechazo verificado end-to-end (Torre 1 intacta tras el POST).
- **Observaciones (informativas)**: (a) `gastos/index.php:385` conserva "…duplicará la facturación mensual…" (no es una de las frases pedidas; candidato a micro-pulido); (b) auditor no verificable por HTTP (sin credenciales; cubierto por guard estático + test); (c) refs lógicas sin FK (`comunicados.unidad_id`) quedan apuntando a entidades borradas (intencional, sin FK).
