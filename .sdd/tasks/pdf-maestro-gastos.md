# Tareas: Motor de Justificación de Gastos y Eventos de Pago

- [x] **Tarea 1**: Crear migración de base de datos (`scripts/migrate_gastos_maestro.php` y `scripts/migrations_phase11.sql`) para agregar `pagina_soporte` y `extracto_texto`.
- [x] **Tarea 2**: Implementar `App\Services\GastoParserService` con soporte para extracción de flujos PDF por páginas, detección de renglones y categorización semántica.
- [x] **Tarea 3**: Actualizar `App\Models\GastosModel` para soportar `importarGastosMaestro()` y persistencia de extractos y páginas.
- [x] **Tarea 4**: Corregir bug de variable en `App\Models\PagoModel::aprobarLote()` y agregar notificación en estado `EN REVISIÓN`.
- [x] **Tarea 5**: Extender `App\Controllers\GastoController` con `cargarMaestro()`, `parsearMaestro()` e `importarMaestro()`.
- [x] **Tarea 6**: Registrar rutas en `public/index.php`.
- [x] **Tarea 7**: Crear vista `app/views/admin/gastos/cargar_maestro.php` y actualizar `app/views/admin/gastos/index.php`.
- [x] **Tarea 8**: Actualizar `app/views/residente/gastos.php` para incorporar el visor modal del extracto exacto (RF 34).
- [x] **Tarea 9**: Crear suite de pruebas `tests/GastosMaestroTest.php`.
- [x] **Tarea 10**: Ejecutar suites de verificación (`check_purity.php`, `audit_security.php`, `tests/run.php`) y validar cumplimiento integral.
