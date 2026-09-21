# Especificación: Ingesta y Parseo de PDF Maestro de Gastos y Eventos de Pago

## 1. Requisitos Funcionales

### RF 30: Ingesta del PDF Maestro
- La interfaz de administración debe ofrecer un formulario en `/admin/gastos/maestro` para subir un documento PDF consolidado (hasta 10MB) correspondiente a un período (mes/año).
- Alternativamente, debe permitir pegar texto crudo o reporte tabular para contingencia.
- El archivo subido se almacenará con nombre desinfectado y aleatorio en `uploads/soportes/` conservando validación de tipo MIME real (`application/pdf`).

### RF 31 & RF 32: Extracción Estructurada con Independencia Tecnológica
- El servicio `GastoParserService` analizará internamente los flujos `/FlateDecode` del PDF en PHP nativo.
- Para cada página detectará renglones de gastos identificando:
  - **Fecha del gasto**: Formatos `DD/MM/YYYY`, `YYYY-MM-DD`, etc.
  - **Proveedor**: Nombre de la empresa o contratista.
  - **Nro. Factura / Comprobante**: Identificador numérico o alfanumérico.
  - **Concepto / Descripción**: Detalle del servicio o compra.
  - **Monto Total**: Soporte para formato venezolano (p. ej. `Bs. 1.250,50` o `1250,50`) y decimal internacional.
  - **Categoría Sugerida**: Clasificación por inferencia semántica de palabras clave (Servicios Básicos, Mantenimiento, Vigilancia, etc.).
  - **Página de soporte**: Índice numérico (1-based) de la página donde se ubica el gasto.
  - **Extracto exacto**: Texto original íntegro del renglón en el PDF.
- Presentará una tabla de previsualización interactiva donde el administrador puede ajustar o descartar renglones antes de la persistencia final.

### RF 33 & RF 34: Justificación Visual y Extracto Exacto para Residentes
- En la tabla de `gastos_comunes` se almacenarán `pagina_soporte` y `extracto_texto`.
- En el visor de rendición de cuentas (`/residente/gastos`), cada gasto con soporte maestro mostrará un botón de justificación que desplegará:
  - Resumen del gasto y alícuota comunitaria estimada.
  - Cita destacada con el **extracto textual exacto** (`extracto_texto`) y la indicación de la página.
  - Visor embebido PDF navegando a `#page=N`.
  - Opción de descarga directa del documento maestro.

### RF 35: Reacción a Eventos de Pago
- Cuando un pago se aprueba o rechaza (individualmente o por lote), el sistema debe registrar una notificación en la bandeja del residente y encolar el correo electrónico con su plantilla HTML (`pago_aprobado` o `pago_rechazado`).
- Corregir el bug de variable `$pago['id']` a `$sqlPago['id']` en `aprobarLote()`.

## 2. Criterios de Aceptación
1. `php scripts/migrate_gastos_maestro.php` ejecuta sin errores de forma idempotente.
2. `php scripts/check_purity.php` reporta cero violaciones arquitectónicas.
3. `php scripts/audit_security.php` reporta cero vulnerabilidades.
4. `php tests/run.php` aprueba 100% de los tests incluyendo `GastosMaestroTest`.
