# Diseño: Conciliación PDF (BDV) y CRUD de Cuentas Bancarias Autorizadas

## 1. Esquema de Base de Datos

```sql
CREATE TABLE IF NOT EXISTS `cuentas_bancarias` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `banco` VARCHAR(100) NOT NULL,
  `tipo_cuenta` ENUM('corriente', 'ahorro') NOT NULL DEFAULT 'corriente',
  `numero_cuenta` VARCHAR(20) NOT NULL,
  `titular` VARCHAR(150) NOT NULL,
  `tipo_identificacion` ENUM('V', 'J', 'E', 'G') NOT NULL DEFAULT 'J',
  `identificacion` VARCHAR(20) NOT NULL,
  `telefono_pago_movil` VARCHAR(20) DEFAULT NULL,
  `permite_transferencia` TINYINT(1) NOT NULL DEFAULT 1,
  `permite_pago_movil` TINYINT(1) NOT NULL DEFAULT 1,
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Agregar columna opcional cuenta_bancaria_id en pagos si no existe
ALTER TABLE `pagos` ADD COLUMN `cuenta_bancaria_id` INT NULL AFTER `banco_receptor`;
```

## 2. Flujo de Arquitectura: Cuentas Autorizadas y Opciones de Pago

```
[Administrador]
       │
       ▼
(/admin/cuentas-bancarias)
       ├── CRUD en CuentaBancariaController
       └── Persiste en CuentasBancariasModel
       │
       ▼
[Residente al Registrar Pago]
       ├── GET /pagos/nuevo o /residente/enviar-pago
       ├── Carga únicamente CuentasBancariasModel::getActivas()
       └── Vista renderiza selector con cuentas autorizadas
              │
              ▼
       (Residente selecciona cuenta)
              │
              ├── JS reactivo muestra datos de cuenta (20 dígitos, RIF, Teléfono)
              └── Envío de POST con cuenta_bancaria_id
              │
              ▼
[PagoController::subir / ResidenteController::enviarPago]
       ├── Verifica que cuenta_bancaria_id sea válida y ACTIVA
       ├── Asigna banco_receptor automáticamente del registro oficial
       └── Guarda pago con cuenta_bancaria_id
```

## 3. Flujo de Arquitectura: Conciliación con PDF BDV

```
[Administrador]
       │
       ▼
(/admin/conciliacion -> Importar Extracto)
       ├── Selecciona: Banco de Venezuela (CSV / TXT / PDF)
       └── Sube archivo .pdf
       │
       ▼
[ConciliacionController::importarExtracto]
       ├── Valida extensión (.csv, .txt, .pdf) y MIME application/pdf
       └── ConciliacionBancariaService::parsearArchivo($path, 'venezuela')
       │
       ▼
[ConciliacionBancariaService]
       ├── extraerTextoDePdf(): Lee streams FlateDecode y decodifica Tj/TJ en PHP nativo
       └── parsearPdfBancoVenezuela():
              ├── Regex tabular para BDV:
              │     Ref (\d{8,20}) + Descripcion + Fecha + Mov (NC|ND) + Débito + Crédito + Saldo
              ├── Normaliza importes venezolanos a float
              └── Clasifica NC como 'credito' (pendiente) y ND como 'debito' (descartado)
       │
       ▼
[ConciliacionModel::insertarExtracto]
       └── Almacena en extractos_bancarios para cruce inteligente
```

## 4. Seguridad
- Roles estrictos: `admin` para CRUD de cuentas y conciliación; `residente` para registro de pagos.
- Sanitización de entradas, validación de RIF y número de 20 dígitos.
- Cero SQL injection mediante consultas preparadas PDO.
- Protección CSRF obligatoria en todos los formularios POST.
- Escape total en vistas con `e()`.
