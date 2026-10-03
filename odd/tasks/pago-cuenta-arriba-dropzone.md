# Cuenta Oficial para el Pago arriba del dropzone (3 vistas de pago)

## Objetivo (instrucción del usuario)
- Mover el selector **"Cuenta Oficial para el Pago"** (label + badge, select `#cuenta_bancaria_id`, aviso `#inconsistencia-cuenta_bancaria_id` y tarjeta `#cardInfoCuenta` con datos y botones de copiado) a **arriba del cuadro donde se arrastra la captura** en las tres vistas de pago.
- Asegurar que el **autorrellenado OCR** de ese campo sigue funcionando tras el movimiento.
- Alcance confirmado por el usuario: **las tres vistas**. Directo en `main` (sin ramas). Commit del orquestador.

## Contexto / hallazgo raíz
- Las tres vistas comparten el mismo patrón: dropzone en "Paso 1" y el bloque de cuenta más abajo en el "Paso 2".
  1. `app/views/residente/enviar_pago.php` — bloque **L271–L346** dentro de "Tarjeta B: Entidades Bancarias y Cuentas" (Paso 2). Destino: Paso 1, entre el header (L83–L95) y `<!-- Dropzone interactivo -->` (L97). Re-indentar 24 → 20.
  2. `app/views/pago_directo/index.php` — bloque **L347–L420** dentro de "Tarjeta B" (Paso 2). Destino: Paso 1, entre el header (L156–L168) y `<div id="dropzone"` (L170). Re-indentar 24 → 20.
  3. `app/views/pagos/residente/subir.php` — bloque **L171–L246** dentro del grid del Paso 2 (contenedor con `sm:col-span-2`). Destino: zona de carga, entre el `<h3>` "Paso 1: Subir Comprobante" (L47–L50) y `<div id="dropzone"` (L52). Re-indentar 20 → 16; quitar `sm:col-span-2` (ya no vive en grid) y añadir `mb-5` al contenedor.
- El bloque contiene: label + badge `#badge-cuenta_bancaria_id`, `<select id="cuenta_bancaria_id">` con `data-banco/cuenta/titular/doc/telefono`, `#inconsistencia-cuenta_bancaria_id` y `#cardInfoCuenta` (en subir.php además `#wrapperInfoTelefono`).
- Autorrellenado actual: tras el OCR, el select se selecciona por `data.cuenta_bancaria_id` o `data.banco_receptor` y se llama `actualizarInfoCuenta(...)`; **todo el JS referencia por ID**, por lo que mover el HTML no lo rompe — pero debe verificarse explícitamente.

## Cambios (solo vistas; sin lógica; líneas guía al estado actual)
1. `residente/enviar_pago.php`: mover el bloque tal cual (mismas clases), re-indentado -4.
2. `pago_directo/index.php`: mover el bloque tal cual (mismas clases), re-indentado -4.
3. `pagos/residente/subir.php`: mover el bloque (mismas clases salvo contenedor: `flex flex-col gap-1.5 sm:col-span-2` → `flex flex-col gap-1.5 mb-5`), re-indentado -4.

Fuera de alcance (NO tocar): JavaScript, controladores, el resto del Paso 2 (monto/fecha/método/referencia/banco pagador), estilos globales, otros archivos.

## Restricciones funcionales
- Conservar íntegros: `id`s, `name`, `data-*`, `required`, `onchange`, textos visibles, íconos, botones de copiado, `csrf_field()` y condicionales PHP.
- **Cero cambios de JS** (los tres `<script>` quedan idénticos).
- Strings verificados por tests que deben permanecer: `name="banco_pagador"` y `data.banco_pagador` (BehaviorTest, `enviar_pago.php`); `Tesseract`, `pago-directo/extraer` y `Extracción Inteligente` (PagoDirectoTest, `pago_directo/index.php`).
- Ajustar líneas en blanco tras el movimiento (sin dobles líneas).

## Criterios de aceptación
1. En cada vista, `id="cuenta_bancaria_id"` aparece **antes** de `id="dropzone"` en el documento.
2. Cada id del bloque existe exactamente **1 vez** por archivo (los 9 comunes + `wrapperInfoTelefono` solo en subir.php).
3. Cero cambios en el JS de autorrellenado; siguen presentes las referencias a `cuenta_bancaria_id` y las llamadas a `actualizarInfoCuenta(...)`.
4. `php -l` 3/3 sin errores; `BehaviorTest` y `PagoDirectoTest` verdes (exit 0, 0 fallos).
5. Diff acotado a las 3 vistas.

## Ruta de implementación
- Writer único (delegado) — disparador writer (2+ archivos no triviales). Verificación del writer (php -l + integridad + suites filtradas); verificador independiente del padre (read-only) + spot check. Directo en `main`; commit del orquestador.
- Skills: `work-unit-commits` (organización; el writer **no** commitea).

## Verificación
- Writer: `php -l` x3; script de integridad de ids + orden (cuenta → dropzone); `php tests/run.php --filter=Behavior`; `php tests/run.php --filter=PagoDirecto`.
- Verificador independiente (read-only): comparar el diff contra `git show HEAD:<archivo>` — bloques idénticos salvo indentación/clase de contenedor; posiciones correctas; JS intacto; scope 3 archivos; sin hallazgos adversariales.
- Spot check del padre: `php -l` x3 + script de integridad + re-ejecutar un suite.
- RDD: `mode status` → on (global). `assess` post-commit → esperado `high`/`unassessable` en OpenCode (runtime no elegible); ruta RDD-off: auto-verificación del writer + verificador independiente + spot check. No desactivar el modo (decisión del usuario).

## Resultado (cierre)
- [x] T1 — `residente/enviar_pago.php`: bloque movido arriba del dropzone (Paso 1); re-indentado -4; resto del Paso 2 intacto.
- [x] T2 — `pago_directo/index.php`: idem; re-indentado -4.
- [x] T3 — `pagos/residente/subir.php`: idem; contenedor `sm:col-span-2` → `mb-5`; re-indentado -4.
- [x] T4 — Verificación completa.
- Verificación del writer: `php -l` 3/3 OK; integridad ids+orden OK (0 BAD); BehaviorTest 111 tests / 260✅; PagoDirectoTest 11 tests / 53✅; diff solo 3 vistas (230+/230−); JS idéntico byte a byte; CRLF preservado.
- Verificador independiente (read-only): VERIFIED — 2 hunks simétricos por archivo; bloques idénticos salvo indentación (y clase de contenedor en subir.php); multiset del archivo completo intacto; divs balanceados (73/73, 79/79, 54/54); ids únicos; referencias cruzadas JS↔HTML y `$cuentasBancarias` disponibles; sin hallazgos adversariales. Límite: verificación estática (sin navegador/OCR real).
- Spot check del padre: `php -l` ×3 OK; orden cuenta→dropzone OK ×3; BehaviorTest 260✅/0❌; diff 230+/230− en 3 archivos.
- RDD: `mode status` → on (global). `assess` (base `e113b3e`, committed-only) → `risk: high`, reason `unassessable` (untracked preexistentes requieren declaración; runtime OpenCode no elegible para revisión inmutable), `review_due: high_risk`, sin `next_transition`. Ruta RDD-off aplicada (auto-verificación + verificador independiente + spot check). Modo NO desactivado.
- Residual honesto: sin verificación en navegador real (DOM/OCR); el autorrellenado se respalda en la invariancia del JS + ids/`data-*` intactos + tests.

## Entrega
- Commit `7c9ddcd` directo en `main` (3 vistas) + este doc en `docs(odd)`. Push: decisión del usuario.
