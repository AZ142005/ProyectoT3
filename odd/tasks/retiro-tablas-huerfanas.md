# Retiro de tablas huérfanas

## Objective

Drop the two tables proven unused or redundant (`conciliacion_lotes`, `movimientos_bancarios`),
remove the code that kept a phantom dependency on them, and verify the system still behaves the same.

## Evidence gathered before the change

- `conciliacion_lotes`: 0 rows, 0 incoming FKs, 0 views/triggers, 0 references in code, migrations
  or tests. Its role is covered by `extractos_bancarios.lote_importacion`.
- `movimientos_bancarios`: 294 rows, identical to `extractos_bancarios` (same ids, same amounts, same
  states, 0 differences). Written only by the phase-16 backfill; the importer and the UI use
  `extractos_bancarios`.
- Every runtime access to `movimientos_bancarios` was already wrapped in feature detection plus
  `try/catch`, so its absence never broke a flow.

## Scope

1. Data backup of both tables.
2. Idempotent drop migration.
3. Remove `ConciliacionModel::obtenerMovimientosPendientes()` (dead, and unguarded).
4. Simplify `conciliarAbono()` and the conciliation service to the real source of truth.
5. Stop `migrate_concurrencia_conciliacion.php` from recreating the table.
6. Update the schema dump used for fresh installs.
7. Apply, then verify with the test suite and against the live database.

Out of scope: `historial_estado_abono` (write-only but still purged by the retention job),
`rate_limits`, `jwt_blacklist`, `refresh_tokens`, `notificaciones_cola`, `movimientos_cuenta`.

## Acceptance criteria

- [x] Both tables are gone from the live database.
- [x] A fresh install from the dump no longer creates them.
- [x] No code references them except historical migrations and verification artifacts.
- [x] The full test suite passes.
- [x] The conciliation read paths that the UI uses still return data.

## Result

### Data

- Before: `conciliacion_lotes` 0 rows; `movimientos_bancarios` 294 rows.
- After: both tables absent. `extractos_bancarios` keeps 294 rows, the same total amount
  (36.310.477,06) and the same date range (2026-08-02 .. 2026-08-31).
- `conciliacion_abono_pago`: 1 row, 0 orphan links.
- Backup: `storage/backups/retiro-tablas-20261023.sql` (gitignored).

### Code

| File | Change |
| --- | --- |
| `scripts/migrations_phase23_drop_tablas_huerfanas.sql` | New idempotent migration; refuses to drop `movimientos_bancarios` if rows exist only there. |
| `app/Models/ConciliacionModel.php` | Removed `obtenerMovimientosPendientes()`; `conciliarAbono()` now reserves the extract directly, without the feature-detection branch. |
| `app/Services/ConciliacionBancariaService.php` | Removed the `movimientos_bancarios` fallback and the second reconciliation-audit block. |
| `scripts/migrate_concurrencia_conciliacion.php` | No longer creates or backfills the retired table. |
| `database/condominio_cobranzas.sql` | Sections removed: 32 tables remain. |
| `tests/ConciliacionConcurrenciaTest.php` | Test schema and scenarios use `extractos_bancarios` only; removed the dual-write assertion. |
| `tests/ConciliacionTwinsCarreraTest.php` | Removed the cleanup statement for the dropped table. |
| `scripts/generate_er_diagram_docx.py` | Domain and relation map updated; regenerated the DOCX (32 tables, 51 relations). |
| `database/verificar_tablas_sin_uso.sql` | Rewritten as a post-retirement verification. |

## Verification

- `php -l` clean on every edited PHP file.
- Per-file suite run (the full runner exits early at `SecurityTest`, a pre-existing behaviour):
  **82 files, 701 tests, 3.570 assertions, 0 failures**.
- Conciliation module alone: 47 tests, 373 assertions, 0 failures.
- Live checks after the drop:
  - `obtenerLotes()` → 1 lote.
  - `obtenerExtractosPendientes()` → 86 movimientos.
  - `obtenerTodosPagosPendientes()` → 3 registros.
  - `ejecutarAuditoriaReconciliacion()` → 0 divergencias.
  - `conciliarAbono()` on a non-existent abono → `ABONO_NO_ENCONTRADO` / 404 (no mutation).

## Side effects observed

- The concurrency tests run against the live database (pre-existing): they appended 9 rows to
  `historial_estado_abono`. The verification probe added 1 rejected-attempt row. The table went from
  151 to 160 rows; it is append-only and purged by retention after 365 days.
- `migrations_phase16.sql` still creates `movimientos_bancarios`; phase 23 must run after it on a
  fresh install, which is the normal order.

## Next step

Nothing committed: the working tree carries unrelated changes. The drop is applied and verified.
