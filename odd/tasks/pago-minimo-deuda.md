# Regla de negocio: el monto del pago no puede ser menor a la deuda de la unidad

Fecha: 2026-10-18
Origen: Pedido del usuario — «haz que los pagos no puedan subirse si el monto registrado es menor a la deuda y agrega todos los cambios al main».
Ruta: ODD. Implementación directa sobre `main` (pedido explícito del usuario) con validación server-side en ambos flujos de subida + guarda cliente + tests. No es SDD.
TDD: off — runner funcional `php tests/run.php` (actual: 632 ✅).

## Regla

Al enviar un pago, si la **deuda pendiente de la unidad** (`FacturasModel::getResumenFinancieroUnidad()['total_deuda']`, la misma cifra que el formulario muestra como «Deuda Pendiente») es mayor que 0, el **monto** debe ser **≥ deuda**. Sobrepago sigue permitido (genera saldo a favor). Deuda 0 → sin restricción.

## Alcance (los 2 únicos flujos de producción que insertan en `pagos`)

1. **Residente** — `POST /pagos/subir` (`PagoController::subir`): guarda server-side **antes** de subir el archivo; guarda cliente en `pagos/residente/subir.php`.
2. **Público sin sesión** — `POST /pago-directo/reportar` (`PagoDirectoController::reportar`): guarda dentro de la cadena de validación; guarda cliente en `pago_directo/index.php` (la deuda ya se carga vía `/pago-directo/deuda`).

Fuera de alcance: flujos admin de aprobación/conciliación, scripts de seed/migración, inserciones directas de tests.

## Cambios

- `PagoController::subir`: chequeo tras la validación de rango del monto (`monto < 0.01 || > 999999.99`) y antes de fecha/archivo. Mensaje: «El monto registrado (formatearMoneda) no puede ser menor a la deuda pendiente de la unidad (formatearMoneda).» + `Flash::error` + redirect a `/pagos/nuevo` (patrón existente).
- `PagoDirectoController::reportar`: mismo chequeo integrado a la cadena `$error` (tras el rango del monto), antes de cualquier subida/inserción.
- Vistas: `data-deuda` en el form + `<p id="errorMontoDeuda">` oculto + listener de `submit` que bloquea en cliente con el mismo criterio (se apoya en `ultimoTotal` del fetch de deuda en pago directo).
- Tests: nuevo `tests/PagoMontoDeudaTest.php` (semántica del dato + guardas server/client por fuente, con los patrones del repo: `SaldoFavorTest`, `PagoUploadHuerfanoTest`).

## Criterios de aceptación

- POST con `monto < deuda` en ambos flujos → rechazado, sin fila nueva en `pagos`, sin archivo huérfano, mensaje claro.
- POST con `monto >= deuda` → comportamiento actual intacto (incluye sobrepago/saldo a favor).
- Unidad sin deuda → sin cambios de comportamiento.
- Suite completa en verde.

## Verificación (realizada)

- `php -l` sin errores (5 archivos). Suite completa: **636 ✅ / 0 ❌** = base 632 + 4 tests nuevos (`--filter=PagoMontoDeuda` → 4/4).
- **POST real residente** (`monto=10` < deuda 450): 302 → `/pagos/nuevo` con flash «El monto registrado (Bs. 10,00) no puede ser menor a la deuda pendiente de la unidad (Bs. 450,00).»; `pagos` 7→7 filas y `uploads/comprobantes` 3→3 archivos (sin fila nueva ni archivo huérfano).
- **POST real invitado** (`monto=10`, unidad 1): 200 con el mismo mensaje en el formulario; sin cambios en filas ni archivos.
- **Guardas cliente (CDP 390px)**: residente — deuda 450.00, submit prevenido + aviso «(Bs. 450,00)» que se oculta al corregir; pago-directo — deuda cargada por fetch (25.152,28 en la primera unidad de la lista) con el mismo comportamiento.
- Nota preexistente de la suite (no relacionada con este cambio): `SecurityTest::testCsrfRejectsInvalidToken` ejecuta `exit` dentro del runner y trunca lo que sigue; los archivos posteriores se probaron individualmente en verde y `UsuarioCrearRolTest` arrastra un error preexistente de transacción anidada.

## Entrega

- Directo en `main` (pedido del usuario): `560ec09` (código + tests, 5 archivos) + commit de este documento.
- Rama `fix/vistas-moviles-sistema` ya fusionada (fast-forward) y conservada.
