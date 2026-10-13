<?php
namespace App\Models;

use PDO;

class CuentasBancariasModel extends BaseModel {

    protected string $table = 'cuentas_bancarias';

    /**
     * Obtiene todas las cuentas bancarias registradas para el panel de administración.
     *
     * @return array
     */
    public function getAll(): array {
        $db = $this->db();
        $stmt = $db->query("SELECT * FROM cuentas_bancarias ORDER BY activa DESC, banco ASC, id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las cuentas bancarias activas autorizadas para recibir pagos.
     * Opcionalmente filtra por método permitido ('transferencia' o 'pago_movil').
     *
     * @param string|null $metodo
     * @return array
     */
    public function getActivas(?string $metodo = null): array {
        $db = $this->db();
        $sql = "SELECT * FROM cuentas_bancarias WHERE activa = 1";
        
        if ($metodo === 'transferencia') {
            $sql .= " AND permite_transferencia = 1";
        } elseif ($metodo === 'pago_movil') {
            $sql .= " AND permite_pago_movil = 1";
        }

        $sql .= " ORDER BY banco ASC, id ASC";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca una cuenta bancaria por su identificador.
     *
     * @param string|int $tableTablaOId
     * @param int|null $id
     * @return array|false
     */
    public function getById($tableTablaOId, ?int $id = null): array|false {
        return parent::getById($tableTablaOId, $id);
    }

    /**
     * Busca una cuenta bancaria activa por su ID.
     *
     * @param int $id
     * @return array|false
     */
    public function getActivaById(int $id): array|false {
        $db = $this->db();
        $stmt = $db->prepare("SELECT * FROM cuentas_bancarias WHERE id = :id AND activa = 1 LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    /**
     * Crea una nueva cuenta bancaria autorizada.
     *
     * @param array $data
     * @return int ID de la cuenta creada
     */
    public function crear(array $data): int {
        $db = $this->db();
        $sql = "
            INSERT INTO cuentas_bancarias 
            (banco, tipo_cuenta, numero_cuenta, titular, tipo_identificacion, identificacion, telefono_pago_movil, permite_transferencia, permite_pago_movil, activa)
            VALUES 
            (:banco, :tipo_cuenta, :numero_cuenta, :titular, :tipo_id, :identificacion, :telefono, :transferencia, :pago_movil, :activa)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'banco'         => trim($data['banco'] ?? ''),
            'tipo_cuenta'   => in_array($data['tipo_cuenta'] ?? '', ['corriente', 'ahorro'], true) ? $data['tipo_cuenta'] : 'corriente',
            'numero_cuenta' => preg_replace('/[^0-9]/', '', $data['numero_cuenta'] ?? ''),
            'titular'       => trim($data['titular'] ?? ''),
            'tipo_id'       => in_array($data['tipo_identificacion'] ?? '', ['V', 'J', 'E', 'G'], true) ? $data['tipo_identificacion'] : 'J',
            'identificacion'=> trim($data['identificacion'] ?? ''),
            'telefono'      => !empty($data['telefono_pago_movil']) ? trim($data['telefono_pago_movil']) : null,
            'transferencia' => !empty($data['permite_transferencia']) ? 1 : 0,
            'pago_movil'    => !empty($data['permite_pago_movil']) ? 1 : 0,
            'activa'        => isset($data['activa']) ? (int)$data['activa'] : 1
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Actualiza los datos de una cuenta bancaria autorizada.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function actualizar(int $id, array $data): bool {
        $db = $this->db();
        $sql = "
            UPDATE cuentas_bancarias 
            SET banco = :banco,
                tipo_cuenta = :tipo_cuenta,
                numero_cuenta = :numero_cuenta,
                titular = :titular,
                tipo_identificacion = :tipo_id,
                identificacion = :identificacion,
                telefono_pago_movil = :telefono,
                permite_transferencia = :transferencia,
                permite_pago_movil = :pago_movil,
                activa = :activa
            WHERE id = :id
        ";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            'id'            => $id,
            'banco'         => trim($data['banco'] ?? ''),
            'tipo_cuenta'   => in_array($data['tipo_cuenta'] ?? '', ['corriente', 'ahorro'], true) ? $data['tipo_cuenta'] : 'corriente',
            'numero_cuenta' => preg_replace('/[^0-9]/', '', $data['numero_cuenta'] ?? ''),
            'titular'       => trim($data['titular'] ?? ''),
            'tipo_id'       => in_array($data['tipo_identificacion'] ?? '', ['V', 'J', 'E', 'G'], true) ? $data['tipo_identificacion'] : 'J',
            'identificacion'=> trim($data['identificacion'] ?? ''),
            'telefono'      => !empty($data['telefono_pago_movil']) ? trim($data['telefono_pago_movil']) : null,
            'transferencia' => !empty($data['permite_transferencia']) ? 1 : 0,
            'pago_movil'    => !empty($data['permite_pago_movil']) ? 1 : 0,
            'activa'        => isset($data['activa']) ? (int)$data['activa'] : 1
        ]);
    }

    /**
     * Alterna el estado activo/inactivo de una cuenta bancaria.
     *
     * @param int $id
     * @return bool
     */
    public function toggleActiva(int $id): bool {
        $db = $this->db();
        $stmt = $db->prepare("UPDATE cuentas_bancarias SET activa = IF(activa = 1, 0, 1) WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Elimina una cuenta bancaria si no tiene pagos vinculados; de lo contrario la desactiva.
     *
     * @param int $id
     * @return array ['exito' => bool, 'accion' => 'eliminada'|'desactivada', 'mensaje' => string]
     */
    public function eliminarODesactivar(int $id): array {
        $db = $this->db();

        // Verificar si tiene pagos vinculados
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM pagos WHERE cuenta_bancaria_id = :id");
        $stmtCheck->execute(['id' => $id]);
        $pagosVinculados = (int)$stmtCheck->fetchColumn();

        if ($pagosVinculados > 0) {
            // Desactivar para preservar integridad histórica
            $stmtUpdate = $db->prepare("UPDATE cuentas_bancarias SET activa = 0 WHERE id = :id");
            $stmtUpdate->execute(['id' => $id]);
            return [
                'exito'   => true,
                'accion'  => 'desactivada',
                'mensaje' => "La cuenta tiene {$pagosVinculados} pago(s) histórico(s) asociados, por lo que fue desactivada en lugar de eliminada."
            ];
        }

        // Si no tiene pagos, se puede eliminar físicamente
        $stmtDel = $db->prepare("DELETE FROM cuentas_bancarias WHERE id = :id");
        $stmtDel->execute(['id' => $id]);
        return [
            'exito'   => true,
            'accion'  => 'eliminada',
            'mensaje' => "Cuenta bancaria eliminada correctamente."
        ];
    }
}
