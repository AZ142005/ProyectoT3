# ODD task: Manual de administrador — sección Respaldos actualizada

**Estado**: COMPLETADO (2026-10-17) — spot check del orquestador (V) cerrado; docx de admin regenerado con la sección nueva
**Autorizado por**: usuario («añade (o cambia) la seccion en el manual de administrador (siguiendo la estructura que ya tienen los otros apartados en el documento)»)
**Ruta**: ODD delegated direct — writer único (JSON + builder + build) + spot check del orquestador

## Objetivo

Actualizar la sección «13. RESPALDOS» del manual de administrador (docx) para reflejar la UI vigente —respaldo único con botón de descarga + panel de datos acumulados/retención + ciclo 7/30— siguiendo la estructura de los demás apartados; y hacer reproducible la generación del manual de administrador desde `contenido_manual.json` (hoy los docx «(Admin)»/«(Residente)» no tienen generador en el repo).

## Contexto (verificado)

- Fuente de verdad: `scripts/manual/contenido_manual.json` (nodos tipados: h1/h2/h3/p/bullets/steps/note/image/toc/part/pagebreak/table) + `scripts/manual/build_manual.ps1` (Word COM; salida por defecto `docs/manual de usuario final.docx/.pdf`; `-OutputDir` opcional).
- `docs/manual de usuario final (Admin).docx` (15-oct, sin generador en el repo) = portada + índice (nodo `toc` estático que menciona PARTE II/FAQ) + capítulos 1–13 (termina en «13.2 Qué encontrará»; NO incluye el cap. 14 ROL AUDITOR). Tiene 3 placeholders «CAPTURA PENDIENTE» (03-2fa, 10-cartelera, 18-respaldos, por conteo contra el plan).
- La sección 13 actual describe la UI VIEJA (tabla «Historial de Respaldos», rotación de 7 días, nota sobre el enlace ausente en la barra lateral). La UI nueva ya está verificada: tarjeta «Respaldo del sistema (vigente)» + botón «Descargar respaldo»; tarjetas «Datos acumulados y retención» y «Purgas y exportaciones»; ciclo 7/30. Strings exactos: leer `app/views/admin/respaldos/index.php`.
- Esta máquina tiene Word (el builder corre por COM; builds ~33 s; idempotente; debe quedar SIN procesos WINWORD residuales).

## Alcance

- En: reescritura de la sección 13 en el JSON (h1 13, p «¿Para qué sirve?», image con caption/desc_pendiente nuevos, 13.1/13.2/13.3, bullets, notas info+warn); extensión del builder con `-Variante` (`full` por defecto = comportamiento actual | `admin` = nodos hasta antes del h1 «14. EL ROL AUDITOR…», salida `manual de usuario final (Admin).docx/.pdf`); regeneración del docx de admin (respaldo previo del original en carpeta temporal); este doc.
- Fuera: la variante «residente» (se deja como está; el parámetro queda extensible), capturas nuevas (18-respaldos sigue pendiente: solo se actualiza el texto del placeholder), el manual combinado (no se regenera), cambios en otras secciones.

## Tareas

- [x] T1 — `contenido_manual.json`: reescribir los nodos de la sección 13 manteniendo el estilo de los otros capítulos y usando los strings EXACTOS de la vista actual. Contenido aprobado (ajustar a los strings reales):
  - p: «¿Para qué sirve? Protege la información del sistema: muestra el respaldo vigente de la base de datos —generado automáticamente por el servidor— con su firma de integridad, y administra la retención de los datos que se acumulan.»
  - image: caption «Respaldo único del sistema con su botón de descarga y el panel de datos acumulados.»; desc_pendiente nuevo (capturar tarjeta del respaldo + botón + tarjeta de retención como administrador).
  - 13.1 Cómo acceder: barra lateral → Respaldos (disponible también para auditor en modo consulta); dirección `/admin/respaldos`.
  - 13.2 El respaldo del sistema (bullets): tarjeta vigente (fecha/tamaño/tablas/firma SHA-256); botón «Descargar respaldo» (verifica integridad antes de descargar); un único respaldo que se regenera a diario y reemplaza al anterior.
  - 13.3 Datos acumulados y retención (bullets): tarjeta de acumulación (registros, tamaño, más antiguo, elegibles, corte); botones «Exportar» y «Respaldar y purgar» (solo admin; el respaldo previo es obligatorio: si falla, no se borra nada); tarjeta «Purgas y exportaciones» (historial descargable con firma).
  - note info: ciclo de limpieza (descargados → 7 días; nunca descargados → 30 días; respaldo vigente diario; los historiales se conservan).
  - note warn: la purga es definitiva pero siempre va con respaldo previo verificado; exportar/purgar = solo admin, auditor consulta/descarga. (Reemplaza la vieja nota del «enlace ausente».)
- [x] T2 — `build_manual.ps1`: `param([string]$OutputDir='', [string]$Variante='full')`; variante `admin` = filtrar `$nodos` hasta (sin incluir) el primer `h1` cuyo texto empiece por «14.»; nombre de salida según variante; docblock actualizado; `full` sin ningún cambio de comportamiento.
- [x] T3 — Build de la variante admin: copiar el docx actual a temp (respaldo), correr el build, verificar docx generado e idempotencia (segunda corrida), sin WINWORD residual.
- [x] T4 — Verificación estructural del docx nuevo (Word COM): capítulos 1–13 presentes y en el mismo orden, cap. 14 ausente, sección 13 con 13.1/13.2/13.3; strings nuevos presentes; «Historial de Respaldos» / «rotación de 7 días» / «respaldos manuales» ausentes del cuerpo; «CAPTURA PENDIENTE» = 3.
- [x] T5 — Actualizar este doc (checkboxes + evidencia) y commit `docs(manual): sección Respaldos de admin actualizada (respaldo único + retención) y build por variante` — SOLO `scripts/manual/contenido_manual.json`, `scripts/manual/build_manual.ps1` y este doc. Los .docx/.pdf NO se commitean.
- [x] V — Spot check del orquestador: el docx abre y la estructura es correcta (caps. 1–13 numerados, sin el 14; sección 13 con 13.1/13.2/13.3; `CAPTURA PENDIENTE` = 3; strings nuevos presentes y viejos ausentes). Detalle en el cierre.

## Criterios de aceptación

- El docx de admin abre, su sección 13 refleja la UI vigente con la estructura de los demás apartados, y el resto del documento queda intacto (mismos títulos/orden que el original).
- El builder por defecto sigue funcionando igual; la variante admin es idempotente y reproducible.
- JSON válido y en UTF-8; build sin procesos Word residuales.

## Verificación (comandos)

- `php -r "json_decode(file_get_contents('scripts/manual/contenido_manual.json')); echo json_last_error_msg(), PHP_EOL;"`
- `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manual\build_manual.ps1 -Variante admin` (×2 → idempotencia)
- Outline por Word COM (script temporal en temp) + `Select-String` sobre `word\document.xml` descomprimido (placeholders/strings).

## Registro de evidencia (2026-10-17)

### T1 — JSON: sección 13 reescrita
- Validación: `php -r "json_decode(file_get_contents('scripts/manual/contenido_manual.json')); echo json_last_error_msg(), PHP_EOL;"` → `No error`.
- Codificación: UTF-8 sin BOM (bytes 0-2 = 123,10,32); fin de línea LF (0 CRLF / 574 LF), igual que antes del cambio.
- Alcance: `git diff --numstat -- scripts/manual/contenido_manual.json` → `15	9	scripts/manual/contenido_manual.json` (solo el bloque de la sección 13).
- Nodos finales: h1 + p «¿Para qué sirve? …» + image `18-respaldos.png` (caption y desc_pendiente nuevos) + 13.1 Cómo acceder + 13.2 El respaldo del sistema (3 bullets) + 13.3 Datos acumulados y retención (3 bullets) + note info (ciclo 7/30) + note warn (purga con respaldo previo; roles). La nota vieja del «enlace ausente» quedó eliminada; strings tomados de `app/views/admin/respaldos/index.php`.

### T2 — Builder: `-Variante`
- `param([string]$OutputDir = '', [string]$Variante = 'full')`; docblock del encabezado actualizado.
- `full` no cambia el flujo existente; `admin` filtra `$nodos` hasta antes del primer h1 «14. …» y ajusta la salida a `manual de usuario final (Admin).docx/.pdf`.
- Sintaxis: `[System.Management.Automation.Language.Parser]::ParseFile(...)` → `SYNTAX OK`.
- Filtro comprobado: 311 nodos (full) → 185 nodos (admin); el subconjunto termina con la sección 13 (pagebreak previo al cap. 14 incluido).

### T3 — Build admin reproducido (×2)
- Respaldo previo del docx original: `C:\Users\Amadeo\AppData\Local\Temp\opencode\manual-admin-backup-OLD.docx` (2.603.243 bytes).
- `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manual\build_manual.ps1 -Variante admin`:
  - Corrida 1: `BUILD OK` — DOCX 2.935.174 bytes; PDF 1.747.883 bytes; párrafos 410; InlineShapes 17; tablas 31; figuras 19; `Placeholders CAPTURA PENDIENTE: 3`; 26,6 s; WINWORD residuales = 0.
  - Corrida 2 (idempotencia): `BUILD OK` — DOCX 2.935.732 bytes; PDF 1.747.883 bytes; mismos conteos estructurales; 23,9 s; WINWORD residuales = 0. (La diferencia de bytes del DOCX es metadato de Word; el PDF conserva el mismo tamaño.)

### T4 — Verificación estructural (Word COM + document.xml)
- Scripts temporales (fuera del repo): `%TEMP%\opencode\{manual-admin-verify,dump-paragraphs,list-heading-numbering}.ps1`.
- Outline del docx nuevo (OutlineLevel 1-2, 51 títulos): «Control de Versiones», «Índice» y capítulos 1–13 en orden, sin el capítulo 14; sección 13 = `13.1 Cómo acceder`, `13.2 El respaldo del sistema`, `13.3 Datos acumulados y retención` (lista completa: `%TEMP%\opencode\manual-new-titles.txt`).
- `word\document.xml` del docx nuevo (docx→zip→Expand-Archive en temp):
  - `CAPTURA PENDIENTE` = 3.
  - Presentes: `Descargar respaldo` (2), `Datos acumulados` (3), `7 días` (1), `30 días` (2), `Respaldo del sistema (vigente)` (2), `Purgas y exportaciones` (1), `Respaldar y purgar` (2), `Respaldos Automatizados del Servidor` (1), `13.1 Cómo acceder` (1), `13.2 El respaldo del sistema` (1), `13.3 Datos acumulados y retención` (1), `CLI / Cron` (1).
  - Ausentes: `Historial de Respaldos` (0), `rotación de 7 días` (0), `respaldos manuales` (0), `Si no encuentra el enlace` (0).
- Comparación con el respaldo OLD (misma técnica): capítulos 1–13 en el mismo orden y cap. 14 ausente en ambos; sección 13 del OLD = `13.1 Cómo acceder` + `13.2 Qué encontrará`. Las cadenas viejas están presentes en el OLD (cada una ×1) y las nuevas clave, ausentes.
- Observación (fuera de la sección 13): el docx OLD no era un render fiel del JSON canónico — títulos sin numeración (p. ej. «PANEL DE CONTROL FINANCIERO» sin «3.»), sin «Control de Versiones», párrafos sin el prefijo «¿Para qué sirve? » y redacciones más cortas (2.1, 5) —. El docx nuevo renderiza el JSON vigente; el diff 15/9 de líneas prueba que el JSON no cambió fuera de la sección 13, por lo que esas diferencias son drift previo del binario OLD, no de este cambio.

### T5 — Cierre
- Commit único `docs(manual): sección Respaldos de admin actualizada (respaldo único + retención) y build por variante` con `scripts/manual/contenido_manual.json`, `scripts/manual/build_manual.ps1` y este documento; `.docx`/`.pdf` no se commitean.

### Cierre del orquestador (2026-10-17)

- **Spot check (V)**: el docx regenerado abre; 410 párrafos; capítulos 1–13 numerados y en orden, sin el capítulo 14; sección 13 con «13.1 Cómo acceder», «13.2 El respaldo del sistema» y «13.3 Datos acumulados y retención»; `CAPTURA PENDIENTE` = 3; cadenas viejas ausentes (`Historial de Respaldos` = 0); cadenas nuevas presentes (`Descargar respaldo` = 2, `Respaldo del sistema (vigente)` = 2, `Datos acumulados` = 3; «7 días»/«30 días» reconfirmados con regex independiente de tildes — la consola cp850 da falsos negativos con needles acentuados).
- **Verificación de alcance**: commit `a994ee8` = solo `contenido_manual.json` + `build_manual.ps1` + este doc (binarios fuera, por diseño). Respaldo del docx anterior conservado en `%TEMP%\opencode\manual-admin-backup-OLD.docx`.
- **Observación registrada**: el docx OLD no era un render fiel del JSON canónico (títulos sin numeración, sin «Control de Versiones», párrafos sin el prefijo «¿Para qué sirve? », redacciones más cortas). El regenerado es fiel a la fuente; las diferencias fuera de la sección 13 son drift previo del binario, no de este cambio.
- **Decisiones**: variante `admin` = nodos hasta antes del cap. 14 (fidelidad al alcance del docx original); `(Residente).docx` y el manual combinado NO se tocaron; `-Variante` queda extensible para una futura variante residente.
- RDD: activado (global); cambio de documentación → readback estructural (spot check de arriba), sin verificador pesado (proporcional).

## Notas

- No tocar `(Residente).docx` ni el manual combinado. No commitear binarios.
- RDD: activado (global); para cambios de documentación aplica readback estructural (sin verificador pesado).
- Espejo Engram: `odd/manual-admin-seccion-respaldos/tasks` (lo sincroniza el orquestador).
