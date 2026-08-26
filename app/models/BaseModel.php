<?php
namespace App\Models;

use App\Core\Database;
use PDO;

abstract class BaseModel {
    protected function db(): \PDO {
        return Database::getConnection();
    }

    /**
     * Inserta un registro genérico en la tabla indicada.
     *
     * @param string $table
     * @param array $data ['columna' => valor, ...]
     * @return bool|string lastInsertId o false
     */
    protected function create(string $table, array $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->db()->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
        return $stmt->execute($data) ? $this->db()->lastInsertId() : false;
    }

    /**
     * Actualiza un registro por ID.
     *
     * @param string $table
     * @param int $id
     * @param array $data ['columna' => valor, ...]
     * @return bool
     */
    protected function update(string $table, int $id, array $data): bool {
        $set = implode(', ', array_map(fn($col) => "{$col} = :{$col}", array_keys($data)));
        $data['id'] = $id;

        $stmt = $this->db()->prepare("UPDATE {$table} SET {$set} WHERE id = :id");
        return $stmt->execute($data);
    }

    /**
     * Obtiene un registro por ID.
     *
     * @param string $table
     * @param int $id
     * @return array|false
     */
    protected function getById(string $table, int $id) {
        $stmt = $this->db()->prepare("SELECT * FROM {$table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Alterna el estado (0 ↔ 1) de un registro.
     *
     * @param string $table
     * @param int $id
     * @return bool true si se modificó al menos 1 fila
     */
    protected function toggleEstado(string $table, int $id): bool {
        $stmt = $this->db()->prepare("UPDATE {$table} SET estado = IF(estado = 1, 0, 1) WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Verifica si existe un valor duplicado en una columna.
     *
     * @param string $table
     * @param string $column Columna a verificar (ej: 'nombre', 'email')
     * @param string $value Valor a buscar
     * @param int|null $excludeId ID a excluir (para edición)
     * @return bool
     */
    protected function exists(string $table, string $column, string $value, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as total FROM {$table} WHERE LOWER({$column}) = LOWER(:value)";
        $params = ['value' => $value];

        if ($excludeId !== null) {
            $sql .= " AND id != :excludeId";
            $params['excludeId'] = $excludeId;
        }

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return intval($stmt->fetch()['total'] ?? 0) > 0;
    }

    /**
     * Pagina una query con filtros dinámicos.
     *
     * @param string $baseSql SQL base con WHERE 1=1 (debe terminar sin ORDER BY ni LIMIT)
     * @param string $countSql SQL de conteo (mismos JOINs y WHERE que $baseSql, sin SELECT columns)
     * @param array $params Parámetros nombrados para ambas queries
     * @param int $pagina Página actual (1-based)
     * @param int $porPagina Registros por página
     * @param string $orderSql Cláusula ORDER BY completa (ej: 'c.fecha_envio DESC')
     * @return array ['datos', 'total', 'pagina', 'porPagina', 'totalPaginas']
     */
    protected function paginate(
        string $baseSql,
        string $countSql,
        array $params,
        int $pagina,
        int $porPagina,
        string $orderSql
    ): array {
        $db = $this->db();

        // Total de registros
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetch()['total'];

        // Datos paginados
        $offset = ($pagina - 1) * $porPagina;
        $sql = $baseSql . " ORDER BY {$orderSql} LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'datos'       => $stmt->fetchAll(),
            'total'       => $total,
            'pagina'      => $pagina,
            'porPagina'   => $porPagina,
            'totalPaginas' => (int) ceil($total / $porPagina),
        ];
    }
}