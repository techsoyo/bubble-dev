<?php

namespace Controllers;

use Middleware\AuthMiddleware;
use Models\User;
use Services\AuthService;
use Utils\JWT;
use Utils\Request;

/**
 * Controlador para la autenticación de usuarios
 */
class AuthController extends BaseController
{
    private $authService;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    /**
     * Iniciar sesión
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function login(Request $request)
    {
        $body = $request->getBody();

        if (!isset($body['email'], $body['password'])) {
            $this->error('Email and password are required', null, 400);
            return;
        }

        $email = $body['email'];
        $password = $body['password'];

        // Buscar usuario real en la base de datos
        $userModel = new \Models\User();
        $user = $userModel->findByEmail($email);
        if (!$user || !isset($user['password'])) {
            $this->error('Invalid credentials', null, 401);
            return;
        }

        // Verificar contraseña usando password_verify (bcrypt)
        if (!password_verify($password, $user['password'])) {
            $this->error('Invalid credentials', null, 401);
            return;
        }

        // No enviar password en la respuesta
        unset($user['password']);

        // Generar JWT seguro
        $token = \Utils\JWT::generate([
          'user_id' => $user['id'],
          'role' => $user['role'],
          'email' => $user['email'],
          'name' => $user['name'] ?? null
        ]);

        $this->success('Login successful', [
          'user' => $user,
          'token' => $token
        ]);
    }

    /**
     * Verificar token
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function verify(Request $request)
    {
        $authHeader = $request->getHeader('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $this->error('No token provided', null, 401);
            return;
        }
        $token = $matches[1];
        $payload = \Utils\JWT::verify($token);
        if (!$payload) {
            $this->error('Invalid or expired token', null, 401);
            return;
        }
        $this->success('Token valid', [
          'valid' => true,
          'user_id' => $payload['user_id'] ?? null,
          'role' => $payload['role'] ?? null,
          'email' => $payload['email'] ?? null,
          'name' => $payload['name'] ?? null
        ]);
    }

    /**
     * Registrar un nuevo usuario
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function register(Request $request)
    {
        // Validar datos de entrada
        $data = $this->validate($request, [
          'name' => 'required',
          'email' => 'required|email',
          'password' => 'required|min:6',
          'password_confirmation' => 'required|matches:password',
          'role' => 'required'
        ]);

        if (!$data) {
            return;
        }

        // Intentar registrar el usuario
        $result = $this->authService->register($data);

        if (!$result['success']) {
            $this->error($result['message']);
            return;
        }

        // Devolver token y datos del usuario
        $this->success('Registro exitoso', $result['data'], 201);
    }

    /**
     * Obtener información del usuario actual
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function me(Request $request)
    {
        AuthMiddleware::handle($request);
        try {
            // Obtener los datos del usuario de la solicitud
            $userData = $request->getUser();
            if (!$userData || !isset($userData['sub'])) {
                $this->error('Token inválido o usuario no autenticado', null, 401);
                return;
            }
            $userId = $userData['sub'];

            // Obtener datos completos del usuario desde la base de datos
            $userModel = new User();
            $user = $userModel->findById($userId);

            if (!$user) {
                $this->error('Usuario no encontrado', null, 404);
                return;
            }

            // Eliminar campos sensibles
            unset($user['password']);

            $this->success('Datos del usuario', $user);
        } catch (\Exception $e) {
            $this->error('Error interno', $e->getMessage(), 500);
        }
    }

    /**
     * Cerrar sesión
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function logout(Request $request)
    {
        AuthMiddleware::handle($request);
        // Expirar la cookie 'token' eliminándola del navegador
        // Al eliminar la cookie del token, solo marcamos la bandera "secure"
        // cuando la solicitud actual se realiza bajo HTTPS.  Esto permite
        // mantener compatibilidad con entornos locales sin TLS, tal como se
        // recomienda en la auditoría.
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('token', '', [
          'expires' => time() - 3600,
          'path' => '/',
          'secure' => $isHttps,
          'httponly' => true,
          'samesite' => 'Strict',
        ]);

        $this->success('Sesión cerrada correctamente');
    }

    /**
     * Solicitar restablecimiento de contraseña
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function forgotPassword(Request $request)
    {
        // Validar datos de entrada
        $data = $this->validate($request, [
          'email' => 'required|email'
        ]);

        if (!$data) {
            return;
        }

        // Generar y enviar token de restablecimiento
        $result = $this->authService->forgotPassword($data['email']);

        // Siempre devolver un mensaje de éxito, incluso si el correo no existe
        // (por seguridad, para no revelar qué correos están registrados)
        $this->success('Si el correo electrónico existe, recibirás instrucciones para restablecer tu contraseña');
    }

    /**
     * Restablecer contraseña
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function resetPassword(Request $request)
    {
        // Validar datos de entrada
        $data = $this->validate($request, [
          'token' => 'required',
          'password' => 'required|min:6',
          'password_confirmation' => 'required|matches:password'
        ]);

        if (!$data) {
            return;
        }

        // Intentar restablecer la contraseña
        $result = $this->authService->resetPassword($data['token'], $data['password']);

        if (!$result['success']) {
            $this->error($result['message']);
            return;
        }

        $this->success('Contraseña restablecida correctamente');
    }

    /**
     * Cambiar contraseña (usuario autenticado)
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function changePassword(Request $request)
    {
        AuthMiddleware::handle($request);
        // Validar datos de entrada
        $data = $this->validate($request, [
          'current_password' => 'required',
          'new_password' => 'required|min:6',
          'new_password_confirmation' => 'required|matches:new_password'
        ]);

        if (!$data) {
            return;
        }

        // Obtener ID del usuario actual
        $userData = $request->getUser();
        $userId = $userData['sub'];

        // Intentar cambiar la contraseña
        $result = $this->authService->changePassword(
            $userId,
            $data['current_password'],
            $data['new_password']
        );

        if (!$result['success']) {
            $this->error($result['message']);
            return;
        }

        $this->success('Contraseña cambiada correctamente');
    }
}
