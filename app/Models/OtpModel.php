<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class OtpModel extends BaseModel {

    private ?PDO $customDb = null;

    public function __construct(?PDO $db = null) {
        $this->customDb = $db;
    }

    protected function db(): PDO {
        return $this->customDb ?? Database::getConnection();
    }

    /**
     * Genera un código OTP de 6 dígitos, invalida los anteriores no usados y lo persiste con hash.
     *
     * @param int $usuarioId
     * @return string Código numérico de 6 dígitos en texto plano para el envío
     */
    public function generarOtp(int $usuarioId): string {
        $db = $this->db();

        // 1. Invalidar códigos previos pendientes para este usuario
        $stmtClean = $db->prepare("UPDATE auth_otp_tokens SET usado = 1 WHERE usuario_id = :uid AND usado = 0");
        $stmtClean->execute(['uid' => $usuarioId]);

        // 2. Generar nuevo código de 6 dígitos y hash criptográfico
        $codigo = sprintf('%06d', random_int(100000, 999999));
        $codigoHash = password_hash($codigo, PASSWORD_BCRYPT);

        // 3. Insertar con tiempo de expiración de 5 minutos
        $stmt = $db->prepare("
            INSERT INTO auth_otp_tokens (usuario_id, codigo_hash, intentos, usado, expires_at)
            VALUES (:uid, :hash, 0, 0, DATE_ADD(NOW(), INTERVAL 5 MINUTE))
        ");
        $stmt->execute([
            'uid'  => $usuarioId,
            'hash' => $codigoHash
        ]);

        return $codigo;
    }

    /**
     * Verifica un código OTP ingresado por el usuario con control de 3 intentos y expiración.
     *
     * @param int $usuarioId
     * @param string $codigoIngresado
     * @return array ['valido' => bool, 'error' => ?string]
     */
    public function verificarOtp(int $usuarioId, string $codigoIngresado): array {
        $db = $this->db();

        $stmt = $db->prepare("
            SELECT *, CASE WHEN NOW() > expires_at THEN 1 ELSE 0 END AS expirado
            FROM auth_otp_tokens 
            WHERE usuario_id = :uid AND usado = 0 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['uid' => $usuarioId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'valido' => false,
                'error'  => 'No hay un código de verificación activo. Por favor, haga clic en "Reenviar Código".'
            ];
        }

        // Verificar expiración temporal evaluada contra el reloj del motor SQL (5 minutos)
        $expirado = array_key_exists('expirado', $row)
            ? (intval($row['expirado']) === 1)
            : (strtotime($row['expires_at']) < time());

        if ($expirado) {
            $stmtExpire = $db->prepare("UPDATE auth_otp_tokens SET usado = 1 WHERE id = :id");
            $stmtExpire->execute(['id' => $row['id']]);

            return [
                'valido' => false,
                'error'  => 'El código de verificación ha expirado. Por favor, solicite un nuevo código.'
            ];
        }

        // Comprobar coincidencia con password_verify
        if (password_verify($codigoIngresado, $row['codigo_hash'])) {
            // Marcar como consumido atómicamente — previene race condition
            $stmtOk = $db->prepare("UPDATE auth_otp_tokens SET usado = 1 WHERE id = :id AND usado = 0");
            $stmtOk->execute(['id' => $row['id']]);

            if ($stmtOk->rowCount() === 0) {
                return [
                    'valido' => false,
                    'error'  => 'El código ya fue utilizado. Por favor, solicite un nuevo código.'
                ];
            }

            return ['valido' => true, 'error' => null];
        }

        // Código incorrecto: Incrementar contador de intentos
        $nuevosIntentos = $row['intentos'] + 1;
        if ($nuevosIntentos >= 3) {
            // Límite de 3 intentos alcanzado: invalidar
            $stmtFail = $db->prepare("UPDATE auth_otp_tokens SET intentos = :intentos, usado = 1 WHERE id = :id");
            $stmtFail->execute(['intentos' => $nuevosIntentos, 'id' => $row['id']]);

            return [
                'valido' => false,
                'error'  => 'Ha superado el límite de 3 intentos fallidos. El código ha sido invalidado. Haga clic en "Reenviar Código" para recibir uno nuevo.'
            ];
        }

        $stmtInc = $db->prepare("UPDATE auth_otp_tokens SET intentos = :intentos WHERE id = :id");
        $stmtInc->execute(['intentos' => $nuevosIntentos, 'id' => $row['id']]);

        return [
            'valido' => false,
            'error'  => "Código de verificación incorrecto. Intento {$nuevosIntentos} de 3."
        ];
    }
}
