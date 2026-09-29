# Especificación Funcional: Gestión de Usuarios y Reinicio de Contraseña (change-008)

## 1. Propósito
Permitir a los administradores del condominio consultar el listado integral de todos los usuarios registrados en el sistema (tanto residentes en `personas` como personal de sistema en `usuarios`), buscar y filtrar de manera ágil con paginación, y reiniciar contraseñas de acceso de forma segura cuando un usuario pierda sus credenciales o se encuentre bloqueado.

---

## 2. Requerimientos Funcionales

### RF-USR-01: Menú y Acceso
- El menú lateral administrativo (`admin_sidebar.php`) debe incluir el ítem **"Usuarios"** con icono `group` o `manage_accounts` en la ruta `/admin/usuarios`.
- La ruta debe estar protegida estrictamente por el rol `admin` (`Auth::requireRole(UserRole::ADMIN)`). Cualquier intento de acceso no autorizado debe redirigir al login o dashboard correspondiente.

### RF-USR-02: Listado Unificado y Filtros
- La vista `/admin/usuarios` debe mostrar una tabla unificada con:
  - **Identificador / Cédula**: Tipo + Número (e.g. V-12345678).
  - **Nombre y Apellido**: Nombre completo de la persona o usuario.
  - **Contacto**: Correo electrónico y teléfono (si aplica).
  - **Rol y Ubicación**: Rol del usuario (Residente Propietario / Inquilino, Administrador, Auditor) y apartamento/torre en el caso de residentes.
  - **Estado de Cuenta**: Badge indicando si está Activo, Inactivo, o Bloqueado temporalmente (por intentos fallidos de login).
  - **Acciones**: Botón de acción **"Reiniciar Contraseña"**.
- Debe disponer de un buscador textual que filtre concurrentemente por cédula, nombre, apellido o correo electrónico.
- Debe disponer de un filtro desplegable por rol: Todos, Residentes, Administradores, Auditores.

### RF-USR-03: Paginación
- El listado debe soportar paginación (15 registros por página por defecto).
- Debe persistir los parámetros de búsqueda (`buscar` y `rol`) en los enlaces de paginación (`?page=2&buscar=...&rol=...`).
- Debe mostrar el contador de resultados (e.g. "Mostrando 1-15 de 42 usuarios registrados").

### RF-USR-04: Reinicio Seguro de Contraseña
- Cada fila debe contener un botón **"Reiniciar Contraseña"** que abra un modal de confirmación.
- El administrador podrá:
  1. **Generación Automática (Recomendada)**: El sistema genera una contraseña aleatoria criptográficamente segura (10 caracteres, mayúsculas, minúsculas, dígitos y símbolos).
  2. **Contraseña Manual**: El administrador puede ingresar una contraseña específica que cumpla la política mínima del sistema (`validarPassword()`: mínimo 8 caracteres, al menos 1 letra y 1 número).
- Al procesar el reinicio vía POST con protección CSRF:
  - Se genera el hash con `password_hash($nuevaClave, PASSWORD_BCRYPT)`.
  - Se actualiza la contraseña en la tabla correspondiente (`personas` o `usuarios`).
  - Se reinician los contadores de seguridad: `intentos_fallidos = 0` y `bloqueado_hasta = NULL` (desbloqueando la cuenta de inmediato).
  - Se presenta la clave temporal en pantalla al administrador en una alerta destacada con botón "Copiar Contraseña" para que pueda entregársela al usuario.

---

## 3. Requerimientos No Funcionales y Seguridad
- **OWASP CSRF**: Todo formulario POST debe incluir `csrf_field()` y validarse vía `Security::validateCSRF()`.
- **OWASP XSS**: Toda salida en la vista debe escaparse mediante `e()`.
- **Inyección SQL**: Todas las consultas a la base de datos deben utilizar sentencias preparadas PDO con parámetros enlazados (`bindValue`).
- **Pureza MVC**: Cero código HTML en controladores o modelos; cero SQL en controladores o vistas.
- **Diseño UI**: Maquetación principal con componentes Bootstrap 5 (`.card`, `.table`, `.modal`, `.badge`, `.alert`, `.btn-primary`).
