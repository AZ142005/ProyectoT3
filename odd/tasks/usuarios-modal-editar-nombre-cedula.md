# Usuarios: editar nombre y cédula en el modal de contacto

## Objetivo

Permitir correcciones rápidas de **nombre**, **apellido** y **cédula** desde el modal
"Actualizar Datos de Contacto" de Usuarios y Solicitudes, sin salir del listado.

## Cambios

- `UsuariosModel::obtenerListadoUnificado()` expone `persona_nombre` y
  `persona_apellido` en la rama de personas (y `NULL` en la de usuarios).
- El botón "Actualizar datos" pasa `data-nombre-pila`, `data-apellido` y
  `data-cedula` al modal.
- El modal agrega inputs editables:
  - **Nombre** (obligatorio).
  - **Apellido** (opcional).
  - **Cédula** (obligatoria).
  - Se conserva el resumen de solo lectura con el nombre completo y la cédula.
- `configurarModalActualizarDatos()` prellena los tres campos.
- `PersonasModel::actualizarDatosPersona()` actualiza nombre, apellido, cédula,
  teléfono y email en una sola sentencia.
- `PersonasModel::cedulaExistsNormalizada()` compara cédulas normalizadas
  (mayúsculas, sin puntos, guiones ni espacios) para evitar duplicados por formato.
- `UsuarioAdminController::actualizarDatos()` valida:
  - Nombre obligatorio, máximo 100 caracteres.
  - Cédula obligatoria. Si cambia respecto a la actual, exige 5-8 dígitos; si se
    conserva, admite los valores existentes (incluidos los de 9 dígitos y `SIN-`).
  - Unicidad normalizada de la cédula.
  - Teléfono ahora **opcional**: si se informa se valida el formato; si no, se guarda
    NULL. Esto evita bloquear correcciones de nombre/cédula en residentes sin teléfono
    cargado.
  - Email sigue obligatorio y único.

## Verificación

- `UsuarioAdminAccionesTest`: 11 tests, 62 aserciones, 0 fallos.
- `UsuarioAdminTest`: 11 tests, 48 aserciones, 0 fallos.
- Suite completa: 792 tests, 4500 aserciones, 0 fallos, 0 errores, 5 omitidos.
- `check_purity` ✅ y `audit_security` ✅.

## Archivos

- `app/Models/UsuariosModel.php`
- `app/Models/PersonasModel.php`
- `app/Controllers/UsuarioAdminController.php`
- `app/views/admin/usuarios/index.php`
- `tests/UsuarioAdminAccionesTest.php`
