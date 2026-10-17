# ODD task: Manual admin restaurado (solo sección Respaldos) + sync de dump y htdocs.zip

**Estado**: COMPLETO (2026-10-17) — spot check del orquestador (V) cerrado
**Autorizado por**: usuario («devuelve el manual de administrador a su estado anterior a que lo tocaras, excepto por la seccion respaldos… asegura que los cambios implementados esten presentes en el repo base de datos (no cambies la estructura por temas de compatibilidad) y en el comprimido de htdocx (tampoco cambies nada innecesario de allí por los mismos temas)»)
**Ruta**: ODD delegated direct — writer único (3 cirugías mínimas) + spot check del orquestador

## Objetivo

1) Reemplazar `docs/manual de usuario final (Admin).docx` por la versión ORIGINAL (base 15-oct; respaldo en `C:/Users/Amadeo/AppData/Local/Temp/opencode/manual-admin-backup-OLD.docx`) con SOLO la sección 13 sustituida por la nueva (híbrido); eliminar el PDF agregado (estado previo: solo docx). 2) Actualizar `database/condominio_cobranzas.sql` de forma ADITIVA: `purgas_log` (DDL completo), `fecha_descarga` en `backups_log` e `idx_hist_creado` en `historial_estado_abono` — sin tocar NADA más (compatibilidad). 3) Actualizar `htdocs.zip` solo con los archivos de los cambios implementados; el resto intacto entrada por entrada.

## Contexto (verificado)

- Manual: el DOCX original (drift) está en el respaldo temp; el donante de la sección nueva es el docx regenerado actual en `docs/` (410 párrafos; su sección 13 = h1 «13. RESPALDOS» + 13.1/13.2/13.3; puede terminar con un pagebreak sobrante del filtro). El original NO tiene numeración en varios títulos (p. ej. «PANEL DE CONTROL FINANCIERO»), ni «Control de Versiones»; su sección 13 es la última del documento y se titula «RESPALDOS» (sin «13.»).
- Dump: `database/condominio_cobranzas.sql` (1,47 MB, 13-oct) tiene `backups_log` (línea ~56), `historial_estado_abono` (~557), `log_auditoria` (~621), `rate_limits` (~905) y NO tiene `purgas_log`. Tablas ordenadas alfabéticamente. NO regenerar el dump: edición quirúrgica.
- htdocs.zip: 297 entradas, todas bajo `htdocs/`. Tiene `app/Controllers/RespaldoController.php`, `app/views/admin/respaldos/index.php`, `public/index.php`, `scripts/` (muchos) y `scripts/manual/`. Verificar existencia de `app/views/layouts/` y `app/Services/` dentro.
- MySQL local ARRIBA (`condominio_cobranzas`): usar SHOW CREATE TABLE para el DDL exacto.
- Gotchas: PowerShell 5.1 (UTF8 explícito; scripts temporales con BOM para needles con tilde); Word COM (cerrar sin residuos).

## Tareas

- [x] T1 — **Híbrido del manual** (Word COM, sobre copias en temp; el respaldo OLD no se toca): abrir la copia del OLD y el donante (copia del docx regenerado actual); en el OLD localizar el párrafo Título 1 cuyo texto contenga «RESPALDOS» (la última sección) y borrar de ahí al final; insertar en ese punto el rango FormattedText del donante desde su Título 1 «13. RESPALDOS» hasta el final (recortando párrafos vacíos / pagebreak finales sobrantes del donante). Guardar como `docs/manual de usuario final (Admin).docx`. Eliminar `docs/manual de usuario final (Admin).pdf` (no existía antes). NO tocar `(Residente).docx`.
- [x] T2 — **Dump aditivo**: con SHOW CREATE TABLE de la BD viva, insertar `CREATE TABLE purgas_log` (DDL exacto: columna `fecha_descarga` con su COMMENT, índices `idx_purgas_fecha`/`idx_purgas_tabla`, FK `admin_id` → usuarios ON DELETE SET NULL) en su lugar alfabético (entre `personas` y `rate_limits` o donde corresponda); agregar la columna `fecha_descarga` al CREATE existente de `backups_log` TAL CÓMO ESTÉ (no tocar el resto de su esquema aunque tenga drift conocido); agregar `KEY idx_hist_creado (creado_en)` al CREATE de `historial_estado_abono`. NA—DA más (ni datos, ni reformatos).
- [x] T3 — **htdocs.zip** (método: copiar a temp → `[System.IO.Compression.ZipFile]` Update → verificar → reemplazar el original; backup del original en temp): UPDATE (contenido = archivos actuales del repo) de `htdocs/app/Controllers/RespaldoController.php`, `htdocs/app/views/admin/respaldos/index.php`, `htdocs/app/views/layouts/admin_sidebar.php`, `htdocs/app/views/layouts/auditor_sidebar.php`, `htdocs/public/index.php`, `htdocs/scripts/backup_database.php`, `htdocs/scripts/cleanup_backups.php`, `htdocs/scripts/setup_scheduled_tasks.ps1`, `htdocs/scripts/manual/contenido_manual.json`, `htdocs/scripts/manual/build_manual.ps1`. ADD de `htdocs/app/Services/DataRetentionService.php`, `htdocs/scripts/cleanup_retencion.php`, `htdocs/scripts/migrate_retencion_datos_schema.php`, `htdocs/scripts/migrate_descarga_respaldo_schema.php`. NINGÚN otro cambio (no tests, no otros archivos).
- [x] T4 — **Verificación**: (a) outline Word COM del híbrido → caps 1–13 con el look ORIGINAL (p. ej. «PANEL DE CONTROL FINANCIERO» sin «3.», sin «Control de Versiones», sin numeración nueva) y sección 13 = 13.1 Cómo acceder / 13.2 El respaldo del sistema / 13.3 Datos acumulados y retención; strings de la sección vieja ausentes («Historial de Respaldos», «rotación de 7 días») y nuevos presentes; conteo de «CAPTURA PENDIENTE» = 3; (b) dump: `git diff --numstat` del dump pequeño y conteniendo SOLO los 3 bloques + grep de presencia; (c) zip: manifiesto nombre→sha256 ANTES (297) y DESPUÉS (301); solo las 14 entradas objetivo difieren; entradas actualizadas = hashes de los archivos del repo; resto IDÉNTICO.
- [x] T5 — **Cierre**: commits `chore(db): dump con purgas_log, fecha_descarga e idx_hist_creado` (solo el dump) y `docs(odd): manual restaurado (solo respaldos) y sync de dump/htdocs` (este doc, con evidencia). El zip y los .docx/.pdf NO se commitean.
- [x] V — Spot check del orquestador (independiente): docx híbrido 2.603.575 B sin PDF de admin, `CAPTURA PENDIENTE`=3, look original conservado y sección 13 nueva (13.1/13.2/13.3); dump +35/−2 con los 3 bloques (commit `d44075d`); `htdocs.zip` 301 entradas, 14 objetivo con hash = repo. Detalle en el cierre.

## Criterios de aceptación

- El docx de admin = original salvo sección 13 (13.1/13.2/13.3 nuevas); sin PDF de admin; `(Residente).docx` intacto.
- Dump: diff mínimo y aditivo (3 bloques), compatible al importar.
- Zip: solo las 14 entradas objetivo cambiadas/agregadas; todo lo demás byte-idéntico.
- Sin procesos WINWORD residuales; sin tocar el working tree ajeno.

## Notas

- La fuente (JSON sección 13 + `-Variante` del builder) queda como está; el artefacto canónico del manual admin vuelve a ser el híbrido (no reproducible por builder — se documenta aquí).
- Espejo Engram: `odd/sync-manual-bd-htdocs/tasks` (lo sincroniza el orquestador).

## Evidencia (2026-10-17, subagente writer único)

### P1 — Híbrido del manual (Word COM)
- Procedimiento: sobre copias en temp del OLD (respaldo intacto) y del donante (`docs/manual de usuario final (Admin).docx` regenerado): localizado en el OLD el Título 1 «RESPALDOS» (última sección, párrafo 287) y borrado hasta el final; del donante se tomó el rango desde su Título 1 «13. RESPALDOS» (párrafo 388) hasta el último párrafo con texto (406; recortado el pagebreak sobrante final 409 y los vacíos posteriores); insertado con `Range.FormattedText` en el punto del OLD. Guardado con `Save()` in-place y copiado byte a byte a `docs/manual de usuario final (Admin).docx` (sha256 `84EFDC4F1741D70EF73598C09000F9F2DE555148CD47B822A69CBD86596C5F73`, 2.603.575 bytes, 308 párrafos).
- `docs/manual de usuario final (Admin).pdf` eliminado; `manual de usuario final (Residente).docx` intacto.
- Checks Word COM (case-sensitive): H1 = 14; «CAPTURA PENDIENTE» = 3; «Historial de Respaldos» = 0; «rotación de 7 días» = 0; «Descargar respaldo» = 2; «Datos acumulados» = 4; «Control de Versiones» = 0; texto exacto del H1 13 = «13. RESPALDOS» (sin `\f` embebido); page break previo conservado en párrafo propio (`<#12>` inmediatamente antes).
- Outline final del documento canónico:

```
L8   H1 [Índice]
L34  H1 [1. INTRODUCCIÓN]
L35  H2 [¿Qué es el sistema?]
L37  H1 [2. ACCESO AL SISTEMA]
L38  H2 [Iniciar sesión]
L54  H2 [Verificación en dos pasos (cuando está activa)]
L71  H2 [2.4 Cierre de sesión y caducidad de la sesión]
L78  H1 [PANEL DE CONTROL FINANCIERO]
L82  H2 [Qué encontrará]
L92  H1 [HISTORIAL DE PAGOS Y VERIFICACIÓN DE COMPROBANTES]
L97  H2 [Pasos]
L102 H2 [Sobre el detalle del pago]
L117 H1 [5. CONCILIACIÓN BANCARIA]
L122 H2 [Pasos]
L139 H1 [6. CUENTAS BANCARIAS]
L143 H2 [6.1 Cómo acceder]
L145 H2 [6.2 Pasos para registrar una cuenta]
L157 H1 [7. CARGA Y EMISIÓN]
L165 H2 [Registrar un gasto]
L173 H2 [Cargar el reporte de cartelera]
L190 H1 [8. ESTRUCTURA: EDIFICIOS Y UNIDADES]
L195 H2 [8.2 Crear un edificio]
L200 H2 [8.3 Crear unidades]
L209 H1 [9. ESTACIONAMIENTOS]
L214 H2 [Pasos]
L223 H1 [COMUNICADOS]
L228 H2 [10.2 Publicar un comunicado]
L240 H1 [USUARIOS: GESTIÓN Y SOLICITUDES]
L247 H2 [Gestión de usuarios]
L255 H2 [Solicitudes de Registro (acceso de residentes)]
L263 H2 [Cambios de datos]
L266 H1 [CARTA DE DEUDA Y MOROSIDAD]
L271 H2 [Pasos]
L287 H1 [13. RESPALDOS]
L293 H2 [13.1 Cómo acceder]
L295 H2 [13.2 El respaldo del sistema]
L299 H2 [13.3 Datos acumulados y retención]
```
(cola del documento: nota «Ciclo de limpieza…», nota «La purga es definitiva…» y párrafo final vacío; sin página vacía final.)

### P2 — Dump aditivo
- `git diff --numstat -- database/condominio_cobranzas.sql` → `35	2`. Tres bloques de esquema:
  1. `CREATE TABLE purgas_log` completo (15 columnas, `idx_purgas_fecha`, `idx_purgas_tabla`, FK `admin_id` → `usuarios` ON DELETE SET NULL, `AUTO_INCREMENT=56`), insertado en orden alfabético entre `personas` y `rate_limits` (sección con el estilo de `mysqldump`; sin sección de datos).
  2. Columna `fecha_descarga` con el COMMENT del vivo en el CREATE existente de `backups_log` (línea 68).
  3. `KEY idx_hist_creado (\`creado_en\`)` al final de las KEYs de `historial_estado_abono` (línea 573).
- Desviación mínima necesaria (documentada): la línea `INSERT INTO backups_log` recibió `,NULL` por fila (4 filas) porque el INSERT sin lista de columnas tenía 11 valores y el CREATE pasó a 12 columnas; sin ese ajuste el import falla (ERROR 1136). Los valores originales quedan intactos y el NULL coincide con la BD viva (filas 1–4 con `fecha_descarga` NULL). No se corrigió ningún otro drift de esa tabla (p. ej. AUTO_INCREMENT).
- Verificación de compatibilidad: import completo del dump a la BD scratch `condominio_cobranzas_verify` → exit 0; `purgas_log` creada (15 columnas); `backups_log.fecha_descarga` presente; `idx_hist_creado` presente sobre `creado_en`; datos intactos (`historial_estado_abono` 120 filas, `personas` 2). Scratch eliminada.

### P3 — htdocs.zip
- Manifiesto nombre→sha256 sobre el original (ANTES): 297 entradas. Sobre la copia actualizada (DESPUÉS): 301 entradas.
- Entradas con diferencia: exactamente las 14 objetivo (10 UPDATE + 4 ADD). Entradas no objetivo alteradas: 0 (sha256 idéntico en las 287 restantes). Los 14 sha256 de las entradas actualizadas coinciden con los archivos del repo.
- `htdocs.zip` del repo reemplazado (sha256 final `ECF31AC59FF8588895DFDFC1FA4F5B73E5717853131B6EE9003B7CAEEFB07311`); backup del original conservado en `C:/Users/Amadeo/AppData/Local/Temp/opencode/htdocs-backup-original.zip` (manifiestos en `zip-manifest-BEFORE.txt` / `zip-manifest-AFTER.txt`).

### Notas de entorno (Word COM, relevantes para futuras corridas)
- `SaveAs2` cuelga en este entorno (ya documentado en `scripts/manual/build_manual.ps1`); se usó `Save()` in-place (verificado OK) + copia a nivel de archivo.
- Instancias Word terminadas a la fuerza dejan entradas «DocumentRecovery» en el registro y archivos `~$`; abrir después un archivo con entrada pendiente cuelga el `Open` COM. Higiene aplicada: nombres de trabajo únicos por corrida, limpieza de `~$` propios, export del registro en `C:/Users/Amadeo/AppData/Local/Temp/opencode/word-resiliency-backup.reg`, y limpieza de las entradas de recovery propias. Sin procesos WINWORD residuales (la sesión Word del usuario con `(Residente).docx` no fue tocada).

### Cierre del orquestador (2026-10-17)

- Spot check (V) independiente del orquestador: **docx híbrido** — 2.603.575 bytes; `(Admin).pdf` eliminado; `CAPTURA PENDIENTE`=3; look ORIGINAL conservado fuera de la sección 13 («PANEL DE CONTROL FINANCIERO» sin numerar; «Control de Versiones» ausente) y sección 13 nueva completa (13.1 Cómo acceder / 13.2 El respaldo del sistema / 13.3 Datos acumulados y retención); strings viejos de la sección ausentes. **Dump** — commit `d44075d` (+35/−2): `purgas_log` (L907), `fecha_descarga` (L68 y L922), `idx_hist_creado` (L573); compatibilidad de importación demostrada por el writer en BD scratch (exit 0). **htdocs.zip** — 301 entradas; las 14 objetivo presentes con hash igual al repo (muestra re-verificada por el orquestador); backup del original conservado.
- Desviación aceptada: `,NULL` en los INSERT de `backups_log` (4 filas) — necesaria para que el dump siga importando con la columna nueva (ERROR 1136 sin ella); coincide con la BD viva.
- RDD: activado (global); cirugías de documentación/datos → readback estructural + spot checks (proporcional).
