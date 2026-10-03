# Endurecimiento anti-duplicados Fase 18 — cierre de huecos restantes

## Objetivo (instrucción del usuario)
Corregir TODOS los puntos flojos identificados en la verificación 2026-10-03, sin romper nada existente ("verificando que al hacerlo no quede afectado algo inesperado"). Directo en `main`, commit del orquestador.

## Estado verificado previo (2026-10-03)
- Fase 17 (comprobantes + rate limit + índice ref) aplicada y verde.
- Huecos confirmados contra BD viva: (1) import de extractos sin índice único → carrera; (2) gastos: falta `uk_gasto_factura_periodo` (declarada en `scripts/migrate_fase3.php:93`, ausente en BD), duplicados sin N° factura, re-ingesta PDF Maestro sin idempotencia; (3) comunicados sin guarda ni rate limit; (4) estacionamientos sin validación de número; (5) OCR sin rate limit y hash no comparado en flujos de pagos.
- Datos locales: **0 grupos duplicados** en gastos (F y S), extractos y comunicados → las migraciones pueden aplicar sin conflicto. Aun así, cada migración DEBE analizar duplicados previos y abortar con reporte si aparecen (comportamiento para servidor).

## WU1 — Extractos bancarios (conciliación)
1. Migración (idempotente, analiza antes): `ALTER TABLE extractos_bancarios ADD UNIQUE KEY uk_extracto_identidad (referencia_bancaria, fecha_movimiento, monto)`.
   - Análisis previo: grupos duplicados por ese triple; si >0 → abortar con reporte (no crear índice) y salir con mensaje claro.
2. `ConciliacionModel::insertarExtracto`: envolver el INSERT en try/catch `PDOException` y si `23000`/`1062` → `$duplicados++; continue;` (cierre de carrera). Mantener el SELECT-first actual para el conteo amigable.

## WU2 — Gastos
1. Migración:
   - **REVISADO (corrección post-revisión)**: el índice plano `uk_gasto_factura_periodo` quedó **descartado** — retenía filas soft-deleted y bloqueaba el re-registro con la misma factura/proveedor/período tras un borrado lógico (contradecía el pre-chequeo existente `deleted_at IS NULL`). La unicidad F+S se resuelve con la guarda unificada delete-aware:
     `dup_guard VARCHAR(191) GENERATED ALWAYS AS (IF(deleted_at IS NOT NULL, NULL, IF(nro_factura_proveedor IS NULL OR nro_factura_proveedor = '', CONCAT('S|', IFNULL(mes,0), '|', IFNULL(anio,0), '|', IFNULL(categoria_id,0), '|', IFNULL(monto_total,0), '|', IFNULL(fecha_gasto,'0000-00-00'), '|', LEFT(IFNULL(proveedor,''),45), '|', LEFT(descripcion,60), '|', tipo_gasto, '|', IFNULL(edificio_id,0)), CONCAT('F|', IFNULL(mes,0), '|', IFNULL(anio,0), '|', LEFT(nro_factura_proveedor,50), '|', LEFT(IFNULL(proveedor,''),45))))) STORED` + `UNIQUE KEY uk_gasto_dup_guard (dup_guard)`. La migración ELIMINA el índice plano si existiera (documentado en cabeceras de ambos scripts).
   - Análisis previo: grupos con COUNT>1 por la expresión unificada sobre `deleted_at IS NULL` (duplicados activos F y S) → abortar con reporte si >0.
   - Columna `soporte_hash CHAR(64) NULL` + índice `idx_gastos_soporte_hash`.
2. `GastosModel::crearGasto`: extender el pre-chequeo para el caso sin factura (mismos campos de la guarda S, `deleted_at IS NULL`, y `nro_factura_proveedor` vacío/NULL) → si existe, tratar como duplicado (mismo retorno/estilo que el chequeo F actual). Capturar `23000/1062` en el INSERT → duplicado (no throw).
3. `GastosModel::importarGastosMaestro`:
   - Recibir `soporteHash` (nuevo parámetro o en firma acordada con el controlador; ver WU2.4).
   - Pre-import: si `soporteHash` no vacío y existe gasto no borrado con mismo `soporte_hash` + `mes` + `anio` → retornar `['procesados'=>0, 'omitidos'=>count($items), 'duplicados'=>count($items), 'archivo_ya_importado'=>true]` sin insertar.
   - Guardar `soporte_hash` en cada fila; por fila, capturar `23000/1062` → `omitidos++` (carrera).
4. `GastoController::importarMaestro`: si `archivo_maestro` no vacío y el archivo existe en `UPLOADS_PATH.'/soportes/'` (usar `basename()`), calcular `hash_file('sha256', ...)` y pasarlo al modelo; si `archivo_ya_importado`, mensaje específico: "Este PDF ya fue importado para el período; se omitieron N renglones.".
   `GastoController::guardar`: si `crearGasto` devuelve false/duplicado → Flash danger "Ya existe un gasto con los mismos datos en el período (posible envío duplicado)." (conservar el mensaje de éxito actual).
5. Tests nuevos `tests/GastosDuplicadosTest.php`.

## WU3 — Comunicados
1. `ComunicadosModel::existeDuplicadoReciente($titulo, $contenido, $edificioId, $unidadId, $adminId, $ventanaMinutos = 10): bool` → `deleted_at IS NULL`, mismo admin/título/contenido/destinos (usar `<=>` null-safe), `fecha_publicacion >= :desde` (calcular `:desde` en PHP, no `INTERVAL :param`).
2. `ComunicadoController::guardar`: rate limit `RateLimiter::attempt('comunicado_'.$adminId, 10, 3600)` → si excede, Flash error + redirect (sin insertar). Antes de `crearComunicado`: si `existeDuplicadoReciente` → Flash info "Este comunicado ya fue publicado hace instantes; se evitó un duplicado." + redirect (sin insertar ni encolar correos).
3. Test nuevo `tests/ComunicadosDuplicadosTest.php` (modelo: duplicado reciente true; contenido distinto false; fuera de ventana false; borrado no cuenta; otro admin no bloquea).

## WU4 — Estacionamientos (puesto)
1. `EstacionamientosModel::numeroExists($numero, $excluirId = null): bool` → `numero = :n AND deleted_at IS NULL [AND id != :id]`.
2. `EstacionamientoController::guardar`: en create y update, si `numeroExists($numero, $id)` → Flash error "Ya existe un puesto con el número 'X'." + redirect (sin insertar/actualizar).
3. Test nuevo `tests/EstacionamientosNumeroTest.php` (duplicado bloqueado; excluirId permite el mismo en update; deleted no cuenta; número distinto ok). Fixtures: `unidad_id` NOT NULL → crear unidad temporal y limpiar.

## WU5 — OCR rate limit + hash cruzado
1. `PagoController::analizarComprobante` (~L498): añadir `RateLimiter::attempt('ocr_analisis_'.Auth::id(), 20, 60)`; si excede → responder JSON 429 (mismo patrón que el sibling `extraer`).
2. `PagoModel::crearPago`: mover `$archivoHash` antes de los chequeos y añadir, si hay hash:
   - vs `pagos`: `archivo_hash = :h AND estado != 'RECHAZADO' AND (deleted_at IS NULL OR estado = 'APROBADO')`.
   - vs `comprobantes_pago` (join facturas para unidad no necesaria; global por hash): `c.archivo_hash = :h AND c.estado != 'rechazado' AND c.deleted_at IS NULL`.
   → si hay hit: rollback + return false.
3. `ComprobantesModel::verificarDuplicado`: añadir chequeo cruzado vs `pagos` por `archivo_hash` (mismo criterio de vigencia de pagos) → criterio `'archivo_hash'`.
4. Test nuevo `tests/HashCruzadoDuplicadosTest.php` (pago con hash H bloquea comprobante con H y viceversa; RECHAZADO/rechazado liberan; hash distinto pasa; sin hash no bloquea).

## Migración local + entrega
- Aplicar las migraciones a la BD local (idempotentes; correr 2 veces para probar idempotencia) y verificar information_schema (índices/columnas nuevas).
- NO sincronizar el dump `database/condominio_cobranzas.sql` (queda para el equipo, como en fase 17).
- Sin commits por parte del writer (los hace el orquestador).

## Restricciones
- Conservar textos visibles en español, flujos, rutas, CSRF y nombres de campos existentes. Cambios acotados a: migraciones nuevas, `ConciliacionModel`, `GastosModel`, `GastoController`, `ComunicadosModel`, `ComunicadoController`, `EstacionamientosModel`, `EstacionamientoController`, `PagoModel`, `PagoController`, `ComprobantesModel` + tests nuevos.
- No tocar: flujos ya correctos (crearPago/crear comprobantes pre-existentes), `fecha` NOT NULL de gastos (comportamiento actual), modales/vistas salvo lo estrictamente necesario (este diseño NO requiere tocar vistas: el hash del PDF se calcula server-side del archivo ya guardado).
- `php -l` limpio en todos los archivos tocados.

## Fuera de alcance (documentado)
- Residual de concurrencia de gemelos legacy en aprobaciones (ventana teórica solo con datos previos a fase 17; requiere rediseño de orden de locks con riesgo de deadlock — no se incluye; los duplicados nuevos son imposibles por construcción).

## Criterios de aceptación
1. Cada guarda/fix implementado y cubierto por los tests nuevos + verificación empírica contra la BD local.
2. Cero regresiones: barrido COMPLETO de suites (todas las clases por separado con `php tests/run.php --filter=<Clase>`) con los mismos fallos preexistentes de entorno (si hay) y nada nuevo.
3. Migraciones idempotentes (corridas 2×) y con análisis previo de duplicados.
4. `php -l` limpio en todos los archivos tocados.

## Ruta de implementación
- Writer único (delegado); verificador independiente del padre (read-only) + spot check + barrido completo de suites. Commit(s) del orquestador directo en `main`.

## Corrección post-revisión (2026-10-03)
- Efecto inesperado detectado por el padre durante la revisión: el índice plano `uk_gasto_factura_periodo` bloqueaba re-registrar un gasto con la misma factura/proveedor/período tras un borrado lógico (la app sí lo permitía). Corregido con la guarda unificada delete-aware; la migración retira el índice plano si existía. Probado: duplicado activo → bloqueado (23000); re-registro tras soft-delete → OK.
- W1: mensaje del pago directo ahora es genérico (también cubre el bloqueo por hash de archivo). W2: el rate limit de comunicados se evalúa tras las validaciones (un envío inválido no consume intentos). S1: un débito duplicado en carrera ya no infla el contador de débitos. S3: `duplicados` del import maestro cuenta solo duplicados reales (las omisiones por datos inválidos ya no cuentan como duplicados).

## Verificación
- Migraciones aplicadas e idempotentes (corridas 2×); esquema vivo: `uk_extracto_identidad`, `uk_gasto_dup_guard` (F+S, delete-aware), `idx_gastos_soporte_hash`, `soporte_hash`; `uk_gasto_factura_periodo` ausente.
- `php -l`: 16/16 sin errores. Tests nuevos: GastosDuplicados 9/37✅, ComunicadosDuplicados 7/13✅, EstacionamientosNumero 4/14✅, HashCruzado 6/14✅, ExtractosDuplicados 4/9✅ (30 tests, 87 asserts, 0 fallos, 0 skips).
- Regresión: PagoDuplicados 10/54✅, ComprobantesDuplicados 6/30✅, ConciliacionTwinsCarrera 4/22✅, GastosMaestro 7/27✅, Estacionamiento 9/27✅, Behavior 111/260✅, PagoDirecto 11/53✅, ConciliacionTest 12/74✅.
- Barrido completo por clase (49 clases): 48 con resumen y 0 fallos/0 errores; `SecurityTest` sin resumen por diseño preexistente (exit en `Security::validateCSRF`). Cero residuos de fixtures en BD.
- Verificador independiente (read-only): VERIFIED — reprodujo de forma independiente que F y S liberan identidad con soft-delete y bloquean activos (23000/1062), predicates de hash exactos, migraciones no-op al re-ejecutar; sin regresiones. Sus hallazgos menores W1/W2/S1/S3 fueron corregidos y re-verificados.
- RDD: `assess` post-commit (OpenCode no elegible para revisión inmutable → ruta RDD-off con verificación independiente).

## Entrega
- Commits directos en `main`: `9fbdfdf` (migración fase 18), `69ce7f6` (extractos + gastos), `3b29e17` (comunicados + estacionamientos + hash/OCR + micro-fixes). Cierre de docs: este commit. Push: decisión del usuario.
