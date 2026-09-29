# Especificación: Unificación de Pantallas de Detalle de Pagos

## 1. Requisitos Funcionales

### RF-UNI-01: Componente Canónico de Detalle de Pagos (`pagos/detalle.php`)
- La vista debe aceptar un esquema de datos polimórfico y normalizado que pueda originarse tanto de la tabla `pagos` como de `comprobantes_pago`.
- Atributos universales soportados:
  - `id`: Identificador del registro.
  - `monto`: Importe del pago formateado.
  - `estado`: Estado normalizado en mayúsculas (`PENDIENTE`, `EN REVISIÓN`, `APROBADO`, `RECHAZADO`).
  - `residente_nombre` y `residente_cedula`: Datos de identidad del pagador.
  - `unidad_numero` y `edificio_nombre`: Ubicación del inmueble.
  - `fecha_pago` y `fecha_registro`: Cronología del evento financiero.
  - `metodo_pago` y `referencia`: Identificadores de transacción.
  - `banco_pagador` y `banco_receptor`: Datos bancarios (si existen).
  - `observaciones`: Notas aportadas por el residente.
  - `archivo`: Nombre del soporte digital o físico cargado.

### RF-UNI-02: Información Extendida de Factura (Cuando Aplique)
- Cuando el pago esté asociado a una factura (origen comprobante):
  - Desplegar tarjeta o campos destacados con:
    - **Número de Factura**: `#XXXX` en fuente monoespaciada.
    - **Saldo Total de la Factura**: Saldo previo a la verificación.
    - **Saldo Restante Estimado**: Saldo posterior tras deducir el comprobante, con advertencia visual verde si genera saldo a favor remanente.

### RF-UNI-03: Adaptador de Acciones de Aprobación y Rechazo
- Si `tipo_origen === 'comprobante'`:
  - Los formularios de acción envían a `/admin/comprobante/verificar?id={id}` con tokens CSRF:
    - Botón "Aprobar Pago": `accion=aprobar`.
    - Botón "Rechazar Pago": `accion=rechazar` con `observaciones` obligatorias.
- Si `tipo_origen === 'pago'` (default):
  - Los formularios de acción envían a `/pagos/cambiar-estado`:
    - Botón "Aprobar Pago": `nuevo_estado=APROBADO`.
    - Botón "Poner en Revisión": `nuevo_estado=EN REVISIÓN`.
    - Botón "Rechazar Pago": `nuevo_estado=RECHAZADO` con `motivo` obligatorio.

### RF-UNI-04: Descarga y Previsualización Persistente
- El comprobante digital o físico se sirve a través de `comprobante-proxy.php?file={archivo}`.
- El botón de descarga activa (`&download=1`) está presente tanto en estado pendiente como en estado procesado.
- Soporte visual diferenciado para PDFs e imágenes (con lightbox/zoom).
