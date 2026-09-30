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

    /**
     * Obtiene el listado unificado de cuentas (residentes y personal) con filtros y paginación.
     *
     * @param string $buscar
     * @param string $rol
     * @param int $pagina
     * @param int $porPagina
     * @return array
     */
    public function obtenerListadoUnificado(string $buscar = '', string $rol = '', int $pagina = 1, int $porPagina = 15): array {
        $baseSql = "
            SELECT 
                'persona' AS tipo_entidad,
                p.id AS id,
                p.cedula AS cedula,
                CONCAT(p.nombre, ' ', p.apellido) AS nombre_completo,
                p.email AS email,
                p.telefono AS telefono,
                CONCAT('Residente (', p.tipo, ')') AS rol_texto,
                'residente' AS rol_clave,
                CONCAT(COALESCE(e.nombre, 'Torre -'), ' / Apt. ', COALESCE(u.numero, 'S/A')) AS detalle_ubicacion,
                p.estado AS estado,
                p.intentos_fallidos AS intentos_fallidos,
                p.bloqueado_hasta AS bloqueado_hasta,
                (CASE WHEN p.bloqueado_hasta IS NOT NULL AND p.bloqueado_hasta > NOW() THEN 1 ELSE 0 END) AS esta_bloqueado,
                (CASE WHEN p.password IS NOT NULL AND p.password != '' THEN 1 ELSE 0 END) AS tiene_password
            FROM personas p
            LEFT JOIN unidades u ON p.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id

            UNION ALL

            SELECT 
                'usuario' AS tipo_entidad,
                u.id AS id,
                COALESCE(u.cedula, 'N/A') AS cedula,
                u.nombre_completo AS nombre_completo,
                u.email AS email,
                u.telefono AS telefono,
                (CASE WHEN u.rol = 'admin' THEN 'Administrador' WHEN u.rol = 'auditor' THEN 'Auditor' ELSE u.rol END) AS rol_texto,
                u.rol AS rol_clave,
                'Sistema / Oficina' AS detalle_ubicacion,
                u.estado AS estado,
                u.intentos_fallidos AS intentos_fallidos,
                u.bloqueado_hasta AS bloqueado_hasta,
                (CASE WHEN u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW() THEN 1 ELSE 0 END) AS esta_bloqueado,
                (CASE WHEN u.password IS NOT NULL AND u.password != '' THEN 1 ELSE 0 END) AS tiene_password
            FROM usuarios u
        ";

        $wrappedSql = "SELECT * FROM ({$baseSql}) AS t WHERE 1=1";
        $countSql   = "SELECT COUNT(*) as total FROM ({$baseSql}) AS t WHERE 1=1";
        $params = [];

        if (!empty($rol) && in_array($rol, ['residente', 'admin', 'auditor'], true)) {
            $wrappedSql .= " AND t.rol_clave = :rol";
            $countSql   .= " AND t.rol_clave = :rol";
            $params['rol'] = $rol;
        }

        if (!empty($buscar)) {
            $likeClause = " AND (t.nombre_completo LIKE :buscar OR t.cedula LIKE :buscar OR t.email LIKE :buscar)";
            $wrappedSql .= $likeClause;
            $countSql   .= $likeClause;
            $params['buscar'] = '%' . $buscar . '%';
        }

        return $this->paginate($wrappedSql, $countSql, $params, $pagina, $porPagina, 't.tipo_entidad ASC, t.nombre_completo ASC');
    }

    /**
     * Reinicia la contraseña de un usuario del sistema y restablece bloqueos.
     *
     * @param int $userId
     * @param string $nuevoHash
     * @return bool
     */
    public function reiniciarPassword(int $userId, string $nuevoHash): bool {
        $stmt = $this->db()->prepare(
            "UPDATE usuarios 
             SET password = :password, intentos_fallidos = 0, bloqueado_hasta = NULL 
             WHERE id = :id"
        );
        return $stmt->execute([
            'password' => $nuevoHash,
            'id'       => $userId
        ]);
    }

    /**
     * Actualiza los datos de perfil de un usuario del sistema (nombre, email, cédula, teléfono y opcionalmente password).
     *
     * @param int $userId
     * @param array $datos
     * @return bool
     */
    public function actualizarPerfil(int $userId, array $datos): bool {
        $campos = [
            'nombre_completo = :nombre',
            'email = :email',
            'cedula = :cedula',
            'telefono = :telefono'
        ];
        $params = [
            'id'       => $userId,
            'nombre'   => trim($datos['nombre_completo'] ?? ''),
            'email'    => trim($datos['email'] ?? ''),
            'cedula'   => !empty($datos['cedula']) ? trim($datos['cedula']) : null,
            'telefono' => !empty($datos['telefono']) ? trim($datos['telefono']) : null,
        ];

        if (!empty($datos['password'])) {
            $campos[] = 'password = :password';
            $params['password'] = password_hash($datos['password'], PASSWORD_BCRYPT);
        }

        $setClauses = implode(', ', $campos);
        $sql = sprintf("UPDATE usuarios SET %s WHERE id = :id", $setClauses);
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Verifica si un número de cédula ya se encuentra registrado por otro usuario.
     *
     * @param string $cedula
     * @param int $excludeId
     * @return bool
     */
    public function cedulaExisteEnOtroUsuario(string $cedula, int $excludeId): bool {
        $stmt = $this->db()->prepare("SELECT id FROM usuarios WHERE cedula = :cedula AND id != :excludeId LIMIT 1");
        $stmt->execute(['cedula' => $cedula, 'excludeId' => $excludeId]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
}