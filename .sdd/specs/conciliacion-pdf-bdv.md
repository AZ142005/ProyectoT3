# Especificación: Conciliación PDF (BDV) y Gestión de Cuentas Bancarias Autorizadas

## 1. Requisitos Funcionales: Conciliación con PDF Banco de Venezuela

### RF-BDV-01: Ingesta de Extractos PDF en Conciliación Bancaria
- La interfaz de importación de extractos (`/admin/conciliacion`) debe aceptar archivos con extensión `.pdf` además de `.csv` y `.txt`.
- El campo selector de banco debe indicar explícitamente el soporte de PDF para Banco de Venezuela (`Banco de Venezuela (CSV / TXT / PDF)`).
- La validación en `ConciliacionController::importarExtracto` debe aceptar tipos MIME `application/pdf`, `application/x-pdf`, `text/csv`, `text/plain`.
- El límite de carga permitido debe ser de hasta 10MB para soportar estados de cuenta extensos de múltiples páginas.

### RF-BDV-02: Extracción y Parseo Nativo de Documentos PDF
- La extracción de texto se ejecutará 100% en PHP nativo mediante descompresión de flujos `/FlateDecode` (usando `gzuncompress`/`gzinflate`) y extracción de operadores de texto `Tj` y `TJ`, garantizando total independencia tecnológica y portabilidad en servidores compartidos o entornos locales XAMPP.
- Se soportarán documentos de múltiples páginas (como el extracto de 14 páginas de Banco de Venezuela).

### RF-BDV-03: Reconocimiento Estructurado de Transacciones BDV
El parser debe identificar los renglones tabulares del estado de cuenta de Banco de Venezuela que contienen la estructura:
`[Referencia] [Descripción] [Fecha] [Mov] [Débito] [Crédito] [Saldo]`

- **Referencia**: Secuencia numérica de 8 a 20 dígitos (ej. `0677232314587`, `0591385593359`).
- **Descripción**: Concepto del movimiento en mayúsculas (ej. `OPERACION PAGOMOVIL BDV`, `TRASPASO OTRAS CTAS BDV E`, `COMISION PAGOMOVILBDV`, `OP PAGOMOVILBDV OTROS BAN`, `TRANSF LINEA BDVAPP NAT N`, `MOVISTAR PREPAGO`, etc.).
- **Fecha**: Formato venezolano `DD/MM/YYYY`, convertida a `YYYY-MM-DD`.
- **Movimiento (Mov)**:
  - `NC` (Nota de Crédito): Inyecciones de fondos / Cobranzas recibidas de residentes. Se clasifica como tipo `'credito'` y se extrae el monto de la columna Crédito.
  - `ND` (Nota de Débito): Egresos, comisiones bancarias, pagos a terceros. Se clasifica como tipo `'debito'` y se extrae el valor absoluto de la columna Débito.
  - `SI` (Saldo Inicial): Omitido de los movimientos operativos.
- **Monto**: Normalizado a `float` eliminando separadores de miles (`.`) y convirtiendo la coma decimal (`,`) en punto (`.`).

### RF-BDV-04: Omisión de Renglones No Operativos
- Omitir cabeceras de página ("Estado de cuenta moneda nacional", "Cliente", "Cuenta", "Período", "Saldo inicial", "Intereses", etc.).
- Omitir numeración de páginas ("Página: X/14").
- Omitir leyendas legales y pies de página ("El Banco de Venezuela S.A...", "Gobierno Bolivariano...").

### RF-BDV-05: Integración con el Flujo de Conciliación
- Los movimientos de crédito ingresan como `estado_conciliacion = 'pendiente'` para cruce inteligente con pagos reportados por residentes.
- Los movimientos de débito ingresan automáticamente como `estado_conciliacion = 'descartado'`.

---

## 2. Requisitos Funcionales: Cuentas Bancarias Autorizadas y Opciones de Pago

### RF-CB-01: Modelo y Almacenamiento de Cuentas Autorizadas
- Tabla `cuentas_bancarias` con atributos:
  - `id`: Autoincremental
  - `banco`: Nombre de la institución (ej. "Banco de Venezuela", "Banesco", "Banco Mercantil")
  - `tipo_cuenta`: `corriente` | `ahorro`
  - `numero_cuenta`: Número completo de 20 dígitos venezolanos
  - `titular`: Razón social o nombre del condominio
  - `tipo_identificacion`: `V` | `J` | `E` | `G`
  - `identificacion`: Número de RIF o Cédula (ej. "J-12345678-0")
  - `telefono_pago_movil`: Teléfono afiliado a Pago Móvil (opcional)
  - `permite_transferencia`: Booleano (1/0)
  - `permite_pago_movil`: Booleano (1/0)
  - `activa`: Booleano (1/0)

### RF-CB-02: CRUD Administrativo de Cuentas Bancarias
- Ruta `/admin/cuentas-bancarias` protegida para rol `admin`.
- Interfaz con diseño Bootstrap 5:
  - Tabla de cuentas registradas con badges de estado (Activa / Inactiva, Transferencia, Pago Móvil).
  - Modal para creación y edición de cuenta con validaciones de longitud (20 dígitos numéricos para cuenta).
  - Botón de alternancia rápida (toggle) de estado Activa / Inactiva.
  - Eliminación segura (solo si no existen pagos asociados, de lo contrario se desactiva).
- Acceso directo en el menú lateral (`admin_sidebar.php`).

### RF-CB-03: Restricción Estricta en el Registro de Pagos por Residentes
- En las vistas de pago de residentes (`/pagos/nuevo` y `/residente/enviar-pago`):
  - **Eliminar el campo de texto libre para banco receptor**.
  - Proveer un selector `<select>` cargado dinámicamente con las **cuentas bancarias activas autorizadas** (`CuentasBancariasModel::getActivas()`).
  - Al seleccionar una cuenta receptora, mostrar visualmente una tarjeta informativa con:
    - Banco y Número de cuenta (20 dígitos).
    - Titular y RIF/Cédula.
    - Teléfono de Pago Móvil (si el método es Pago Móvil o la cuenta lo permite).
  - En los controladores (`PagoController::subir` y `ResidenteController::enviarPago`):
    - Validar que el `cuenta_bancaria_id` (o cuenta seleccionada) corresponda a una cuenta existente y con `activa = 1`.
    - Si la cuenta no existe o está inactiva, rechazar la transacción con mensaje descriptivo de seguridad.

---

## 3. Criterios de Aceptación
1. Migración `scripts/migrate_cuentas_bancarias.php` crea la tabla de forma idempotente.
2. El administrador puede crear, editar, alternar y listar cuentas bancarias autorizadas.
3. El residente solo puede seleccionar cuentas bancarias activas del condominio al reportar pagos.
4. Carga exitosa de extractos `.pdf` de Banco de Venezuela reconociendo créditos y débitos sin inflación.
5. Cero violaciones arquitectónicas (`scripts/check_purity.php`).
6. Cero vulnerabilidades estáticas OWASP (`scripts/audit_security.php`).
7. 100% de la suite de pruebas aprobada (`tests/run.php`).
