# ODD task: Manual de administrador — cap. 7 «Carga manual de deuda» (+ nota de re-envío)

**Estado**: COMPLETADO (2026-10-17) — edición COM sobre el docx de admin + JSON actualizado; verificación estructural cerrada
**Autorizado por**: usuario (subsección «Registrar un gasto» renombrada a «Carga manual de deuda» y actualizada al funcionamiento vigente)
**Ruta**: ODD delegated direct — writer único (docx por Word COM + JSON) + verificación estructural independiente

## Objetivo

Renombrar la subsección «Registrar un gasto» del capítulo 7 («7. CARGA Y EMISIÓN») del manual de administrador a «Carga manual de deuda» y actualizar su contenido al funcionamiento vigente del feature de deuda manual (modal «Deuda manual por unidad», alcance por edificios/unidades, monto por unidad, tarjeta «Deudas manuales», «Enviar / Re-enviar Deuda»), además de reescribir la nota warn del capítulo sobre el re-envío de deudas. Solo esa subsección y esa nota.

## Contexto (verificado)

- `docs/manual de usuario final (Admin).docx` (binario canónico, no versionado): 308 párrafos, 15 InlineShapes, 20 tablas, 0 revisiones; cap. 7 en los párrafos 157–189. Subsección objetivo: Título 2 «Registrar un gasto» + 5 pasos en estilo `Párrafo de lista` (lista numerada real, numId 12; con residuo `w:highlight val="yellow"` en pPr y run) + párrafo «Historial de Reportes» (`Normal`, justificado) + párrafo vacío. La nota warn (párrafo 185) vive dentro de una tabla 1×1 (relleno #FFF3E0, Calibri 10,5 pt) y su última oración va `w:b` + `w:u val="single"`.
- Strings del feature verificados contra `app/views/admin/gastos/index.php` y `app/Models/DeudasManualesModel.php`: «Deuda manual por unidad», «Selección libre», «Seleccionar todo», «Monto por unidad (Bs.)», «Guardar Deuda Manual», «Deudas manuales —», «Limpiar período», y el aviso «Deudas ya enviadas» («el sistema recalculará y actualizará las facturas del período (no se duplican): se conserva lo ya pagado y se aplican las deudas manuales guardadas»).
- `scripts/manual/contenido_manual.json` = fuente versionada del variante regenerable (nodos `[103]` h2 «7.2 Registrar un gasto», `[104]` steps, `[105]` p «Historial de Reportes», `[110]` note warn).
- Esta máquina tiene Word; edición por COM con `Save()` in-place (SaveAs2 cuelga). Word COM con acentos: los textos se pasan por un `data.json` UTF-8 leído con encoding explícito (consola cp850 da falsos negativos con needles acentuados).

## Alcance

- En: docx de admin (título + pasos 1–5 reescritos + paso 6 nuevo por duplicación del párrafo anterior + párrafo de cierre `Normal` justificado + nota warn con cola bold+subrayada); `contenido_manual.json` (h2 `[103]`, steps `[104]`, nodo `p` nuevo, note warn `[110]`→`[111]`); este documento.
- Fuera: `docs/manual de usuario final (Residente).docx`, manual combinado, capturas nuevas, otros capítulos/secciones, `build_manual.ps1` (sin cambios).

## Tareas

- [x] T1 — DOCX (Word COM, sobre COPIA en temp): localizar por TEXTO (no por índice), renombrar el Título 2, reescribir los 5 pasos conservando formato/highlight, duplicar el paso 5 para el paso 6 (hereda numeración real «6.»), duplicar el párrafo «Historial de Reportes» para insertar antes el cierre `Normal` justificado, reescribir la nota warn y re-aplicar bold+subrayado a la última oración; `Save()` in-place; copia del binario sobre el canónico con backup previo en temp.
- [x] T2 — `contenido_manual.json`: h2 → «7.2 Carga manual de deuda»; steps → 6 pasos; nodo `p` nuevo después de `[104]`; note warn → texto nuevo. Reemplazos byte-safe (str_replace con conteo exacto = 1 por patrón); UTF-8 sin BOM, LF.
- [x] T3 — Verificación independiente del docx final (COM read-only + XML descomprimido): conteos estructurales, outline del cap. 7, presentes/ausentes, comparación de secuencia de párrafos viejo↔nuevo (solo la zona objetivo cambió), higiene Word.
- [x] T4 — Verificación del JSON (`json_decode`, `git diff --numstat`, bytes) y actualización de este doc.
- [x] T5 — Commit único selectivo (sin push): `scripts/manual/contenido_manual.json` + este doc.

## Criterios de aceptación

- El docx abre, el cap. 7 muestra «Carga manual de deuda» (Título 2) con 6 pasos numerados 1.–6., párrafo de cierre antes del «Historial de Reportes», y la nota warn nueva con la última oración en bold+subrayado; el resto del documento intacto (mismos párrafos y orden salvo los 2 insertados).
- Strings viejos ausentes en docx y JSON (`Registrar un gasto` como título, `Tipo / Alcance del Gasto`, `duplica la deuda`, `eliminó las deudas anteriores`); strings nuevos presentes.
- JSON válido, UTF-8 sin BOM, LF; diff mínimo y solo en la zona del cap. 7.
- Sin procesos WINWORD residuales; `(Residente).docx` sin cambios.

## Verificación (comandos y resultados)

- `C:/xampp/php/php.exe -r "json_decode(...); echo json_last_error_msg();"` → `No error`.
- `git diff --numstat -- scripts/manual/contenido_manual.json` → `10	8` (solo zona cap. 7).
- Word COM read-only sobre el docx final → párrafos 310; InlineShapes 15; tablas 20; revisiones 0; outline cap. 7 = Título 1 «7. CARGA Y EMISIÓN» + Título 2 «Carga manual de deuda» + Título 2 «Cargar el reporte de cartelera»; siguiente Título 1 = «8. ESTRUCTURA: EDIFICIOS Y UNIDADES».
- `word\document.xml` (docx→zip): conteos presentes/ausentes y comparación de secuencia de párrafos (detalle en el registro).

## Registro de evidencia (2026-10-17)

### T1 — DOCX editado por COM
- Pilotaje previo en modo DryRun (sin guardar) → todos los hallazgos esperados; luego corrida real: `SAVED OK`.
- Backup del original: `%TEMP%\opencode\manual7\admin-backup-precarga-20261017-174136.docx` (2.603.575 bytes, SHA256 `84EFDC4F…`). Copia de trabajo: `admin-edit-20261017-174136.docx`.
- Docx final: 2.604.035 bytes, SHA256 `960514BD179995F66B51C8EFC2613A063AC72CF41AC2C557DC2FBD108C48BD18` (idéntico al de la copia de trabajo tras `Save()`).
- COM (antes → después): párrafos 308 → **310**; InlineShapes 15 → 15; tablas 20 → 20; revisiones 0 → 0.
- Mecánica: título por texto único; pasos 1–5 reescritos en su mismo rango (conserva run rPr con `w:highlight val="yellow"` y pPr); paso 6 = copia del párrafo 5 pegada tras él (ListFormat.ListString releído = «6.», estilo `Párrafo de lista`); cierre = copia del párrafo «Historial de Reportes» pegada al inicio de este (releído `jc=both`, estilo `Normal`); nota warn reescrita y cola formateada `Bold=-1`, `Underline=1` (verificado en XML: `<w:b/>` + `<w:u w:val="single"/>`, Calibri 10,5 pt conservado, dentro de su celda de tabla).
- El párrafo 159 devuelve `/` por `Range.Text` y en el XML no hay texto (artefacto COM ya conocido): NO se tocó.

### T2 — JSON actualizado
- Patrones reemplazados (conteo exacto 1 c/u antes de escribir): h2 `[103]`, bloque steps `[104]`, anclaje del nodo `p` «Historial de Reportes», note warn `[110]`.
- Resultado: nodos 311 → 312; `[103]` = «7.2 Carga manual de deuda»; `[104]` = 6 ítems; `[105]` = p de cierre nuevo; `[106]` = p «Historial de Reportes» (conservado); `[111]` = note warn nueva; `[116]` = h1 «8. …».
- Bytes: primeros bytes `123,10,32` (sin BOM); 0 CRLF / 576 LF.

### T3 — Verificación del docx final
- COM read-only: párrafos 310; InlineShapes 15; tablas 20; revisiones 0. Outline del cap. 7: H1 «7. CARGA Y EMISIÓN», L2 «Carga manual de deuda», L2 «Cargar el reporte de cartelera»; siguiente H1 «8. ESTRUCTURA: EDIFICIOS Y UNIDADES».
- Conteos sobre el texto (COM): PRESENTES ×1 — `Carga manual de deuda`, `Guardar Deuda Manual`, `Deudas manuales —`, `Limpiar período`, `Selección libre`, `no se divide`, `Cargar el reporte de cartelera`; `CAPTURA PENDIENTE` = 3. AUSENTES = 0 — `Registrar un gasto`, `Tipo / Alcance del Gasto`, `duplica la deuda`, `eliminó las deudas anteriores` (ANTES = 1 c/u según recon XML).
- XML `document.xml` (viejo vs nuevo): párrafos 288 → 290; región previa al título (0..152) con **0** diferencias; cola alineada (+2) con **0** diferencias inesperadas y exactamente **1** cambio esperado (la nota warn); último párrafo intacto. `w:highlight` 13 → 15: título + 6 pasos ×2 (pPr+run) + nota ajena; el paso 6 hereda el highlight por diseño («duplica el párrafo anterior para heredar formato/numeración»).
- `Registrar un gasto` aparecía 1 sola vez en todo el documento (el título): no había entradas de índice u otras menciones que actualizar. `Tipo / Alcance del Gasto` quedó en 0 en el capítulo.
- Higiene: 0 procesos WINWORD residuales; sin archivos `~$` en `docs` ni en temp; `(Residente).docx` sin cambios (mtime 15/10/2026 15:02:52, tamaño 1.994.070 bytes).

### T4 — Cierre
- Commit único `docs(manual): capitulo 7 — carga manual de deuda y nota de re-envio actualizada` con `scripts/manual/contenido_manual.json` y este documento; el `.docx` NO se commitea (untracked por convención).

## Notas

- No tocar `(Residente).docx` ni el manual combinado. No commitear binarios.
- El binario canónico de admin es la fuente de verdad de capturas/estilos; el JSON es la fuente versionada del variante regenerable. Los textos nuevos quedaron alineados en ambos.
- Capturas: este cambio no introduce placeholders nuevos; `CAPTURA PENDIENTE` se mantiene en 3 (03-2fa, 10-cartelera, 18-respaldos).
- RDD: activado (global); cambio de documentación → readback estructural (verificación de arriba).
