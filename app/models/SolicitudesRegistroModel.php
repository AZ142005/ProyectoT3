<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class SolicitudesRegistroModel extends BaseModel {
    protected string $table = 'solicitudes_registro';

    /**
     * Registra una nueva solicitud de acceso con verificación concurrente de disponibilidad.
     *
     * @param array $data
     * @return int ID de la solicitud creada
     * @throws \RuntimeException Si la unidad ya no está disponible o hay colisión
     */
    public function crearSolicitud(array $data): int {
        $db = $this->db();
        $inTransactionExternally = $db->inTransaction();
        if (!$inTransactionExternally) {
            $db->beginTransaction();
        }

        try {
            $unidadId = intval($data['unidad_id'] ?? 0);

            // Bloqueo pesimista sobre la unidad para evitar condiciones de carrera
            $stmtU = $db->prepare("
                SELECT id, estado, propietario_id 
                FROM unidades 
                WHERE id = :id 
                FOR UPDATE
            ");
            $stmtU->execute(['id' => $unidadId]);
            $unidad = $stmtU->fetch(PDO::FETCH_ASSOC);

            if (!$unidad || (int)$unidad['estado'] !== 1 || !empty($unidad['propietario_id'])) {
                throw new \RuntimeException('El apartamento seleccionado ya no se encuentra disponible.');
            }

            // Comprobar que no existan residentes activos en la unidad
            $stmtP = $db->prepare("SELECT COUNT(*) FROM personas WHERE unidad_id = :id AND estado = 1");
            $stmtP->execute(['id' => $unidadId]);
            if ((int)$stmtP->fetchColumn() > 0) {
                throw new \RuntimeException('El apartamento seleccionado ya posee residentes asignados.');
            }

            // Comprobar que no exista otra solicitud en estado pendiente para esta unidad
            $stmtS = $db->prepare("
                SELECT COUNT(*) 
                FROM solicitudes_registro 
                WHERE unidad_id = :id AND estado = 'pendiente'
            ");
            $stmtS->execute(['id' => $unidadId]);
            if ((int)$stmtS->fetchColumn() > 0) {
                throw new \RuntimeException('Existe una solicitud previa en revisión para este apartamento.');
            }

            // Comprobar que la cédula o email no tengan ya una solicitud pendiente
            $stmtCed = $db->prepare("
                SELECT COUNT(*) 
                FROM solicitudes_registro 
                WHERE cedula = :cedula AND estado = 'pendiente'
            ");
            $stmtCed->execute(['cedula' => $data['cedula']]);
            if ((int)$stmtCed->fetchColumn() > 0) {
                throw new \RuntimeException('Ya existe una solicitud pendiente de aprobación para esta cédula.');
            }

            $stmtEm = $db->prepare("
                SELECT COUNT(*) 
                FROM solicitudes_registro 
                WHERE LOWER(email) = LOWER(:email) AND estado = 'pendiente'
            ");
            $stmtEm->execute(['email' => $data['email']]);
            if ((int)$stmtEm->fetchColumn() > 0) {
                throw new \RuntimeException('Ya existe una solicitud pendiente de aprobación asociada a este correo electrónico.');
            }

            // Inserción de la solicitud en estado 'pendiente'
            $stmtInsert = $db->prepare("
                INSERT INTO solicitudes_registro (
                    cedula, nombre, apellido, telefono, email, 
                    unidad_id, numero_residentes, password_hash, estado
                ) VALUES (
                    :cedula, :nombre, :apellido, :telefono, :email, 
                    :unidad_id, :numero_residentes, :password_hash, 'pendiente'
                )
            ");
            $stmtInsert->execute([
                'cedula'            => $data['cedula'],
                'nombre'            => $data['nombre'],
                'apellido'          => $data['apellido'],
                'telefono'          => $data['telefono'],
                'email'             => $data['email'],
                'unidad_id'         => $unidadId,
                'numero_residentes' => max(1, intval($data['numero_residentes'] ?? 1)),
                'password_hash'     => $data['password_hash'],
            ]);

            $solicitudId = (int)$db->lastInsertId();

            if (!$inTransactionExternally) {
                $db->commit();
            }

            return $solicitudId;
        } catch (\Exception $e) {
            if (!$inTransactionExternally && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Obtiene las solicitudes de registro paginadas con detalles de edificio y unidad.
     *
     * @param int $pagina
     * @param int $porPagina
     * @param string|null $filtroEstado 'pendiente', 'aprobada', 'rechazada' o null para todas
     * @return array ['datos' => [], 'total' => int, 'pagina' => int, 'porPagina' => int, 'totalPaginas' => int]
     */
    public function obtenerListado(int $pagina = 1, int $porPagina = 15, ?string $filtroEstado = 'pendiente'): array {
        $db = $this->db();
        $offset = max(0, ($pagina - 1) * $porPagina);

        $where = "1=1";
        $params = [];
        if (!empty($filtroEstado)) {
            $where .= " AND sr.estado = :estado";
            $params['estado'] = $filtroEstado;
        }

        // Conteo total
        $countSql = "SELECT COUNT(*) FROM solicitudes_registro sr WHERE {$where}";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        // Obtener datos
        $dataSql = "
            SELECT sr.*, u.numero as unidad_numero, e.nombre as edificio_nombre,
                   adm.nombre_completo as admin_nombre
            FROM solicitudes_registro sr
            LEFT JOIN unidades u ON sr.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN usuarios adm ON sr.admin_id = adm.id
            WHERE {$where}
            ORDER BY sr.created_at DESC
            LIMIT {$porPagina} OFFSET {$offset}
        ";
        $stmtData = $db->prepare($dataSql);
        $stmtData->execute($params);
        $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        $totalPaginas = $total > 0 ? (int)ceil($total / $porPagina) : 1;

        return [
            'datos'        => $datos,
            'total'        => $total,
            'pagina'       => $pagina,
            'porPagina'    => $porPagina,
            'totalPaginas' => $totalPaginas,
        ];
    }

    /**
     * Obtiene una solicitud por su ID con información de la unidad y edificio.
     *
     * @param int $id
     * @return array|null
     */
    public function getDetalleById(int $id): ?array {
        $stmt = $this->db()->prepare("
            SELECT sr.*, u.numero as unidad_numero, e.nombre as edificio_nombre,
                   adm.nombre_completo as admin_nombre
            FROM solicitudes_registro sr
            LEFT JOIN unidades u ON sr.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN usuarios adm ON sr.admin_id = adm.id
            WHERE sr.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Genera candidatos de búsqueda para cédula.
     *
     * @param string $identificador
     * @return array
     */
    private function generarCandidatosCedula(string $identificador): array {
        $candidatos = [];
        $talCual = trim($identificador);
        if ($talCual !== '') {
            $candidatos[] = $talCual;
        }

        if (function_exists('normalizarCedula')) {
            $norm = normalizarCedula($identificador);
        } else {
            $norm = strtoupper(preg_replace('/[\s\.\-]/', '', $identificador));
        }
        if ($norm !== '') {
            $candidatos[] = $norm;
        }

        $soloDigitos = preg_replace('/\D/', '', $identificador);
        if (strlen($soloDigitos) >= 4) {
            $candidatos[] = $soloDigitos;
            $candidatos[] = 'V' . $soloDigitos;
            $candidatos[] = 'E' . $soloDigitos;
            $candidatos[] = 'V-' . $soloDigitos;
            $candidatos[] = 'E-' . $soloDigitos;
        }

        return array_values(array_unique($candidatos));
    }

    /**
     * Busca una solicitud pendiente por cédula o correo.
     *
     * @param string $identificador
     * @return array|null
     */
    public function buscarPendientePorIdentificador(string $identificador): ?array {
        $candidatos = $this->generarCandidatosCedula($identificador);
        if (empty($candidatos)) {
            $candidatos = [trim($identificador)];
        }

        $placeholders = [];
        $params = [':email' => trim($identificador)];
        foreach ($candidatos as $idx => $cand) {
            $ph = ":ced_{$idx}";
            $placeholders[] = $ph;
            $params[$ph] = $cand;
        }
        $inClause = implode(', ', $placeholders);

        $sql = 'SELECT * FROM solicitudes_registro '
             . 'WHERE (cedula IN (' . $inClause . ') OR LOWER(email) = LOWER(:email)) '
             . 'AND estado = \'pendiente\' '
             . 'ORDER BY id DESC LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca la última solicitud (de cualquier estado) por cédula o correo.
     *
     * @param string $identificador
     * @return array|null
     */
    public function buscarUltimaPorIdentificador(string $identificador): ?array {
        $candidatos = $this->generarCandidatosCedula($identificador);
        if (empty($candidatos)) {
            $candidatos = [trim($identificador)];
        }

        $placeholders = [];
        $params = [':email' => trim($identificador)];
        foreach ($candidatos as $idx => $cand) {
            $ph = ":ced_{$idx}";
            $placeholders[] = $ph;
            $params[$ph] = $cand;
        }
        $inClause = implode(', ', $placeholders);

        $sql = 'SELECT * FROM solicitudes_registro '
             . 'WHERE (cedula IN (' . $inClause . ') OR LOWER(email) = LOWER(:email)) '
             . 'ORDER BY id DESC LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }


    /**
     * Aprueba formalmente una solicitud de registro:
     * - Bloquea la solicitud y la unidad con FOR UPDATE.
     * - Inserta o reactiva la persona en 'personas' con estado=1 y su password.
     * - Asigna la unidad y titularidad de propietario en 'unidades'.
     * - Cambia estado a 'aprobada' y audita en 'log_auditoria'.
     *
     * @param int $solicitudId
     * @param int $adminId
     * @param string $ipAddress
     * @return bool
     * @throws \RuntimeException
     */
    public function aprobar(int $solicitudId, int $adminId, string $ipAddress = ''): bool {
        $db = $this->db();
        $db->beginTransaction();

        try {
            // 1. Bloquear y verificar solicitud
            $stmtS = $db->prepare("SELECT * FROM solicitudes_registro WHERE id = :id FOR UPDATE");
            $stmtS->execute(['id' => $solicitudId]);
            $solicitud = $stmtS->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                throw new \RuntimeException('La solicitud indicada no existe.');
            }
            if ($solicitud['estado'] !== 'pendiente') {
                throw new \RuntimeException("La solicitud ya fue procesada anteriormente ({$solicitud['estado']}).");
            }

            // 2. Bloquear y verificar unidad
            $stmtU = $db->prepare("SELECT * FROM unidades WHERE id = :id FOR UPDATE");
            $stmtU->execute(['id' => $solicitud['unidad_id']]);
            $unidad = $stmtU->fetch(PDO::FETCH_ASSOC);

            if (!$unidad || (int)$unidad['estado'] !== 1) {
                throw new \RuntimeException('La unidad asociada no existe o se encuentra inactiva.');
            }

            // 3. Crear o reactivar habitante en 'personas'
            $stmtCheckP = $db->prepare("SELECT id, estado FROM personas WHERE cedula = :cedula FOR UPDATE");
            $stmtCheckP->execute(['cedula' => $solicitud['cedula']]);
            $personaExistente = $stmtCheckP->fetch(PDO::FETCH_ASSOC);

            $personaId = 0;
            if ($personaExistente) {
                if ((int)$personaExistente['estado'] === 1) {
                    throw new \RuntimeException('Ya existe un habitante activo con esta cédula de identidad.');
                }
                // Reactivación
                $personaId = (int)$personaExistente['id'];
                $stmtUpdateP = $db->prepare("
                    UPDATE personas SET 
                        nombre = :nombre,
                        apellido = :apellido,
                        telefono = :telefono,
                        email = :email,
                        unidad_id = :unidad_id,
                        tipo = 'propietario',
                        numero_residentes = :num_res,
                        password = :password,
                        estado = 1
                    WHERE id = :id
                ");
                $stmtUpdateP->execute([
                    'nombre'    => $solicitud['nombre'],
                    'apellido'  => $solicitud['apellido'],
                    'telefono'  => $solicitud['telefono'],
                    'email'     => $solicitud['email'],
                    'unidad_id' => $solicitud['unidad_id'],
                    'num_res'   => $solicitud['numero_residentes'],
                    'password'  => $solicitud['password_hash'],
                    'id'        => $personaId
                ]);
            } else {
                // Inserción nueva
                $stmtInsertP = $db->prepare("
                    INSERT INTO personas (
                        cedula, nombre, apellido, telefono, email,
                        unidad_id, tipo, numero_residentes, password, estado
                    ) VALUES (
                        :cedula, :nombre, :apellido, :telefono, :email,
                        :unidad_id, 'propietario', :num_res, :password, 1
                    )
                ");
                $stmtInsertP->execute([
                    'cedula'    => $solicitud['cedula'],
                    'nombre'    => $solicitud['nombre'],
                    'apellido'  => $solicitud['apellido'],
                    'telefono'  => $solicitud['telefono'],
                    'email'     => $solicitud['email'],
                    'unidad_id' => $solicitud['unidad_id'],
                    'num_res'   => $solicitud['numero_residentes'],
                    'password'  => $solicitud['password_hash'],
                ]);
                $personaId = (int)$db->lastInsertId();
            }

            // 4. Asignar titular de la unidad si estaba vacante
            if (empty($unidad['propietario_id'])) {
                $stmtSetProp = $db->prepare("UPDATE unidades SET propietario_id = :pid WHERE id = :uid");
                $stmtSetProp->execute(['pid' => $personaId, 'uid' => $solicitud['unidad_id']]);
            }

            // 5. Marcar solicitud como aprobada
            $stmtAprobar = $db->prepare("
                UPDATE solicitudes_registro 
                SET estado = 'aprobada', admin_id = :admin_id, reviewed_at = NOW() 
                WHERE id = :id
            ");
            $stmtAprobar->execute([
                'admin_id' => $adminId,
                'id'       => $solicitudId
            ]);

            // 6. Registro de Auditoría Estricto
            $stmtAudit = $db->prepare("
                INSERT INTO log_auditoria (
                    admin_id, usuario_id, accion, tabla_afectada, 
                    registro_id, detalles, ip_address, estado_anterior, estado_nuevo
                ) VALUES (
                    :admin_id, :usuario_id, 'APROBACION_REGISTRO', 'solicitudes_registro',
                    :reg_id, :detalles, :ip, 'pendiente', 'aprobada'
                )
            ");
            $stmtAudit->execute([
                'admin_id'   => $adminId,
                'usuario_id' => $personaId,
                'reg_id'     => $solicitudId,
                'detalles'   => "Solicitud de registro aprobada para {$solicitud['nombre']} {$solicitud['apellido']} (CI: {$solicitud['cedula']}). Unidad ID: {$solicitud['unidad_id']}.",
                'ip'         => $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
            ]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Rechaza una solicitud de registro.
     *
     * @param int $solicitudId
     * @param int $adminId
     * @param string|null $motivo
     * @param string $ipAddress
     * @return bool
     * @throws \RuntimeException
     */
    public function rechazar(int $solicitudId, int $adminId, ?string $motivo = null, string $ipAddress = ''): bool {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $stmtS = $db->prepare("SELECT * FROM solicitudes_registro WHERE id = :id FOR UPDATE");
            $stmtS->execute(['id' => $solicitudId]);
            $solicitud = $stmtS->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                throw new \RuntimeException('La solicitud indicada no existe.');
            }
            if ($solicitud['estado'] !== 'pendiente') {
                throw new \RuntimeException("La solicitud ya fue procesada anteriormente ({$solicitud['estado']}).");
            }

            $motivoLimpio = trim($motivo ?? '') ?: 'No cumple con los requisitos del condominio';

            $stmtRechazar = $db->prepare("
                UPDATE solicitudes_registro 
                SET estado = 'rechazada', admin_id = :admin_id, motivo_rechazo = :motivo, reviewed_at = NOW() 
                WHERE id = :id
            ");
            $stmtRechazar->execute([
                'admin_id' => $adminId,
                'motivo'   => $motivoLimpio,
                'id'       => $solicitudId
            ]);

            // Auditoría
            $stmtAudit = $db->prepare("
                INSERT INTO log_auditoria (
                    admin_id, accion, tabla_afectada, 
                    registro_id, detalles, ip_address, estado_anterior, estado_nuevo
                ) VALUES (
                    :admin_id, 'RECHAZO_REGISTRO', 'solicitudes_registro',
                    :reg_id, :detalles, :ip, 'pendiente', 'rechazada'
                )
            ");
            $stmtAudit->execute([
                'admin_id' => $adminId,
                'reg_id'   => $solicitudId,
                'detalles' => "Solicitud de registro rechazada para {$solicitud['nombre']} {$solicitud['apellido']} (CI: {$solicitud['cedula']}). Motivo: {$motivoLimpio}",
                'ip'       => $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
            ]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cuenta cuántas solicitudes pendientes existen en tiempo real.
     */
    public function contarPendientes(): int {
        return (int)$this->db()->query("SELECT COUNT(*) FROM solicitudes_registro WHERE estado = 'pendiente'")->fetchColumn();
    }
}
