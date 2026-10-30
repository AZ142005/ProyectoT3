# Modal de estacionamientos: cascada Edificio → Unidad sin residente

## Objetivo

Estandarizar el modal de asignación de puestos con el patrón de `/pago-directo`:
dos selectores dependientes (Edificio y Unidad), sin mostrar el nombre del residente
dentro del modal, y manteniendo el nombre en la tabla general.

## Cambios

- `EstacionamientoController::unidades()`: endpoint JSON que devuelve las unidades
  activas de un edificio (`id`, `numero`), sin datos del residente.
- Ruta `GET /admin/estacionamientos/unidades` (admin/auditor).
- Modal de asignación:
  - Selector **Edificio / Torre** con los edificios activos.
  - Selector **Unidad / Apartamento** deshabilitado; al elegir un edificio se cargan
    por `fetch` solo sus unidades y se habilita.
  - Las opciones muestran únicamente `Apto. <numero>`.
  - El edificio y la unidad actuales se preseleccionan al abrir el modal usando
    `$unidadEdificioMap` y `data-unidad-actual`; la carga se dispara en
    `shown.bs.modal`.
  - La opción "Sin Asignar (Liberar Puesto)" aparece solo si el puesto ya está
    asignado; permite liberarlo.
  - `onsubmit` valida que haya una unidad o "Sin Asignar" seleccionada para evitar
    liberar el puesto por accidente mientras el selector está deshabilitado.
- La tabla general ya mostraba `persona_nombre`; se conserva. El backend sigue
  recibiendo únicamente `unidad_id` y resuelve el residente desde la unidad.

## Verificación

- `EstacionamientoTest`: 12 tests, 42 aserciones, 0 fallos.
- Suite completa: 789 tests, 4475 aserciones, 0 fallos, 0 errores, 5 omitidos.
- `node --check` sobre el JavaScript del modal: OK.
- `check_purity` ✅ y `audit_security` ✅.
- `getActivasPorEdificio(360)` devuelve 42 unidades (1-01…1-66 según existan).

## Archivos

- `app/Controllers/EstacionamientoController.php`
- `public/index.php`
- `app/views/admin/estacionamientos/index.php`
- `tests/EstacionamientoTest.php`
