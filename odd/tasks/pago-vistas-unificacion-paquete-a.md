# Unificación visual de las 3 vistas de pago — Paquete A (componentes compartidos)

## Objetivo (instrucción del usuario)
- El usuario eligió el **Paquete A**: los componentes compartidos deben verse idénticos en las 3 vistas de pago, puro visual, **sin tocar la estructura del Paso 2 ni los flujos**.
- Vistas: `app/views/residente/enviar_pago.php` (referencia canónica, **sin cambios**), `app/views/pago_directo/index.php` y `app/views/pagos/residente/subir.php` (se alinean a la referencia).
- Directo en `main`; commit del orquestador.

## Alcance exacto
### A. `pagos/residente/subir.php` (se alinea a enviar/público)
1. **Dropzone** → receta de enviar/público: `border-2 border-dashed border-outline-variant hover:border-primary bg-white rounded-2xl p-6 transition-all text-center cursor-pointer relative group flex flex-col items-center justify-center min-h-[170px]`; ícono `w-14 h-14 bg-background rounded-full border border-outline-variant flex items-center justify-center mb-3 group-hover:scale-105 transition-transform text-primary` + span `material-symbols-outlined text-3xl`; título `font-bold text-on-surface text-base`; subtítulo `text-xs text-slate-500 mt-1` + "Soporta JPG, PNG y PDF (Máx. 5MB) • Extracción automática"; preview: img `max-h-44`, ícono PDF `text-5xl text-rose-500 mb-1`, `pdfName text-xs`, "Haz clic para cambiar el archivo" `text-[11px] text-primary font-bold mt-3 bg-primary/10 px-3 py-1 rounded-md`.
2. **Encabezado Paso 1** → componente de enviar/público: `div.flex items-center justify-between flex-wrap gap-2 mb-4` con h4 (`document_scanner text-[20px]`, "Paso 1: Subir Comprobante (Extracción Inteligente)"), subtítulo "Al cargar el comprobante se autocompletarán los campos vacíos del pago." y badge `#ocrStatusBadge` (con `#ocrSpinner` + `#ocrStatusText`). Reemplaza al h3 "Paso 1: Subir Comprobante".
3. **Quitar botón manual `btnOCR`** (y su wrapper) y portar el patrón badge + "Reanalizar": resumen con clase `hidden mt-4 p-3 rounded-xl text-xs flex items-center justify-between gap-3 transition-all` y botón `#btnReanalizar` con el markup exacto de enviar/público (bg-slate-800, ícono refresh).
4. **JS de subir**: portar `actualizarEstadoOCR(tipo,mensaje)` y `finalizarEstadoOCR()` de enviar (ocultan/muestran `ocrStatusBadge`); `ejecutarExtraccion` deja de tocar `btnOCR`/`ocrText` y usa el badge con los mismos textos que enviar ("Analizando PDF en el servidor..."); quitar refs `btnOCR`/`ocrText` (consts, `disabled` en handleFiles/resetPreview, listener del botón); agregar listener `btnReanalizar` (preventDefault + re-ejecutar si `archivoActual`); `mostrarResumenExtraccion` revela `btnReanalizar` (`remove('hidden')` + `add('inline-flex')`). Mantener: `if (!file) return;`, endpoint `/pagos/extraer`, auto-disparo en handleFiles, lógica de extracción/autocompletado intacta.
5. **Encabezado Paso 2** → texto "Paso 2: Datos del Pago (Verifique o complete)" + `<span class="text-xs text-slate-400 font-medium">* Campos obligatorios</span>` (componente de enviar, `pb-2 border-b border-background mb-4 flex items-center justify-between`, h4 con `edit_document`).
6. **Labels/textos**: `text-slate-500` → `text-slate-600` (monto, fecha, referencia, notas); "Fecha de Pago" → "Fecha de Realización"; "Notas Adicionales" → "Observaciones Complementarias"; placeholder de notas → "Información adicional sobre el pago..."; placeholder de referencia → "Ej. 12345678".
7. **Inputs** → receta blanca de enviar: monto `bg-white ... text-slate-900 ... font-black text-lg`; fecha `bg-white ... text-slate-800 ... font-medium text-sm`; referencia `bg-white ... text-slate-900 ... font-mono font-medium text-sm`; select banco `bg-white ... text-slate-800 ... font-semibold text-sm`; textarea observaciones `bg-white ... text-slate-800 ... focus:ring-2 focus:ring-primary/20 ... resize-none text-sm`. Sin `bg-slate-50`/`focus:bg-white`.

### B. `pago_directo/index.php` (detalles finos)
8. "Cuenta oficial para el pago" → "Cuenta Oficial para el Pago"; badge de tarjeta "Cuenta oficial" → "Cuenta Oficial".
9. Tarjeta: contenedor `mt-1` → `mt-3` y `+ shadow-xs`; las 4 celdas `bg-white/90 ... shadow-xs`; celda del teléfono con `id="wrapperInfoTelefono"`.
10. JS `actualizarInfoCuenta`: número agrupado `.replace(/(\d{4})/g, '$1 ').trim()`; teléfono condicional (si no hay, `wrapperInfoTelefono.classList.add('hidden')`).
11. Copiar: botones `onclick="copiarDatoCuenta('infoNumero'|'infoDoc'|'infoTelefono', this)"`; reemplazar `copiarDato` por `copiarDatoCuenta` (feedback "¡Copiado!" + bg esmeralda + copia sin espacios); quitar el loop `[data-copiar]` y los atributos `data-copiar`.
12. Textarea observaciones → receta de enviar (`bg-white ... text-slate-800 ... focus:ring-2 focus:ring-primary/20 ... resize-none text-sm`), sin `bg-background`.

### C. `residente/enviar_pago.php`
13. Sin cambios (referencia).

**Fuera de alcance (Paquetes B/C):** estructura Tarjeta A/B en subir, banner/ancho/pie de acciones, Método de Pago, obligatoriedad de Referencia, centrado del pie de público, tarjetas Deuda/Saldo, selector de factura.

## Restricciones funcionales
- Cero cambios de backend/controladores/rutas/otras vistas.
- Conservar `name`, `id`s, `data-*`, `required`, endpoints, textos funcionales y reglas de autocompletado/inconsistencias.
- Mantener auto-extracción al seleccionar archivo y el pipeline `ComprobanteOCR.procesar`.

## Criterios de aceptación
1. Bloques compartidos coinciden entre vistas (dropzone, header Paso 1, resumen+Reanalizar, header Paso 2, tarjeta de cuenta, recetas de inputs).
2. En subir no quedan rastros de `btnOCR`/`ocrText`; `btnReanalizar` revela y reejecuta; badge de estado funciona.
3. En público no quedan `data-copiar`/`copiarDato`; `copiarDatoCuenta` con feedback; número agrupado; teléfono condicional; sin `bg-background` en el textarea.
4. `php -l` 2/2 OK; `node --check` del JS extraído (si node disponible); BehaviorTest y PagoDirectoTest verdes.
5. Diff acotado a `subir.php` y `pago_directo/index.php`.

## Ruta de implementación
- Writer único (delegado) + verificación del writer + verificador independiente read-only + spot check del padre. Commit del orquestador. Skill: `work-unit-commits` (el writer NO commitea).

## Verificación
- Writer: `php -l` ×2; greps de rastros (`btnOCR`, `ocrText`, `data-copiar`, `copiarDato`, `bg-slate-50`, `bg-background` en obs, "Cuenta oficial" minúscula); `node --check` del script extraído; `php tests/run.php --filter=Behavior`; `--filter=PagoDirecto`; `git diff --stat -- app/views`.
- Verificador independiente: diff vs `git show HEAD:`; equivalencia de bloques compartidos entre las 3 vistas; adversarial (ids únicos, orden, JS sin referencias muertas).
- RDD: `mode status` → on (global); `assess` post-commit esperado `high`/`unassessable` (OpenCode); ruta RDD-off (auto-verificación + verificador + spot check); no desactivar modo.

## Resultado (cierre)
- [x] A — `subir.php` alineada: header Paso 1 (`document_scanner` + "(Extracción Inteligente)" + subtítulo + badge `ocrStatusBadge`), dropzone receta blanca (p-6 / 170px / ícono 56px + "• Extracción automática"), botón manual `btnOCR` eliminado → `btnReanalizar` en el resumen (JS portado: `actualizarEstadoOCR`/`finalizarEstadoOCR`, listener, reveal), header Paso 2 con nota de obligatorios, labels `slate-600`, "Fecha de Realización", "Observaciones Complementarias", placeholders, inputs receta blanca (incluido el select de cuenta).
- [x] B — `pago_directo/index.php` alineada: "Cuenta Oficial" (label + badge), tarjeta `mt-3` + `shadow-xs` (contenedor y celdas), `wrapperInfoTelefono` condicional, número agrupado cada 4, botones Copiar con `copiarDatoCuenta` (feedback) y sin `data-copiar`, textarea obs receta blanca, labels de celda idénticos ("Número de Cuenta (20 dígitos)", "Titular Autorizado") y label de observaciones idéntico.
- [x] C — `enviar_pago.php` intacta (referencia canónica).
- Verificación del writer: `php -l` 2/2; `node --check` 2/2; greps sin rastros (`btnOCR`, `ocrText`, `focus:bg-white`, `data-copiar`, `copiarDato(`, "Cuenta oficial" minúscula); equivalencia de bloques 3-vías 1/1/1; BehaviorTest 260✅; PagoDirectoTest 53✅.
- Verificador independiente #1: HALLAZGOS — **H1 (ALTA)**: los `onclick` inline de público no resolvían `copiarDatoCuenta` (función dentro del IIFE) → copiar roto; H2/H3: labels de celda y de observaciones no alineados; H4: guard de `navigator.clipboard` perdida vs HEAD; H5: comentario obsoleto.
- Fix pass (orquestador, inline): `window.copiarDatoCuenta = copiarDatoCuenta;` dentro del IIFE + guard de clipboard; labels de celda y observaciones alineados; comentario `<!-- Observaciones -->`.
- Re-verificación independiente: **FIXES VERIFIED** — delta exacto de +8/−4 (solo los 5 fixes); auditoría completa de handlers inline de público (3/3 resueltos vía `window`; repro vm); tests verdes; sin regresiones nuevas.
- RDD: `mode status` → on (global). `assess` (base `278b114`, committed-only) → `risk: high`, reason `unassessable` (untracked preexistentes; OpenCode no elegible), `review_due: high_risk`, sin `next_transition`. Ruta RDD-off aplicada (writer + verificador independiente + re-verificación + spot check). Modo NO desactivado.
- Residuales honestos: en HTTP no seguro el copiar queda como no-op silencioso (paridad con `btnCopiarMonto`); divergencia preexistente de limpieza de clases en `mostrarResumenExtraccion` (H6, no introducida); sin verificación en navegador real (estática + node + tests).

## Entrega
- Commit `c1ebc9a` directo en `main` (2 vistas) + este doc en `docs(odd)`. Push: decisión del usuario. Paquetes B/C quedan pendientes de decisión.
