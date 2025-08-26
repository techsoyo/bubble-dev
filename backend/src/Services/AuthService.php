<?php declare(strict_types=1);
namespace Services;

use Models\User;
use PDO;
use Exception;

/**
 * Servicio para la autenticaciÃƒÆ’Ã‚Â³n de usuarios
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
     * Iniciar sesiÃƒÆ’Ã‚Â³n
     *
     * @param string $email Email del usuario
     * @param string $password ContraseÃƒÆ’Ã‚Â±a sin encriptar
     * @return array Resultado de la operaciÃƒÆ’Ã‚Â³n
     */
    public function login($email, $password)
    {
        // ImplementaciÃƒÆ’Ã‚Â³n real pendiente: validar contra base de datos y polÃƒÆ’Ã‚Â­tica de contraseÃƒÆ’Ã‚Â±as
        // Por seguridad, este mÃƒÆ’Ã‚Â©todo debe integrarse con el modelo User y hashing de contraseÃƒÆ’Ã‚Â±as.
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Registrar un nuevo usuario
     *
     * @param array $data Datos del usuario
     * @return array Resultado de la operaciÃƒÆ’Ã‚Â³n
     */
    public function register($data)
    {
        // ImplementaciÃƒÆ’Ã‚Â³n real pendiente: validar y crear usuario en BD
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Solicitar restablecimiento de contraseÃƒÆ’Ã‚Â±a
     *
     * @param string $email Email del usuario
     * @return array Resultado de la operaciÃƒÆ’Ã‚Â³n
     */
    public function forgotPassword($email)
    {
        // ImplementaciÃƒÆ’Ã‚Â³n real pendiente
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Restablecer contraseÃƒÆ’Ã‚Â±a
     *
     * @param string $token Token de restablecimiento
     * @param string $password Nueva contraseÃƒÆ’Ã‚Â±a
     * @return array Resultado de la operaciÃƒÆ’Ã‚Â³n
     */
    public function resetPassword($token, $password)
    {
        // ImplementaciÃƒÆ’Ã‚Â³n real pendiente
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Cambiar contraseÃƒÆ’Ã‚Â±a (usuario autenticado)
     *
     * @param int $userId ID del usuario
     * @param string $currentPassword ContraseÃƒÆ’Ã‚Â±a actual
     * @param string $newPassword Nueva contraseÃƒÆ’Ã‚Â±a
     * @return array Resultado de la operaciÃƒÆ’Ã‚Â³n
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        try {
            $db = getDBConnection();

            // Obtener el hash de la contraseÃƒÆ’Ã‚Â±a actual del usuario
            $stmt = $db->prepare("SELECT password_hash FROM bt_candidates WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }

            // Verificar que la contraseÃƒÆ’Ã‚Â±a actual es correcta
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'La contraseÃƒÆ’Ã‚Â±a actual es incorrecta'];
            }

            // Validar la nueva contraseÃƒÆ’Ã‚Â±a
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'La nueva contraseÃƒÆ’Ã‚Â±a debe tener al menos 6 caracteres'];
            }

            // Generar hash para la nueva contraseÃƒÆ’Ã‚Â±a
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Actualizar la contraseÃƒÆ’Ã‚Â±a en la base de datos
            $updateStmt = $db->prepare("UPDATE bt_candidates SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $result = $updateStmt->execute([$newPasswordHash, $userId]);

            if ($result) {
                return ['success' => true, 'message' => 'ContraseÃƒÆ’Ã‚Â±a actualizada correctamente'];
            } else {
                return ['success' => false, 'message' => 'Error al actualizar la contraseÃƒÆ’Ã‚Â±a'];
            }
        } catch (Exception $e) {
            error_log("Error en changePassword: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }
}
