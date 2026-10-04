# Usuarios: bloquear ascenso de auditores a admin + permitir eliminar auditores

## Objetivo (instrucción del usuario)
- "Que el admin no pueda ascender a los auditores" (auditor → admin bloqueado).
- "Pero sí eliminar el usuario que él u otro admin haya creado" (las cuentas de auditor creadas por cualquier admin se pueden eliminar desde el módulo de Usuarios).

## Contexto / hallazgos
- `UsuarioAdminController::cambiarRol` hoy bloquea: mismo rol, rol propio y degradar al último admin activo. Falta bloquear el ascenso auditor → admin (la UI hoy ofrece Admin/Auditor en el selector por fila).
- `UsuarioAdminController::eliminar` hoy bloquea TODA cuenta que no sea `persona`: "No está permitido eliminar cuentas administrativas ni usuarios del sistema." → los auditores no se pueden borrar.
- El listado unificado (`UsuariosModel::obtenerListadoUnificado`) filtra `estado = 1`, así que un soft-delete desaparece de la lista. El login usa `getActiveByEmail` (estado=1) → soft-delete = acceso revocado (cae al mensaje de "cuenta inactiva").
- FK crítica: `log_auditoria.admin_id` es `ON DELETE CASCADE` → un DELETE físico del usuario borraría su historial de auditoría. Por eso la eliminación de usuarios será **soft-delete** (mismo criterio que residentes en `PersonasModel::eliminarResidente`), preservando el log.
- La ruta `/admin/usuarios/eliminar` permite `[ADMIN, AUDITOR]` (iteración anterior); aquí solo se ajusta el comportamiento.

## Cambios
1. `app/controllers/UsuarioAdminController.php::cambiarRol` — tras el bloqueo de rol propio y antes del check de último admin:
```php
        // No se puede ascender a un auditor al rol de Administrador.
        if ($rolActual === 'auditor' && $nuevoRol === 'admin') {
            Flash::error('No es posible ascender a un auditor al rol de Administrador.');
            $this->redirect('/admin/usuarios');
            return;
        }
```
2. `app/models/UsuariosModel.php` — nuevo método `eliminarUsuario(int $userId): bool` (soft-delete transaccional + revocación de refresh tokens, espejo de `Auth::logout`):
```php
    /**
     * Elimina lógicamente una cuenta del sistema (soft-delete): desactiva el
     * acceso y revoca sus sesiones API, preservando el historial de auditoría.
     */
    public function eliminarUsuario(int $userId): bool {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                UPDATE usuarios
                SET estado = 0, intentos_fallidos = 0, bloqueado_hasta = NULL
                WHERE id = :id
            ");
            $stmt->execute(['id' => $userId]);

            $stmtTokens = $db->prepare("UPDATE refresh_tokens SET revocado = 1 WHERE usuario_id = :id AND revocado = 0");
            $stmtTokens->execute(['id' => $userId]);

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("[UsuariosModel::eliminarUsuario] Error: " . $e->getMessage());
            return false;
        }
    }
```
3. `app/controllers/UsuarioAdminController.php::eliminar` — reemplazar el bloqueo total por ramas:
   - `persona`: ruta actual intacta.
   - `usuario`: cargar con `UsuariosModel::getById`; orden de guards: (a) si no existe → error; (b) si `rol === 'admin'` → `Flash::error('No está permitido eliminar cuentas administrativas del sistema.')` (conservar este texto: hay test que lo aserta); (c) si `$id === Auth::id()` → `Flash::error('No es posible eliminar su propia cuenta.')`; (d) `eliminarUsuario($id)` → success `"El usuario {$nombre} ha sido eliminado correctamente."` / error.
   - otro tipo → `Flash::error('Identificador o tipo de cuenta no válido.')`.
   - Actualizar docblock del método (elimina residentes o usuarios auditor; bloquea administradores).
4. `app/views/admin/usuarios/index.php`:
   - Selector de rol por fila (~L269): agregar `&& $u['rol_clave'] !== 'auditor'` a la condición (sin control de rol para filas auditor = sin ascensos).
   - Dropdown "Eliminar" (~L374): condición pasa de `$esAdminCuenta || $u['tipo_entidad'] === 'usuario'` a solo `$esAdminCuenta` (los auditores ahora son eliminables); tooltip del botón deshabilitado → "No se permite eliminar cuentas de administrador".
   - Modal `#modalEliminarUsuario` (textos residente → dinámicos):
     - Header paso 1: `Confirmar Eliminación de <span id="tituloEliminarEntidad">Residente</span> (1/2)`.
     - `Residente a eliminar:` → `Cuenta a eliminar:`.
     - Envolver el bloque informativo residente del paso 1 en `<div id="textoEliminarResidente">...</div>` y agregar `<div id="textoEliminarUsuario" class="d-none">` con aviso para cuentas del sistema: "¿Está seguro de que desea eliminar esta cuenta del sistema? El acceso del usuario será revocado de inmediato y dejará de aparecer en el listado." + "Por integridad de la auditoría, el historial de acciones se conservará intacto, pero su acceso quedará revocado."
     - Paso 2: frase "eliminación y desvinculación definitiva de este residente" → "eliminación definitiva de esta cuenta".
     - JS `configurarModalEliminar`: tras setear campos, `const esUsuario = (tipo === 'usuario');` → actualizar `tituloEliminarEntidad` a 'Usuario'/'Residente' y togglear `d-none` en `textoEliminarResidente`/`textoEliminarUsuario`.
5. Tests:
   - `tests/UsuarioCrearRolTest.php`: `testCambiarRolBloqueaAscenderAuditor` (transacción + rollback; inserta auditor, POST `nuevo_rol=admin` → error con 'ascender', rol intacto).
   - `tests/UsuarioAdminAccionesTest.php`: `testEliminarUsuarioAuditorDesactivaCuenta` — (1) sesión con el propio auditor → POST eliminar → error 'propia cuenta'; (2) sesión admin id 1 → POST eliminar → success 'eliminado', `estado = 0`; limpieza del row. Actualizar el docblock del test existente `testBackendRestringeEliminarAdministradores` (sigue asertando el mensaje de cuentas administrativas).

## Restricciones
- Conservar el texto `'No está permitido eliminar cuentas administrativas'` (substring asertado por test existente) y el orden de guards indicado (admin antes que propia).
- NO tocar la ruta ni los guards de rol; no cambiar el flujo de eliminación de residentes.
- `php -l` limpio; suites existentes verdes.

## Criterios de aceptación
1. `cambiarRol` auditor → admin: bloqueado con mensaje y sin tocar la BD; la UI no ofrece control de rol a filas auditor.
2. Un admin (u otro admin/auditor con acceso) puede eliminar una cuenta de auditor: desaparece del listado, su acceso queda revocado (estado=0) y el log de auditoría se conserva (sin DELETE físico).
3. Eliminar administradores sigue bloqueado; la auto-eliminación muestra error propio.
4. Suites verdes: UsuarioCrearRolTest, UsuarioAdminAccionesTest, UsuariosSolicitudesTabsTest, RbacAuthorizationTest, BehaviorTest, AuthTest.

## Ruta de implementación
- Writer único (delegado). Verificación del writer + readback/spot check del padre. Commit del orquestador directo en `main`, sin push.
- Skills: `work-unit-commits`. TDD no configurado; runner `php tests/run.php --filter=<Clase>`.

## Verificación
- Writer (delegado): `php -l` 5/5 sin errores; suites filtradas exit 0: UsuarioCrearRolTest 7/27 ✅ (incluye `testCambiarRolBloqueaAscenderAuditor`), UsuarioAdminAccionesTest 8/37 ✅ (incluye `testEliminarUsuarioAuditorDesactivaCuenta`), UsuariosSolicitudesTabsTest 8/34, RbacAuthorizationTest 8/221, BehaviorTest 108/252, AuthTest 20/37. Verificación empírica del soft-delete (script temporal): `ok=true`, `estado=0`, `revocado=1`, `intentos=0`, `bloqueo=NULL`.
- Desviación necesaria revisada y aceptada: `testCambiarRolActualizaRol` asertaba el éxito del ascenso auditor→admin, ahora prohibido por el nuevo guard; se adaptó a la transición permitida admin→auditor (mantiene la cobertura de "el cambio de rol actualiza la fila").
- Spot check del padre: UsuarioCrearRolTest 7/27 y UsuarioAdminAccionesTest 8/37 (exit 0) + readback del diff (guards en orden correcto: existe → admin bloqueado → propia cuenta → soft-delete; texto 'No está permitido eliminar cuentas administrativas' conservado) + greps de vista (selector de rol sin filas auditor, botón Eliminar solo deshabilitado para admin, modal dinámico Residente/Usuario con `tituloEliminarEntidad`/`textoEliminarUsuario`).
- RDD: modo on (global); preflight nativo inoperable en OpenCode. Ruta off aplicada (auto-verificación del writer + spot check del padre).
- Residual honesto: el soft-delete conserva `usuario`/`email` ocupados (no reutilizables, consistente con residentes); "Actualizar datos" para auditores sigue deshabilitado (preexistente, fuera de alcance); sin verificación en navegador.

## Entrega
- Commit `c72ac6d` (feature: guard de ascenso + `UsuariosModel::eliminarUsuario` + ramas de eliminar + UI/modal dinámico + 2 tests; 5 archivos, +199/−40) + commit de cierre `docs(odd)`. Directo en `main` (sin push, decisión del usuario).
