<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\JWT;
use App\Core\AuthMiddleware;
use App\Core\Database;
use App\Core\UserRole;
use App\Core\RateLimiter;
use App\Models\UsuariosModel;
use App\Models\PersonasModel;
use App\Models\MovimientosModel;
use App\Models\OtpModel;
use PDO;

class ApiController extends Controller {

    /**
     * Endpoint de autenticación REST: emite Access Token JWT y Refresh Token.
     */
    public function login() {
        // Rate limiting: máximo 5 intentos cada 15 minutos (basado en IP)
        $rateKey = 'api_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!RateLimiter::attempt($rateKey, 5, 900)) {
            $this->json([
                'success' => false,
                'error'   => 'Demasiados intentos. Intente de nuevo más tarde.'
            ], 429);
            return;
        }

        // Límite de tamaño del body JSON: 10KB
        $rawBody = file_get_contents('php://input');
        if (strlen($rawBody) > 10240) {
            $this->json(['success' => false, 'error' => 'Payload demasiado grande.'], 413);
            return;
        }

        $input = json_decode($rawBody, true) ?? $_POST;
        $email = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($email) || empty($password)) {
            $this->json([
                'success' => false,
                'error'   => 'Debe proporcionar email/cédula y contraseña.'
            ], 400);
            return;
        }

        $db = Database::getConnection();
        $user = null;
        $role = null;
        $admin = null;
        $residente = null;

        // 1. Buscar en usuarios (Admin / Auditor)
        $usuariosModel = new UsuariosModel();
        $admin = $usuariosModel->getActiveByEmail($email);
        if ($admin && password_verify($password, $admin['password'])) {
            $user = [
                'id'     => (int)$admin['id'],
                'nombre' => $admin['nombre_completo'],
                'email'  => $admin['email'] ?? $admin['usuario'],
                'role'   => $admin['rol'] ?? UserRole::ADMIN
            ];
            $role = $user['role'];
        }

        // 2. Buscar en personas (Residente)
        if (!$user) {
            $personasModel = new PersonasModel();
            $residente = filter_var($email, FILTER_VALIDATE_EMAIL)
                ? $personasModel->getActiveByEmail($email)
                : $personasModel->getActiveByCedula($email);

            if ($residente && !empty($residente['password']) && password_verify($password, $residente['password'])) {
                $user = [
                    'id'         => (int)$residente['id'],
                    'persona_id' => (int)$residente['id'],
                    'nombre'     => trim($residente['nombre'] . ' ' . $residente['apellido']),
                    'email'      => $residente['email'],
                    'role'       => UserRole::RESIDENTE
                ];
                $role = UserRole::RESIDENTE;
            }
        }

        if (!$user) {
            $this->json([
                'success' => false,
                'error'   => 'Credenciales inválidas.'
            ], 401);
            return;
        }

        // Verificar 2FA si está habilitado para el usuario
        $twoFactorEnabled = false;
        if ($role === UserRole::RESIDENTE) {
            $twoFactorEnabled = !empty($residente['two_factor_enabled'] ?? false);
        } else {
            $twoFactorEnabled = !empty($admin['two_factor_enabled'] ?? false);
        }

        if ($twoFactorEnabled) {
            $otpModel = new OtpModel();
            $otp = $otpModel->generarOtp($user['id']);

            $this->json([
                'success'    => false,
                'requires_2fa' => true,
                'message'    => 'Se ha enviado un código de verificación a su correo.',
                'otp_sent'   => true
            ], 428);
            return;
        }

        RateLimiter::clear($rateKey);

        // 3. Generar Access Token JWT (Vigencia: 2 horas = 7200 seg)
        $accessToken = JWT::encode([
            'sub'   => $user['id'],
            'email' => $user['email'],
            'role'  => $role,
            'name'  => $user['nombre']
        ], 7200);

        // 4. Generar Refresh Token criptográfico (Vigencia: 7 días)
        $refreshTokenPlain = bin2hex(random_bytes(32));
        $refreshTokenHash = hash('sha256', $refreshTokenPlain);

        $stmtRef = $db->prepare("
            INSERT INTO refresh_tokens (usuario_id, token_hash, expires_at, revocado)
            VALUES (:uid, :thash, DATE_ADD(NOW(), INTERVAL 7 DAY), 0)
        ");
        $stmtRef->execute([
            'uid'   => $user['id'],
            'thash' => $refreshTokenHash
        ]);

        $this->json([
            'success'       => true,
            'token_type'    => 'Bearer',
            'access_token'  => $accessToken,
            'expires_in'    => 7200,
            'refresh_token' => $refreshTokenPlain,
            'user'          => $user
        ], 200);
    }

    /**
     * Endpoint de renovación: emite un nuevo Access Token validando el Refresh Token.
     */
    public function refresh() {
        $rawBody = file_get_contents('php://input');
        if (strlen($rawBody) > 10240) {
            $this->json(['success' => false, 'error' => 'Payload demasiado grande.'], 413);
            return;
        }

        $input = json_decode($rawBody, true) ?? $_POST;
        $refreshToken = trim($input['refresh_token'] ?? '');

        if (empty($refreshToken)) {
            $this->json([
                'success' => false,
                'error'   => 'Parámetro refresh_token obligatorio.'
            ], 400);
            return;
        }

        $db = Database::getConnection();
        $tokenHash = hash('sha256', $refreshToken);

        $stmt = $db->prepare("
            SELECT * FROM refresh_tokens 
            WHERE token_hash = :thash 
            LIMIT 1
        ");
        $stmt->execute(['thash' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Validaciones estrictas: existencia, no revocado y fecha de expiración
        if (!$row) {
            $this->json(['success' => false, 'error' => 'Refresh token no reconocido.'], 401);
            return;
        }

        if ((int)$row['revocado'] === 1) {
            $this->json(['success' => false, 'error' => 'El refresh token ha sido revocado. Inicie sesión nuevamente.'], 401);
            return;
        }

        if (strtotime($row['expires_at']) < time()) {
            $this->json(['success' => false, 'error' => 'El refresh token ha expirado.'], 401);
            return;
        }

        // Revocar el refresh token usado (rotation)
        $stmtRevoke = $db->prepare("UPDATE refresh_tokens SET revocado = 1 WHERE id = :id");
        $stmtRevoke->execute(['id' => $row['id']]);

        // Obtener datos del usuario para el nuevo payload — verificar que exista y esté activo
        $stmtU = $db->prepare("SELECT id, email, rol, nombre_completo, estado FROM usuarios WHERE id = :id AND estado = 1");
        $stmtU->execute(['id' => $row['usuario_id']]);
        $usuario = $stmtU->fetch(PDO::FETCH_ASSOC);

        $email = null;
        $role = null;
        $name = null;

        if ($usuario) {
            $email = $usuario['email'];
            $role = $usuario['rol'];
            $name = $usuario['nombre_completo'];
        } else {
            $stmtP = $db->prepare("SELECT id, email, CONCAT(nombre, ' ', apellido) AS nombre_completo, estado FROM personas WHERE id = :id AND estado = 1");
            $stmtP->execute(['id' => $row['usuario_id']]);
            $persona = $stmtP->fetch(PDO::FETCH_ASSOC);
            if ($persona) {
                $email = $persona['email'];
                $role = UserRole::RESIDENTE;
                $name = $persona['nombre_completo'];
            }
        }

        if (!$email) {
            $this->json(['success' => false, 'error' => 'El usuario ya no existe o está desactivado.'], 401);
            return;
        }

        $newAccessToken = JWT::encode([
            'sub'   => (int)$row['usuario_id'],
            'email' => $email,
            'role'  => $role,
            'name'  => $name
        ], 7200);

        // Generar nuevo refresh token (rotation)
        $newRefreshPlain = bin2hex(random_bytes(32));
        $newRefreshHash = hash('sha256', $newRefreshPlain);
        $stmtNewRef = $db->prepare("
            INSERT INTO refresh_tokens (usuario_id, token_hash, expires_at, revocado)
            VALUES (:uid, :thash, DATE_ADD(NOW(), INTERVAL 7 DAY), 0)
        ");
        $stmtNewRef->execute([
            'uid'   => (int)$row['usuario_id'],
            'thash' => $newRefreshHash
        ]);

        $this->json([
            'success'      => true,
            'token_type'   => 'Bearer',
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshPlain,
            'expires_in'   => 7200
        ], 200);
    }

    /**
     * Endpoint protegido: Consulta el estado de cuenta y balance para clientes API.
     */
    public function estadoCuenta() {
        // Middleware de validación JWT
        $payload = AuthMiddleware::validarJWT();

        // IDOR fix: solo residentes pueden consultar su propio estado de cuenta
        if (($payload['role'] ?? '') !== UserRole::RESIDENTE) {
            $this->json(['success' => false, 'error' => 'Solo residentes pueden consultar su estado de cuenta.'], 403);
            return;
        }

        $personaId = (int)$payload['sub'];

        $db = Database::getConnection();
        $stmtU = $db->prepare("SELECT id, numero FROM unidades WHERE propietario_id = :pid LIMIT 1");
        $stmtU->execute(['pid' => $personaId]);
        $unidad = $stmtU->fetch(PDO::FETCH_ASSOC);

        if (!$unidad) {
            $this->json([
                'success'      => true,
                'saldo_actual' => 0.00,
                'unidad'       => null,
                'movimientos'  => []
            ]);
            return;
        }

        $movimientosModel = new MovimientosModel();
        $saldo = $movimientosModel->obtenerSaldoActualUnidad((int)$unidad['id']);
        $historial = $movimientosModel->obtenerHistorialUnidad((int)$unidad['id'], 1, 10);

        $this->json([
            'success'      => true,
            'unidad'       => $unidad,
            'saldo_actual' => $saldo,
            'movimientos'  => $historial['datos']
        ]);
    }
}
