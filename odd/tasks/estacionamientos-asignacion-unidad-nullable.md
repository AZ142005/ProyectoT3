# Asignación de estacionamientos: modal sin `piso` y liberación con NULL

## Problema

1. El modal "Asignar Puesto" mostraba `Warning: Undefined array key "piso"` en cada
   opción. La vista usaba `$u['torre'] ?? 'Piso ' . $u['piso']`, pero
   `UnidadesModel::getActivas()` devuelve `edificio_nombre` y no tiene `piso`.
2. Liberar o reasignar un puesto fallaba con
   `SQLSTATE[23000] Duplicate entry '0' for key 'uk_estacionamiento_unidad'`.
   `estacionamientos.unidad_id` era `NOT NULL` + UNIQUE, pero el modelo libera con
   `NULL`; MySQL guardaba `0` y solo permitía una fila con ese valor.
3. El controlador ocultaba el motivo real y mostraba "Error al eliminar el puesto de
   estacionamiento" incluso al asignar.

## Solución

- Migración `scripts/migrate_estacionamientos_unidad_nullable.php`: hace
  `unidad_id` NULL-able, normaliza los `0` a `NULL` y conserva el índice único.
- `UnidadesModel::getActivas()` agrega `propietario_nombre` (JOIN a personas).
- La opción del modal muestra `Unidad/Apto <numero> (<Torre>) - <propietario>`.
- `EstacionamientoController::asignar()` muestra el mensaje de negocio real y separa
  `PDOException` para no filtrar SQL. `eliminar()` captura errores sin 500.
- `database/condominio_cobranzas.sql` actualiza la columna a `DEFAULT NULL`.
- Tests nuevos en `EstacionamientoTest`: liberar con NULL, reasignar, `getActivas` con
  edificio/propietario y modal sin `piso`.
- `BehaviorTest`: las aserciones de `v.deleted_at` aceptan el alias `v2.deleted_at`
  del refactor local (el filtro existe).

## Verificación

- `EstacionamientoTest`: 12 tests, 37 aserciones, 0 fallos.
- `EstacionamientosNumeroTest`: 4 tests, 14 aserciones, 0 fallos.
- Suite completa: 788 tests, 4455 aserciones, 0 fallos, 0 errores, 5 omitidos.
- `check_purity` ✅ y `audit_security` ✅.
- Migración idempotente: segunda ejecución no altera ni normaliza nada.

## Flujo de uso

Para mover un puesto de una unidad a otra: abrir el modal del puesto, elegir
"Sin Asignar (Liberar Puesto)" y guardar; luego abrir el puesto destino y asignar la
unidad del residente. Si la unidad ya tiene un puesto, el sistema lo informa y pide
liberar el actual primero.
