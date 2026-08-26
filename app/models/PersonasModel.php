<?php
namespace App\Models;

class PersonasModel extends BaseModel {
    public function getActiveByCedula($cedula) {
        $stmt = $this->db()->prepare("SELECT * FROM personas WHERE cedula = :cedula AND estado = 1");
        $stmt->execute(['cedula' => $cedula]);
        return $stmt->fetch();
    }

    public function getActiveById($id) {
        return $this->getById('personas', $id);
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

    public function emailExists($email) {
        return $this->exists('personas', 'email', $email);
    }
}