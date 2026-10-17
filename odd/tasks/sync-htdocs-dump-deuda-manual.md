# ODD task: sync de htdocs.zip y dump con deuda manual (y modales)

**Estado**: COMPLETO (2026-10-17)
**Autorizado por**: usuario («actualiza el comprimido de htdocs con los cambios implementados y la base de datos con la tabla única nueva» — delegado)
**Ruta**: ODD delegated direct — writer único (2 cirugías mínimas)

## Objetivo

1) Actualizar `htdocs.zip` con los 13 archivos desplegables de los cambios implementados desde el sync anterior (`7f1c724..HEAD`): 10 entradas UPDATE + 3 entradas ADD. NINGUNA otra entrada se toca. 2) Actualizar `database/condominio_cobranzas.sql` de forma ADITIVA: `deudas_manuales` (DDL completo) en su lugar alfabético, sin tocar NADA más (compatibilidad).

## Contexto (verificado)

- `git diff --name-only 7f1c724..HEAD` (rutas desplegables) = exactamente 13 archivos; el zip (301 entradas, todas bajo `htdocs/`) tiene entrada o carpeta para las 13 rutas.
- Dump: la única tabla de la BD viva (`condominio_cobranzas`) que NO está en el dump es `deudas_manuales` (resto de tablas: idénticas en nombre). No hay otro cambio de esquema.
- `htdocs.zip` previo al trabajo (sha256 `ECF31AC59FF8588895DFDFC1FA4F5B73E5717853131B6EE9003B7CAEEFB07311`) coincide con el estado final del sync anterior (`odd/tasks/sync-manual-bd-htdocs.md`).
- MySQL local ARRIBA (`condominio_cobranzas`): `SHOW CREATE TABLE` para el DDL exacto.
- Gotchas: archivo `.env` y `htdocs/files for your website should be uploaded here!` dentro del zip deben permanecer intactos; dump en LF sin BOM.

## Tareas

- [x] T1 — **htdocs.zip** (método del sync anterior: backup del original en temp → manifiesto name→sha256 ANTES → copia a temp → `[System.IO.Compression.ZipFile]::Open($copia,'Update')` → reemplazo/alta con bytes exactos del repo → manifiesto DESPUÉS → comparación → reemplazar el original solo si todo cuadra): UPDATE (contenido = archivos actuales del repo) de `htdocs/app/Controllers/AdminController.php`, `htdocs/app/Controllers/GastoController.php`, `htdocs/app/Models/FacturasModel.php`, `htdocs/app/views/admin/conciliacion/index.php`, `htdocs/app/views/admin/cuentas_bancarias/index.php`, `htdocs/app/views/admin/gastos/index.php`, `htdocs/app/views/admin/solicitudes_registro/index.php`, `htdocs/app/views/admin/usuarios/index.php`, `htdocs/public/index.php`, `htdocs/scripts/manual/contenido_manual.json`. ADD de `htdocs/app/Models/DeudasManualesModel.php`, `htdocs/scripts/migrate_fase22_deudas_manuales.php`, `htdocs/scripts/migrations_phase22.sql`. NINGÚN otro cambio.
- [x] T2 — **Dump aditivo**: con `SHOW CREATE TABLE deudas_manuales` de la BD viva, insertar el bloque `CREATE TABLE deudas_manuales` (DDL exacto: UNIQUE `uk_deuda_manual_unidad_periodo`, KEY `idx_deuda_manual_periodo`, FK `fk_deuda_manual_unidad` → `unidades` ON DELETE CASCADE, `AUTO_INCREMENT=158`) en su lugar alfabético, entre `cuentas_bancarias` y `edificios`, con el estilo de `mysqldump` de las tablas vecinas y sin sección de datos (tabla vacía; igual que `purgas_log`). NADA más.
- [x] T3 — **Verificación**: (a) zip: manifiesto ANTES (301) / DESPUÉS (304); exactamente 10 cambiadas + 3 agregadas = 13 objetivo; 0 diferencias en las otras 291; sha256 de las 13 == archivos del repo; (b) dump: `git diff --numstat` = `24 0` (solo el bloque, aditivo); LF preservado; import completo del dump en BD scratch `condominio_cobranzas_verify` → exit 0 → `SHOW CREATE TABLE deudas_manuales` en la scratch idéntico al vivo → scratch eliminada; (c) working tree ajeno intacto.
- [x] T4 — **Cierre**: commits `chore(db): dump con tabla deudas_manuales` (solo el dump) y `docs(odd): sync de htdocs y dump con deuda manual y modales` (este doc, con evidencia). El zip NO se commitea.

## Criterios de aceptación

- Zip: 301 → 304 entradas; solo las 13 entradas objetivo cambiadas/agregadas; las otras 291 byte-idénticas (incluidas `htdocs/.env`, `htdocs/.env.example` y `htdocs/files for your website should be uploaded here!`).
- Dump: diff mínimo y aditivo (solo el bloque de `deudas_manuales`), compatible al importar (exit 0 en BD scratch).
- Sin tocar el working tree ajeno.

## Notas

- El zip del repo no está trackeado por git (`?? htdocs.zip`); por eso no genera commit. Se conserva backup del original en temp.
- Espejo Engram: `odd/sync-htdocs-dump-deuda-manual/tasks` (lo sincroniza el orquestador).

## Evidencia (2026-10-17, subagente writer único)

### P1 — htdocs.zip
- Manifiesto name→sha256 sobre el original (ANTES): 301 entradas. Sobre la copia actualizada (DESPUÉS): 304 entradas. Manifiestos: `C:/Users/Amadeo/AppData/Local/Temp/opencode/zip2-manifest-BEFORE.txt` / `zip2-manifest-AFTER.txt`.
- Entradas con diferencia: exactamente las 13 objetivo (10 UPDATE + 3 ADD). Entradas no objetivo alteradas: 0 (sha256 idéntico en las 291 restantes). Los 13 sha256 de las entradas actualizadas coinciden con los archivos del repo (`TARGET_HASHES_MATCH_REPO=True`).
- 10 UPDATE: `AdminController.php`, `GastoController.php`, `FacturasModel.php`, `views/admin/conciliacion/index.php`, `views/admin/cuentas_bancarias/index.php`, `views/admin/gastos/index.php`, `views/admin/solicitudes_registro/index.php`, `views/admin/usuarios/index.php`, `public/index.php`, `scripts/manual/contenido_manual.json`.
- 3 ADD: `app/Models/DeudasManualesModel.php`, `scripts/migrate_fase22_deudas_manuales.php`, `scripts/migrations_phase22.sql`.
- `htdocs.zip` del repo reemplazado (sha256 final `FA4938095AF2B266DB3303861E942BD0FD6BB0E2D35BDCEAA2D0C08C2D5D2916`, 1.196.665 bytes); backup del original conservado en `C:/Users/Amadeo/AppData/Local/Temp/opencode/htdocs-backup-original-presync2.zip`.

### P2 — Dump aditivo
- `git diff --numstat -- database/condominio_cobranzas.sql` → `24	0`. Bloque único `deudas_manuales` insertado entre `cuentas_bancarias` y `edificios` (líneas 352–375), con el encabezado de sección `-- Table structure for table` y sin sección de datos (tabla vacía). Nada más del dump cambia.
- Codificación/fin de línea preservados: LF-only, sin BOM (1218 → 1242 líneas LF, 0 CRLF; 1.472.356 → 1.473.393 bytes).
- Verificación de compatibilidad: import completo del dump a la BD scratch `condominio_cobranzas_verify` → exit 0; `SHOW CREATE TABLE deudas_manuales` en la scratch idéntico al vivo (9 columnas, UNIQUE `uk_deuda_manual_unidad_periodo`, KEY `idx_deuda_manual_periodo`, FK `fk_deuda_manual_unidad` → `unidades` ON DELETE CASCADE, `AUTO_INCREMENT=158`, utf8mb4_general_ci); 34 tablas en total. Scratch eliminada.

### P3 — Working tree
- Sin tocar archivos ajenos al alcance: solo `database/condominio_cobranzas.sql` (M) y `htdocs.zip` (destrackeado, reemplazado). El resto del ruido del working tree queda como estaba.

### Cierre (2026-10-17)

- Commit A: `chore(db): dump con tabla deudas_manuales` — solo `database/condominio_cobranzas.sql`.
- Commit B: `docs(odd): sync de htdocs y dump con deuda manual y modales` — solo este doc.
