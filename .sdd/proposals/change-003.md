# change-003: Ingesta y Parseo de PDF Maestro de Gastos (RF 30 - RF 34) y Notificaciones de Eventos (RF 35)

## Status
COMPLETED

## Description
Implementación del Motor de Justificación de Gastos (Módulo 7) para procesar el PDF maestro mensual con facturas y soportes consolidados, parseo estructurado de renglones con independencia tecnológica (RF 32), persistencia en base de datos con extracto textual exacto (RF 34), visor embebido por página para residentes, y resolución del disparador de notificaciones multicanal para eventos de pago (RF 35).

## Summary of Completed Work
1. **Migración de Base de Datos**: Creados `scripts/migrate_gastos_maestro.php` y `scripts/migrations_phase11.sql` agregando `pagina_soporte` y `extracto_texto` a la tabla `gastos_comunes`.
2. **Servicio de Parseo Independiente (RF 31, RF 32)**: `App\Services\GastoParserService` descomprime y extrae flujos PDF por páginas nativamente en PHP (`/FlateDecode`), estructurando renglones financieros con fecha, proveedor, factura, descripción, monto e inferencia semántica de categorías.
3. **Persistencia Transaccional**: `App\Models\GastosModel::importarGastosMaestro()` permite la inserción en bloque y registro en `log_auditoria`.
4. **Vistas e Interfaz**:
   - `app/views/admin/gastos/cargar_maestro.php`: Pantalla administrativa de ingesta con tabla interactiva de previsualización y edición de renglones antes de la confirmación masiva.
   - `app/views/admin/gastos/index.php`: Botón de acceso a Ingesta y badges de página en historial.
   - `app/views/residente/gastos.php`: Visor modal enriquecido con cita del extracto exacto (RF 34), cuota comunitaria estimada y visor embebido del soporte PDF.
5. **Reacción a Eventos de Pago (RF 35)**: Corregido bug de variable no definida en `PagoModel::aprobarLote()` y agregadas notificaciones multicanal e internas en cambios de estado de pago.
6. **Validación y Pruebas**: Suite `tests/GastosMaestroTest.php` creada y aprobada (7 tests, 27 aserciones). Auditoría OWASP (0 vulnerabilidades) y pureza MVC 100% aprobadas. Suite general con 283 tests aprobados.
