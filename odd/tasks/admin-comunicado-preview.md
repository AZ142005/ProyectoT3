# Admin "Nuevo Comunicado": vista previa residente + correo OFF por defecto + coherencia estética

## Objetivo (instrucción del usuario)
"haz que la sección de nuevo comunicado se parezca a como se verá el comunicado para los usuarios, que el switch de enviar por correo esté en desactivado por default y asegurate que la estética del comunicado y de la sección de nuevo comunicado coincidan estéticamente con el resto del sistema."

## Contexto (estado actual)
- `app/views/admin/comunicados/index.php`: lista/historial ya convertido (Lote A, commit 3969d00). El modal "Nuevo Comunicado" (L114-172) es Bootstrap puro: labels `text-muted`, `form-control`/`form-select`, switch `form-check form-switch` con `checked` (L159), footer `btn btn-secondary`/`btn btn-primary`. Sin JS propio en la vista.
- Controller `ComunicadoController::guardar`: `$enviarEmail = !empty($_POST['enviar_email'])` → con el switch ausente NO envía correo. Quitar `checked` es suficiente (sin cambios de controller).
- Vista del residente (referencia visual del comunicado): `app/views/residente/dashboard.php` L64-100 + `app/views/residente/cartelera.php` (badges de urgencia idénticos; fecha `d/m/Y H:i`; badge edificio `text-primary bg-primary/10`; contenedor `bg-white rounded-2xl border border-outline-variant`).
- Tests existentes: `ComunicadosDuplicadosTest` (strings de `guardar`, no tocar), `BehaviorTest` (strip_tags/LIMIT 500, no tocar). Ningún test asserta el modal/checked (grep 0).

## Alcance (5 tareas)
- T1. Modal "Nuevo Comunicado" reestilizado a patrones de casa:
  - Labels: `fw-bold small text-on-surface-variant` (hoy `text-muted`).
  - Inputs/select/textarea: `w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors` (verificar contra los inputs de filtros admin, p. ej. `app/views/admin/usuarios/index.php`, y usar las clases canónicas vivas). Conservar `name`, `required`, `placeholder`, options e ids.
  - Switch de correo: contenedor `form-check form-switch p-3 bg-background rounded-xl border border-outline-variant`, label `fw-bold text-on-surface`; **QUITAR `checked`** (default OFF). Conservar `name="enviar_email" value="1" id="checkEnviarEmail"`.
  - Footer: "Cancelar" → R2 (`bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5`); "Publicar Comunicado" → R1 (`bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5` + icono `send`). Conservar `type="submit"`, `data-bs-dismiss`, form/method/action/csrf.
- T2. Vista previa en vivo del comunicado (como lo verá el residente), dentro del modal:
  - Layout: `row g-4` con el formulario en `col-lg-7` y la preview en `col-lg-5` (stack en móvil). No romper el modal Bootstrap ni el form.
  - Cabecera de la preview: "Vista previa para residentes" (texto pequeño `text-xs font-bold text-on-surface-variant uppercase tracking-wider`).
  - Tarjeta con los MISMOS estilos del comunicado del residente (copiar literales de `residente/dashboard.php`): contenedor `bg-white rounded-2xl border border-outline-variant p-5 shadow-sm`; fila: badge de urgencia (clases EXACTAS L76-82), fecha `dd/mm/yyyy HH:mm`, badge edificio `text-xs font-bold text-primary bg-primary/10 px-3 py-1 rounded-lg`; título `font-bold text-on-surface`; contenido `text-sm text-on-surface-variant mt-1` con `white-space: pre-wrap`.
  - JS al final de la vista (inline, patrón del proyecto): ids `previewComunicado` (wrapper), `previewTitulo`, `previewContenido`, `previewUrgencia`, `previewFecha`, `previewEdificio`. Listeners `input`/`change` sobre título, contenido, urgencia y edificio; inicializar también en `shown.bs.modal`. Urgencia → clases/texto exactos del residente (Urgente/Importante/Normal). Edificio: value vacío → "Todos los Edificios"; si no, el texto de la opción seleccionada. Fecha: `new Date()` formateada `dd/mm/yyyy HH:mm`. Contenido con `textContent` (nunca innerHTML).
- T3. Coherencia de tokens en las vistas del residente (solo empty states de "Sin avisos"): `text-slate-300` → `text-on-surface-variant/30` y `text-slate-500` → `text-on-surface-variant` en `cartelera.php` y `residente/dashboard.php`. Nada más.
- T4. Test nuevo `tests/AdminComunicadosVistaTest.php` (clase `Tests\AdminComunicadosVistaTest`; patrón source-check con `file_get_contents` como `PaginacionEstandarTest`):
  - Contiene `id="previewComunicado"` y el texto "Vista previa".
  - El input `enviar_email` NO tiene `checked` (regex `name="enviar_email"[^>]*\schecked` → assertFalse).
  - Contiene los patrones de botón R1 (`hover:bg-primary-hover`) y R2 (`hover:bg-slate-200`) del footer del modal.
- T5. Verificación: `php -l` de los archivos tocados; suite completa + filtros; las 4 clases que el runner no alcanza por su early-exit preexistente (SolicitudesRegistro, UsuarioAdmin, UsuarioAdminAcciones, UsuariosSolicitudesTabs) por separado; verificador independiente; commit.

## Restricciones
- Conservar: `name`, `required`, `id`, `data-bs-*`, `csrf_field()`, textos de options (edificios/urgencia), action/method, y la tabla/historial existente (no tocar salvo lo indicado).
- No tocar controladores ni modelos.
- No romper strings asertados por tests existentes (`ComunicadosDuplicadosTest`, `BehaviorTest`, `ComunicadosResidenteTest`).
- Contenido de la preview con `textContent` (sin HTML crudo).
- No commits del writer.

## Verificación
- Writer: `php -l`; `php tests/run.php` → exit 0; filtros: `--filter=AdminComunicadosVistaTest`, `--filter=ComunicadosDuplicadosTest`, `--filter=ComunicadosResidenteTest`, `--filter=PaginacionEstandarTest`, `--filter=RbacAuthorizationTest`, `--filter=BehaviorTest` → exit 0. Auto-auditoría del diff (acotado a 3 vistas + 1 test nuevo).
- Verificador independiente read-only: diff completo + ejecución de suites + adversarial.
- Spot check del padre.
- Cierre: commit work-unit en `feat/admin-comunicado-preview`; merge/push = decisión del usuario.

## Evidencia (cierre 2026-10-03)
- T1-T4 aplicadas: modal-xl con labels/inputs/switch a patrones de casa; footer R1 (icono send) + R2; preview en vivo `#previewComunicado` con estructura y clases literales del residente (verificadas por igualdad de string carácter a carácter); `enviar_email` sin `checked` (default OFF; `guardar` ya trata la ausencia como false); empty states del residente con tokens de casa.
- `php -l` 4/4 sin errores; `node --check` del JS inline OK; 0 usos de innerHTML (todo `textContent`).
- Suites (todas exit 0): run completo (muere en SecurityTest por defecto preexistente); filtros AdminComunicadosVista 3 tests/7 asserts, ComunicadosDuplicados 7, ComunicadosResidente 10, PaginacionEstandar 10, Rbac 7, Behavior 111; clases no alcanzadas por el run completo, por separado: SolicitudesRegistro 7, UsuarioAdmin 17, UsuariosSolicitudesTabs 8.
- Verificador independiente read-only: VERIFIED CON OBSERVACIONES (comparaciones carácter a carácter, preservación del form byte a byte, falsificaciones). Observaciones atendidas: aserción R1 acotada al footer del modal y regex del switch cubriendo `checked` antes/después de `name` (tests reforzados; re-run 3 tests / 7 asserts verde). Menores aceptadas: `bg-light` del modal-footer (idioma de modales de la casa) y `p-5` de la preview (prescrito en esta tarea).
- Spot check del padre: censo del modal (sin `checked`, ids de preview presentes), `php -l`, filtro reforzado.
- Commit: 861bbc3. Merge/push: decisión del usuario.
- RDD: on; assess high/unassessable (untracked + runtime V2 sin elegibilidad inmutable), sin `next_transition` → ruta RDD-off aplicada (writer + verificador independiente + spot check).
