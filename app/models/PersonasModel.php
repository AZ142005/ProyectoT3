<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class PersonasModel {
    /**
     * Busca una persona por su cédula y que esté activa (estado = 1).
     *
     * @param string $cedula Cédula del residente
     * @return array|false Retorna el registro de la persona o false si no existe
     */
    public function getActiveByCedula($cedula) {
        $db = Database::getConnection();
        
        // Uso obligatorio de consulta preparada PDO para mitigar SQL Injection
        $stmt = $db->prepare("SELECT * FROM personas WHERE cedula = :cedula AND estado = 1");
        $stmt->execute(['cedula' => $cedula]);
        
        return $stmt->fetch();
    }
    
    /**
     * Busca una persona activa por su ID.
     *
     * @param int $id ID de la persona
     * @return array|false
     */
    public function getActiveById($id) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("SELECT * FROM personas WHERE id = :id AND estado = 1");
        $stmt->execute(['id' => $id]);
        
        return $stmt->fetch();
    }

    /**
     * Obtiene los datos detallados del residente junto con su unidad y torre.
     *
     * @param int $residente_id ID del residente
     * @return array|false
     */
    public function getResidenteDetails($residente_id) {
        $db = Database::getConnection();
        
        $sql = "
            SELECT p.*, u.numero as unidad_numero, e.nombre as torre 
            FROM personas p
            INNER JOIN unidades u ON p.unidad_id = u.id 
            LEFT JOIN edificios e ON u.edificio_id = e.id
            WHERE p.id = :id AND p.estado = 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $residente_id]);
        
        return $stmt->fetch();
    }

    /**
     * Busca una persona activa por su email.
     *
     * @param string $email
     * @return array|false
     */
    public function getActiveByEmail($email) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("SELECT * FROM personas WHERE email = :email AND estado = 1");
        $stmt->execute(['email' => $email]);
        
        return $stmt->fetch();
    }

    /**
     * Registra un nuevo residente validando que la cédula ya exista
     * en la tabla personas (pre-registrado por administración).
     * Actualiza el email y password del registro existente.
     *
     * @param string $cedula
     * @param string $email
     * @param string $hashedPassword
     * @return bool
     */
    public function register($cedula, $email, $hashedPassword) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("UPDATE personas SET email = :email, password = :password WHERE cedula = :cedula AND estado = 1");
        return $stmt->execute([
            'email'    => $email,
            'password' => $hashedPassword,
            'cedula'   => $cedula
        ]);
    }

    /**
     * Verifica si un email ya existe en la tabla personas.
     *
     * @param string $email
     * @return bool
     */
    public function emailExists($email) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM personas WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        
        return intval($row['total'] ?? 0) > 0;
    }
}
