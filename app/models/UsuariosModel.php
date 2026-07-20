<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class UsuariosModel {
    /**
     * Obtiene un usuario activo por su nombre de usuario.
     *
     * @param string $usuario
     * @return array|false
     */
    public function getActiveByUsuario($usuario) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE usuario = :usuario AND estado = 1");
        $stmt->execute(['usuario' => $usuario]);
        
        return $stmt->fetch();
    }
    
    /**
     * Actualiza la fecha y hora del último acceso del usuario.
     *
     * @param int $id
     * @return bool
     */
    public function updateUltimoAcceso($id) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Obtiene un usuario activo por su email.
     *
     * @param string $email
     * @return array|false
     */
    public function getActiveByEmail($email) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email AND estado = 1");
        $stmt->execute(['email' => $email]);
        
        return $stmt->fetch();
    }
}
