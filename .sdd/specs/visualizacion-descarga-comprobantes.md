# Especificación: Visualización y Descarga de Comprobantes en el Flujo de Aprobación de Pagos

## 1. Requisitos Funcionales

### RF-VD-01: Vista de Detalle Integral para Administradores
- Proveer una vista de detalle completa donde el administrador pueda auditar y revisar todos los datos asociados al pago registrado antes de emitir un dictamen de aprobación.
- Datos obligatorios a mostrar en la vista de detalle:
  - **Identificador de Pago**: Número formateado del pago registrado.
  - **Monto y Moneda**: Monto exacto reportado formateado en la moneda del sistema.
  - **Estado Actual**: Badge semántico del ciclo de vida (`PENDIENTE`, `EN REVISIÓN`, `APROBADO`, `RECHAZADO`).
  - **Residente y Unidad**: Nombre completo del residente pagador, cédula de identidad, edificio/torre y número de unidad habitacional.
  - **Datos de Transacción**: Fecha exacta del pago, número de referencia bancaria, banco pagador (origen), banco receptor (destino) y cuenta bancaria receptora registrada.
  - **Observaciones**: Notas o comentarios ingresados por el residente al momento de la carga.
  - **Historial de Auditoría**: Línea de tiempo cronológica con cada cambio de estado, fecha, hora, administrador que ejecutó la acción y motivo registrado.
  - **Visualizador de Comprobante**: Renderizado responsivo del soporte digital con distinción de formato:
    - Para imágenes (`jpg`, `jpeg`, `png`): visualizador escalable con capacidad de ampliación (lightbox / zoom en nueva pestaña).
    - Para documentos (`pdf`): visor o tarjeta de documento con icono distinguible, nombre del archivo y botón de apertura.

### RF-VD-02: Acciones de Aprobación Directas en la Vista de Detalle
- Integrar en la vista de detalle (`/pagos/detalle/{id}` y `/admin/comprobante/verificar`) una tarjeta de acciones administrativas contextuales:
  - Cuando el pago se encuentra en estado **PENDIENTE** o **EN REVISIÓN**:
    - **Botón "Aprobar Pago"**: Dispara la aprobación inmediata del pago con token CSRF, actualizando el estado a `APROBADO`, desencadenando la liquidación contable en cascada (`LiquidacionPagoService`) y notificando al residente.
    - **Botón "Poner en Revisión"** (solo si está en PENDIENTE): Permite al administrador marcar el comprobante para una segunda verificación.
    - **Botón "Rechazar Pago"**: Despliega un modal o formulario que exige de forma obligatoria un motivo de rechazo (mínimo 5 caracteres) antes de confirmar el rechazo.
  - Cuando el pago ya se encuentra **APROBADO** o **RECHAZADO**:
    - Ocultar los botones de mutación para evitar inconsistencias o aprobaciones duplicadas.
    - Mostrar una tarjeta informativa indicando la resolución final del pago (aprobado o rechazado), fecha de procesamiento y responsable.

### RF-VD-03: Descarga Activa y Funcional en Todo el Ciclo de Vida
- Habilitar un botón o enlace explícito de descarga ("Descargar Comprobante") con icono representativo:
  - **Etapa previa a la aprobación**: Funcional y disponible cuando el pago está en estado `PENDIENTE` o `EN REVISIÓN`, permitiendo al administrador descargar el archivo original para verificación forense, cotejo con extractos bancarios o aumento de resolución.
  - **Etapa posterior a la aprobación**: Funcional y disponible cuando el pago ya está en estado `APROBADO` (o `RECHAZADO`), permitiendo descargar el soporte físico para auditorías fiscales, comprobantes de pago archivados o trámites legales.
- La descarga debe estar disponible tanto en:
  1. La vista de detalle del pago (`/pagos/detalle/{id}` y `/admin/comprobante/verificar`).
  2. Las tablas de listado administrativo (`/pagos` y `/admin/comprobantes`) como acción directa por fila.

### RF-VD-04: Proxy Seguro de Archivos con Soporte de Descarga Forzada
- El servicio `public/comprobante-proxy.php` debe soportar el parámetro `download=1` (o `descargar=1`):
  - Cuando `download=1`: emitir cabecera `Content-Disposition: attachment; filename="..."` para obligar al navegador a descargar el archivo físicamente en lugar de visualizarlo.
  - Cuando `download` no está presente: emitir cabecera `Content-Disposition: inline; filename="..."` para permitir previsualización incrustada.
  - Validar estrictamente la sesión del usuario (denegando acceso anónimo con HTTP 403).
  - Validar control de acceso por rol (los residentes solo pueden descargar comprobantes propios; los administradores y auditores pueden descargar cualquier comprobante del sistema).
  - Sanitizar el parámetro `file` con `basename()` para neutralizar ataques de path traversal.

## 2. Requisitos No Funcionales
- **RNF-01 (Seguridad)**: Protección contra ataques CSRF en todas las solicitudes POST de cambio de estado. Sanitización de toda salida en vistas mediante `e()`.
- **RNF-02 (Arquitectura)**: Mantener pureza estricta MVC en controladores y modelos sin emitir HTML ni ejecutar SQL desordenado.
- **RNF-03 (Interfaz y Usabilidad)**: Interfaz responsiva basada en los patrones de diseño del proyecto (Bootstrap 5 y utilidades complementarias), con iconografía semántica de Material Symbols.
