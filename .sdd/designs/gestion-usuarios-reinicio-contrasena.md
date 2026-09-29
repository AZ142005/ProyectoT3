# Diseño Técnico y Arquitectura: Gestión de Usuarios y Reinicio de Contraseña (change-008)

## 1. Arquitectura de Componentes (MVC Puro)

```
[ Router: public/index.php ]
        |
        +---> GET  /admin/usuarios                 ---> UsuarioAdminController::index()
        +---> POST /admin/usuarios/reiniciar-pass  ---> UsuarioAdminController::reiniciarPassword()
                     |                                       |
                     v                                       v
             [ UsuariosModel ]                       [ UsuariosModel / PersonasModel ]
             (Consulta UNION paginada                (update password hash + unblock)
              con filtros y orden)                           |
                     |                                       v
                     v                               [ Flash Session / UI Modal ]
             [ Vista: admin/usuarios/index.php ]
             (Bootstrap 5 Cards, Table,
              Pagination, Reset Modal)
```

---

## 2. Capa de Datos (Modelos)

### `UsuariosModel`
Se agregará el método `obtenerListadoUnificado(string $buscar = '', string $rol = '', int $pagina = 1, int $porPagina = 15): array`:
- Query `UNION ALL` unificando `personas` y `usuarios`:
  - `tipo_entidad`: `'persona'` | `'usuario'`
  - `id`: identificador primario
  - `cedula`: documento de identidad
  - `nombre_completo`: nombre y apellido concatenados
  - `email`: correo electrónico
  - `telefono`: número telefónico
  - `rol_clave`: `'residente'`, `'admin'`, `'auditor'`
  - `rol_texto`: etiqueta amigable para la vista
  - `detalle_ubicacion`: torre y apartamento o departamento de oficina
  - `estado`: `1` (activo) / `0` (inactivo)
  - `esta_bloqueado`: cálculo booleano según `bloqueado_hasta > NOW()`
  - `tiene_password`: booleano indicando si tiene contraseña configurada
- Soporte para cláusulas `WHERE` sobre la consulta unificada con parámetros preparados.
- Uso del método `paginate()` de `BaseModel` para obtener datos, totales y metadatos de paginación.

Se agregará el método `reiniciarPassword(int $id, string $passwordHash): bool`:
- Ejecuta `UPDATE usuarios SET password = :hash, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id`.

### `PersonasModel`
Se agregará el método `reiniciarPassword(int $id, string $passwordHash): bool`:
- Ejecuta `UPDATE personas SET password = :hash, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id`.

---

## 3. Capa de Controladores

### `UsuarioAdminController`
- Hereda de `App\Core\Controller`.
- `index()`:
  - Valida rol: `Auth::requireRole(UserRole::ADMIN)`.
  - Captura parámetros GET: `buscar`, `rol`, `page`.
  - Invoca `UsuariosModel::obtenerListadoUnificado()`.
  - Renderiza `admin/usuarios/index` con paginación, filtros y breadcrumbs.
- `reiniciarPassword()`:
  - Valida rol: `Auth::requireRole(UserRole::ADMIN)`.
  - Valida método POST y CSRF.
  - Parámetros POST: `tipo_entidad`, `id`, `modo_generacion` ('auto' o 'manual'), `password_manual`.
  - Si es auto: genera contraseña segura (ej. `bin2hex(random_bytes(4)) . '!A1'`).
  - Si es manual: valida formato con `validarPassword()`.
  - Aplica `password_hash($clave, PASSWORD_BCRYPT)`.
  - Llama a `reiniciarPassword()` en el modelo correspondiente.
  - Guarda en Flash session:
    `Flash::set('password_reiniciada', ['nombre' => $nombre, 'cedula' => $cedula, 'password' => $clave])`.
  - Redirige a `/admin/usuarios`.

---

## 4. Capa de Presentación (Vistas)

### `app/views/admin/usuarios/index.php`
- Layout admin Bootstrap 5 estándar.
- Barra superior con:
  - Buscador por texto con botón de submit y botón de limpiar.
  - Filtro select por rol (Todos, Residentes, Administradores, Auditores).
- Mensaje destacado / Alerta Flash si se acaba de resetear una clave:
  - Cuadro de advertencia verde/azul con la clave temporal visible y botón interactivo para copiar al portapapeles con feedback instantáneo.
- Tabla responsive:
  - Cédula / Documento
  - Nombre completo
  - Contacto (Email + Teléfono con badges)
  - Rol / Apartamento
  - Estado (Activo / Inactivo / Bloqueado)
  - Botón: "Reiniciar Contraseña" (abre modal pasando datos del usuario vía atributos `data-*`).
- Modal Bootstrap 5:
  - Datos del usuario objetivo.
  - Opción de autogenerar clave o escribir una manual.
  - Formulario POST apuntando a `/admin/usuarios/reiniciar-password` con `csrf_field()`.
- Paginación Bootstrap 5 estándar con preservación de filtros en la URL.

### `app/views/layouts/admin_sidebar.php`
- Inclusión del elemento:
  `['route' => 'usuarios', 'url' => '/admin/usuarios', 'icon' => 'group', 'label' => 'Usuarios']`.
