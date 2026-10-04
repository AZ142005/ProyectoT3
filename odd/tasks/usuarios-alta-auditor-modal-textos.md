# Alta de usuarios solo Auditor + modal "Nuevo Usuario" a la receta + textos obsoletos

## Objetivo (instrucción del usuario)
1. "Quita los textos obsoletos" (los que dicen que el auditor es de solo lectura).
2. "Haz que la ventana emergente de nuevo usuario coincida estéticamente con sus homónimas".
3. "Haz que los usuarios creados por el admin solo puedan ser auditor".
   - Nota: la parte de "que no se verifique que ya exista…" fue retirada por el usuario ("estaba pensando en otra cosa"); las validaciones de duplicados se mantienen intactas.

## Cambios
1. Textos obsoletos (rol auditor ya no es solo lectura):
   - `app/controllers/PerfilController.php` L57: `'El rol de auditor es de solo lectura y fiscalización.'` → `'El rol de auditor no envía solicitudes de cambio de datos desde este perfil.'`
   - `app/views/perfil/index.php` L156: `Este perfil cuenta con facultades de supervisión y solo lectura inmutable sobre libros y transacciones.` → `Este perfil cuenta con facultades de fiscalización y gestión sobre los libros y transacciones del condominio.`
   - `app/views/auditor/dashboard.php` L37 comentario → `<!-- Banner Informativo de Fiscalización -->`; L41: `Este rol cuenta con permisos de solo consulta (lectura inmutable) sobre todos los libros contables, eventos de seguridad y comprobantes del condominio.` → `Cuenta con acceso de consulta y gestión sobre todos los módulos del sistema, con trazabilidad inmutable de cada acción registrada.` (conservar `<strong>Perfil de Fiscalización Activo:</strong>`).
   - `app/controllers/AuditorController.php` L13 docblock: `Dashboard general de fiscalización y solo lectura para el Auditor.` → `Dashboard general de fiscalización para el Auditor.`
   - NO tocar menciones de "inmutable" referidas a DATOS (log de auditoría, movimientos, estado de cuenta) ni los "(Solo Lectura)" de secciones del dashboard admin.
2. Modal "Nuevo Usuario" (`app/views/admin/usuarios/index.php`, `#modalCrearUsuario`, ~L443-541) a la receta de la casa (referencias exactas: `app/views/admin/estacionamientos/index.php` ~L190-275 y `app/views/admin/comunicados/index.php` ~L130-260):
   - `modal-content border-0 shadow-lg rounded-4 overflow-hidden` → `modal-content rounded-3 border-0 shadow-lg`.
   - Header → `modal-header bg-primary text-white py-3`; título `modal-title fw-bold flex-fill d-flex align-items-center gap-2`; conservar icono `person_add` y `btn-close btn-close-white`.
   - Párrafo guía → `text-xs text-on-surface-variant mb-4` y texto nuevo: `Se creará una cuenta de acceso con rol de Auditor. Los campos marcados con <span class="text-danger">*</span> son obligatorios.`
   - Labels → `form-label fw-bold small text-on-surface-variant` y los `*` como `<span class="text-danger">*</span>`.
   - Inputs/selects → clases exactas de la receta (copiar de estacionamientos/comunicados): `w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors` (+ `cursor-pointer` en selects). Conservar ids, names, maxlength, required, autocomplete, pattern/title/oninput del teléfono.
   - Helper de contraseña `text-muted` → `text-xs text-on-surface-variant`.
   - **Rol**: select con UNA sola opción `<option value="auditor" selected>Auditor (fiscalización)</option>` (se elimina la opción Administrador).
   - Footer → `modal-footer bg-light`; Cancelar con clases de la casa (`bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5`); submit `bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5` (conservar icono).
   - Comentario del modal: `<!-- Modal para Crear Nuevo Usuario (Auditor) -->`.
3. Alta solo Auditor (`app/controllers/UsuarioAdminController.php::crearUsuario`):
   - Docblock `(rol admin o auditor)` → `(rol auditor)`.
   - Validación L178: `if (!in_array($rol, ['admin', 'auditor'], true))` → `if ($rol !== 'auditor')`; mensaje `'El rol seleccionado no es válido.'` → `'El alta de usuarios está limitada al rol Auditor.'`.
   - `$rolTexto = $rol === 'admin' ? 'Administrador' : 'Auditor';` → `$rolTexto = 'Auditor';`.
   - Las 3 validaciones de duplicados (usuario, correo, cédula) se MANTIENEN intactas.
4. Tests (`tests/UsuarioCrearRolTest.php`): nuevo `testCrearUsuarioRechazaRolAdmin` — POST con `rol => 'admin'` y datos válidos → redirect `/admin/usuarios`, Flash de error con 'limitada al rol Auditor', sin fila nueva (contar antes/después por usuario de prueba).

## Restricciones
- Sin cambios de rutas/guards (la ruta `/admin/usuarios/crear` sigue solo-admin).
- Conservar ids/names/JS existentes; diff quirúrgico.
- `php -l` limpio; suites existentes verdes.

## Criterios de aceptación
1. Los 4 textos de rol actualizados; sin afirmaciones de "solo lectura" del rol.
2. El modal "Nuevo Usuario" tiene el mismo lenguaje visual que sus homónimos (contenedor, header, labels, inputs, footer) y solo ofrece rol Auditor.
3. `crearUsuario` rechaza rol distinto de auditor con mensaje claro y sin insertar.
4. Suites verdes: UsuarioCrearRolTest, UsuariosSolicitudesTabsTest, PerfilAdminTest, RbacAuthorizationTest, BehaviorTest, AuthTest.

## Ruta de implementación
- Writer único (delegado). Verificación del writer + readback/spot check del padre. Commit del orquestador directo en `main`, sin push.
- Skills: `work-unit-commits`. TDD no configurado; runner `php tests/run.php --filter=<Clase>`.

## Verificación
- Writer (delegado): `php -l` 7/7 sin errores; suites filtradas exit 0: UsuarioCrearRolTest 6/24 ✅ (incluye `testCrearUsuarioRechazaRolAdmin`), UsuariosSolicitudesTabsTest 8/34, PerfilAdminTest 5/25, RbacAuthorizationTest 8/221, BehaviorTest 108/252, AuthTest 20/37. Grep de control sobre `#modalCrearUsuario`: 0 `form-control`, 0 `form-select`, 0 `btn btn-primary/secondary`, 0 `text-dark/secondary/muted`; una sola `<option value="auditor">`; ids/required/csrf/pattern preservados. Barrido "solo lectura/solo consulta" del rol: 0 restantes; se conservan a propósito las menciones "inmutable" referidas a DATOS (log, movimientos).
- Spot check del padre: re-ejecución de UsuarioCrearRolTest (6/24 ✅ exit 0) + readback del diff completo (modal a la receta, restricción `$rol !== 'auditor'`, 4 textos). Sin verificador independiente en esta iteración por tratarse de copia/estilos + una validación con test dedicado.
- RDD: modo on (global); preflight nativo inoperable en OpenCode (`immutable_review_transport_unsupported`). Ruta off aplicada (auto-verificación del writer + spot check del padre).
- Residual honesto: sin verificación renderizada en navegador; el modal mantiene `modal-lg` (cambio de tamaño fuera de alcance).

## Entrega
- Commit `a6ccf66` (feature: 4 textos + modal a la receta + alta solo auditor + test; 7 archivos, +62/−34) + commit de cierre `docs(odd)`. Directo en `main` (sin push, decisión del usuario).
