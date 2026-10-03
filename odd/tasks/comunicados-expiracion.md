# Comunicados con duración: selector (default 1 semana) y auto-eliminación al vencer

## Objetivo (instrucción del usuario)
"haz que al crear comunicados haya un selector de duración con un default de una semana, y al pasar la cantidad de tiempo se auto eliminen, luego agrega todo al main."

## Contexto
- Modal actual (post vista previa): `app/views/admin/comunicados/index.php` — form en `col-lg-7` con fila `row g-3` de Edificio + Urgencia (`col-md-6` x2), contenido y switch; preview en `col-lg-5`.
- `guardar()` (ComunicadoController L51-127): validaciones → rate limit → duplicado → `crearComunicado` → email opt-in. Strings asertados por `ComunicadosDuplicadosTest` (RateLimiter, orden duplicado<crear, flash exacto) — PRESERVAR.
- Reloj: escritura y lectura de comunicados usan reloj PHP (fix TZ previo). La expiración también.
- Sin scheduler en el repo → "auto eliminar" = soft-delete oportunista (`eliminarExpirados()`) al abrir el módulo admin + filtro de vencimiento en la lectura del residente (garantía inmediata de invisibilidad).
- Migraciones: patrón `scripts/migrate_faseNN_*.php` idempotente (information_schema) + gemelo `scripts/migrations_phaseNN.sql` (estilo phase18); dump canónico trackeado `database/condominio_cobranzas.sql` a actualizar.
- RDD: on; OpenCode unassessable → writer + verificador independiente + spot check. TDD no configurado → suites.

## Alcance (6 tareas, 8 archivos)
- T1. Migración y esquema:
  - NUEVO `scripts/migrate_fase19_comunicados_expiracion.php`: idempotente; agrega `fecha_expiracion DATETIME NULL DEFAULT NULL` a `comunicados` (chequeo information_schema antes del ALTER; mensaje claro si ya existe).
  - NUEVO `scripts/migrations_phase19.sql`: gemelo SQL idempotente (estilo de `migrations_phase18.sql`, con bloque dinámico que verifica la columna).
  - `database/condominio_cobranzas.sql`: en el `CREATE TABLE \`comunicados\`` agregar la línea `` `fecha_expiracion` datetime DEFAULT NULL,`` después de `fecha_publicacion`.
  - Ejecutar la migración contra la BD local (dos veces, para probar idempotencia).
- T2. `app/models/ComunicadosModel.php`:
  - `crearComunicado`: INSERT con columna `fecha_expiracion` (`:fecha_expiracion`; null si vacío/no provista).
  - `obtenerPorResidente`: filtro adicional `AND (c.fecha_expiracion IS NULL OR c.fecha_expiracion > :ahora_exp)` y `$params['ahora_exp'] = date('Y-m-d H:i:s')` (placeholder DISTINTO de `:ahora`; EMULATE_PREPARES=false).
  - NUEVO `eliminarExpirados(): int`: `UPDATE comunicados SET deleted_at = :marca WHERE deleted_at IS NULL AND fecha_expiracion IS NOT NULL AND fecha_expiracion <= :corte` (ambos parámetros = reloj PHP); retorna rowCount. Colocar al final de la clase (después de `softDelete`).
- T3. `app/controllers/ComunicadoController.php`:
  - `index()`: llamar `$comunicadosModel->eliminarExpirados();` antes de `obtenerTodosAdmin` (auto-eliminación al abrir el módulo).
  - `guardar()`: tras `$enviarEmail = ...` parsear `duracion_dias` (whitelist `[0,1,3,7,14,30]`; ausente o inválido → 7; 0 → sin vencimiento), calcular `$fechaExpiracion = $dias > 0 ? date('Y-m-d H:i:s', time() + $dias * 86400) : null` y pasar `'fecha_expiracion' => $fechaExpiracion` en el array de `crearComunicado`. SIN alterar orden ni strings existentes (rate limit, duplicado, flash, try/catch).
- T4. `app/views/admin/comunicados/index.php` (modal): convertir la fila Edificio/Urgencia de `col-md-6` x2 a `col-md-4` x3 y agregar tercera columna:
  - Label: `Duración en Cartelera <span class="text-danger">*</span>` con las clases de label del modal.
  - `<select name="duracion_dias" required class="...clases canónicas de los otros selects del modal... cursor-pointer">` con opciones: `24 horas`(1), `3 días`(3), `1 semana (Predeterminado)`(7, **selected**), `2 semanas`(14), `30 días`(30), `Sin vencimiento`(0).
- T5. Tests:
  - `tests/ComunicadosResidenteTest.php`: `testComunicadoExpiradoNoEsVisible` (fecha_expiracion = reloj PHP -1h → no visible), `testComunicadoConVencimientoFuturoEsVisible` (+24h → visible), `testEliminarExpiradosAplicaSoftDelete` (crear vencido; `eliminarExpirados()` → rowCount ≥ 1 y no visible), `testCrearComunicadoPersisteFechaExpiracion` (SELECT devuelve el valor exacto). Fechas de expiración ancladas al reloj PHP (no dbTime).
  - `tests/AdminComunicadosVistaTest.php`: `testSelectorDuracionConDefaultSemana` (contiene `name="duracion_dias"` y `value="7" selected`).
- T6. Verificación completa (abajo) + commit.

## Restricciones
- No tocar la preview ni el resto del modal salvo la fila/select nuevo; no tocar la tabla/historial.
- No romper tests existentes (ComunicadosDuplicadosTest, BehaviorTest, RbacAuthorizationTest, PaginacionEstandarTest, AdminComunicadosVistaTest, ComunicadosResidenteTest).
- Fechas de expiración SIEMPRE con reloj PHP (escritura y comparación), consistente con el fix TZ.
- Writer NO commitea. La migración SÍ se ejecuta contra la BD local (parte de la tarea).

## Verificación
- Writer: `php -l` de todos los .php tocados; `php scripts/migrate_fase19_comunicados_expiracion.php` dos veces (idempotencia); `php tests/run.php` → exit 0; filtros: `ComunicadosResidenteTest`, `AdminComunicadosVistaTest`, `ComunicadosDuplicadosTest`, `PaginacionEstandarTest`, `RbacAuthorizationTest`, `BehaviorTest`; clases fuera del run completo (defecto preexistente del runner): `SolicitudesRegistro`, `UsuarioAdmin`, `UsuariosSolicitudesTabs` → exit 0. Auto-auditoría del diff: acotado a los 8 archivos (+doc).
- Verificador independiente read-only: diff + esquema aplicado + adversarial (incl. falsificación de que un vencido NO se ve).
- Spot check del padre.
- Cierre: commit work-unit en `feat/comunicados-expiracion`; luego merge de TODA la sesión a main (esta rama + `feat/admin-comunicado-preview`) por instrucción explícita del usuario.

## Evidencia (se completa al cierre)
- T1-T6: pendiente
- php -l: pendiente
- Migración + idempotencia: pendiente
- Suites: pendiente
- Verificación independiente: pendiente
- Commit + merge a main: pendiente
