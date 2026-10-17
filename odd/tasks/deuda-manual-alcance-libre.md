# ODD task: Deuda manual con alcance libre (unidades/edificios/todo el condominio) y emisión con Enviar/Re-enviar Deuda

**Estado**: COMPLETADO Y VERIFICADO (2026-10-17) — 7 commits en `main` local, sin push. Verificación independiente read-only: APROBADO CON OBSERVACIONES (0 críticos) + 2 follow-ups aplicados.
**Fecha**: 2026-10-17
**Ruta**: delegated-direct — un writer (migración + modelo + controladores + vista + rutas + tests) + verificación funcional del orquestador + verificador independiente (tier high/unassessable como en features previas del repo).

## Objetivo

Que el apartado de gastos permita **cargar deuda manual** con el mismo procesamiento que la importación de cartelera (deuda directa por unidad, sin pasar por prorrateo):

1. **Alcance libre**: seleccionar cualquier unidad y/o edificio (combinables), o todo el condominio.
2. **Monto POR UNIDAD, sin repartir**: cada unidad afectada debe exactamente el monto elegido. Edificio seleccionado ⇒ todas sus unidades activas reciben el monto completo (no se divide entre ellas).
3. **Guardado por período** (mes/año): seleccionar un nuevo monto para una unidad **reemplaza** el anterior (upsert por unidad+período).
4. **Emisión con el botón Enviar/Re-enviar Deuda**: las unidades con deuda manual reciben su factura por el monto manual (Sustituye su cuota prorrateada del mes, decisión del usuario); las demás conservan el comportamiento actual (cuota prorrateada; cuota <= 0 ⇒ sin factura).
5. **Re-emitir actualiza, no duplica** (como el upsert de cartelera): las facturas del período se actualizan por `numero_factura` `FAC-…`; se protege lo ya pagado; las deudas `CART-…` de cartelera no se tocan y no bloquean la emisión.
6. **Solo deuda** (como cartelera): NO se crea fila en `gastos_comunes`, NO aparece en Rendición de Cuentas ni en egresos del dashboard. Sin campo "concepto" en el formulario (la vista de residente no tiene dónde mostrarlo — decisión del usuario 2026-10-17).

## Decisiones del usuario (2026-10-17, literales)

- "no repartas el monto, haz que si esta seleccionado un edificio para enviar la deuda, se aplique la cantidad elegida para todas las unidades del edificio"
- "haz que al seleccionar un nuevo monto para una unidad este reemplace al que ya estaba"
- "que se guarden los cambios para poder emitir deuda con el boton enviar/re enviar"
- "Sustituye su cuota del mes" (unidades con deuda manual)
- "Solo deuda manual… no pongas concepto ya que la vista de residente no tiene a donde ir, por ende no se vería"

## Diseño técnico

### Tabla nueva `deudas_manuales`

```sql
CREATE TABLE deudas_manuales (
  id INT(11) NOT NULL AUTO_INCREMENT,
  unidad_id INT(11) NOT NULL,
  mes TINYINT NOT NULL,
  anio SMALLINT NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  admin_id INT(11) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_deuda_manual_unidad_periodo (unidad_id, mes, anio),
  KEY idx_deuda_manual_periodo (mes, anio),
  CONSTRAINT fk_deuda_manual_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

- Migración: `scripts/migrate_fase22_deudas_manuales.php` (patrón `scripts/migrate_fase21_reportes_cartelera.php`: idempotente, `CREATE TABLE IF NOT EXISTS`, CLI) + `scripts/migrations_phase22.sql`.
- Aplicar en la BD local `condominio_cobranzas` con `C:/xampp/mysql/bin/mysql.exe -u root condominio_cobranzas < scripts/migrations_phase22.sql`.

### `app/Models/DeudasManualesModel.php` (nuevo)

- `guardarLote(int $mes, int $anio, array $unidadIds, float $monto, int $adminId): int` — upsert por (unidad, mes, anio): `ON DUPLICATE KEY UPDATE monto = VALUES(monto), admin_id = VALUES(admin_id), deleted_at = NULL, updated_at = NOW()`. Transacción + una fila de auditoría por lote (`log_auditoria`, `accion='deuda_manual'`, detalles: N unidades, Bs. X c/u, período).
- `obtenerPorPeriodo(int $mes, int $anio): array` — join unidades+edificios (numero, edificio_nombre), orden por edificio/unidad.
- `obtenerMapaPorPeriodo(int $mes, int $anio): array` — `unidad_id => monto` (para la emisión).
- `eliminar(int $unidadId, int $mes, int $anio): bool` — soft delete (deleted_at = NOW) + auditoría.
- `eliminarPeriodo(int $mes, int $anio): int` — soft delete masivo + auditoría (conteo).

### `app/Models/FacturasModel.php` — reescritura de `crearFacturasMasivas($unidades, $mes, $anio)`

- **Quitar el bloqueo global** del período (`countByPeriod > 0 ⇒ return false`). La protección anti-duplicado pasa a ser el upsert por `numero_factura`.
- **Override manual**: cargar `DeudasManualesModel::obtenerMapaPorPeriodo`; para cada unidad activa: si tiene monto manual ⇒ `monto_factura = monto manual` (sustituye la cuota prorrateada); si no ⇒ lógica actual (`calcularDistribucionCuotas`, fallback `cuota_mensual`; `<= 0` ⇒ skip).
- **Upsert por número** `FAC-{anio}-{mes}-{unidad_id}` (se conserva el esquema actual): `SELECT id, deleted_at, monto_total, monto_pagado FROM facturas WHERE numero_factura = :numero` (solo ese número; NUNCA por existencia genérica del período — las `CART-…` no cuentan).
  - Filas activas: `UPDATE facturas SET monto_total = :nuevo, saldo = GREATEST(0, :nuevo - monto_pagado), estado = (saldo > 0 ? 'pendiente' : 'pagada')` — conserva `monto_pagado` (no re-cobra lo pagado) y las fechas originales. Contar `actualizadas`.
  - Soft-deleted (`deleted_at` no NULL): **reactivar** (`deleted_at = NULL`) con los valores nuevos conservando `monto_pagado`. Contar `reactivadas`.
  - No existe: INSERT como hoy (saldo según saldo a favor, `estado` pendiente/pagada, remanente de saldo a favor ⇒ fila `ABONO-REM-…`). Contar `generadas`.
- **Saldo a favor**: la lógica actual aplica solo en la creación (una vez). En updates/reactivaciones NO se re-consume saldo a favor.
- **Libro mayor** (`MovimientosModel::registrarMovimiento`): al crear ⇒ `cargo_factura` como hoy (huella idempotente por factura+monto). Al actualizar/reactivar ⇒ `ajuste` por el delta (`nuevo_monto − monto_total_anterior`, con signo); si delta = 0 ⇒ sin movimiento. El ledger no debe inflarse ni duplicarse.
- **Stats** devueltos: `['generadas','actualizadas','reactivadas','con_saldo_favor','total_saldo_favor_usado']` (mantener claves existentes).
- `countByPeriod($mes, $anio)`: contar SOLO `numero_factura LIKE 'FAC-%' AND deleted_at IS NULL` (las CART no marcan el período como emitido). Es lo que alimenta "Deudas Creadas" y el estado Enviar/Re-enviar de la vista.

### `app/Controllers/GastoController.php`

- `index()`: cargar `deudasManuales` del período (`DeudasManualesModel::obtenerPorPeriodo`) y pasarlas a la vista; mantener `unidades`/`edificios` actuales (la vista agrupa unidades por edificio desde `unidades`).
- `guardarDeudaManual()` (POST `/admin/gastos/deuda-manual`): `Auth::requireRole('admin')` (auditor solo lectura, consistente con `guardar()`).
  - Entradas: `alcance` ∈ {`todo`,`seleccion`}, `edificios[]` ints, `unidades[]` ints, `monto_unidad` (0.01–999999.99), `mes`/`anio` (via `periodoDesdePost`).
  - Resolver set de unidades: `todo` ⇒ todas las activas (`UnidadesModel::getActivas`); `seleccion` ⇒ unión dedupe de (unidades de edificios elegidos) + (unidades elegidas), validando que sean activas. Si el set queda vacío ⇒ flash danger.
  - `guardarLote(...)`; flash success: "Deuda manual guardada: N unidades a Bs. X c/u. Se aplicará al enviar/re-enviar la deuda del período." Redirect a `/admin/gastos?mes=&anio=`.
- `eliminarDeudaManual()` (POST `/admin/gastos/deuda-manual/eliminar`): unidad+período, soft delete, flash.
- `eliminarDeudasManualesPeriodo()` (POST `/admin/gastos/deuda-manual/limpiar`): borra todas del período, flash con conteo.

### `app/Controllers/AdminController.php` — `generarFacturas()`

- Ya no bloquea si existen facturas del período: siempre llama a `crearFacturasMasivas` (upsert).
- Flash: "Se emitieron N deudas del mes {Mes} {Año} (M actualizadas, R reactivadas)." + saldo a favor como hoy.

### Rutas (`public/index.php`, zona del módulo Gastos)

```php
$router->post('/admin/gastos/deuda-manual', [GastoController::class, 'guardarDeudaManual'], [UserRole::ADMIN, UserRole::AUDITOR]);
$router->post('/admin/gastos/deuda-manual/eliminar', [GastoController::class, 'eliminarDeudaManual'], [UserRole::ADMIN, UserRole::AUDITOR]);
$router->post('/admin/gastos/deuda-manual/limpiar', [GastoController::class, 'eliminarDeudasManualesPeriodo'], [UserRole::ADMIN, UserRole::AUDITOR]);
```

### Vista `app/Views/admin/gastos/index.php`

- Modal `#modalNuevoGasto`: primer selector **"Tipo de registro"**:
  - `deuda_manual` (default): "Deuda manual por unidad — se emite con Enviar/Re-enviar Deuda".
  - `gasto`: "Gasto del condominio (común o individual)" — flujo actual intacto.
  - Campos de deuda manual (sin concepto, sin proveedor/categoría/soporte):
    - Alcance: radio "Todo el condominio" / "Selección libre".
    - Picker (solo en Selección libre): edificios (checkbox que marca/desmarca todas sus unidades) + unidades agrupadas por edificio con checkbox propio, contenedor con scroll y buscador simple; botones "Seleccionar todo"/"Limpiar selección".
    - "Monto por unidad (Bs.)", Mes, Año. Ayuda: "El monto se aplica completo a cada unidad seleccionada (no se divide entre ellas). Al guardar se reemplaza el monto anterior de esas unidades en el período."
    - Contador vivo: "N unidades seleccionadas · Total a facturar Bs. X".
    - POST a `/admin/gastos/deuda-manual`.
  - Campos del gasto clásico sin cambios (tipo comun/individual, categoría, proveedor, etc.), POST a `/admin/gastos/guardar` como hoy; JS `toggleEdificioModal` conservado dentro del modo gasto.
- Sección Emisión: tarjeta "Deudas manuales — {Mes} {Año}" con tabla (edificio, unidad, monto, quitar por fila), total, y botón "Limpiar período" (con confirmación). Si `countByPeriod > 0`: aviso "Hay facturas emitidas para este período: presiona Re-enviar Deuda para aplicar los cambios."
- Actualizar el texto del aviso "Deudas ya enviadas": re-enviar ya NO duplica: actualiza las facturas del mes. Mantener botones "Enviar Deuda"/"Re-enviar Deuda".

### Tests `tests/DeudaManualTest.php` (nuevo, patrón live-DB del repo)

1. Clases/métodos existen + tabla existe.
2. `guardarLote` guarda y **reemplaza** (misma unidad+período ⇒ 1 fila con monto nuevo).
3. Resolución por edificio: seleccionar edificio ⇒ todas sus unidades activas quedan con el monto completo (vía emisión).
4. Emisión: unidad con deuda manual ⇒ factura `FAC-…` por el monto exacto (sin reparto); unidad sin deuda manual ⇒ cuota normal; unidad con cuota 0 y sin deuda manual ⇒ sin factura.
5. Re-emisión: actualiza la misma factura (1 fila), sin acumular; conserva `monto_pagado` (`saldo = max(0, nuevo − pagado)`).
6. Soft-deleted: reactiva la factura con valores nuevos.
7. Facturas `CART-…` del período intactas y `countByPeriod` FAC-only.
- Limpieza defensiva en `finally` (patrón `SaldoFavorTest` con transacción/rollback o `CarteleraUpsertTest` con restauración — elegir el que aplique según los COMMIT de `crearFacturasMasivas`).

## Checks

- `C:/xampp/php/php.exe -l` en todos los archivos tocados.
- `C:/xampp/php/php.exe tests/run.php --filter=DeudaManual` (nuevo).
- `--filter=Gastos`, `--filter=SaldoFavor`, `--filter=Cartelera`, `--filter=Router`, `--filter=Model`, `--filter=GastosFacturacionTabs` (si rompe textos, actualizar aserciones).
- Suite completa: falla preexistente conocida `SecurityTest::testCsrfRejectsInvalidToken` (no tocar).
- Smoke E2E manual (orquestador): guardar deuda manual a un edificio de prueba → emitir con upsert → verificar facturas → limpiar datos de prueba.

## Commits

Work units en `main` local, **sin push** (patrón del repo), convencionales en español, sin atribución IA. `git add` SOLO archivos de la feature (el working tree trae ruido no relacionado: vendor/, capturas, docs borrados, htdocs.zip; NO incluirlos).

## Progreso y evidencia

- [x] **T1 — Migración + tabla + modelo** (`1067131`): `scripts/migrations_phase22.sql` + `scripts/migrate_fase22_deudas_manuales.php` (idempotente, probado con tabla existente y sin ella) + `app/Models/DeudasManualesModel.php` (upsert por lote con auditoría, listado por período, eliminar/eliminarPeriodo con soft delete). Tabla aplicada en `condominio_cobranzas` (UNIQUE unidad+mes+anio, FK CASCADE).
- [x] **T2 — FacturasModel** (`685fe3e`): `crearFacturasMasivas` con override manual (monto completo, sin reparto; sustituye la cuota de la unidad) + upsert por `FAC-…` (conserva pagado: `saldo = max(0, nuevo − pagado)`, reactiva soft-deleted, stats `generadas/actualizadas/reactivadas`), sin bloqueo global del período, `countByPeriod` FAC-only, ledger `cargo_factura` al crear + `ajuste` por delta al actualizar/reactivar.
- [x] **T3 — Controladores + rutas** (`23d1239`): `guardarDeudaManual`/`eliminarDeudaManual`/`eliminarDeudasManualesPeriodo` (requireRole admin, resolución de alcance `todo`/`seleccion` con dedupe de edificios+unidades, validaciones de monto/período), `AdminController::generarFacturas` sin bloqueo con flash de creadas/actualizadas/reactivadas; 3 rutas POST nuevas `[ADMIN, AUDITOR]`.
- [x] **T4 — Vista** (`e32a601`): selector "Tipo de registro" (deuda manual default | gasto clásico intacto), picker edificios+unidades con buscador/contador/todo/limpiar, tarjeta "Deudas manuales del período" con quitar/limpiar/aviso de re-envío, textos de emisión actualizados.
- [x] **T5 — Tests** (`daa4bab`, extendido en `99f6b60`): `tests/DeudaManualTest.php` 8 tests live-DB (upsert/reemplazo, alcance por edificio con monto completo, emisión sin reparto vs cuota, re-emisión sin acumular conservando pagado, reactivación con pagado, CART intactas, countByPeriod FAC-only, bajas lógicas). Sin ajustes necesarios en tests existentes.
- [x] **T6 — Verificación**: checks del writer (php -l ×8, filtros DeudaManual/Gastos/SaldoFavor/Cartelera/Router/Model/Tabs todos verdes) + spot-checks del orquestador (re-ejecución de filtros) + **verificador independiente read-only: APROBADO CON OBSERVACIONES (0 críticos)**; follow-ups aplicados: aviso de deudas de cartelera del período (`0325cee`) y test de reactivación con monto pagado (`99f6b60`). Render de vista probado en 4 escenarios (con/sin CART, con/sin FAC, variable ausente). BD sin residuos tras las corridas (CART 362 / 8.891.132,51 intactas).

### Commits (main local, sin push)

| Hash | Mensaje |
|---|---|
| `1067131` | feat(gastos): migracion fase22 y modelo de deudas manuales por unidad |
| `685fe3e` | feat(facturas): emision con upsert por numero, override de deuda manual y conteo FAC |
| `23d1239` | feat(gastos): controladores y rutas de deuda manual con re-emision sin bloqueo |
| `e32a601` | feat(gastos): vista con tipo de registro, picker de unidades y tarjeta de deudas manuales |
| `daa4bab` | test(gastos): cobertura live-db de deuda manual, upsert y conteo FAC-only |
| `0325cee` | feat(gastos): aviso de deudas de cartelera del periodo |
| `99f6b60` | test(gastos): reactivacion conserva monto pagado |

### Observaciones abiertas (verificación independiente)

- **W1 (decisión de producto pendiente)**: quitar la deuda manual de una unidad ya emitida y re-enviar NO anula su factura si la unidad queda sin cuota (la factura vieja persiste). Se puede re-ajustar cambiando el monto manual; la anulación automática necesita decisión del usuario.
- **W2**: el límite de 10 ajustes/día/unidad de `MovimientosModel` puede abortar el lote completo tras >10 re-emisiones con cambio el mismo día en una unidad (edge; el comentario del modelo dice "20", el código aplica 10).
- **S2**: alcance "todo el condominio" usa `UnidadesModel::getActivas()` (LIMIT 500) — no aplica hoy (218 unidades activas).
- **S3 (preexistente)**: el rol auditor puede disparar `generarFacturas` (comportamiento previo a esta feature).
- **Despliegue**: el hosting requiere ejecutar `scripts/migrations_phase22.sql` una vez, además de subir los archivos.
- **Smoke en navegador**: pendiente (usuario).
