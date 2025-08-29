<?php

declare(strict_types=1);

namespace Services;

use Models\User;
use PDO;
use Exception;

/**
 * Servicio para la autenticación de usuarios
 */
class AuthService
{
    /**
     * Modelo de usuario
     * @var User
     */
    private $userModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Iniciar sesión
     *
     * @param string $email Email del usuario
     * @param string $password Contraseí±a sin encriptar
     * @return array Resultado de la operación
     */
    public function login($email, $password)
    {
        // Implementación real pendiente: validar contra base de datos y polí­tica de contraseí±as
        // Por seguridad, este método debe integrarse con el modelo User y hashing de contraseí±as.
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Registrar un nuevo usuario
     *
     * @param array $data Datos del usuario
     * @return array Resultado de la operación
     */
    public function register($data)
    {
        // Implementación real pendiente: validar y crear usuario en BD
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Solicitar restablecimiento de contraseí±a
     *
     * @param string $email Email del usuario
     * @return array Resultado de la operación
     */
    public function forgotPassword($email)
    {
        // Implementación real pendiente
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Restablecer contraseí±a
     *
     * @param string $token Token de restablecimiento
     * @param string $password Nueva contraseí±a
     * @return array Resultado de la operación
     */
    public function resetPassword($token, $password)
    {
        // Implementación real pendiente
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Cambiar contraseí±a (usuario autenticado)
     *
     * @param int $userId ID del usuario
     * @param string $currentPassword Contraseí±a actual
     * @param string $newPassword Nueva contraseí±a
     * @return array Resultado de la operación
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword)
    {
        try {
            // Forzar tipo INT en parámetros
            $userId = (int)$userId;
            $db = getDBConnection();

            // Obtener el hash de la contraseña actual del usuario
            $stmt = $db->prepare("SELECT password_hash FROM bt_candidates WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }

            // Verificar que la contraseña actual es correcta
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'La contraseña actual es incorrecta'];
            }

            // Validar la nueva contraseña
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres'];
            }

            // Generar hash para la nueva contraseña
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Actualizar la contraseña en la base de datos
            $updateStmt = $db->prepare("UPDATE bt_candidates SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $result = $updateStmt->execute([$newPasswordHash, $userId]);

            if ($result) {
                return ['success' => true, 'message' => 'Contraseña actualizada correctamente'];
            } else {
                return ['success' => false, 'message' => 'Error al actualizar la contraseña'];
            }
        } catch (Exception $e) {
            error_log("Error en changePassword: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }

    /**
     * Busca o crea un usuario desde login social
     */
    public function findOrCreateUserFromSocialLogin(array $userInfo, string $provider): array
    {
        try {
            $db = getDBConnection();

            // Buscar usuario existente por provider_id
            $stmt = $db->prepare("
                SELECT id, email, name, provider, provider_id
                FROM bt_candidates
                WHERE provider = ? AND provider_id = ?
            ");
            $stmt->execute([$provider, $userInfo['provider_id']]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {
                // Actualizar información del usuario
                $updateStmt = $db->prepare("
                    UPDATE bt_candidates
                    SET name = ?, email = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $userInfo['name'],
                    $userInfo['email'],
                    $existingUser['id']
                ]);

                return $existingUser;
            }

            // Crear nuevo usuario
            $insertStmt = $db->prepare("
                INSERT INTO bt_candidates
                (name, email, provider, provider_id, email_verified, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $insertStmt->execute([
                $userInfo['name'],
                $userInfo['email'],
                $provider,
                $userInfo['provider_id'],
                $userInfo['email_verified'] ? 1 : 0
            ]);

            $newUserId = $db->lastInsertId();

            return [
                'id' => $newUserId,
                'name' => $userInfo['name'],
                'email' => $userInfo['email'],
                'provider' => $provider,
                'provider_id' => $userInfo['provider_id']
            ];
        } catch (Exception $e) {
            error_log("Error in findOrCreateUserFromSocialLogin: " . $e->getMessage());
            throw new Exception('Failed to create or find user');
        }
    }

    /**
     * Genera tokens de autenticación JWT
     */
    public function generateAuthTokens(array $user): array
    {
        // Generar JWT tokens (implementación básica)
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'provider' => $user['provider'],
            'iat' => time(),
            'exp' => time() + (60 * 60) // 1 hora
        ]);

        $secret = getenv('JWT_SECRET') ?: 'default-secret-key';
        $signature = hash_hmac('sha256', $header . "." . base64_encode($payload), $secret);

        $accessToken = $header . "." . base64_encode($payload) . "." . $signature;

        // Generar refresh token
        $refreshPayload = json_encode([
            'user_id' => $user['id'],
            'type' => 'refresh',
            'iat' => time(),
            'exp' => time() + (30 * 24 * 60 * 60) // 30 días
        ]);
        $refreshSignature = hash_hmac('sha256', $header . "." . base64_encode($refreshPayload), $secret);
        $refreshToken = $header . "." . base64_encode($refreshPayload) . "." . $refreshSignature;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * Almacena sesión temporal para tokens OAuth
     */
    public function storeTemporarySession(string $sessionToken, array $authTokens): void
    {
        // Almacenar en cache temporal (puedes usar Redis, Memcached, o base de datos)
        $cacheKey = 'oauth_session_' . $sessionToken;
        $data = json_encode([
            'tokens' => $authTokens,
            'created_at' => time(),
            'expires_at' => time() + 300 // 5 minutos
        ]);

        // Por simplicidad, usar archivo temporal (en producción usa Redis/Memcached)
        $tempFile = sys_get_temp_dir() . '/' . $cacheKey;
        file_put_contents($tempFile, $data);
    }

    /**
     * Obtiene tokens desde sesión temporal
     */
    public function getTokensFromSession(string $sessionToken): ?array
    {
        $cacheKey = 'oauth_session_' . $sessionToken;
        $tempFile = sys_get_temp_dir() . '/' . $cacheKey;

        if (!file_exists($tempFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($tempFile), true);

        // Verificar expiración
        if (!$data || time() > $data['expires_at']) {
            unlink($tempFile); // Limpiar archivo expirado
            return null;
        }

        return $data['tokens'];
    }

    /**
     * Limpia sesión temporal
     */
    public function clearTemporarySession(string $sessionToken): void
    {
        $cacheKey = 'oauth_session_' . $sessionToken;
        $tempFile = sys_get_temp_dir() . '/' . $cacheKey;

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}
