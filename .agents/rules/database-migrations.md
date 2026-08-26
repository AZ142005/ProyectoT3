# Reglas de Base de Datos, Migraciones y Transacciones

## 1. Scripts de Migración Idempotentes (`scripts/migrate_*.php`)
- Cada cambio en el esquema de base de datos (nuevas tablas, columnas o claves foráneas) debe implementarse como un script ejecutable en `scripts/`.
- **Idempotencia Obligatoria**: Los scripts deben poder ejecutarse múltiples veces sin fallar.
  - Usar `CREATE TABLE IF NOT EXISTS`.
  - Comprobar existencia de columnas antes de `ALTER TABLE ADD COLUMN`:
    ```php
    $stmt = $db->query("SHOW COLUMNS FROM unidades LIKE 'edificio_id'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE unidades ADD COLUMN edificio_id INT DEFAULT NULL");
    }
    ```
- **Integridad Referencial**: Definir siempre restricciones de clave foránea (`FOREIGN KEY ... REFERENCES ... ON DELETE SET NULL / CASCADE`).

---

## 2. Transacciones Atómicas en Procesos Financieros
- Toda operación que involucre múltiples cambios de saldo, conciliación de pagos, aprobación/rechazo de comprobantes o generación de facturas DEBE envolverse en una transacción PDO:
  ```php
  $db = Database::getConnection();
  try {
      $db->beginTransaction();
      // Operación 1: Actualizar estado de comprobante
      // Operación 2: Actualizar saldo de factura
      // Operación 3: Generar registro de auditoría
      $db->commit();
  } catch (\Exception $e) {
      $db->rollBack();
      throw $e;
  }
  ```

---

## 3. Seeders de Prueba (`scripts/seed_*.php`)
- Los scripts de seed deben verificar existencia previa con `SELECT` o `ON DUPLICATE KEY UPDATE` antes de insertar, para no duplicar datos ni corromper llaves únicas.
