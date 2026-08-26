<?php
namespace App\Models;

use PDO;

class PersonasModel extends BaseModel {
    protected string $table = 'personas';

    public function getActiveByCedula($cedula) {
        $stmt = $this->db()->prepare("SELECT * FROM personas WHERE cedula = :cedula AND estado = 1");
        $stmt->execute(['cedula' => $cedula]);
        return $stmt->fetch();
    }

    public function getActiveById($id) {
        return parent::getById($id);
    }

    public function getResidenteDetails($residente_id) {
        $sql = "
            SELECT p.*, u.numero as unidad_numero, e.nombre as torre 
            FROM personas p
            INNER JOIN unidades u ON p.unidad_id = u.id 
            LEFT JOIN edificios e ON u.edificio_id = e.id
            WHERE p.id = :id AND p.estado = 1
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $residente_id]);
        return $stmt->fetch();
    }

    public function getActiveByEmail($email) {
        $stmt = $this->db()->prepare("SELECT * FROM personas WHERE email = :email AND estado = 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public function register($cedula, $email, $hashedPassword) {
        $stmt = $this->db()->prepare("UPDATE personas SET email = :email, password = :password WHERE cedula = :cedula AND estado = 1");
        return $stmt->execute([
            'email'    => $email,
            'password' => $hashedPassword,
            'cedula'   => $cedula
        ]);
    }

    public function emailExists($email, ?int $excludeId = null) {
        return $this->exists('personas', 'email', $email, $excludeId);
    }

    /**
     * Incrementa el contador de intentos fallidos de login.
     * A los 5 intentos, bloquea la cuenta por 30 minutos.
     */
    public function incrementarIntentosFallidos(int $personaId): void {
        $stmt = $this->db()->prepare(
            "UPDATE personas SET intentos_fallidos = COALESCE(intentos_fallidos, 0) + 1,
             bloqueado_hasta = CASE WHEN COALESCE(intentos_fallidos, 0) + 1 >= 5
             THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE) ELSE bloqueado_hasta END
             WHERE id = :id"
        );
        $stmt->execute(['id' => $personaId]);
    }

    /**
     * Resetea el contador de intentos fallidos tras login exitoso.
     */
    public function resetIntentosFallidos(int $personaId): void {
        $stmt = $this->db()->prepare(
            "UPDATE personas SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id"
        );
        $stmt->execute(['id' => $personaId]);
    }

    /**
     * Verifica si la cuenta está bloqueada por intentos fallidos.
     */
    public function estaBloqueado(int $personaId): bool {
        $stmt = $this->db()->prepare(
            "SELECT bloqueado_hasta FROM personas WHERE id = :id"
        );
        $stmt->execute(['id' => $personaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['bloqueado_hasta'])) {
            return false;
        }
        return strtotime($row['bloqueado_hasta']) > time();
    }
}