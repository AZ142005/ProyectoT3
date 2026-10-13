<?php
namespace App\Models;

/**
 * Registro de cartas de deuda enviadas por residente.
 *
 * La clave de cooldown es el propietario cuando existe; si la unidad no tiene
 * propietario registrado, la clave pasa a ser la unidad. El bloqueo es por
 * día calendario (DATE(created_at) = CURDATE()), no por 24 horas móviles.
 */
class CartasDeudaEnviosModel extends BaseModel {
    /**
     * Tabla principal del modelo.
     * @var string
     */
    protected string $table = 'cartas_deuda_envios';

    /**
     * Registra un envío de carta de deuda.
     *
     * @param int $unidadId Unidad notificada
     * @param int|null $propietarioId Propietario notificado (null si la unidad no tiene)
     * @param string $canal Canal del envío ('email' por defecto)
     * @param int|null $usuarioId Usuario administrativo que ejecutó el envío
     * @return string|false lastInsertId o false si falló el INSERT
     */
    public function registrarEnvio(int $unidadId, ?int $propietarioId, string $canal = 'email', ?int $usuarioId = null): string|false {
        return $this->create([
            'unidad_id'      => $unidadId,
            'propietario_id' => $propietarioId,
            'canal'          => $canal,
            'usuario_id'     => $usuarioId,
        ]);
    }

    /**
     * Obtiene el último envío dirigido al residente (o a la unidad si no hay propietario).
     *
     * @param int $unidadId Unidad consultada
     * @param int|null $propietarioId Propietario consultado (null para usar la unidad)
     * @return array|null Fila del último envío o null si no existe
     */
    public function obtenerUltimoEnvio(int $unidadId, ?int $propietarioId): ?array {
        $db = $this->db();

        if ($propietarioId !== null) {
            $stmt = $db->prepare("SELECT * FROM {$this->table} WHERE propietario_id = :pid ORDER BY id DESC LIMIT 1");
            $stmt->execute(['pid' => $propietarioId]);
        } else {
            $stmt = $db->prepare("SELECT * FROM {$this->table} WHERE unidad_id = :uid ORDER BY id DESC LIMIT 1");
            $stmt->execute(['uid' => $unidadId]);
        }

        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $fila !== false ? $fila : null;
    }

    /**
     * Indica si ya se envió una carta al residente en el día calendario actual.
     *
     * @param int $unidadId Unidad consultada
     * @param int|null $propietarioId Propietario consultado (null para usar la unidad)
     * @return bool true si existe un envío de hoy para la clave
     */
    public function enviadoHoy(int $unidadId, ?int $propietarioId): bool {
        $db = $this->db();

        if ($propietarioId !== null) {
            $stmt = $db->prepare("SELECT COUNT(*) AS total FROM {$this->table} WHERE propietario_id = :pid AND DATE(created_at) = CURDATE()");
            $stmt->execute(['pid' => $propietarioId]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) AS total FROM {$this->table} WHERE unidad_id = :uid AND DATE(created_at) = CURDATE()");
            $stmt->execute(['uid' => $unidadId]);
        }

        return intval($stmt->fetch()['total'] ?? 0) > 0;
    }
}
