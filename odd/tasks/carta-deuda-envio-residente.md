# Carta de Deuda: mensaje WhatsApp alineado + registro de envío por residente

## Objetivo
1) Alinear el mensaje predefinido del enlace WhatsApp de la carta con el nuevo formato oficial y dar por resuelto el "0 d" (el mensaje ya no muestra días por factura; refleja la tabla mensual de la carta).
2) Registrar cada envío de carta por correo en una tabla nueva (`cartas_deuda_envios`), mostrar el estado "Carta enviada" en la vista de la carta (botón deshabilitado con fecha/hora) y **bloquear el reenvío al mismo residente hasta el día siguiente** (por calendario).

## Alcance
- `app/core/helpers.php`: helper `agruparFacturasPorMes(array $facturas): array` (usado por la vista y por el controlador; evita duplicar la lógica de agrupación).
- `app/controllers/ReporteController.php`: `generarCartaDeuda()` (mensaje nuevo, `dias_mora` fuera de uso, datos de último envío a la vista) y `enviarAvisoCobro()` (cooldown + registro + flashes).
- `app/models/CartasDeudaEnviosModel.php` (nuevo, extiende `BaseModel`).
- `scripts/migrate_cartas_deuda_envios.php` (nuevo, idempotente; ejecutar en dev).
- `app/views/admin/reportes/carta_deuda.php`: estado "Carta enviada" (botón deshabilitado) + "Último envío" si aplica.
- Tests: `tests/CartaDeudaMensajeTest.php` (nuevo), `tests/CartaDeudaEnvioTest.php` (nuevo), `tests/HelperTest.php`, `tests/CartaDeudaFormatoTest.php` (si requiere ajuste de fixture).

## Fuera de alcance
- El clic de WhatsApp no se registra (WhatsApp no confirma el envío real desde el servidor; solo se registra el envío por correo, que sí es verificable).
- La lista de morosidad no cambia (sin badge por fila en esta iteración).
- No tocar: `EmailService`, `SmtpMailer`, worker `scripts/process_notifications.php`, `.env`, dumps `database/*.sql`, `morosidad.php`.

## Reglas de negocio (fijas)
- **Cooldown por residente**: bloquea si existe un envío registrado HOY (`DATE(created_at) = CURDATE()`) para el mismo `propietario_id`; si la unidad no tiene propietario, la clave es `unidad_id`. Mañana vuelve a permitirse (calendario, no 24 h móviles).
- El registro de envío se hace **solo si el encolado del correo tuvo éxito** (`encolarNotificacion` → `true`).
- Al bloquear: flash de aviso claro + redirect a la carta (`/admin/reportes/carta-deuda/{id}`). No se encola nada.
- Params de la migración:
  ```sql
  CREATE TABLE IF NOT EXISTS `cartas_deuda_envios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `unidad_id` INT NOT NULL,
    `propietario_id` INT NULL,
    `canal` VARCHAR(20) NOT NULL DEFAULT 'email',
    `usuario_id` INT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_carta_envio_unidad` (`unidad_id`, `created_at`),
    KEY `idx_carta_envio_propietario` (`propietario_id`, `created_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  ```
- Mensaje WhatsApp nuevo (texto fijo institucional = el de la carta):
  ```
  CONDOMINIO CONJUNTO LAS MESETAS
  RIF. J-400577870
  Av. Ppal. de Morón. Oficina Administrativa
  Urb. Las Mesetas. Teléf. 0271-2259170 - Valera, Edo. Trujillo

  Valera, {j} de {Mes} de {año}

  Ciudadana (o): {NOMBRE EN MAYÚSCULAS}
  Propietario(a) del Apto. {n}. Del Edificio "{edificio}".
  PRESENTE.-

  Nos es grato dirigirnos a usted, para saludarle y a la vez notificarle que a la fecha, tiene una morosidad de {X} meses vencidos de condominio, por un monto de {LETRAS} ({Bs. total}). Monto total se detalla a continuación:

  {primera fila: "Saldo de {Mes}-{año}: Bs. X"; siguientes "{Mes}-{año}: Bs. X"}
  TOTAL: Bs. {total}

  Le solicitamos realizar el pago a la brevedad posible por los medios establecidos: Cuenta Corriente Banco de Venezuela Nº. 0102-0373-2200-0010-2432; Divisa (Tasa del BCV del día de cancelación); o al correo pagosjdclm@gmail.com.

  Atentamente,
  Ninoska Moncada — Administradora
  ```

## Checklist
- [x] T1: helper `agruparFacturasPorMes` + refactor de la vista + mensaje WhatsApp nuevo + tests (`CartaDeudaMensajeTest`)
- [x] T2: migración + `CartasDeudaEnviosModel` + tests de modelo
- [x] T3: controlador (registro/cooldown) + vista (estado enviada) + tests de vista/controlador (`CartaDeudaEnvioTest`)
- [x] T4: verificación + 2 commits work-unit + evidencia

## Verificación
- `php -l` de cada PHP tocado; migración ejecutada en dev.
- Filtros: `Helper`, `CartaDeudaFormato`, `CartaDeudaMensaje`, `CartaDeudaEnvio`, `ArreglosAuditoria`, `Rbac`, `Notification`.
- Suite completa (aborto conocido en `SecurityTest`, preexistente).
- Verificador independiente posterior sobre los commits.

## Notas
- TDD: off (runner custom `php tests/run.php`), como el resto del repo.
- RDD nativo no evaluable en OpenCode: verificación por suites + verificador independiente.
- Convención de entrega: DIRECTO EN MAIN, local, sin push.

## Entrega
- Commit 1 (mensaje WhatsApp): `5bab4ed` — `feat(carta-deuda): mensaje WhatsApp alineado al formato oficial con monto en letras y detalle mensual` (5 archivos: helpers, vista, controlador, `HelperTest`, `CartaDeudaMensajeTest`).
- Commit 2 (registro de envío + cooldown): `cf49d78` — `feat(carta-deuda): registro de envío por residente y bloqueo de reenvío hasta el día siguiente` (6 archivos, incluido este doc). Este bloque de evidencia se añadió después con `git commit --amend` (solo doc), por lo que `HEAD` puede diferir del hash citado: un commit no puede contener su propio hash.
- Entrega local en `main`, sin push.
- `php -l` sin errores en: `app/core/helpers.php`, `app/controllers/ReporteController.php`, `app/views/admin/reportes/carta_deuda.php`, `app/models/CartasDeudaEnviosModel.php`, `scripts/migrate_cartas_deuda_envios.php`, `tests/HelperTest.php`, `tests/CartaDeudaMensajeTest.php`, `tests/CartaDeudaEnvioTest.php`.
- Migración ejecutada dos veces en dev (`php scripts/migrate_cartas_deuda_envios.php`): idempotente. Tabla `cartas_deuda_envios` creada con el DDL del spec (utf8mb4_general_ci; índices `idx_carta_envio_unidad` y `idx_carta_envio_propietario`).
- Baseline previo al cambio: Helper 64 tests/105 passed, CartaDeudaFormato 9/28, ArreglosAuditoria 13/33, Notification 6/25 (0 failed / 0 errors en todos).
- Con los commits aplicados:
  - `php tests/run.php --filter=Helper` → 67 tests | 120 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=CartaDeudaMensaje` → 1 test | 15 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=CartaDeudaEnvio` → 8 tests | 36 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=CartaDeudaFormato` → 9 tests | 28 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=ArreglosAuditoria` → 13 tests | 33 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=Rbac` → 9 tests | 234 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=Notification` → 6 tests | 25 passed | 0 failed | 0 errors.
- Suite completa (`php tests/run.php`): aborto preexistente en `SecurityTest` (su prueba de CSRF inválido llama a `Security::validateCSRF()`, que hace `exit;` y termina el proceso con la vista 403, sin resumen). Antes del aborto corren en verde todas las clases previas —incluidas `HelperTest`, `CartaDeudaMensajeTest`, `CartaDeudaEnvioTest` y `CartaDeudaFormatoTest`— sin ningún `❌`/`⚠`. Las clases posteriores al corte se verificaron aparte: `--filter=Solicitudes` → 22 tests | 144 passed | 0 failed; `--filter=Usuario` → 33 tests | 134 passed | 0 failed.
- Datos de prueba: el flujo del controlador se prueba sin ejecutar el worker (sin SMTP real). Limpieza verificada con consultas de residuos: 0 filas en `cartas_deuda_envios`, `personas`, `unidades`, `facturas` y `notificaciones_cola` de prueba.
- Desviación menor del spec: la aserción "no contiene `0 d`" usa el patrón `/\b0 d\b/` en lugar de `str_contains('0 d')` para no dar falso positivo cuando el día de la fecha termina en 0 (p. ej. "10 de Octubre de 2026").
- Rollback: revertir el commit 2 elimina modelo, migración, cooldown, estado de la vista y sus tests (la tabla puede dejarse o eliminarse con `DROP TABLE cartas_deuda_envios`); revertir el commit 1 restaura el mensaje anterior, el bloque de agrupación en línea de la vista y elimina helper y tests nuevos. Ningún commit toca `EmailService`, `SmtpMailer`, el worker, `.env` ni la lista de morosidad.
- Verificación independiente (read-only) de `5bab4ed` y `0dfdf43`: **VERIFIED WITH OBSERVATIONS** — alcance exacto (9 rutas), refactor de vista byte-idéntico (9.896 bytes), migración re-ejecutada (idempotente), modelo y controlador conformes al spec, bloqueo sin efectos colaterales (conteo de filas antes/después), suites reproducidas una a una; observaciones menores de tests (cédula fija sin sufijo, sin caso singular "1 mes vencido", sin assert literal del atributo `disabled`) sin impacto funcional.
