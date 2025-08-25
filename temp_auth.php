<?php

namespace Controllers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Security\Cookies;
use Utils\Request;
use Utils\ResponseHelper;

// Bootstrap unificado requerido
require_once __DIR__ . '/../../public/api/bootstrap.php';

class AuthController
{
    public function __construct()
    {
        // Bloquear Authorization header en producción (solo cookies permitidas)
        if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized (cookie required)']);
            exit;
        }
    }

    /**
     * Genera JWT firmado con JWT_SECRET
     */
    private function generateJwt(array $claims): string
    {
        $now = time();
        $exp = $now + (int)($_ENV['JWT_TTL'] ?? 86400); // 24h por defecto
        $iss = $_ENV['JWT_ISS'] ?? 'bubble';
        $aud = $_ENV['JWT_AUD'] ?? 'bubble-app';

        $payload = array_merge([
            'iat' => $now,
            'nbf' => $now,
            'exp' => $exp,
            'iss' => $iss,
            'aud' => $aud,
        ], $claims);

        $secret = $_ENV['JWT_SECRET'] ?? '';
        if (empty($secret)) {
            throw new \Exception('JWT_SECRET not configured');
        }
        
        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * Configura cookies JWT y CSRF
     */
    private function setAuthCookies(string $jwt): void
    {
        // Cookie HttpOnly con JWT
        Cookies::setJwt($jwt);
        
        // CSRF double-submit cookie (no HttpOnly)
        $csrf = bin2hex(random_bytes(32));
        setcookie('XSRF-TOKEN', $csrf, Cookies::options(['httponly' => false]));
    }

    /**
     * Valida credenciales contra base de datos
     */
    private function validateCredentials(string $email, string $password): ?array
    {
        // TODO: Implementar validación real contra base de datos
        // Por ahora, simulamos validación exitosa para testing
        if (empty($email) || empty($password)) {
            return null;
        }
        
        // Mock user data - REEMPLAZAR con consulta DB real
        return [
            'id' => '1',
            'email' => $email,
            'role' => 'user',
            'subrole' => null,
            'status' => 'active'
        ];
    }

    /**
     * Respuesta de login exitoso con JWT seguro
     */
    private function successfulLoginResponse(array $user, string $jwt): void
    {
        $hideToken = filter_var($_ENV['HIDE_TOKEN_IN_RESPONSE'] ?? 'true', FILTER_VALIDATE_BOOL);
        
        $response = [
            'ok' => true,
            'user' => [
                'id' => (string)$user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'sr' => $user['subrole'] ?? null,
            ]
        ];
        
        // Solo en dev incluir token en response si HIDE_TOKEN_IN_RESPONSE=false
        if (!$hideToken) {
            $response['token'] = $jwt;
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * Login de usuario (staff o candidato)
     */
    public function login(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            $user = $this->validateCredentials($email, $password);
            
            if (!$user) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'invalid_credentials']);
                exit;
            }
            
            if ($user['status'] !== 'active') {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'forbidden']);
                exit;
            }
            
            $jwt = $this->generateJwt([
                'sub' => (string)$user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'sr' => $user['subrole'] ?? null,
            ]);
            
            $this->setAuthCookies($jwt);
            $this->successfulLoginResponse($user, $jwt);
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
    }

    /**
     * Login específico de candidato
     */
    public function candidateLogin(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            $user = $this->validateCredentials($email, $password);
            
            if (!$user) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'invalid_credentials']);
                exit;
            }
            
            if ($user['status'] !== 'active') {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'forbidden']);
                exit;
            }
            
            // Forzar role como candidate
            $user['role'] = 'candidate';
            
            $jwt = $this->generateJwt([
                'sub' => (string)$user['id'],
                'email' => $user['email'],
                'role' => 'candidate',
                'sr' => null,
            ]);
            
            $this->setAuthCookies($jwt);
            $this->successfulLoginResponse($user, $jwt);
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
    }

    /**
     * Login específico de staff
     */
    public function staffLogin(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            $user = $this->validateCredentials($email, $password);
            
            if (!$user) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'invalid_credentials']);
                exit;
            }
            
            if ($user['status'] !== 'active') {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'forbidden']);
                exit;
            }
            
            // Forzar role como staff y determinar subrole
            $user['role'] = 'staff';
            $user['subrole'] = $user['subrole'] ?? 'recruiter'; // admin|recruiter
            
            $jwt = $this->generateJwt([
                'sub' => (string)$user['id'],
                'email' => $user['email'],
                'role' => 'staff',
                'sr' => $user['subrole'],
            ]);
            
            $this->setAuthCookies($jwt);
            $this->successfulLoginResponse($user, $jwt);
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
    }

    /**
     * Logout - limpiar cookies
     */
    public function logout(Request $request, array $params = [])
    {
        try {
            // Limpiar cookies JWT y CSRF
            setcookie('access_token', '', time() - 3600, '/', '', true, true);
            setcookie('XSRF-TOKEN', '', time() - 3600, '/', '', true, false);
            
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
    }

    /**
     * Información del usuario autenticado (requiere JWT válido)
     */
    public function userInfo(Request $request, array $params = [])
    {
        try {
            // TODO: Implementar validación JWT desde cookie
            // Por ahora devolvemos error sin JWT válido
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'jwt_required']);
            exit;
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
    }

    /**
     * Verificar sesión activa
     */
    public function verifySession(Request $request, array $params = [])
    {
        try {
            // TODO: Implementar validación JWT desde cookie
            // Por ahora devolvemos error sin JWT válido
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'session_invalid']);
            exit;
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
    }

    /**
     * Generar CSRF Token
     */
    public function csrfToken(Request $request, array $params = [])
    {
        try {
            $csrf = bin2hex(random_bytes(32));
            
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => true,
                'csrf_token' => $csrf
            ]);
            exit;
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
    }

    /**
     * Validar CSRF Token
     */
    public function validateCsrf(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            $token = $data['csrf_token'] ?? null;
            
            // TODO: Implementar validación CSRF real contra cookie XSRF-TOKEN
            $valid = !empty($token);
            
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => true,
                'valid' => $valid
            ]);
            exit;
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
    }

    /**
     * Cambio de contraseña (requiere autenticación)
     */
    public function changePassword(Request $request, array $params = [])
    {
        try {
            // TODO: Implementar validación JWT desde cookie
            // TODO: Implementar cambio de contraseña en BD
            
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'jwt_required']);
            exit;
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
    }

    /**
     * Ping de autenticación
     */
    public function ping(Request $request, array $params = [])
    {
        try {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => true,
                'status' => 'auth_controller_online'
            ]);
            exit;
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
    }

    /**
     * Interceptor de autenticación
     */
    public function intercept(Request $request, array $params = [])
    {
        try {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => true,
                'intercepted' => true
            ]);
            exit;
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
    }

    /**
     * Login seguro con 2FA/scope adicional
     */
    public function secureLogin(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $twoFactorCode = $data['two_factor'] ?? '';
            
            $user = $this->validateCredentials($email, $password);
            
            if (!$user) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'invalid_credentials']);
                exit;
            }
            
            if ($user['status'] !== 'active') {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'forbidden']);
                exit;
            }
            
            // TODO: Validar 2FA code real
            if (empty($twoFactorCode)) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'two_factor_required']);
                exit;
            }
            
            $jwt = $this->generateJwt([
                'sub' => (string)$user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'sr' => $user['subrole'] ?? null,
                'scope' => 'secure',
            ]);
            
            $this->setAuthCookies($jwt);
            $this->successfulLoginResponse($user, $jwt);
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'internal_server_error']);
            exit;
        }
