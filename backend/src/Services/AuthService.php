<?php

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
     * @param string $password Contraseña sin encriptar
     * @return array Resultado de la operación
     */
    public function login($email, $password)
    {
        // Implementación real pendiente: validar contra base de datos y política de contraseñas
        // Por seguridad, este método debe integrarse con el modelo User y hashing de contraseñas.
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
     * Solicitar restablecimiento de contraseña
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
     * Restablecer contraseña
     *
     * @param string $token Token de restablecimiento
     * @param string $password Nueva contraseña
     * @return array Resultado de la operación
     */
    public function resetPassword($token, $password)
    {
        // Implementación real pendiente
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Cambiar contraseña (usuario autenticado)
     *
     * @param int $userId ID del usuario
     * @param string $currentPassword Contraseña actual
     * @param string $newPassword Nueva contraseña
     * @return array Resultado de la operación
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        try {
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
}
