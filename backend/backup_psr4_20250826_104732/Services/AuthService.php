<?php declare(strict_types=1);

namespace Services\AuthService.php\Services;

use Models\User;
use PDO;
use Exception;

/**
 * Servicio para la autenticaciÃ³n de usuarios
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
     * Iniciar sesiÃ³n
     *
     * @param string $email Email del usuario
     * @param string $password ContraseÃ±a sin encriptar
     * @return array Resultado de la operaciÃ³n
     */
    public function login($email, $password)
    {
        // ImplementaciÃ³n real pendiente: validar contra base de datos y polÃ­tica de contraseÃ±as
        // Por seguridad, este mÃ©todo debe integrarse con el modelo User y hashing de contraseÃ±as.
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Registrar un nuevo usuario
     *
     * @param array $data Datos del usuario
     * @return array Resultado de la operaciÃ³n
     */
    public function register($data)
    {
        // ImplementaciÃ³n real pendiente: validar y crear usuario en BD
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Solicitar restablecimiento de contraseÃ±a
     *
     * @param string $email Email del usuario
     * @return array Resultado de la operaciÃ³n
     */
    public function forgotPassword($email)
    {
        // ImplementaciÃ³n real pendiente
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Restablecer contraseÃ±a
     *
     * @param string $token Token de restablecimiento
     * @param string $password Nueva contraseÃ±a
     * @return array Resultado de la operaciÃ³n
     */
    public function resetPassword($token, $password)
    {
        // ImplementaciÃ³n real pendiente
        return ['success' => false, 'message' => 'No implementado'];
    }

    /**
     * Cambiar contraseÃ±a (usuario autenticado)
     *
     * @param int $userId ID del usuario
     * @param string $currentPassword ContraseÃ±a actual
     * @param string $newPassword Nueva contraseÃ±a
     * @return array Resultado de la operaciÃ³n
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        try {
            $db = getDBConnection();

            // Obtener el hash de la contraseÃ±a actual del usuario
            $stmt = $db->prepare("SELECT password_hash FROM bt_candidates WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }

            // Verificar que la contraseÃ±a actual es correcta
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'La contraseÃ±a actual es incorrecta'];
            }

            // Validar la nueva contraseÃ±a
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'La nueva contraseÃ±a debe tener al menos 6 caracteres'];
            }

            // Generar hash para la nueva contraseÃ±a
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Actualizar la contraseÃ±a en la base de datos
            $updateStmt = $db->prepare("UPDATE bt_candidates SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $result = $updateStmt->execute([$newPasswordHash, $userId]);

            if ($result) {
                return ['success' => true, 'message' => 'ContraseÃ±a actualizada correctamente'];
            } else {
                return ['success' => false, 'message' => 'Error al actualizar la contraseÃ±a'];
            }
        } catch (Exception $e) {
            error_log("Error en changePassword: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }
}
