# ODD task: Retiro del Estado de Cuenta + RF 7 (bloqueo por intentos fallidos) en el SRS

**Estado**: COMPLETADO Y VERIFICADO (2026-10-18)
**Autorizado por**: usuario («quita estado-cuenta, agrega bloqueo por intentos fallidos a los requisitos y ordenalo para que no saltee numeros»)
**Ruta**: ODD — U1 sistema: 1 writer delegado (borrado multi-archivo + tests); U2 SRS: edición docx inline del orquestador (inserción de un párrafo, previa copia en temp).

## Objetivo

1. **U1 — Quitar el Estado de Cuenta del residente**: rutas `/residente/estado-cuenta` y `/residente/estado-cuenta/imprimir` (+ API `/api/v1/residente/estado-cuenta`), `EstadoCuentaController` completo, `ApiController::estadoCuenta()`, vistas `residente/estado_cuenta.php` y `residente/estado_cuenta_imprimir.php`, y los 6 tests de BehaviorTest que lo cubren (bloques «30. IDOR API», «39. PRINT LIMIT 500» y sección «4.2 Rate limiting impresión» ×4).
2. **U2 — SRS**: agregar el bloqueo por intentos fallidos como **RF 7** (el hueco que faltaba: la lista vigente salta de RF 6 a RF 8) y verificar que la numeración quede 1..37 sin saltos. La inserción en el hueco deja el resto de números intacto (no se renumera nada más).

## Contexto verificado

- Referencias del módulo: `public/index.php:237,238,249`; `EstadoCuentaController.php` (index+imprimir; solo lo usa esta área); `ApiController::estadoCuenta` (~L327-374; usa MovimientosModel); vistas ×2; tests BehaviorTest: L545-558, L665-676, L1305-1343. Sin enlaces desde otras vistas (solo links internos).
- `MovimientosModel` y `errors/404` se conservan (revisar callers restantes y reportar; no borrar modelos).
- `Requisitos.docx`: sin bloqueo de Word (verificado); modificaciones previas del usuario ya en el working tree (no se commitea; queda como modificación de trabajo).
- Texto RF 7 propuesto: «El sistema debe bloquear temporalmente las cuentas tras varios intentos fallidos de inicio de sesión, limitando además la frecuencia de intentos de autenticación.» (implementado: lockout por cuenta + RateLimiter).

## Tareas

- [x] U1 — Writer + limpieza final: commit `9fc6fae` (−462 líneas: 3 rutas, `EstadoCuentaController`, `ApiController::estadoCuenta` + import, 2 vistas, 6 tests, banner huérfano; +1 ajuste de regex `|\Z` en `testRefreshRevokesOldToken` — el método retirado era el último del archivo). Greps 0; HTTP 404 en ambas rutas; Behavior 103/253✅ y Router 23/44✅.
- [x] U2 — Orquestador: RF 7 insertado tras RF 6 (párrafo `Prrafodelista` clonado); verificación: 37 RF únicos 1..37 sin saltos (orden 6<7<8), RNF 1..8 por texto, diff = +1 línea exacta; Word abre OK (97 párrafos, RF 7 y RF 8 presentes). sha256 `B5391EC0…`; backup en `%TEMP%\opencode\req7\`. El docx NO se commitea (modificación de trabajo del usuario).
- [x] Spot check del orquestador (greps, tests, HTTP, XML).
- [x] Cierre: cumplimiento-rf (bitácora) + commit de docs.

## Notas

- RF 34 («extracto exacto al residente») sigue pendiente de decisión — su vehículo (la vista del residente) ya fue retirado ayer.
- Los 21B/22A/33A elegidos por el usuario aún NO se aplicaron al docx (pendiente en esta misma línea de trabajo).
