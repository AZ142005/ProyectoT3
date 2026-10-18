# Revisión móvil completa del sistema — admin, auditor y re-verificación general

Fecha: 2026-10-18
Origen: Pedido del usuario — «revisión completa de las vistas móviles del sistema para corregir errores visuales (elementos y/o textos cortados, interfaces mal alineadas), asegurar que los cambios no dañan la vista de escritorio».
Ruta: ODD. QA visual automatizada (Chrome headless + CDP, reutiliza el harness de `%TEMP%\qa-mobile`) + revisión de capturas + fixes acotados. No es SDD.
TDD: off — runner funcional `php tests/run.php` (base: 599 ✅).
Rama: `fix/vistas-moviles-sistema` (se aparta de `main`; la fusión/push es decisión del usuario). Commits: `d173d06` (código, 7 archivos) + este documento (docs).

## Objetivo

Revisar a 390 px (móvil real, DPR 2) TODAS las vistas del sistema, corregir recortes/desalineaciones, y verificar que no haya regresión en escritorio (≥1280 px) de las páginas tocadas.

## Alcance

- **Re-verificación** (ya cubierto en `vistas-moviles-residente.md`, commits `35e8599`/`76f258f`): login, registro, registro-exitoso, pago-directo (+éxito), residente (dashboard, pagos, pagos/nuevo, detalle, notificaciones, cartelera), perfil.
- **NUEVO en esta ronda**:
  - Guest: `/auth/verificar-2fa` (sesión 2FA sembrada), 404.
  - Residente: 403 (acceso denegado a `/admin/dashboard`).
  - Admin (id 1): dashboard, comprobantes, verificar comprobante (`?id`), facturas/generar, estructura, estacionamientos, morosidad (+imprimir), carta-deuda (unidad 1), comunicados, solicitudes-registro, usuarios, conciliación, cuentas-bancarias, gastos, cartelera, respaldos, `/pagos` (lista admin), detalle de pago admin, perfil admin.
  - Auditor layout: dashboard fiscal, log-transacciones (acceso real vía admin; el módulo fue retirado del rol auditor).
  - Sidebars admin/auditor en móvil (off-canvas, overlay, hamburguesa).
- **Fuera de alcance**: plantillas de correo (`app/views/emails/*`), vista 500 (solo alcanzable con error real), APIs JSON, interactividad de formularios (solo layout).

## Metodología

- Sesiones sembradas server-side (`%TEMP%\qa-mobile\seed_sessions.php`, nuevo): admin id=1, auditor id=317, residente persona 207, y sesión `2fa_pending`. Sin tocar contraseñas.
- Harness CDP nuevo (`qa_mobile_full.js`): por cada ruta → screenshot full-page 390×844 + sonda programática por página:
  - `scrollWidth` del documento vs viewport; elementos fuera de viewport (excluyendo off-canvas intencional);
  - candidatos a texto cortado (`scrollWidth > clientWidth` con overflow oculto);
  - tablas sin contenedor `overflow-x-auto`;
  - imágenes rotas; errores de consola.
- Revisión visual: lectura de cada captura por el orquestador.
- Regresión escritorio: capturas 1280×800 de las páginas con cambios (antes/después).
- Cierre: `php -l` de archivos tocados + suite completa `php tests/run.php`.

## QA base ejecutada (43 rutas + 6 modales)

- **0 desbordes de documento** (`scrollWidth == 390`) en las 43 rutas; **0 truncados** de texto programáticos; errores de consola solo los esperables (404/403 propios de sus páginas).
- Modales Bootstrap probados (estacionamientos, comunicados, usuarios, conciliación, cuentas, gastos): todos caben (374/390 px).
- Tablas anchas en admin/residente: todas dentro de wrappers con scroll horizontal (patrón ya aceptado en la ronda del residente).

## Hallazgos y correcciones (confirmados)

| # | Vista / archivo | Hallazgo (captura) | Fix |
|---|---|---|---|
| 1 | `admin/estructura.php` — `tablaDirectorioEdificios` y `tablaConfiguracionEdificios` | `table-layout: fixed` + `width:100%` comprime las columnas fijas: chips «N unidades/N residentes» y botones «Ver Unidades/+ Unidad» se **solapan/cortan** (captura 22) | `min-width: 820px` en ambas tablas: el wrapper `overflow-x-auto` desplaza en móvil; escritorio ≥ ~1080 px contenido no cambia |
| 2 | `admin/conciliacion/index.php` (líneas 53-55) | Grupo «Lote activo:» (label + select = 370 px) desborda a la izquierda con `justify-end`: label recortado, en pantalla se lee «**OTE ACTIVO:**» (capturas 30/41) | `flex-wrap min-w-0` al grupo; `max-w-full min-w-0` al select |
| 3 | `admin/respaldos/index.php` (línea 52) | Badge «Política: 365 días…» (370 px) **cortado** en el borde de la tarjeta (captura 34) | clase `text-wrap` (Bootstrap) para permitir 2 líneas en móvil |
| 4 | `admin/gastos/index.php` (línea 304) | Opción del select del modal **cortada**: «Deuda manual por unidad — se emite con…» (captura 32-modal) | Acortar copy a «Deuda manual por unidad» (la explicación ya está debajo) |
| 5 | `admin/cuentas_bancarias/index.php` (línea 138) | Número de cuenta (20 dígitos, `chunk_split`) se apila como «cajitas» una por grupo de 4 dígitos (captura 31) | `whitespace-nowrap` en el span: queda en una línea; la tabla ya tiene scroll |
| 6 | `admin/estacionamientos/index.php` (líneas 133/197/340) | Doble prefijo: «Puesto **#Puesto - A-101**» — el dato ya incluye «Puesto - » (lo genera `UnidadesModel`); la plantilla antepone otro «Puesto #» | Quitar el prefijo literal «Puesto #» (mostrar el número tal cual) |
| 7 | `admin/dashboard.php` (cabecera, líneas 24-53) | `h-16` con título + subtítulo + 2 selects: en 390px el título se comprime a 116px → 3 líneas y **se recorta por arriba** (t=-47) mientras el subtítulo desborda 42px bajo la barra (sonda focalizada) | Cabecera responsive: `flex-wrap` + `max-md:py-2.5` + controles a fila propia en móvil (`w-full justify-end md:w-auto`) + `min-w-0` al bloque de título. Escritorio: **0.000% de diff de píxeles** |

## Observaciones (sin corrección)

- Tablas con scroll horizontal en móvil: patrón consistente en admin y residente (ya avalado en la ronda anterior).
- `admin-verificar?id=78`: la imagen del comprobante da 404 porque el archivo `97e537…png` no existe en `uploads/` local (dato de prueba, no código).
- Vistas de impresión (`morosidad/imprimir`, `carta_deuda`) sin viewport móvil: son para impresión; se dejan.
- Botones azules Bootstrap (`.btn-primary`) en admin: deuda preexistente ya documentada; inputs `file` nativos se muestran truncados por el navegador (comportamiento nativo).

## Verificación (post-fix)

- **Móvil (390×844, sesión admin)**: 7/7 PASS con sonda — dashboard: h1.top=10, sin recorte, cabecera 122px; estructura: tablas a 820px, chips/botones contenidos en su celda (3/3 y 2/2), cabeceras de columna sin solape (3/3); conciliación: «LOTE ACTIVO:» visible desde l=24 (antes l=-4); respaldos: badge envuelto (h=42, right=350); gastos: select muestra «Deuda manual por unidad»; cuentas: número en una línea (h=27); estacionamientos: «Puesto - A-101» exacto. En las 7 rutas: `docW=390`, `truncCount=0`, `consoleErrors=0`.
- **Sidebars admin/auditor (off-canvas)**: PASS — abren (l=0) y cierran (l=-256), overlay alterna, 11 enlaces y tarjeta de usuario visibles; sin cambios necesarios.
- **Escritorio (1280×800, admin)**: diff de píxeles antes/después — dashboard, estructura, conciliación y gastos-top: **0%**; respaldos: solo el timestamp vivo de «Corte de retención» (esperado); gastos-modal (copy), cuentas (número en una línea — mejora también en desktop) y estacionamientos (texto sin doble prefijo): cambios intencionales. Capturas en `%TEMP%\qa-mobile\out-full\desktop-before|desktop-after`.
- **Lección**: el primer intento `py-3 md:py-0` chocó con `.py-3` de Bootstrap (`!important`) y desplazaba 2.5px el escritorio; resuelto con `max-md:py-2.5` (clase que Bootstrap no define) — mismo tipo de colisión que la deuda técnica documentada.
- `php -l` sin errores en los 7 archivos; suite completa `php tests/run.php`: **632 ✅ / 0 ❌** (exit 0).

## Notas

- Deuda técnica preexistente (documentada en su momento, NO se toca sin decisión): colisión Bootstrap/Tailwind en utilidades de espaciado (`p-4` real 24px vs 16, etc.).
- `git status` tiene cambios locales preexistentes (docs borrados, vendor) ajenos a esta tarea: no se incluirán en ningún commit.
