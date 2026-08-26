<?php
namespace App\Models;

class UsuariosModel extends BaseModel {
    protected string $table = 'usuarios';

    public function getActiveByUsuario($usuario) {
        $stmt = $this->db()->prepare("SELECT * FROM usuarios WHERE usuario = :usuario AND estado = 1");
        $stmt->execute(['usuario' => $usuario]);
        return $stmt->fetch();
    }

    public function updateUltimoAcceso($id) {
        $stmt = $this->db()->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getActiveByEmail($email) {
        $stmt = $this->db()->prepare("SELECT * FROM usuarios WHERE email = :email AND estado = 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }
}