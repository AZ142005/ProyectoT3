# Sync Fase 17: anti-duplicados en comprobantes_pago (desde dump #5)

**Objetivo**: Alinear la base local `condominio_cobranzas` con el esquema del dump `database/condominio_cobranzas (5).sql` en lo referente a la Fase 17 (proteccion de duplicados en comprobantes y hashes SHA-256), y sincronizar el dump versionado del repo.

## Problema / por qué
- El codigo del commit `ae0f1dc` (`ComprobantesModel`, `PagoModel`) ya inserta `referencia_norm`, `archivo_hash` y usa `dup_guard` en `comprobantes_pago`, y `archivo_hash` en `pagos`; la base local NO tiene esas columnas/índices → las rutas de insercion de comprobantes y pagos con hash fallarían.
- El dump (5) del compañero ya refleja el esquema Fase 17; la migracion del repo (`scripts/migrate_comprobantes_duplicados.php`) es la via idempotente con backfill.

## Alcance
- Aplicar en la BD local: `php scripts/migrate_comprobantes_duplicados.php` (columnas + backfill + dup_guard + únicos/índices en `comprobantes_pago` y `pagos`).
- Regenerar `database/condominio_cobranzas.sql` desde la BD local (mysqldump XAMPP, `--result-file`).
- NO aplicar la regresion de `backups_log`: el dump (5) trae el esquema viejo (`archivo/tamano/checksum`); la local conserva el esquema Fase 6 (regla de la ronda anterior).
- NO importar datos del dump (5): solo estructura.

## Checklist
- [x] T1. Ejecutar migracion Fase 17 contra BD local.
- [x] T2. Verificar diff estructural: (5) vs vivo solo debe diferir en `backups_log` (regresion conocida), contadores AUTO_INCREMENT y cosmético `UNSIGNED` vs `unsigned` en jwt_blacklist/rate_limits.
- [x] T3. Correr tests `--filter=Duplicados`.
- [x] T4. Regenerar dump versionado desde la BD local.
- [x] T5. Verificar dump regenerado ≡ BD viva (structdiff sin diferencias).
- [x] T6. Commits de la unidad de trabajo (sin push).

## Criterios de aceptación
- `comprobantes_pago`: `referencia_norm`, `archivo_hash`, `dup_guard` (STORED) + `uk_comprobante_dup_guard` + `idx_comprobantes_referencia_norm` + `idx_comprobantes_archivo_hash`.
- `pagos`: `archivo_hash` + `idx_pagos_referencia_norm` + `idx_pagos_archivo_hash`.
- Diff estructural (5) vs vivo sin diferencias fuera de las conocidas.
- Suite `--filter=Duplicados` en verde.
- Dump del repo refleja la BD local.

## Verificación
- `structdiff from-db` + `diff` vs `model_5.json` → esperado: solo `backups_log` + contadores + UNSIGNED.
- `php tests/run.php --filter=Duplicados`.
- structdiff del dump regenerado vs `from-db` → sin diferencias reales.

## Evidencia (2026-10-03)
- **T1**: `php scripts/migrate_comprobantes_duplicados.php` → comprobantes_pago: `referencia_norm` + `archivo_hash` agregadas, backfill a 5 filas, `dup_guard` STORED + `uk_comprobante_dup_guard` + `idx_comprobantes_referencia_norm` + `idx_comprobantes_archivo_hash`; pagos: `archivo_hash` + `idx_pagos_referencia_norm` + `idx_pagos_archivo_hash` (backfill 0 filas).
- **T2**: structdiff (dump 5 vs vivo): 26/29 idénticas; quedan solo `backups_log` (regresión del dump, deliberadamente NO aplicada), 2 diferencias cosméticas `UNSIGNED`/`unsigned` y contadores de datos.
- **T3**: `php tests/run.php --filter=Duplicados` → 16/16 tests, 84 asserts, 0 fallos (el "Lock wait timeout" impreso es esperado del test de contención concurrente).
- **T4**: `mysqldump` XAMPP 10.4.32 `--result-file` → `database/condominio_cobranzas.sql` regenerado (417.479 bytes).
- **T5**: structdiff dump regenerado vs vivo → sin diferencias reales (solo artefactos de comentarios `DISABLE/ENABLE KEYS`).
- **Nota**: el archivo recibido `database/condominio_cobranzas (5).sql` queda sin versionar (referencia); no se importaron sus datos.
- **Commits**: `f61d5a9` (chore db: dump sincronizado) + commit de este doc. Sin push.
