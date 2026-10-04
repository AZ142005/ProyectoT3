# Cumplimiento RF — Contexto y Plan de Correcciones

> **Documento vivo.** Se actualiza al cerrar cada pendiente: estado + bitácora + decisión/implementación.
> Proyecto: Sistema Web de Administración de Cobranza — Conjunto Residencial "Las Mesetas de Morón".
> Creado: 2026-10-03 (sesión de auditoría de cumplimiento RF).

## 1. Resumen de la auditoría

- Se auditaron los **34 RF evaluables** del SRS contra el código actual del repositorio (working tree, con evidencia archivo:línea).
- Excluidos por pedido: **RF 30 y RF 31**. El SRS no define **RF 7** (salta de RF 6 a RF 8).
- Resultado inicial: **25 CUMPLE · 8 PARCIAL · 1 NO CUMPLE**.
- Resultado vigente: **30 aceptados/cumplidos · 4 pendientes** (RF 9 resuelto el 2026-10-04; ver bitácora).

### Cumplidos (30)

RF 1, 3, 4, 5, 6, 8, 9, 10, 11, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 28, 29, 32, 34, 35, 37
+ RF 12 y RF 36 (obviados por decisión — ver sección 2).

### Pendientes (4 activos)

| RF | Tema | Estado |
|----|------|--------|
| RF 2 | Cruce de correo con padrón de propietarios (único NO CUMPLE) | ⏸ Pendiente |
| RF 3 | Doble verificación (2FA) sin UI de activación | ✅ Resuelto |
| RF 8 | Gestión de roles (alta/asignación + ENUM de BD) | ✅ Resuelto |
| RF 9 | Bandeja de solicitudes de cambio de datos | ✅ Resuelto |
| RF 13 | Previsualización de PDF al cargar | ⏸ Pendiente |
| RF 27 | Detección de montos discordantes en conciliación | ⏸ Pendiente |
| RF 33 | Servido de soportes de gastos (ruta rota → 404) | ⏸ Pendiente |

Leyenda de estados: ⏸ Pendiente · ⏳ Opciones presentadas · 🔧 En implementación · ✅ Resuelto.

## 2. Decisiones registradas

| Requisito | Decisión | Motivo |
|-----------|----------|--------|
| RF 12 — Estacionamientos | **Obviado (cumplido por alcance)** | La unidad habitacional asociada al vehículo se considera el "dueño"; no se requiere selector de propietario. |
| RF 36 — Notificaciones multicanal | **Obviado (limitación aceptada)** | Costo de la API de WhatsApp. El envío por email funciona (cola + worker); WhatsApp queda como enlace manual de click-to-chat. |

## 3. Orden de trabajo

RF 3 → RF 8 → RF 9 → RF 13 → RF 27 → RF 33 → RF 2.

(Al cerrar cada pendiente se pasa directamente al siguiente y se actualiza este documento.)

## 4. Detalle por pendiente

### RF 2 — Validación de identidad (cruce correo ↔ padrón de propietarios) — NO CUMPLE

- **Situación**: el registro solo valida formato y duplicados de cédula/email; no cruza el correo contra el padrón de propietarios. La aprobación del admin empareja por email **o** cédula sin exigir coincidencia entre ambos.
- **Evidencia**: `app/controllers/AuthController.php:367-375`; `app/models/SolicitudesRegistroModel.php:327-329`.
- **Propuesta preliminar**: validar el email contra `personas.email` (tipo propietario) de la unidad elegida; definir flujo cuando no coincide (revisión manual) y registrar el resultado en auditoría.
- **Decisión**: pendiente.

### RF 3 — Doble verificación (2FA) — PARCIAL

- **Situación**: el flujo 2FA por OTP está completo de punta a punta en el backend, pero **ninguna vista permite activarlo**: `two_factor_enabled` es 0 por defecto y `POST /perfil/2fa/toggle` (que exige la contraseña actual) no tiene invocador en la UI.
- **Cómo funciona hoy (verificado)**:
  1. Login con contraseña correcta → si `two_factor_enabled`, no se abre sesión: se guarda un estado temporal en sesión y se genera un OTP de 6 dígitos (`random_int`), hasheado con bcrypt en `auth_otp_tokens`, con expiración de 5 minutos; los códigos anteriores se invalidan.
  2. El código se envía al correo del usuario (`NotificationService::enviarOtp`).
  3. En `/auth/verificar-2fa` se ingresa el código: máximo 3 intentos por código, reenvío limitado (3 cada 5 min) y rate limit de verificación (10 cada 5 min); al validarlo se consume atómicamente y se inicia sesión según el rol.
- **Evidencia**: `app/controllers/AuthController.php:65-66,97-98,150-256,261+`; `app/models/OtpModel.php:15-111`; `app/controllers/PerfilController.php:233-287`; rutas en `public/index.php:221-225`; tests en `tests/Security2FATest.php`.
- **Opciones presentadas**:
  - **A. UI mínima (recomendada)**: formulario en `app/views/perfil/index.php` (contraseña actual + botón) que postee al toggle existente, mostrando estado activado/desactivado.
  - **B. A + guía de seguridad**: además, instrucciones breves de qué implica, estado visible y recomendación para roles privilegiados.
  - **C. Forzar 2FA para Administrador/Auditor**: política por rol (depende de RF 8). Mayor alcance.
- **Decisión e implementación (2026-10-03)**: Opción A acotada — solo para usuarios del sistema (admin/auditor), al final de su panel de perfil.
  - Vista `app/views/perfil/index.php`: tarjeta "Verificación en Dos Pasos (2FA)" al final del contenido de usuarios, con estado actual (activada/desactivada) y formulario de contraseña que envía al toggle existente.
  - Controlador `app/controllers/PerfilController.php`: `verPerfil` ahora carga también la fila de `usuarios` para el rol auditor (antes solo admin), necesaria para mostrar el estado.
  - Verificación: `php -l` limpio y suite completa de tests en verde (0 fallos). Commit: `c37c2c9`.
  - Ampliación (2026-10-03, a pedido del usuario): la tarjeta ahora se muestra **también a residentes** (al final de su panel); se añadió acceso "Mi Perfil" en el dashboard del residente y se corrigió un bug latente (`verPerfil` llamaba a un método inexistente `findById`; ahora usa `getActiveById`, que rompía el perfil de residentes). Commit: `7e8669a`.

### RF 8 — Gestión de roles — PARCIAL

- **Situación**: el RBAC funciona (Admin/Residente/Auditor) pero no hay UI/ruta para crear usuarios del sistema ni asignar rol; `usuarios.rol` es `ENUM('admin')` en el esquema versionado → un auditor no puede persistirse.
- **Evidencia**: `public/index.php:188-191`; `database/condominio_cobranzas.sql:1041`; `app/views/admin/usuarios/index.php`.
- **Propuesta preliminar**: migración `ENUM('admin','auditor')` + alta de usuarios del sistema con selector de rol y auditoría del cambio.
- **Decisión e implementación (2026-10-03)**: Opción B — alta de usuarios + cambio de rol + protección del último administrador activo.
  - Migración `scripts/migrate_rf8_rol_auditor.php` (idempotente; ejecutada en la BD local): `usuarios.rol` → `ENUM('admin','auditor')`.
  - `UsuarioAdminController`: `crearUsuario` (validaciones + unicidad usuario/email/cédula + bcrypt + estado 1) y `cambiarRol` (bloquea degradar al último admin activo); rutas POST `/admin/usuarios/crear` y `/admin/usuarios/cambiar-rol` (rol ADMIN; CSRF cubierto por middleware global de `public/index.php`).
  - Vista `app/views/admin/usuarios/index.php`: botón + modal "Nuevo Usuario" y selector compacto de rol solo en filas de usuarios del sistema.
  - Tests: `tests/UsuarioCrearRolTest.php` (4 tests) en verde. Commits: `6dfe34c`, `1cef01b`, `ab0b76e`.

### RF 9 — Solicitudes de cambio de datos — PARCIAL

- **Situación**: el residente crea solicitudes (quedan guardadas), pero no existe bandeja admin para aprobarlas/rechazarlas; `obtenerTodasAdmin` y `procesarSolicitud` están implementados sin llamadores.
- **Evidencia**: `app/models/SolicitudesModel.php:81-95,110-192`; `app/controllers/UsuarioAdminController.php:36-37`.
- **Propuesta preliminar**: exponer rutas admin (p. ej. `/admin/solicitudes-datos[...]`) + vista que use lo ya implementado.
- **Decisión e implementación (2026-10-04)**: Opción B — pestaña integrada "Cambios de Datos" + comparación visual "Actual → Solicitado" (se descartó la ruta antigua por la deprecación deliberada).
  - Modelo: `obtenerTodasAdmin` con filtro opcional por estado + `contarPendientes`.
  - Controlador: `index` carga la pestaña `cambios`; nueva acción `procesarSolicitudCambio` (rechazo exige motivo; aplica cambios y notifica vía `procesarSolicitud`).
  - Vista/partial: 3.ª pestaña en `app/views/admin/usuarios/index.php` (con badge) + partial nuevo `app/views/admin/solicitudes_cambio/index.php` (filtros, paginación, comparación por campo, aprobar/rechazar).
  - Tests: `tests/SolicitudesCambioDatosTest.php` (5 tests) en verde. Commit: `a0919bf`.

### RF 13 — Previsualización de PDF al cargar — PARCIAL

- **Situación**: las imágenes tienen previsualización inmediata; el PDF solo muestra icono y nombre.
- **Evidencia**: `app/views/pagos/residente/subir.php:157-160,382-393`.
- **Propuesta preliminar**: usar `<iframe>`/`<embed>` con el objectURL que ya se genera.
- **Decisión**: pendiente (opciones se detallarán al abordarlo).

### RF 27 — Detección de montos discordantes (conciliación) — PARCIAL

- **Situación**: los duplicados se detectan, pero un pago con referencia coincidente y monto distinto cae en `sin_coincidencia` sin alerta ("Monto dispar" no existe). Además la bandeja de inconsistencias pierde el pago y el motivo, y hay un bug con extracto null (filas con fecha 01/01/1970).
- **Evidencia**: `app/services/ConciliacionBancariaService.php:552-568,681-700`; `app/controllers/ConciliacionController.php:49-57`.
- **Propuesta preliminar**: marcar "Monto dispar" al detectar referencia coincidente con monto distinto; conservar `$match['pago']` y el motivo; proteger extracto null.
- **Decisión**: pendiente (opciones se detallarán al abordarlo).

### RF 33 — Servido de soportes de gastos — PARCIAL

- **Situación**: los soportes se guardan en `uploads/soportes` (raíz del proyecto) pero los enlaces apuntan a `/uploads/soportes/...`, que con docroot en `public/` resuelve a `public/uploads/soportes` → 404 sin proxy. Afecta la visual de RF 22 y RF 34.
- **Evidencia**: `app/config/config.php:6`; `app/views/residente/gastos.php:122`; `app/views/admin/gastos/index.php:273`; `public/comprobante-proxy.php` (patrón existente para comprobantes).
- **Propuesta preliminar**: proxy autenticado para soportes (patrón `comprobante-proxy.php`, validando rol/unidad) o alinear almacenamiento y URL.
- **Decisión**: pendiente (opciones se detallarán al abordarlo).

## 5. Bitácora

- **2026-10-03 — Sesión de auditoría**: auditoría de los 34 RF contra el código (informe 25/8/1 con evidencia). Decisiones: RF 12 obviado (unidad = dueño), RF 36 obviado (costo API WhatsApp). Creado este documento. Presentadas opciones de RF 3.
- **2026-10-03 — RF 3 cerrado (✅)**: implementada Opción A para usuarios del sistema (commit `c37c2c9`); suite completa en verde. Siguiente: opciones de RF 8 presentadas.
- **2026-10-03 — RF 3 ampliado**: 2FA también para residentes + enlace "Mi Perfil" en el dashboard del residente + fix `findById` → `getActiveById` en `verPerfil` (bug latente que rompía el perfil de residentes). Commit `7e8669a`.
- **2026-10-03 — RF 8 cerrado (✅)**: Opción B implementada (migración rol auditor + alta y cambio de rol + protección del último admin). Commits `6dfe34c`, `1cef01b`, `ab0b76e`. Migración ejecutada en la BD local.
- **2026-10-03 — Nota de verificación (runner)**: `php tests/run.php` termina con exit 0 pero **aborta en `SecurityTest`** (defecto preexistente documentado: `Security::validateCSRF` hace `exit` con token inválido) antes del RESUMEN, saltando las últimas clases. Desde ahora: las clases finales se verifican por separado con `--filter=`. Las áreas tocadas en los cierres (Perfil, Usuarios) se corrieron en verde con y sin filtro. Recomendación: ticket aparte para el runner/seguridad.
- **2026-10-04 — RF 9 cerrado (✅)**: Opción B implementada (pestaña "Cambios de Datos" con badge, comparación visual Actual → Solicitado, filtros por estado y aprobar/rechazar con motivo). Commit `a0919bf`. Filtros re-verificados por el orquestador: SolicitudesCambioDatos 5/5, Solicitudes 20/20, Usuario 29/29 en verde.

## 6. Evidencia clave de la auditoría

- Identidad/acceso: `app/controllers/AuthController.php`, `app/core/JWT.php`, `app/models/OtpModel.php`
- Unidades/activos: `app/controllers/EstructuraController.php`, `app/models/EstacionamientosModel.php`
- Pagos: `app/models/PagoModel.php`, `app/core/EstadoPago.php`, `app/services/LiquidacionPagoService.php`
- Conciliación: `app/services/ConciliacionBancariaService.php`, `app/controllers/ConciliacionController.php`
- Gastos/justificación: `app/services/GastoParserService.php`, `app/controllers/GastoController.php`
- Estadística/reportes: `app/models/ReportesModel.php`, `app/models/MovimientosModel.php`
- Notificaciones: `app/services/NotificationService.php`, `scripts/process_notifications.php`
