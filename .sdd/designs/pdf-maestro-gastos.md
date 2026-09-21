# Diseño: Motor de Justificación de Gastos y Eventos de Pago

## 1. Arquitectura y Flujo de Datos

```
[Administrador]
      │
      ▼
(Carga PDF Maestro: mes, anio, archivo)
      │
      ▼
[GastoController::parsearMaestro]
      │
      ▼
[GastoParserService]
  ├── Descomprime Streams FlateDecode por Página (PHP nativo)
  ├── Tokeniza y segmenta renglones
  ├── Aplica Regex Heurísticas (Fecha, Proveedor, Factura, Monto)
  ├── Clasifica Categoría por Palabras Clave
  └── Preserva extracto_texto y pagina_soporte
      │
      ▼
(Renderiza o devuelve JSON con Tabla de Previsualización Editable)
      │
      ▼
[Administrador revisa y confirma importación]
      │
      ▼
[GastoController::importarMaestro]
      │
      ▼
[GastosModel::importarGastosMaestro]
  ├── Inicia Transacción PDO
  ├── Inserta cada registro en `gastos_comunes`
  ├── Registra acción en `log_auditoria`
  └── Commit
      │
      ▼
[Residente en /residente/gastos]
      │
      ▼
(Clic en "Ver Justificación y Extracto")
      │
      ▼
[Modal Bootstrap 5]
  ├── Datos del Gasto y Alícuota
  ├── Cita del extracto exacto del PDF Maestro
  └── <iframe src="/uploads/soportes/archivo.pdf#page=N">
```

## 2. Cambios de Esquema
```sql
ALTER TABLE gastos_comunes 
ADD COLUMN pagina_soporte INT NULL DEFAULT 1 AFTER soporte_digital,
ADD COLUMN extracto_texto TEXT NULL DEFAULT NULL AFTER pagina_soporte;
```

## 3. Seguridad
- Subida de archivos restringida a MIME `application/pdf`, extensión `.pdf`, tamaño máximo 10MB con nombres generados por `random_bytes(16)`.
- Toda salida HTML en vistas protegida con `e()`.
- Tokens CSRF validados en formularios POST.
- Rutas restringidas a `UserRole::ADMIN` para gestión y `UserRole::RESIDENTE` para visualización.
