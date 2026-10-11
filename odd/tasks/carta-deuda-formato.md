# Carta de Deuda: formato de Notificación Oficial (según imagen de ejemplo)

## Objetivo
Reformatear la carta de deuda individual (`app/views/admin/reportes/carta_deuda.php`) para replicar la estructura de la notificación oficial de la imagen `carta deuda ejemplo.jpeg` (raíz del repo): membrete con logo, fecha a la derecha, destinatario, cuerpo con meses de mora y monto en letras, tabla mensual, párrafo legal, medios de pago, firma de la administradora y caja "Recibido Por".

## Referencia (imagen de ejemplo)
- Imagen: `carta deuda ejemplo.jpeg`.
- Datos institucionales tomados de la imagen (textos fijos de la carta): `CONDOMINIO CONJUNTO LAS MESETAS`, `RIF. J-400577870`, `Av. Ppal. de Morón. Oficina Administrativa`, `Urb. Las Mesetas. Teléf. 0271-2259170 - Valera, Edo. Trujillo`, cuenta `Banco de Venezuela Nº. 0102-0373-2200-0010-2432`, divisa BCV, correo `pagosjdclm@gmail.com`, administradora `Ninoska Moncada`.
- Logo: `public/img/logo_condominio.png` (ya coincide con el de la imagen).
- La tabla se arma agrupando las facturas por mes/año (suma de saldos; la primera fila se rotula `Saldo de {Mes}-{año}`) y el conteo de "meses vencidos" del cuerpo es la cantidad de grupos.

## Alcance
- `app/core/helpers.php`: nuevo helper `montoEnLetras($valor)`.
- `tests/HelperTest.php`: tests del helper.
- `app/views/admin/reportes/carta_deuda.php`: reformateo completo del documento (se conserva la barra de acciones no imprimible, el enlace WhatsApp condicional, el envío por email, el botón imprimir, flash y CSS de impresión).
- `tests/CartaDeudaFormatoTest.php`: nuevo test de vista con fixtures (render por include + buffer).
- Fuera de alcance: `ReporteController` (el texto predefinido del WhatsApp se reporta aparte, no se modifica), otras vistas y modelos.

## Restricciones
- No cambiar la firma `generarCartaDeuda($unidadId)` ni las variables que entrega el controlador (`unidad`, `facturas`, `totalDeuda`, `enlaceWhatsapp`, `analisisTel`).
- No tocar el controlador `ReporteController.php` ni `morosidad.php`.
- Escapar todo dato dinámico con `e()`.
- Comentarios y textos en español (el proyecto ya usa español); sin atribución AI en commits.
- Commit work-unit único en `main`, local, sin push (convención del repo). Solo archivos del alcance.

## Checklist
- [x] T1: helper `montoEnLetras` + tests en `tests/HelperTest.php`
- [x] T2: reformateo de `carta_deuda.php` + `tests/CartaDeudaFormatoTest.php`
- [x] T3: verificación (`php -l`, filtros Helper/CartaDeudaFormato/ArreglosAuditoria/Rbac + suite completa) + commit + evidencia

## Verificación
- `php -l` de cada archivo PHP tocado.
- Baseline y resultados de `php tests/run.php` (suite completa) y filtros afectados.
- Verificación independiente posterior (verificador read-only sobre el commit).

## Entrega
- Commit del cambio verificado: `d24b7ae` — `feat(carta-deuda): formato de notificación oficial según imagen de ejemplo` (rama `main`, local, sin push). Este bloque de evidencia se añadió después con `git commit --amend` (solo doc), por lo que `HEAD` puede diferir del hash citado: un commit no puede contener su propio hash.
- `php -l` sin errores en los 4 archivos PHP tocados.
- Baseline previo al cambio: Helper 59 tests/93 passed, ArreglosAuditoria 13/33, Rbac 9/234 (0 failed / 0 errors).
- Con el commit aplicado:
  - `php tests/run.php --filter=Helper` → 64 tests | 105 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=CartaDeudaFormato` → 9 tests | 28 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=ArreglosAuditoria` → 13 tests | 33 passed | 0 failed | 0 errors.
  - `php tests/run.php --filter=Rbac` → 9 tests | 234 passed | 0 failed | 0 errors.
- Suite completa (`php tests/run.php`): hallazgo de baseline — no cierra porque `SecurityTest::testCsrfRejectsInvalidToken` llama a `App\Core\Security::validateCSRF()`, que ante token inválido hace `exit;` y termina el proceso (código 0, sin resumen). Se reprodujo también sin los cambios de este commit (stash), en el mismo punto de corte. Antes del corte corren en verde todas las clases previas (incluidas `HelperTest` y `CartaDeudaFormatoTest`), y las clases que quedan después del corte se verificaron aparte: `--filter=Solicitudes` → 22 tests | 144 passed | 0 failed; `--filter=Usuario` → 33 tests | 134 passed | 0 failed.
- Verificación independiente (read-only) del commit `d24b7ae`: **VERIFIED WITH OBSERVATIONS** — alcance exacto (5 archivos), fidelidad a la imagen, escaping XSS probado con render real, helper revalidado (9 casos del contrato + extras), suites reproducidas; observaciones cosméticas sin impacto. RDD: evaluación nativa `high/unassessable` (runtime OpenCode no eligible para review inmutable) → verificación por suite + verificador independiente.
- Rollback: revertir el commit de cierre elimina helper, vista, tests y este doc; no toca `ReporteController` ni la base de datos.
