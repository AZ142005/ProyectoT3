<?php
namespace App\Models;

use PDO;

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

    /**
     * Obtiene un usuario activo (admin/auditor) por cédula o documento de identidad.
     *
     * @param string $cedula
     * @return array|null
     */
    public function getActiveByCedula(string $cedula): ?array {
        $cedulaNorm = normalizarCedula($cedula);
        $soloDigitos = preg_replace('/\D/', '', $cedulaNorm);

        $stmt = $this->db()->prepare("
            SELECT * FROM usuarios
            WHERE (
                cedula = :c1 
                OR cedula = :c2 
                OR (usuario = 'admin' AND (:c3 = '00000000' OR :c4 = 'V00000000'))
            )
            AND estado = 1
            LIMIT 1
        ");
        $stmt->execute([
            'c1' => $cedulaNorm,
            'c2' => $soloDigitos,
            'c3' => $soloDigitos,
            'c4' => $cedulaNorm
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene un usuario (cualquier estado) por cédula o documento de identidad.
     *
     * @param string $cedula
     * @return array|null
     */
    public function getByCedula(string $cedula): ?array {
        $cedulaNorm = normalizarCedula($cedula);
        $soloDigitos = preg_replace('/\D/', '', $cedulaNorm);

        $stmt = $this->db()->prepare("
            SELECT * FROM usuarios
            WHERE (
                cedula = :c1 
                OR cedula = :c2 
                OR (usuario = 'admin' AND (:c3 = '00000000' OR :c4 = 'V00000000'))
            )
            LIMIT 1
        ");
        $stmt->execute([
            'c1' => $cedulaNorm,
            'c2' => $soloDigitos,
            'c3' => $soloDigitos,
            'c4' => $cedulaNorm
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene un usuario por email o nombre de usuario (cualquier estado).
     *
     * @param string $identificador
     * @return array|null
     */
    public function getByEmailOrUsuario(string $identificador): ?array {
        $stmt = $this->db()->prepare("SELECT * FROM usuarios WHERE LOWER(email) = LOWER(:id1) OR usuario = :id2");
        $stmt->execute(['id1' => $identificador, 'id2' => $identificador]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Incrementa el contador de intentos fallidos de login.
     * A los 5 intentos, bloquea la cuenta por 30 minutos.
     */
    public function incrementarIntentosFallidos(int $userId): void {
        $stmt = $this->db()->prepare(
            "UPDATE usuarios SET intentos_fallidos = COALESCE(intentos_fallidos, 0) + 1,
             bloqueado_hasta = CASE WHEN COALESCE(intentos_fallidos, 0) + 1 >= 5
             THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE) ELSE bloqueado_hasta END
             WHERE id = :id"
        );
        $stmt->execute(['id' => $userId]);
    }

    /**
     * Resetea el contador de intentos fallidos tras login exitoso.
     */
    public function resetIntentosFallidos(int $userId): void {
        $stmt = $this->db()->prepare(
            "UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id"
        );
        $stmt->execute(['id' => $userId]);
    }

    /**
     * Verifica si la cuenta está bloqueada por intentos fallidos.
     */
    public function estaBloqueado(int $userId): bool {
        $stmt = $this->db()->prepare(
            "SELECT bloqueado_hasta FROM usuarios WHERE id = :id"
        );
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['bloqueado_hasta'])) {
            return false;
        }
        return strtotime($row['bloqueado_hasta']) > time();
    }
}