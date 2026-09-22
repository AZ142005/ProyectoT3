# change-006: Conciliación Bancaria con PDF (Banco de Venezuela) y CRUD de Cuentas Bancarias Autorizadas

## Status
COMPLETED

## Description
1. **Conciliación Bancaria con PDF (BDV)**: Extensión del motor de conciliación bancaria para importar y parsear estados de cuenta oficiales en formato PDF de Banco de Venezuela (BDV), extrayendo movimientos de crédito y débito de forma nativa en PHP sin dependencias externas, mapeando referencias, montos normalizados, descripciones y tipos de movimiento (NC/ND) para cruce inteligente automático.
2. **Cuentas Bancarias Autorizadas**: Implementación de un CRUD completo en el panel de administración para gestionar las cuentas bancarias del condominio autorizadas para recibir pagos (Banco, Tipo de Cuenta, Número de 20 dígitos, Titular, RIF/Cédula, Teléfono Pago Móvil, Switches para Transferencia y Pago Móvil, Estado Activa/Inactiva).
3. **Restricción de Pagos para Residentes**: Restricción estricta en las pantallas de reporte de pagos de residentes (`/pagos/nuevo` y `/residente/enviar-pago`) para que **únicamente** se puedan seleccionar las cuentas bancarias autorizadas y activas del condominio, eliminando campos de texto libre y mostrando al residente la información oficial de la cuenta seleccionada.

## Summary of Completed Work
1. **Migración de Base de Datos**: Creados `scripts/migrations_phase13.sql` y `scripts/migrate_cuentas_bancarias.php` con tabla `cuentas_bancarias`, columna `cuenta_bancaria_id` en `pagos` y cuenta semilla de Banco de Venezuela.
2. **Modelo y CRUD de Cuentas Bancarias**: `App\Models\CuentasBancariasModel` y `App\Controllers\CuentaBancariaController` implementados con protección de roles, validación de 20 dígitos de cuenta y trazabilidad relacional.
3. **Vistas Administrativas con Bootstrap 5**: `app/views/admin/cuentas_bancarias/index.php` maquetada con tarjetas resumen, tabla reactiva, modal de alta/edición y enlace en `admin_sidebar.php`.
4. **Restricción Estricta para Residentes**: Reemplazo de campos de texto libre por selector de cuentas activas y tarjeta informativa en tiempo real en `app/views/pagos/residente/subir.php` y `app/views/residente/enviar_pago.php`, con validación estricta en `PagoController::subir()` y `ResidenteController::enviarPago()`.
5. **Parseo Nativo de PDF de Banco de Venezuela**: Implementado `extraerTextoDePdf()` y `parsearPdfBancoVenezuela()` en `App\Services\ConciliacionBancariaService` mediante streams `/FlateDecode`, extrayendo referencias, fechas, notas de crédito (`NC`) y notas de débito (`ND`), y descartando saldos iniciales y cabeceras.
6. **Integración en Conciliación**: `App\Controllers\ConciliacionController` y `app/views/admin/conciliacion/index.php` adaptados para admitir archivos `.pdf` y banco `'venezuela'`.
7. **Pruebas y Auditorías**: Suite de 308 tests aprobada al 100% (`tests/run.php`), incluyendo `ConciliacionTest` (PDF sintético BDV) y `CuentasBancariasTest` (CRUD y aislamiento), 0 violaciones en `check_purity.php` y 0 vulnerabilidades en `audit_security.php`.
