# Vehículos desde Mi Perfil con aprobación del administrador

## Objetivo

Permitir que el residente registre o actualice vehículos desde **Mi Perfil de Usuario**,
con la misma bandeja de revisión que usa la actualización de datos: el administrador
aprueba o rechaza desde **Usuarios y Solicitudes → Cambios de Datos**.

## Solución final (2026-10-29)

- **Registro simplificado**: solo se piden `tipo_vehiculo` y `color` como obligatorios;
  `marca` es opcional. Ya no se solicitan placa, modelo ni observaciones.
- **Tipos admitidos** (`VehiculosModel::TIPOS_VEHICULO`):
  - `pequeno` → Pequeño
  - `camioneta` → Camioneta (mediano)
  - `camion_f350` → Camión tipo F-350
- **Migración** `scripts/migrate_vehiculos_tipo_simplificado.php`:
  - Agrega `vehiculos.tipo_vehiculo ENUM('pequeno','camioneta','camion_f350') NULL`.
  - Hace `vehiculos.placa` nullable; la placa queda como dato heredado opcional.
- **Plaza de estacionamiento obligatoria para registrar**:
  - Solo se permite crear un vehículo si la unidad tiene al menos una fila activa en
    `estacionamientos` con su `unidad_id`.
  - Se valida en tres capas:
    - `EstacionamientoController::guardarVehiculo()` (registro administrativo).
    - `PerfilController::solicitarCambioVehiculo()` (alta desde Mi Perfil).
    - `SolicitudesModel::procesarSolicitudVehiculo()` (aprobación).
  - La actualización de un vehículo existente no exige una plaza nueva.
  - Si la unidad no tiene plaza, la vista muestra un aviso y oculta el alta; si ya
    existen vehículos, permite solicitar su actualización.
- **Modelo**:
  - `crearVehiculo()` valida tipo/color, acepta marca opcional y placa opcional.
  - `actualizarVehiculo()` actualiza tipo, marca y color; conserva la placa heredada.
  - `crearVehiculo()` asigna automáticamente la primera plaza activa de la unidad.
- **Solicitudes**:
  - Payload nuevo: `tipo_solicitud=vehiculo`, `accion_vehiculo`, `vehiculo_id`
    (solo actualización), `vehiculo_tipo`, `vehiculo_marca`, `vehiculo_color`.
  - La lista blanca del JSON ya no acepta placa/modelo/observaciones.
  - Al aprobar un alta sin plaza, la transacción falla y la solicitud queda pendiente.
- **Vistas**:
  - Perfil: listado por tipo/marca/color/puesto; formulario con tipo, color y marca;
    prellenado al elegir un vehículo existente.
  - Admin Estacionamientos: modal con tipo, color y marca; la unidad debe tener plaza.
  - Bandeja de Cambios: muestra tipo, marca y color de la solicitud.
- **Ruta**: `POST /perfil/solicitar-cambio-vehiculo`.

## Verificación

- `PerfilVehiculosTest`: 10 tests, 53 aserciones, 0 fallos.
- Suite completa: **804 tests, 4554 aserciones, 0 fallos, 0 errores, 5 omitidos**.
- `php scripts/check_purity.php` ✅
- `php scripts/audit_security.php` ✅

## Archivos

- `scripts/migrate_vehiculos_tipo_simplificado.php`
- `app/Models/VehiculosModel.php`
- `app/Models/SolicitudesModel.php`
- `app/Models/EstacionamientosModel.php`
- `app/Core/helpers.php` (`etiquetaTipoVehiculo()`)
- `app/Controllers/PerfilController.php`
- `app/Controllers/EstacionamientoController.php`
- `public/index.php`
- `app/views/perfil/index.php`
- `app/views/admin/estacionamientos/index.php`
- `app/views/admin/solicitudes_cambio/index.php`
- `tests/PerfilVehiculosTest.php`

## Paquete de despliegue actualizado (2026-10-29)

- `database/condominio_cobranzas.sql`:
  - Bloque `vehiculos` actualizado con `tipo_vehiculo` (`ENUM('pequeno','camioneta','camion_f350')`)
    y `placa` nullable.
  - Importado correctamente en una base scratch: 32 tablas, 221 personas, 252 unidades,
    252 estacionamientos, 362 facturas, 0 vehículos y 0 pagos/movimientos/extractos/notificaciones.
- `htdocs.zip` y `database/htdocs.zip`:
  - 261 entradas, 2.300.892 bytes (2.19 MB), hashes idénticos.
  - Incluyen los modelos/controladores/vistas modificados y
    `scripts/migrate_vehiculos_tipo_simplificado.php`.
  - Conservan `.env`, `.htaccess`, placeholder y estructura de uploads/storage.
  - Excluyen los JSON con PII y los scripts one-off de carga/reversión.
  - Contenido de los archivos clave verificado contra el repo (hash con LF normalizado).

