# Estética vista usuarios: correcciones residuales (usuarios + solicitudes)

## Objetivo (instrucción del usuario)
"Aplica las correcciones estéticas verificando que no hay pérdida de funcionalidad o de visualización de datos."
→ Aplicar las desviaciones detectadas en la auditoría read-only del 2026-10-03 sobre `app/views/admin/usuarios/index.php` y su tab `app/views/admin/solicitudes_registro/index.php`.

## Contexto
- Patrón dominante documentado en `odd/tasks/estetica-listas-admin.md` y `odd/tasks/botones-superior-derecha-conciliacion.md` (commits 3969d00 / aaad633 / 0d217b1, más 9aea2e3 y 1b1cdb8 posteriores sobre la vista).
- Auditoría (engram `auditoria/estetica-vista-usuarios`): 2 desviaciones altas (dropdown de fila único en `app/views/admin`; badges con `text-*-emphasis`), medias (botones de fila de solicitudes Bootstrap, banner de reset híbrido, radio/footer de tarjeta) y bajas (`text-slate-500`, cédula como badge, email sin token).
- Baseline de tests ANTES de los cambios: `php tests/run.php` sobre dc15d49 → exit 0 (todo verde).
- RDD: on (global). Runtime OpenCode no elegible para revisión inmutable (`assess` → unassessable, sin `next_transition`) → ruta aplicada: auto-verificación del writer + verificador independiente read-only + spot check del padre (mismo criterio que tareas previas del repo).
- TDD: no configurado (no hay artefacto sdd-init que lo active) → verificación funcional por las suites existentes. Runner: `php tests/run.php [--filter=NombreTest]`.
- Nota runner: un test deja un buffer de salida abierto y puede tragarse el texto del RESUMEN final; el exit code es autoritativo (`exit(1)` si falló algo, `exit(0)` si todo pasó).

## Alcance (solo estos 2 archivos)
- C1. Dropdown de acciones por fila (`usuarios` ~L257-338): gatillo → patrón botón-icono de fila de `comprobantes.php` (~L159, copiar literal); ítems estáticos `text-muted`→`text-on-surface-variant` (texto e iconos) y `text-dark`→`text-on-surface`. NO tocar `dropdown-item`, el contenedor ni los atributos JS de Bootstrap. Verificado: el JS del archivo NO está acoplado a estas clases (el `text-muted` toggled en L761/L801 pertenece al hint de cooldown del modal de eliminación; los modales están fuera de alcance).
- C2. Badges de rol (`usuarios` ~L224-231): quitar sufijo `-emphasis` (`text-primary` / `text-danger` / `text-info`), conservando `badge bg-*-subtle` y resto de clases.
- C3. Badges de estado (`usuarios` ~L239/243/247): adoptar el patrón exacto del tab hermano (`solicitudes_registro` ~L87-91, copiar literal): `badge bg-{success|danger|secondary} rounded-pill px-3 py-1.5`, mapeando el color 1:1 y conservando condicionales y contenido interno.
- C4. Cédula (`usuarios` ~L197): quitar wrapper badge → `font-mono text-xs`. Email (~L207): agregar `text-on-surface-variant` (conservar `text-truncate` y `style="max-width: 220px;"`).
- C5. Banner de reset de contraseña (`usuarios` ~L88-116): `border-start border-4 border-success` **se conserva** (el swap a `border-l-4 border-green-500` se probó y se revirtió: Bootstrap `.border-0` es `!important` y anulaba el render — ver Evidencia); `text-muted` → `text-on-surface-variant` (L94/101/108); `rounded-2` → `rounded-xl` (~L100); `fs-5` → `text-lg` y `bg-light` → `bg-background` (~L102); `fs-6` → `text-sm` (~L104/109); botón `btn btn-sm btn-outline-primary …` → patrón secundario compacto del botón "Limpiar" de `comprobantes.php` (~L86, copiar literal).
- C6. Tarjeta de usuarios (~L163): `rounded-4` → `rounded-3` (unifica con los hermanos de la misma página). Footer de paginación (~L362): `card-footer bg-white border-top py-3 px-4` → `p-3 border-top bg-light` (patrón de wrappers hermanos: `solicitudes_registro` ~L136 / `comunicados` ~L107).
- C7. `solicitudes_registro/index.php`: `text-slate-500` (~L97) → `text-on-surface-variant`; botones de fila (~L115/120) → chips verde/rojo de acciones de fila de `conciliacion/index.php` (~L260-273, copiar literal), preservando la separación entre ambos botones.

Fuera de alcance (se conservan tal cual): modales (patrón de casa compartido con el resto del admin), tabs, toolbar de filtros, tabla (ya canónica), niveles de título h2/h5, y `text-danger` de acciones destructivas.

## Restricciones
- Solo atributos `class` (y tokens de `style` si aplicara). CERO cambios en: textos visibles, `<?= … ?>`, `id`, `name`, `href`, `onclick`, `data-*`, `type`, `<form>`, `csrf_field()`, condicionales PHP, JS.
- Preservar strings/atributos asertados por `UsuariosSolicitudesTabsTest`: `usuariosTabs`, `tab-usuarios-btn`, `tab-solicitudes-btn`, `tab-usuarios`, `tab-solicitudes`, `Solicitudes de Registro`, `admin/solicitudes_registro/index`, `name="tab"`, `tab=solicitudes`, `$queryParams['tab'] = 'usuarios'`, `history.replaceState`, `csrf_field()`, `$paginacionSolicitudes`, `$estadoSolicitud`, `$pendientesCount`, `abrirModalRechazoRegistro`, hrefs `/admin/solicitudes-registro/aprobar|rechazar`; y las negativas del partial (no `layouts/admin_sidebar.php`, no `components/flash_messages.php`, no `/admin/solicitudes-registro"`).
- Cero cambios en otros archivos. El writer NO commitea (commits del orquestador).

## Checklist
- [x] C1 aplicada (dropdown fila: gatillo + ítems)
- [x] C2 aplicada (badges rol sin -emphasis)
- [x] C3 aplicada (badges estado → pills hermanas)
- [x] C4 aplicada (cédula/email)
- [x] C5 aplicada (banner reset; borde conservado por conflicto `.border-0 !important` — ver Evidencia)
- [x] C6 aplicada (tarjeta + footer)
- [x] C7 aplicada (solicitudes: token + botones fila)
- [x] `php -l` 2/2 sin errores
- [x] `php tests/run.php` completo → exit 0 (cero fallos/errores)
- [x] Verificación independiente read-only (VERIFIED CON OBSERVACIONES; observación corregida)
- [x] Commit work-unit en `fix/estetica-usuarios-correcciones` (81d2f02)

## Verificación
- Writer: `php -l` en ambos archivos; suite completa (`php tests/run.php` → exit 0); auto-auditoría del `git diff` (solo atributos `class`; datos/atributos intactos). Reporte `<comando>: <resultado>`.
- Verificador independiente read-only: diff completo, clases nuevas iguales a los patrones literales copiados, preservación de `<?= … ?>`/ids/hrefs/handlers, ejecución de suites.
- Spot check del padre: `php -l` + suite filtrada `UsuariosSolicitudesTabsTest` + revisión del diff.
- Cierre: commit work-unit; push/PR = decisión del usuario.

## Evidencia (cierre 2026-10-03)
- C1-C7: aplicadas 7/7. Clases literales verificadas carácter a carácter contra referencias vivas (comprobantes L159/L86, conciliacion L260-273, solicitudes L86-92): IDENTICAL en gatillo, botón de banner, chips y pills; mapeo 1:1 en `bg-secondary`.
- `php -l` 2/2: "No syntax errors detected".
- Suite completa: `php tests/run.php` → exit 0 (baseline previa también verde). Filtrada `UsuariosSolicitudesTabsTest` → exit 0 (8 tests / 34 asserts ✅).
- Verificación independiente read-only: **VERIFIED CON OBSERVACIONES**. Diff 100% valores de class (incluidos 3 literales de `$rolBadgeClass`); funcionalidad y datos intactos (handlers, ids, csrf, include de paginación, `$queryParams`, condicionales); modales intactos.
  - Hallazgo adversario: `border-l-4 border-green-500` no renderizaba (Bootstrap `.border-0{border:0!important}` anula utilidades Tailwind sin `!important`; verificado headless contra los CDN reales). Corrección aplicada: borde revertido a `border-start border-4 border-success` (idioma de acentos KPI ya usado en el admin; render confirmado) — la línea del banner quedó byte-idéntica al estado previo.
- Spot check del padre: `php -l` 2/2 + `UsuariosSolicitudesTabsTest` exit 0 + confirmación de que la línea del banner no aparece en el diff.
- Commit work-unit: `81d2f02` en rama `fix/estetica-usuarios-correcciones` (2 vistas + este doc; 85 ins / 30 del). Push/PR: decisión del usuario.
- RDD: on (global). `assess` (working tree y post-commit base dc15d49) → `risk: high`, `unassessable` (untracked requiere declaración; runtime OpenCode sin elegibilidad de revisión inmutable en V2). Sin `next_transition` → ruta RDD-off aplicada: auto-verificación del writer + verificador independiente + spot check del padre.
