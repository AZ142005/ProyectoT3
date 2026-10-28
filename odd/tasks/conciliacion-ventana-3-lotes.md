# Conciliación: ventana de 3 lotes por banco

## Decisión
- Implementar la variante **sin cambio de esquema**: los **3 lotes más recientes por banco**, ordenados por `MAX(fecha_carga)` y `MAX(id)`.
- Si la importación es mensual, equivale a 3 meses. No se agrega `periodo`; si luego se necesita calendario exacto, se hace una segunda etapa con una columna de período.
- No se modifica el esquema ni se agregan rutas.

## Configuración
- `CONCILIACION_VENTANA_LOTES` en `.env` (default 3, rango 1..12; un valor inválido cae a 3).
- Documentada en `.env.example` y usada por el controlador, el modelo y la UI.

## Reglas de negocio
1. **Ventana** = por banco, los 3 `lote_importacion` más recientes. Un lote pertenece a un banco.
2. `obtenerLotesVentana(int $limite = 3)` devuelve los lotes de la ventana (todos los bancos). `obtenerLotesVentanaPorBanco(string $banco, int $limite = 3)` para la importación.
3. `obtenerExtractosPendientesVentana(int $limite = 3)` devuelve los créditos disponibles de todos los lotes de la ventana, con las mismas exclusiones duras (estado, pago, vínculo 1:1).
4. `obtenerExtractosPendientes($lote)` devuelve el lote sólo si pertenece a la ventana; un lote fuera de la ventana devuelve `[]`. El método sigue existiendo para la selección explícita.
5. `obtenerLotes()` agrega `es_activo` = 1 si el lote está en la ventana de su banco (no sólo el último). Mantiene `periodo` no aplica; el orden sigue siendo el más reciente primero.
6. **Importación:**
   - Si el banco ya tiene 3 lotes, al entrar un lote nuevo con filas el más viejo de ese banco se expulsa.
   - Antes de insertar/adoptar, tomar el snapshot del lote que se va a expulsar (si existe).
   - Insertar/adoptar como hoy. Si el nuevo lote no aporta filas (`insertados + adoptados = 0`), la ventana no rota y no se guarda reporte.
   - Si hubo expulsión y filas nuevas, persistir el reporte histórico del lote expulsado.
   - Los primeros 3 lotes de un banco no generan reporte histórico.
   - La rotación se decide por filas nuevas **o adoptadas** (`$nuevos > 0`), pero se confirma contra la ventana posterior: un lote que sigue dentro de la ventana no se archiva.
   - El cruce automático se ejecuta sobre `obtenerExtractosPendientesVentana(3)`, no sólo el lote nuevo.
   - El flash informa la ventana y, si hubo, el lote archivado.
7. **Reporte actual** = reporte de la ventana (3 lotes). **Históricos** = reportes de lotes expulsados.
   - `ReporteConciliacionService::construirVentana(array $lotes, ?string $fechaCorte = null)`.
   - La sección de disponibles del PDF/CSV debe incluir la columna **Lote** (los rows ya traen `lote_importacion`).
   - `construir($lote)` sigue para el reporte histórico de un lote expulsado.
8. **UI:**
   - Selector: opción `Ventana activa (3 lotes)` (value="") por defecto; luego los lotes con `[en ventana]` o `[histórico]`.
   - Botones `Reporte de la ventana PDF` / `CSV`; el modal histórico se mantiene.
   - Aviso al seleccionar un lote fuera de la ventana: sus disponibles no están en la bandeja.
   - Nota en el modal de importación: se conservan los 3 últimos lotes por banco; al entrar el cuarto, el más antiguo se archiva.
9. **Compatibilidad:** `obtenerLoteActivo()` y `insertarExtracto()` mantienen su firma; no hay migraciones ni columnas nuevas.

## Archivos previstos
- `app/Models/ConciliacionModel.php`
- `app/Services/ReporteConciliacionService.php`
- `app/Controllers/ConciliacionController.php`
- `app/views/admin/conciliacion/index.php`
- `tests/ConciliacionVentanaTest.php` (nuevo)
- Ajustes en tests de conciliación/reportes afectados.

## Tareas
- [x] **T1 — Modelo ventana.** `obtenerLotesVentana`, `obtenerLotesVentanaPorBanco`, `obtenerExtractosPendientesVentana`, `obtenerExtractosPendientes` limitado a la ventana, `obtenerLotes` con `es_activo` de ventana. Tests de modelo.
- [x] **T2 — Reporte de ventana.** `construirVentana`, columna Lote en disponibles, metadata/nombre de archivo, tests de CSV/PDF/construcción.
- [x] **T3 — Importación/UI.** Snapshot y archivado del lote expulsado, cruce sobre la ventana, flash, `index()` con ventana por defecto, selector/botones/avisos, tests de integración.
- [x] **T4 — Suite completa y cierre.** `php tests/run.php` verde, `check_purity`, `audit_security`, `php -l`; commit y entrega.

## Criterios de aceptación
1. Con 4 lotes del mismo banco, los 3 más recientes son la ventana; el más viejo queda fuera y sus disponibles no aparecen en la bandeja ni en el cruce.
2. Los `CONCILIADO` de lotes fuera de la ventana se conservan.
3. Al entrar el cuarto lote se genera el reporte histórico del expulsado antes de la adopción; los primeros 3 no generan reporte; un no-op no rota.
4. El reporte actual cubre los 3 lotes; el CSV/PDF de disponibles identifica el lote.
5. La UI permite ver la ventana y los lotes históricos; el selector y los avisos son coherentes.
6. Sin cambios de esquema; suite completa verde.

## Evidencia inicial
- `main` en `03a92fa`, suite 764/3433/0/0/5, esquema local alineado al dump (32 tablas).
- Feature branch: `feat/conciliacion-ventana-3-lotes`.

## Evidencia final
- Ventana implementada y verificada: 771 tests / 3.514 aserciones antes de la config; 772 / 3.528 con `CONCILIACION_VENTANA_LOTES`; suite completa con 0 fallos, 0 errores y 5 omitidos.
- Configuración: `CONCILIACION_VENTANA_LOTES` en `.env` (default 3, rango 1..12, valor inválido -> 3), documentada en `.env.example`; usada por controlador, modelo y vista.
- Verificación independiente: PASS WITH FINDINGS; se corrigió el archivado falso (sólo se archiva el lote que realmente sale de la ventana posterior) y se ajustó la rotación para contar insertados + adoptados. Riesgos LOW aceptados: no hay test conductual de importación, los tests asumen ventana 3 y el fallback inválido de config no se ejecuta en runtime.
- Sin cambios de esquema ni rutas nuevas. Feature branch `feat/conciliacion-ventana-3-lotes`.
