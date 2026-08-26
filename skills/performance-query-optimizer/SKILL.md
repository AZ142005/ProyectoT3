---
name: performance-query-optimizer
description: >-
  Playbook para optimizar consultas MySQL, índices, paginación eficiente y evitar problemas de N+1 en modelos PHP.
  Úsalo cuando el usuario pida mejorar la velocidad del sistema, optimizar consultas lentas o paginar listados grandes.
---

# Skill: Optimización de Consultas SQL y Rendimiento

Esta habilidad provee técnicas para mantener consultas rápidas y eficientes en la base de datos MySQL mediante PDO.

---

## 1. Prevención del Problema N+1 (Uso de JOINs)
- **Anti-patrón**: Hacer un bucle `foreach` para consultar los datos relacionados de cada fila.
- **Patrón Óptimo**: Unir las tablas en una sola consulta estructurada:
```sql
-- ✅ UNA SOLA CONSULTA con JOIN
SELECT u.*, e.nombre AS edificio_nombre, COUNT(p.id) AS total_residentes
FROM unidades u
LEFT JOIN edificios e ON u.edificio_id = e.id
LEFT JOIN personas p ON p.unidad_id = u.id AND p.estado = 1
WHERE u.estado = 1
GROUP BY u.id
ORDER BY e.nombre ASC, u.numero ASC;
```

---

## 2. Paginación Eficiente
Para listados grandes (pagos, comprobantes, facturas):
```php
public function obtenerPagosPaginados($pagina = 1, $porPagina = 20) {
    $db = Database::getConnection();
    $offset = ($pagina - 1) * $porPagina;

    // 1. Conteo total optimizado
    $total = $db->query("SELECT COUNT(*) FROM pagos")->fetchColumn();

    // 2. Extracción de página con LIMIT y OFFSET
    $stmt = $db->prepare("
        SELECT p.*, per.nombre, per.cedula 
        FROM pagos p
        INNER JOIN personas per ON p.residente_id = per.id
        ORDER BY p.fecha_pago DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', (int) $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'total'        => (int) $total,
        'pagina'       => (int) $pagina,
        'porPagina'    => (int) $porPagina,
        'totalPaginas' => (int) ceil($total / $porPagina),
        'datos'        => $stmt->fetchAll()
    ];
}
```

---

## 3. Índices Recomendados en MySQL
- Claves foráneas: `INDEX idx_unidades_edificio (edificio_id)`, `INDEX idx_personas_unidad (unidad_id)`.
- Campos de búsqueda frecuente: `INDEX idx_personas_cedula (cedula)`, `INDEX idx_pagos_fecha (fecha_pago)`.
- Estados para filtros: `INDEX idx_pagos_estado (estado)`.
