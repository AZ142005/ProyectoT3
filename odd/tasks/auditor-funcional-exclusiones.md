# Auditor funcional con exclusiones + admin sin cambio de rol propio

## Objetivo (instrucción del usuario)
1. "Haz que todo sea funcional para el auditor, a excepción de: nueva cuenta bancaria, edición de datos de cuentas autorizadas, agregar edificios y unidades, la pestaña de cambio de datos, y nuevo usuario (ni cambiar roles)."
2. "Si puedes, también haz que el administrador no tenga la opción de cambiar el rol de su propio usuario."

## Decisiones de diseño
- El rol `auditor` pasa de "solo lectura" a **operador funcional**: se elimina el bloqueo global de mutaciones (`RoleMiddleware`) y las acciones permitidas se habilitan en ruta + guard de controlador. Las exclusiones quedan **solo-admin** en ruta + guard (+ UI oculta para auditor).
- Excepciones exactas y su implementación (lectura literal del pedido):
  1. **Nueva cuenta bancaria + edición de datos de cuentas**: `POST /admin/cuentas-bancarias/guardar` sigue solo-admin; se ocultan para auditor los botones "Nueva Cuenta Bancaria" y "Editar". Toggle (activar/desactivar) y eliminar SÍ funcionales (no fueron excluidos).
  2. **Agregar edificios y unidades**: se ocultan "Agregar Edificio", "Registrar el primer edificio", "+ Crear Nueva Unidad" y "+ Crear primer edificio" para auditor; `guardarEdificio`/`guardarUnidad` permiten auditor SOLO en edición (`id > 0`) y bloquean creación (`id <= 0`) con Flash de error. Toggle de edificio/unidad SÍ funcional. Editar SÍ funcional (el pedido excluyó "agregar", no editar).
  3. **Pestaña de cambio de datos**: la pestaña sigue visible; se oculta para auditor el formulario de aprobar/rechazar dentro del partial (queda lista en modo lectura). `POST /admin/usuarios/procesar-solicitud-cambio` sigue solo-admin.
  4. **Nuevo usuario ni cambiar roles**: se oculta el botón "Nuevo Usuario" para auditor; el formulario de cambio de rol por fila se oculta para auditor y para la fila del propio admin (usuario actual). `POST /admin/usuarios/crear` y `POST /admin/usuarios/cambiar-rol` siguen solo-admin; `cambiarRol` agrega bloqueo de rol propio con Flash.
- **Rol propio del admin**: en `UsuarioAdminController::cambiarRol`, tras el check de "mismo rol", agregar: si `$id === Auth::id()` → Flash `No es posible cambiar el rol de su propio usuario.` + redirect, sin tocar la BD.
- Todo lo demás del admin queda funcional para auditor: pagos (cambiar estado, aprobar masivo), conciliación (importar/conciliar/conciliar-lote/verificar/rechazar + APIs de conciliación), gastos (guardar/eliminar/importar maestro/generar facturas), comunicados (guardar/eliminar), estacionamientos y vehículos (guardar/asignar/eliminar), solicitudes de registro (aprobar/rechazar), usuarios (reiniciar clave, actualizar datos, eliminar), respaldos (generar/descargar), reportes (enviar aviso de cobro), comprobante verificar POST.
- `RoleMiddleware` queda obsoleto: se elimina su llamada en `Router::dispatch` y se borra `app/core/RoleMiddleware.php` (sin referencias en código ni tests).

## Cambios
1. `app/core/Router.php` — quitar la línea `RoleMiddleware::handle();` (y su comentario).
2. Eliminar `app/core/RoleMiddleware.php`.
3. `public/index.php` — pasar a `[UserRole::ADMIN, UserRole::AUDITOR]` estas rutas (además de las GET ya habilitadas en la iteración anterior):
   POST `/admin/comprobante/verificar`; `any /admin/facturas/generar`; `/admin/estructura/edificio/guardar`, `/admin/estructura/edificio/toggle`, `/admin/estructura/unidad/guardar`, `/admin/estructura/unidad/toggle`; `/admin/estacionamientos/guardar|asignar|eliminar`, `/admin/vehiculos/guardar|eliminar`; `/admin/reportes/enviar-aviso-cobro`; `/admin/comunicados/guardar|eliminar`; `/admin/solicitudes-registro/aprobar|rechazar`; `/admin/usuarios/reiniciar-password|actualizar-datos|eliminar`; `/admin/conciliacion/importar|conciliar|conciliar-lote|verificar|rechazar`; `/admin/cuentas-bancarias/toggle|eliminar`; `/admin/gastos/parsear-maestro|importar-maestro|guardar|eliminar|generar-facturas`; `/api/conciliacion/conciliar`, `/api/v1/conciliacion/conciliar`; `/admin/respaldos/generar`, `/admin/respaldos/descargar/{id}`; `/pagos/cambiar-estado`, `/admin/pagos/aprobar-masivo`.
   Quedan SOLO-ADMIN: `/admin/cuentas-bancarias/guardar`, `/admin/usuarios/crear`, `/admin/usuarios/cambiar-rol`, `/admin/usuarios/procesar-solicitud-cambio` (+ login/logout sin cambio).
4. Controladores (guards a array con auditor, estilo del archivo): `AdminController::generarFacturas`; `PagoController::cambiarEstado|aprobarMasivo`; `ConciliacionController::importarExtracto|conciliarPago|conciliarLote|verificarPagoDirecto|rechazarPago`; `GastoController::guardar|eliminar|parsearMaestro|importarMaestro`; `ComunicadoController::guardar|eliminar`; `EstacionamientoController::guardar|asignar|eliminar|guardarVehiculo|eliminarVehiculo`; `EstructuraController::guardarEdificio|guardarUnidad` (+ bloqueo de creación para auditor) y `toggleEdificio|toggleUnidad`; `CuentaBancariaController::toggle|eliminar`; `UsuarioAdminController::reiniciarPassword|actualizarDatos|eliminar` (+ bloqueo de rol propio en `cambiarRol`); `SolicitudesRegistroController::aprobar|rechazar`; `ReporteController::enviarAvisoCobro`; `RespaldoController::generarManual|descargar`.
5. Vistas:
   - `admin/cuentas_bancarias/index.php`: ocultar botón "Nueva Cuenta Bancaria" y botones "Editar" para auditor.
   - `admin/estructura.php`: ocultar "Agregar Edificio", botón "Registrar el primer edificio", "+ Crear Nueva Unidad" y "+ Crear primer edificio" para auditor. Editar/toggle quedan.
   - `admin/usuarios/index.php`: ocultar botón "Nuevo Usuario" para auditor; formulario de rol oculto para auditor y para la fila propia (`tipo_entidad === 'usuario' && id !== Auth::id()`).
   - `admin/solicitudes_cambio/index.php`: para auditor, la celda de acciones de una solicitud pendiente muestra badge "Pendiente" en lugar del formulario aprobar/rechazar.
6. Tests:
   - `tests/RbacAuthorizationTest.php`: reemplazar el test de la iteración anterior (`testAdminGetRoutesAllowAuditorAndMutationsStayAdminOnly`) por `testAdminRoutesAllowAuditorExceptDocumentedExclusions`: toda ruta `/admin/*` con middleware de roles debe contener `UserRole::AUDITOR`, EXCEPTO `['/admin/cuentas-bancarias/guardar','/admin/usuarios/crear','/admin/usuarios/cambiar-rol','/admin/usuarios/procesar-solicitud-cambio']` que NO deben contenerlo; login/logout excluidos del scan; contador > 45.
   - `tests/UsuarioCrearRolTest.php`: nuevo `testCambiarRolBloqueaCambiarElPropioRol` (transacción + rollback; inserta admin temporal, sesión con ese id, POST `id` propio + `nuevo_rol=auditor` → Flash de error con "propio usuario", rol intacto).
   - `tests/EstructuraDirectorioEdificiosTest.php`: nuevo `testAuditorNoPuedeAgregarEdificioNiUnidad` (sesión auditor + controlador anónimo con redirect capturado; POST creación sin id → Flash de error y sin filas nuevas; espejo del patrón de UsuarioCrearRolTest).

## Restricciones funcionales
- Las exclusiones deben bloquearse en RUTA **y** en GUARD (defensa en profundidad).
- No tocar el portal residente, ni `/perfil` (el auditor sigue sin poder solicitar cambios de perfil), ni rutas de residente.
- Conservar mensajes y patrones existentes; mensajes nuevos en español neutro.
- `php -l` limpio en todos los archivos tocados; tests existentes verdes.

## Criterios de aceptación
1. Auditor puede ejecutar todas las acciones listadas en el punto 3 de "Cambios" (sin 403).
2. Auditor recibe 403/ruta bloqueada en las 4 excepciones; la creación de edificio/unidad queda bloqueada por guard interno con Flash.
3. UI: auditor no ve "Nueva Cuenta Bancaria", "Editar" (cuentas), "Agregar Edificio", "Crear Unidad", "Nuevo Usuario", formulario de rol, ni formularios de procesamiento de cambios.
4. Ningún admin puede cambiar su propio rol (UI oculta + backend bloquea).
5. Suites verdes: RbacAuthorizationTest, UsuarioCrearRolTest, EstructuraDirectorioEdificiosTest, además de las ya usadas (BehaviorTest, BalanceAgrupadoEdificiosTest, GastosFacturacionTabsTest, PagoDetalleNavegacionTest, AuthTest, RouterTest, CuentasBancariasTest, SolicitudesCambioDatosTest).

## Ruta de implementación
- Writer único (delegado). Verificación del writer + verificador independiente (read-only, adversarial: matriz de exclusiones, doble capa, rol propio, UI) + spot check del padre. Commit del orquestador directo en `main`, sin push.
- Skills: `work-unit-commits`. TDD no configurado; runner `php tests/run.php --filter=<Clase>`.

## Verificación
- Writer (delegado): `php -l` 21/21 (luego 3 más) sin errores; suites filtradas exit 0: RbacAuthorizationTest 8/221 ✅ (nuevo contrato audita 58 rutas + 4 exclusiones), UsuarioCrearRolTest 5/21 ✅, EstructuraDirectorioEdificiosTest 5/34 ✅, CuentasBancariasTest 4/29, SolicitudesCambioDatosTest 7/63, SolicitudesRegistroTest 7/47, EstacionamientoTest 9/27, GastosFacturacionTabsTest 5/30, BalanceAgrupadoEdificiosTest 9/47, PagoDetalleNavegacionTest 4/31, BehaviorTest 108/252, AuthTest 20/37, RouterTest 23/44. Verificación extra por render real (script temporal): auditor sin botones de creación excluidos; admin con ellos.
- Verificador independiente (read-only, adversarial): **VERIFIED CON OBSERVACIONES** — RoleMiddleware eliminado sin referencias; 58 rutas con auditor salvo las 4 exclusiones exactas (falsificado empíricamente con mutaciones en memoria del test); guards método por método correctos; bloqueo de creación en estructura con `id<=0` (edición permitida); rol propio bloqueado en orden correcto; UI de exclusiones oculta y editar/toggle intactos. Observaciones atendidas con corrección scoped: (1) `admin/gastos/index.php` ocultaba al auditor "Ingesta PDF Maestro"/"Nuevo Gasto"/"Generar Facturas" pese a rutas habilitadas → condicionales eliminados; (2) `pagos/detalle.php` mostraba botón de rechazo muerto al auditor → tarjeta "Flujo de Aprobación" + modal + JS ahora `$isAdmin || $isAuditor`; (3) etiqueta obsoleta "Auditor (solo lectura y fiscalización)" del modal de alta → "Auditor (fiscalización)". Corrección re-verificada por suites (PagoDetalle 31, ComprobanteFlujoAprobacion 26, GastosFacturacionTabs 30, Rbac 221, UsuariosSolicitudesTabs 34, todas exit 0).
- Spot check del padre: RbacAuthorizationTest 8/221 ✅ exit 0; diff quirúrgico revisado (26 archivos: 24 modificados + RoleMiddleware eliminado + tests).
- RDD: modo on (global); preflight nativo inoperable en OpenCode (`immutable_review_transport_unsupported`, registrado en la sesión). Ruta RDD-off aplicada: auto-verificación del writer + verificador independiente + spot check del padre.
- Residuales honestos: (a) mensaje de `PerfilController::solicitarCambio` para auditor ("solo lectura y fiscalización") quedó obsoleto — pendiente de pase de textos; (b) la tarjeta informativa del perfil del auditor ("solo lectura inmutable sobre libros y transacciones") también quedó desactualizada; (c) el auditor puede descargar respaldos completos de BD y toggle/eliminar cuentas (lectura literal del pedido — confirmar si se desea podar); (d) el test de rutas escanea líneas simples de `public/index.php`; (e) sin verificación en navegador.

## Entrega
- Commit `b92d747` (feature: 37 rutas + 12 controladores + 4 vistas + RoleMiddleware eliminado + 3 tests; 26 archivos, +257/−149) + commit de cierre `docs(odd)`. Directo en `main` (sin push, decisión del usuario).
