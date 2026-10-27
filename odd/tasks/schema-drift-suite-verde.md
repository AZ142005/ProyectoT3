# Esquema local y suite completa verde

## Objetivo
- Alinear la base local `condominio_cobranzas` con el dump canónico usando las migraciones idempotentes ya versionadas.
- Eliminar los bloqueos restantes de la suite que no son de negocio: límite global de 30s en CLI, `imagedestroy()` deprecado en PHP 8.5, aislamiento del test de auto-eliminación y dependencia de datos CART reales.
- Dejar `php tests/run.php` completamente verde sin cambiar el comportamiento funcional del sistema.

## Hallazgos
- Faltaban en la base local: tablas `cartas_deuda_envios`, `reportes_cartelera`, `deudas_manuales` y `purgas_log`; columnas `notificaciones_cola.adjuntos`, `backups_log.fecha_descarga` y `purgas_log.fecha_descarga`.
- Ya existen migraciones idempotentes para todo: `migrate_cartas_deuda_envios.php`, `migrate_fase21_reportes_cartelera.php`, `migrate_fase22_deudas_manuales.php`, `migrate_notificaciones_adjuntos.php`, `migrate_retencion_datos_schema.php` y `migrate_descarga_respaldo_schema.php`.
- `app/config/config.php:53` fija `max_execution_time=30`; la suite completa ahora supera ese límite y aborta en `UsuarioCrearRolTest::testCrearUsuarioAuditorPersisteConHashYEstadoActivo`.
- `app/Services/FileUploader.php` y `tests/FileUploaderCompresionTest.php` llaman `imagedestroy()`; PHP 8.5 lo marca deprecado y el runner convierte la deprecación en excepción.
- `tests/UsuarioAdminAccionesTest::testEliminarUsuarioAuditorDesactivaCuenta` usa una sesión `auditor` contra `UsuarioAdminController::eliminar()` (exige admin); el 403 intenta `http_response_code()` después de que el runner ya emitió salida.
- `tests/DeudaManualTest::testCarteleraIntactaYCountByPeriodSoloFAC` exige deudas `CART-%` reales de 10/2026; la base local no tiene ninguna. El test debe crear su propio fixture determinista.
- Tablas legacy `conciliacion_lotes` y `movimientos_bancarios` siguen en la base local pero no existen en el dump. No bloquean la suite y no se eliminan para no destruir datos sin autorización explícita.

## Tareas
- [x] **T1 — Aplicar migraciones de esquema local.** Ejecutadas las seis migraciones existentes. Resultado: 33 tablas; `purgas_log` y todas las columnas presentes.
- [x] **T2 — Runner y compatibilidad PHP 8.5.** `tests/run.php` usa `set_time_limit(0)` para la suite CLI; `FileUploader` y su test reemplazaron `imagedestroy()` por `unset()`; `tests/SchemaDriftTest.php` verifica tablas, columnas e índice de las migraciones.
- [x] **T3 — Aislamiento de tests.** El test de auto-eliminación usa sesión `admin` con el mismo id del objetivo (antes era auditor y moría en el 403); `DeudaManualTest` crea un fixture `CART-TEST-*` determinista en 10/2026 dentro de su transacción; `UsuariosModel::cambiarRolSeguro()` respeta transacciones ambientales (no abre/cierra una ajena).
- [x] **T4 — Suite completa verde.** `php tests/run.php`: **764 tests, 3.433 aserciones verdes, 0 fallos, 0 errores, 5 omitidos**, 31,7s, exit 0. Skips justificados: fixture PDF Dotwood ausente, datos de reimportación, 2 permisos POSIX en Windows y fixture EXIF JPEG. `check_purity`, `audit_security` y `php -l`: OK. Se limpiaron 3 usuarios residuales `del_aud_*` de corridas previas.

## Criterios de aceptación
1. Las tablas/columnas faltantes existen y `SchemaDriftTest` pasa.
2. `php tests/run.php` termina con `0 failed, 0 errors` (se permiten skips justificados y preexistentes).
3. No se cambia el comportamiento funcional; sólo compatibilidad PHP 8.5, aislamiento de tests y fixtures.
4. `check_purity`, `audit_security` y `php -l` siguen limpios.
5. `scratch/` sigue fuera de Git.

## Evidencia inicial
- Suite previa a los arreglos: 761 tests, 3.208 verdes, 34 fallos, 25 errores, 8 omitidos; abortada a los 30s.
- Migraciones aplicadas: `cartas_deuda_envios` ✔, `reportes_cartelera` ✔, `deudas_manuales` ✔, `notificaciones_cola.adjuntos` ✔, `purgas_log` ✔, `backups_log.fecha_descarga` ✔, `purgas_log.fecha_descarga` ✔.

## Notas de cierre
- El dump canónico ya contiene `notificaciones_cola.adjuntos` y `fecha_descarga`; la base local simplemente no tenía aplicadas las migraciones.
- Los 2 objetos extra de la base local se retiraron el 2026-10-27 con la migración phase23, previo backup verificado en `storage/backups/retiro-tablas-20261027-173516.sql` (restauración de prueba: 0 filas en `conciliacion_lotes` y 294 en `movimientos_bancarios`). El esquema local quedó con 32 tablas, idéntico al dump, y la suite siguió verde (764/3433/0/0/5). El backup queda en la carpeta ignorada y protegida.
- `SchemaDriftTest` falla si vuelve a faltar cualquiera de las tablas/columnas/índice migrados.

## Cierre
- Backup verificado, phase23 ejecutada, tablas legacy retiradas y suite verde sobre `main`.
- Esquema local: 32 tablas, alineado al dump canónico; `SchemaDriftTest` sigue pasando.
