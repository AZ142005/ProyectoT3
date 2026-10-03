# Comunicados: historial con duración + selects sin corte + "Avanzado" (unidades específicas multi-edificio)

## Objetivo (instrucción del usuario)
"todos los cambios van al main, haz que en el historial de comunicados del administrador se muestre la duración, los textos de los seleccionadores en nuevo comunicado se cortan, si puedes agrega un drill con 'extras', 'avanzado' o algún sinónimo que quede bien, para mostrar opciones que permitan seleccionar unidades específicas de uno o varios edificios para mandar los comunicados."

**Directo en main** (sin rama): preferencia explícita del usuario.

## Contexto
- Historial admin: 6 columnas (Título, Alcance/Destino, Urgencia, Publicado por, Fecha de Emisión, Acciones) — falta Duración (hoy solo se guarda `fecha_expiracion`, deducible).
- Modal: 3 selects en `col-md-4` dentro de `col-lg-7` → los textos largos ("-- Todos los Edificios (Global) --", "Urgente (Notificación al instante)") se cortan.
- Targeting por unidad: soportado en esquema/modelo (columna `unidad_id`) pero la UI jamás lo expuso; `$unidades` (getActivas: id, numero, cuota, edificio_id, edificio_nombre) ya se carga en `index()` sin uso. El pedido requiere **múltiples unidades de uno o varios edificios** → tabla de unión `comunicados_destinos`.
- `encolarComunicadoCorreo` usa `INNER JOIN unidades u ON u.propietario_id = p.id` (relación vieja) — al mandar a unidades específicas DEBE usar `p.unidad_id = u.id` (coherente con el portal; incluye inquilinos). Preservar strings `persona_id` y `LIMIT 500` (BehaviorTest).
- Migraciones: patrón fase (PHP idempotente + SQL gemelo + dump). Última: fase19. Nueva: fase20.
- RDD: on; OpenCode unassessable → writer + verificador independiente + spot check. TDD no configurado → suites.

## Alcance (8 tareas, 8-9 archivos)
- T1. Migración/schema:
  - NUEVO `scripts/migrate_fase20_comunicados_destinos.php` (idempotente): crea tabla `comunicados_destinos` (`id` PK AI, `comunicado_id` INT NOT NULL, `unidad_id` INT NOT NULL, KEY `idx_comdest_comunicado` (comunicado_id), KEY `idx_comdest_unidad` (unidad_id)) ENGINE=InnoDB utf8mb4_general_ci. Chequeo `SHOW TABLES LIKE` antes.
  - NUEVO `scripts/migrations_phase20.sql` (gemelo idempotente estilo fase19).
  - `database/condominio_cobranzas.sql`: agregar el CREATE TABLE `comunicados_destinos` (junto al bloque de comunicados).
  - Ejecutar la migración DOS veces contra la BD local (aplicada + no-op).
- T2. `app/models/ComunicadosModel.php`:
  - NUEVO `asignarDestinosUnidades(int $comunicadoId, array $unidadIds): void` — dedupe/intval; INSERT por unidad (`comunicado_id`, `unidad_id`).
  - `obtenerPorResidente`: dentro del `if ($unidadId)` agregar `OR EXISTS (SELECT 1 FROM comunicados_destinos cd WHERE cd.comunicado_id = c.id AND cd.unidad_id = :unidad_destino)` con `$params['unidad_destino']` = mismo `$unidadId` (placeholder DISTINTO; EMULATE_PREPARES=false).
  - `obtenerTodosAdmin`: agregar al SELECT `(SELECT COUNT(*) FROM comunicados_destinos cd WHERE cd.comunicado_id = c.id) AS destinos_count`.
- T3. `app/controllers/ComunicadoController.php`:
  - `guardar()`: parsear `$_POST['unidades']` (array; intval; únicos; >0). Si hay seleccionadas → `$edificioId = null; $unidadId = null;` (modo unidades tiene prioridad). Tras `crearComunicado`, llamar `asignarDestinosUnidades($comunicadoId, $unidadesDestino)`. Pasar `$unidadesDestino` a `encolarComunicadoCorreo`. NO alterar strings/orden asertados (RateLimiter, duplicado antes de crear, flashes, try/catch).
  - `encolarComunicadoCorreo(...)`: nuevo parámetro `array $unidadesDestino = []`; JOIN → `INNER JOIN unidades u ON p.unidad_id = u.id`; prioridad de filtros: unidadesDestino (`AND u.id IN (placeholders :ud0,:ud1...)`) > unidadId > edificioId; preservar `persona_id` y `LIMIT 500` en el cuerpo.
- T4. Vista admin — HISTORIAL (`app/views/admin/comunicados/index.php`):
  - Nueva columna **Duración** (entre Fecha de Emisión y Acciones): label deducida de `fecha_expiracion - fecha_publicacion` → [1=>'24 horas',3=>'3 días',7=>'1 semana',14=>'2 semanas',30=>'30 días', fallback "N días"]; `NULL` → 'Sin vencimiento'; con `title` = "Vence: dd/mm/yyyy HH:mm" si aplica. Actualizar `colspan` del empty state a 7.
  - Columna Alcance/Destino: nueva rama `elseif (!empty($c['destinos_count']))` → badge "N unidades específicas" (antes de la rama global).
- T5. Vista admin — MODAL (mismo archivo):
  - **Arreglar corte de textos**: reestructurar a filas `col-md-6` x2: (Destino + Urgencia) y (Duración sola). El fix es de ANCHO: conservar TODOS los textos de las opciones tal cual están (no recortar copy); ningún texto debe cortarse.
  - **Sección "Avanzado"**: botón toggle (`data-bs-toggle="collapse" data-bs-target="#bloqueDestinosAvanzados"`, patrón R2, icono `tune`) con texto **"Avanzado (unidades específicas)"**; panel colapsable con: hint ("Si seleccionás unidades, el comunicado se dirige solo a ellas (se ignora el destino general)."), y checklist scrollable (`max-h-64 overflow-y-auto`) agrupada por `edificio_nombre` con `<input type="checkbox" name="unidades[]" value="{id}">` y label "Apto {numero} — {edificio_nombre}".
  - Preview: al cambiar checkboxes → badge edificio de la preview muestra "{n} unidades específicas" (y restaura la lógica del select si no hay ninguna); mantener `textContent`.
- T6. Tests:
  - `tests/ComunicadosResidenteTest.php`: actualizar `limpiar()` para borrar primero `comunicados_destinos` de los comunicados del prefijo (JOIN delete). NUEVO `testComunicadoDirigidoAUnidadesEspecificasEsVisible`: crear (destinos null) + `asignarDestinosUnidades($id, [UNIDAD_A])` → visible con (EDIFICIO_B, UNIDAD_A); NO visible con (EDIFICIO_A, UNIDAD_B) ni con (null, null).
  - `tests/AdminComunicadosVistaTest.php`: NUEVO `testHistorialMuestraDuracionYAvanzadoDeUnidades` (contiene `Duración`, `name="unidades[]"`, `bloqueDestinosAvanzados`, `Avanzado`).
- T7. Verificación completa (abajo).
- T8. Commit directo en `main`.

## Restricciones
- No tocar la preview salvo lo del badge de unidades; no tocar la tabla fuera de lo indicado (sí: nueva columna + rama destino + colspan).
- Preservar strings de tests (ComunicadosDuplicadosTest, BehaviorTest `persona_id`/`LIMIT 500`, RbacAuthorizationTest, PaginacionEstandarTest).
- Reloj PHP para fechas; no reutilizar placeholders nombrados.
- Writer NO commitea; migración SÍ se ejecuta contra la BD local (2 veces).

## Verificación
- Writer: `php -l`; migración x2; `php tests/run.php` exit 0; filtros: ComunicadosResidenteTest, AdminComunicadosVistaTest, ComunicadosDuplicadosTest, PaginacionEstandarTest, RbacAuthorizationTest, BehaviorTest; clases fuera del run completo: SolicitudesRegistro, UsuarioAdmin, UsuariosSolicitudesTabs → exit 0. Auto-auditoría: diff acotado a los archivos del alcance.
- Verificador independiente read-only: schema aplicado; adversarial (falsificar visibilidad multi-unidad, limpieza sin huérfanos en `comunicados_destinos`, no corte de textos razonable por estructura, email JOIN); suites.
- Spot check del padre. Commit directo en `main`. Push: decisión del usuario.

## Evidencia (cierre 2026-10-03)
- T1-T6 aplicadas: migración fase20 (PHP + SQL gemelo + dump, +26 líneas exactas) ejecutada 2x (aplicada + no-op; `SHOW CREATE TABLE` verificado). Modelo con semántica crítica verificada por probes transaccionales: solo-destinos invisible para (null,null)/otra unidad, visible para la unidad destino; dedupe; `destinos_count`; count/datos consistentes. Controller: parseo endurecido (`is_scalar` tras observación del verificador) y correo con JOIN `personas.unidad_id` + placeholders únicos (réplica devolvió los residentes correctos, inquilino incluido). Vista renderizada con datos sintéticos (labels de duración 24 horas/3 días/1 semana/2 semanas/30 días + fallback + Sin vencimiento, badge N unidades, colspan 7) y opciones del modal byte-idénticas a HEAD (fix solo de ancho).
- `php -l` 6/6; `git diff --check` limpio (espacio final corregido); suites: full exit 0 + filtros (ComunicadosResidente 15, AdminComunicadosVista 5, ComunicadosDuplicados 7, PaginacionEstandar 10, Rbac 7, Behavior 111, SolicitudesRegistro 7, UsuarioAdmin 17, UsuariosSolicitudesTabs 8).
- Verificador independiente: **VERIFIED** (observaciones menores: array anidado en unidades → endurecido con `is_scalar`; duplicado en modo multi-unidad documentado como limitación conocida; tope `getActivas` 500 preexistente).
- Spot check del padre: `php -l` + filtros clave re-ejecutados tras las micro-correcciones.
- Commit: bc1e006 (feature, directo en main por instrucción del usuario) + commit docs de cierre. Push: pendiente (decisión del usuario).
- RDD: on; assess high/unassessable (untracked + runtime V2 sin elegibilidad inmutable), sin `next_transition` → ruta RDD-off aplicada (writer + verificador independiente + spot check).
