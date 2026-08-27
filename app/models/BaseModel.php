<?php
namespace App\Models;

use App\Core\Database;
use PDO;

abstract class BaseModel {
    /**
     * Nombre de la tabla principal asociada al modelo hijo (opcional si se especifica en llamadas).
     * @var string
     */
    protected string $table = '';

    protected function db(): \PDO {
        return Database::getConnection();
    }

    /**
     * Ejecuta una llamada dentro de una transacción PDO de forma atómica.
     *
     * @param callable $callback Recibe la instancia de PDO como argumento
     * @return mixed Retorna el valor devuelto por el callback o false si ocurrió un error
     */
    protected function transaction(callable $callback): mixed {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("[DATABASE TRANSACTION ERROR] " . $e->getMessage());
            return false;
        }
    }

    /**
     * Inserta un registro genérico en la tabla indicada o $this->table.
     *
     * @param string|array $tableTablaOData Si es array, usa $this->table
     * @param array|null $data ['columna' => valor, ...]
     * @return string|false lastInsertId o false
     */
    public function create($tableTablaOData, ?array $data = null): string|false {
        if (is_array($tableTablaOData)) {
            $data = $tableTablaOData;
            $table = $this->table;
        } else {
            $table = $tableTablaOData ?: $this->table;
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->db()->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
        return $stmt->execute($data) ? (string)$this->db()->lastInsertId() : false;
    }

    /**
     * Actualiza un registro por ID en la tabla indicada o $this->table.
     *
     * @param string|int $tableTablaOId Si es int, usa $this->table y $tableTablaOId es el $id
     * @param int|array|null $idOData Si $tableTablaOId es int, esto es $data. Si no, es $id.
     * @param array|null $data ['columna' => valor, ...]
     * @return bool
     */
    public function update($tableTablaOId, $idOData = null, ?array $data = null): bool {
        if (is_int($tableTablaOId) || is_numeric($tableTablaOId)) {
            $table = $this->table;
            $id = (int)$tableTablaOId;
            $data = is_array($idOData) ? $idOData : [];
        } else {
            $table = (string) $tableTablaOId;
            $id = (int) $idOData;
            $data = $data ?? [];
        }

        $set = implode(', ', array_map(fn($col) => "{$col} = :{$col}", array_keys($data)));
        $data['id'] = $id;

        $stmt = $this->db()->prepare("UPDATE {$table} SET {$set} WHERE id = :id");
        return $stmt->execute($data);
    }

    /**
     * Obtiene un registro por ID.
     *
     * @param string|int $tableTablaOId
     * @param int|null $id
     * @return array|false
     */
    public function getById($tableTablaOId, ?int $id = null): array|false {
        if (is_int($tableTablaOId) || is_numeric($tableTablaOId)) {
            $table = $this->table;
            $id = (int)$tableTablaOId;
        } else {
            $table = (string) $tableTablaOId;
            $id = (int) $id;
        }

        $stmt = $this->db()->prepare("SELECT * FROM {$table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: false;
    }

    /**
     * Alterna el estado (0 ↔ 1) de un registro.
     *
     * @param string|int $tableTablaOId
     * @param int|null $id
     * @return bool true si se modificó al menos 1 fila
     */
    public function toggleEstado($tableTablaOId, ?int $id = null): bool {
        if (is_int($tableTablaOId) || is_numeric($tableTablaOId)) {
            $table = $this->table;
            $id = (int)$tableTablaOId;
        } else {
            $table = (string) $tableTablaOId;
            $id = (int) $id;
        }

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
     */
    protected function paginate(
        string $baseSql,
        string $countSql,
        array $params,
        int $pagina,
        int $porPagina,
        string $orderSql
    ): array {
        // Cap de seguridad: máximo 100 registros por página
        $porPagina = max(1, min($porPagina, 100));
        $pagina = max(1, $pagina);

        $db = $this->db();

        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetch()['total'];

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