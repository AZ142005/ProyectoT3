# Mensajes al usuario: "Rechazado" → "No Aprobado"

## Objetivo (decisión del usuario)
- Reemplazar la copy visible al usuario (residente/solicitante) que dice "Rechazado/rechazado" por **"No Aprobado / no aprobado"** (elección del usuario, 2026-10-01, tras el inventario propuesto).
- SOLO capa visible: el estado interno `rechazado`/`RECHAZADO` (BD, lógica, auditoría, transiciones) NO se toca.
- Botones/acciones del panel admin ("Rechazar", modales, flashes "rechazado exitosamente", filtros "Rechazados") quedan como están.
- Directo en `main`, sin ramas.

## Superficies y cambios (7 archivos)
1. `app/core/helpers.php` — `badgeEstado()`: etiqueta `'Rechazado'` → `'No Aprobado'` (color rojo e ícono `cancel` se conservan).
2. `app/models/PagoModel.php`:
   - SMS/WhatsApp (~L596): para `RECHAZADO` el texto pasa a "…no fue aprobado." (el resto de estados igual).
   - Asunto correo (~L613): `✖ Pago No Aprobado - Referencia X`.
   - Notificación bandeja (~L622): título "Pago No Aprobado"; cuerpo "Su pago Ref. X no fue aprobado. Motivo: …".
3. `app/views/emails/pago_rechazado.php` — `<title>` y badge "Pago No Aprobado"; "Motivo de la no aprobación:" (se conservan colores/✖).
4. `app/views/pagos/detalle.php` — frase "Este pago no fue aprobado. No se generaron modificaciones a las facturas."; los estados crudos visibles (título "Pago X", "Pago Registrado (X)", "Cambio a X", "Procesado como X") mapean `RECHAZADO` → `NO APROBADO`. NO tocar botones admin ("Rechazar Pago") ni el modal.
5. `app/views/perfil/index.php` — badge de solicitud: `Rechazado` → `No Aprobado`.
6. `app/models/SolicitudesModel.php` — guard de 24 h y notificación de solicitud: "no fue aprobada"; título "Solicitud de Datos Aprobada / No Aprobada".
7. `app/controllers/AuthController.php` — "Su solicitud de registro no fue aprobada por la administración: …".

## Restricciones
- No tocar: estados internos, acciones/flashes/filtros admin, CSRF, tests.
- Tests que deben seguir verdes: ComprobanteFlujoAprobacionTest (exige `Rechazar Pago` en detalle), HistorialPagosTest, ConciliacionTest (fallos de entorno preexistentes), SolicitudesRegistroTest, HelperTest, BehaviorTest, PagoDuplicadosTest, PagoDetalleNavegacionTest, PerfilAdminTest, AuthTest.
- Las notificaciones ya guardadas en BD conservan el texto viejo (solo cambian las nuevas).

## Criterios de aceptación
1. Ninguna superficie user-facing dice "Rechazado/rechazado" para el estado del pago/solicitud; dice "No Aprobado / no fue aprobado".
2. Estados internos y flujo admin intactos; suites relevantes verdes.
3. `php -l` sin errores en los archivos tocados.

## Ruta
- Writer único delegado; verificación del writer + spot check del padre + verificador independiente (ruta RDD-off). Commit del orquestador directo en `main`.

## Entrega
- Commit directo en `main`: `7e26111` — feat(ui): reemplazar 'Rechazado' por 'No Aprobado' en los mensajes al usuario (7 archivos, +23/−17, sin rama). Push: decisión del usuario.

## Verificación
- Writer: `php -l` 7/7; suites verdes: HelperTest (93), ComprobanteFlujoAprobacionTest (26, exige `Rechazar Pago` — intacto), HistorialPagosTest (20), PagoDetalleNavegacionTest (31), SolicitudesRegistroTest (47), AuthTest (35), PerfilAdminTest (25). Fallos preexistentes de entorno (verificados contra HEAD con stash): BehaviorTest (`public/uploads/.htaccess` ausente), ConciliacionTest (3, `.env`), PagoDuplicadosTest (19+1, `APP_KEY`/`NOTIFICATION_ENCRYPT_KEY` sin `.env`).
- Spot check del padre: `php -l` 7/7 + HelperTest 59 tests / 93✅ / 0❌.
- Verificador independiente: VERIFICADO — diff 1:1 al contrato; badge `badgeEstado('rechazado')` renderiza `cancel` + "No Aprobado"; cero copy "Rechazado" visible al usuario; UI admin y estados internos intactos; adversarial sin hallazgos. Observación: `stash@{0}` preexistente (GitHub Desktop, 29-sep, solo `.atl/*`) ajeno al cambio.
- RDD: assess (inventario declarado) → `medium` / `under_budget`; ruta RDD-off con verificador independiente (writer en modelo flash).
