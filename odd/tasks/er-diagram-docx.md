# Diagrama entidad-relación en DOCX

## Objective

Produce `database/Diagrama_ER_ProyectoT3.docx`: a shareable document with the entity-relationship
model of `condominio_cobranzas`, built from the authoritative schema dump and the application
models, not from a guess.

## Problem and why

The repository has the schema dump (`database/condominio_cobranzas.sql`) and 22 migration scripts,
but no human-readable data model. The dump declares only 18 physical foreign keys; most relations
live in `app/Models`. A diagram that reads only the dump would document a third of the real model.

## Scope

- Parse tables, columns, keys, indexes and physical foreign keys from the dump.
- Recover logical relations from column conventions, confirmed against `app/Models` and the
  phase 16 conciliation migration.
- Render the model as five domain diagrams in crow's foot notation.
- Generate the DOCX with cover, notation legend, diagrams, relation catalog, table catalog and a
  method note listing polymorphic references.

Out of scope: row counts, indexes beyond unique keys, reverse-engineering runtime state.

## Constraints

- No project dependencies may be added and no global Python packages installed. Generation runs from
  a throwaway virtualenv under `%TEMP%`.
- The working tree already holds unrelated in-progress changes, so this task creates files only.
  Branching, committing and delivery stay with the user.
- Diagrams must stay legible when printed: keys only inside boxes, full detail in the catalog.

## Acceptance criteria

- [x] `database/Diagrama_ER_ProyectoT3.docx` opens as a valid OOXML package.
- [x] Every one of the 34 tables appears in the diagram of its domain and in the catalog.
- [x] Every physical foreign key appears in the relation catalog marked as declared.
- [x] Every inferred relation is justified by evidence and marked as inferred.
- [x] Polymorphic references are documented instead of drawn as false relations.
- [x] Rendered diagrams contain no overlapping boxes or unreadable labels.

## Checks

- Structural readback: 7 sections, 6 images, 39 tables, 494 table rows, valid zip.
- Text extraction from the Word-exported PDF: 34/34 tables present, exactly 18 "Declarada" and 34
  "Inferida" cells, 52 relations.
- Visual inspection of all 23 rendered pages.
- Script fails loudly when a reference column is missing from its relation map.

## TDD

Not applicable. This is a one-off documentation generator, not application code; it is verified by
structural readback and visual inspection of the rendered output.

## Route

| Task | Route | Trigger evidence |
| --- | --- | --- |
| T1 schema extraction | inline | three files read inline were enough to fix the model |
| T2 relation recovery | inline | evidence already gathered from models and migrations |
| T3 diagram renderer | inline | single self-contained script, no unresolved design left |
| T4 DOCX assembly | inline | same script |
| T5 verification | inline | readback plus visual inspection |

## Tasks

- [x] T1 Extract schema structure (tables, columns, keys, FKs) from the dump.
- [x] T2 Recover logical relations and classify polymorphic columns.
- [x] T3 Implement domain layout and crow's foot renderer.
- [x] T4 Assemble the DOCX (cover, legend, diagrams, catalogs, method note).
- [x] T5 Verify package structure and inspect every rendered diagram.

## Result

- 34 tables, 52 relations: 18 declared in the engine, 34 inferred from the application layer.
- 5 polymorphic references documented instead of drawn: `auth_otp_tokens.usuario_id`,
  `jwt_blacklist.user_id`, `log_auditoria.registro_id`, `movimientos_cuenta.referencia_id`,
  `refresh_tokens.usuario_id`.
- 23 pages, 7 sections, 6 figures. Five domain diagrams; four fit their page at 8.7–9.8 pt.
- Anchor tables (grey) are repeated across domains so no relation is lost; the procedure is stated in
  the document.

## Verification evidence

- `python scripts/generate_er_diagram_docx.py` → 34 tables, 52 relations, 5 polymorphic, 7 sections.
- Word opened the file and exported 23 pages without errors.
- PDF text extraction: 34/34 tables, 18 declared + 34 inferred cells.

## Decisions

- Domain diagrams show keys only; the catalog carries every column, type and default.
- Relations that cross domains are drawn by repeating the target table as a grey reference node,
  rather than listing them outside the figure. Without this, `cobranzas`, `conciliacion` and
  `seguridad` were sparse and one table ended as a 1956 px vertical strip.
- A domain map figure was replaced by a cross-domain dependency matrix: the figure could not contain
  its own labels reliably, and the matrix states the same numbers exactly.
- Levels use the levelling (longest or shortest path) that keeps the most relations between adjacent
  columns. Edges that still skip a column are routed through inter-column channels and a bottom
  corridor, never across a table box.

## Next step

The document is a snapshot of the dump. Regenerate it after new migrations; the script stops if a
reference column is not classified.

## Update after the phase-23 retirement

The figures above describe the state before `conciliacion_lotes` and `movimientos_bancarios` were
retired. The document was regenerated afterwards and now covers **32 tables and 51 relations**
(18 declared, 33 inferred). `conciliacion_abono_pago.movimiento_id` and
`historial_estado_abono.movimiento_id` now point to `extractos_bancarios`, the single source of
truth. A parser bug was also fixed: `NOT NULL` inside a generated-column expression no longer marks
the column as non-nullable.
