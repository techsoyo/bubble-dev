<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

// Configurar headers CORS seguros
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost:3002');
header('Access-Control-Allow-Credentials: false');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Rate limiting básico por IP
session_start();
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$attemptsKey = "auth_attempts_$clientIP";

if (!isset($_SESSION[$attemptsKey])) {
    $_SESSION[$attemptsKey] = ['count' => 0, 'last_attempt' => time()];
}

// Resetear contador si han pasado más de 15 minutos
if (time() - $_SESSION[$attemptsKey]['last_attempt'] > 900) {
    $_SESSION[$attemptsKey] = ['count' => 0, 'last_attempt' => time()];
}

// Bloquear si hay más de 10 intentos en 15 minutos
if ($_SESSION[$attemptsKey]['count'] >= 10) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Demasiados intentos de autenticación. Intenta en 15 minutos.',
        'error_code' => 'RATE_LIMIT_EXCEEDED'
    ]);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        // Obtener y validar input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['email']) || !isset($input['password']) || !isset($input['action'])) {
            $_SESSION[$attemptsKey]['count']++;
            $_SESSION[$attemptsKey]['last_attempt'] = time();

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Email, password y action son requeridos',
                'error_code' => 'MISSING_CREDENTIALS'
            ]);
            exit;
        }

        $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
        $password = $input['password'];
        $action = $input['action']; // 'login' o 'register'

        // Validar email
        if (!$email) {
            $_SESSION[$attemptsKey]['count']++;
            $_SESSION[$attemptsKey]['last_attempt'] = time();

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Email inválido',
                'error_code' => 'INVALID_EMAIL_FORMAT'
            ]);
            exit;
        }

        // Validar password - MÍNIMO 8 CARACTERES
        if (strlen($password) < 8) {
            $_SESSION[$attemptsKey]['count']++;
            $_SESSION[$attemptsKey]['last_attempt'] = time();

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'La contraseña debe tener al menos 8 caracteres',
                'error_code' => 'PASSWORD_TOO_SHORT'
            ]);
            exit;
        }

        // Validar action
        if (!in_array($action, ['login', 'register'], true)) {
            $_SESSION[$attemptsKey]['count']++;
            $_SESSION[$attemptsKey]['last_attempt'] = time();

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Action debe ser "login" o "register"',
                'error_code' => 'INVALID_ACTION'
            ]);
            exit;
        }

        $db = getDbConnection();

        if ($action === 'login') {
            // LOGIN DE CANDIDATO
            $stmt = $db->prepare('SELECT id, email, name, first_name, last_name, password_hash, role, status FROM bt_candidates WHERE email = ? AND status = "active"');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $_SESSION[$attemptsKey]['count']++;
                $_SESSION[$attemptsKey]['last_attempt'] = time();

                // Log fallido
                error_log("LOGIN FAILED: Email: $email - IP: $clientIP - Attempts: " . $_SESSION[$attemptsKey]['count']);

                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Credenciales incorrectas',
                    'error_code' => 'INVALID_CREDENTIALS'
                ]);
                exit;
            }

            // Login exitoso - Generar JWT
            unset($user['password_hash']); // Remover password del response

            $payload = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'type' => 'candidate',
                'iat' => time(),
                'exp' => time() + (int)($_ENV['JWT_EXPIRY'] ?? 3600)
            ];

            $token = \Utils\JWTHelper::generateToken($payload);

            // Resetear contador de intentos fallidos
            unset($_SESSION[$attemptsKey]);

            // Log exitoso
            error_log("LOGIN SUCCESS: User ID {$user['id']} - Email: {$user['email']} - IP: $clientIP");

            echo json_encode([
                'success' => true,
                'message' => 'Login de candidato exitoso',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'expires_in' => (int)($_ENV['JWT_EXPIRY'] ?? 3600)
                ]
            ]);
        } elseif ($action === 'register') {
            // REGISTRO DE CANDIDATO
            $firstName = isset($input['first_name']) ? trim($input['first_name']) : '';
            $lastName = isset($input['last_name']) ? trim($input['last_name']) : '';

            if (empty($firstName) || empty($lastName)) {
                $_SESSION[$attemptsKey]['count']++;
                $_SESSION[$attemptsKey]['last_attempt'] = time();

                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nombre y apellido son requeridos para el registro',
                    'error_code' => 'MISSING_NAME'
                ]);
                exit;
            }

            // Validar nombres (solo letras, espacios, guiones)
            if (!preg_match('/^[a-zA-Z\s\-]+$/', $firstName) || !preg_match('/^[a-zA-Z\s\-]+$/', $lastName)) {
                $_SESSION[$attemptsKey]['count']++;
                $_SESSION[$attemptsKey]['last_attempt'] = time();

                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nombre y apellido solo pueden contener letras, espacios y guiones',
                    'error_code' => 'INVALID_NAME_FORMAT'
                ]);
                exit;
            }

            // Verificar si el usuario ya existe
            $stmt = $db->prepare('SELECT id FROM bt_candidates WHERE email = ?');
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {
                $_SESSION[$attemptsKey]['count']++;
                $_SESSION[$attemptsKey]['last_attempt'] = time();

                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'El usuario ya existe. Usa la opción de login.',
                    'error_code' => 'USER_EXISTS'
                ]);
                exit;
            }

            // Crear nuevo candidato
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $fullName = $firstName . ' ' . $lastName;

            $stmt = $db->prepare('INSERT INTO bt_candidates (email, password_hash, first_name, last_name, name, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $result = $stmt->execute([
                $email,
                $hashedPassword,
                $firstName,
                $lastName,
                $fullName,
                'candidate',
                'active',
                date('Y-m-d H:i:s')
            ]);

            if (!$result) {
                error_log("REGISTRATION FAILED: Could not create user - Email: $email");
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al crear el usuario',
                    'error_code' => 'REGISTRATION_FAILED'
                ]);
                exit;
            }

            $userId = $db->lastInsertId();

            // Generar JWT para el nuevo usuario
            $payload = [
                'user_id' => $userId,
                'email' => $email,
                'role' => 'candidate',
                'type' => 'candidate',
                'iat' => time(),
                'exp' => time() + (int)($_ENV['JWT_EXPIRY'] ?? 3600)
            ];

            $token = \Utils\JWTHelper::generateToken($payload);

            // Log exitoso
            error_log("REGISTRATION SUCCESS: User ID $userId - Email: $email - IP: $clientIP");

            echo json_encode([
                'success' => true,
                'message' => 'Registro de candidato exitoso',
                'data' => [
                    'user' => [
                        'id' => $userId,
                        'email' => $email,
                        'name' => $fullName,
                        'role' => 'candidate',
                        'first_name' => $firstName,
                        'last_name' => $lastName
                    ],
                    'token' => $token,
                    'expires_in' => (int)($_ENV['JWT_EXPIRY'] ?? 3600)
                ]
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Action debe ser "login" o "register"',
                'error_code' => 'INVALID_ACTION'
            ]);
        }
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido',
            'error_code' => 'METHOD_NOT_ALLOWED'
        ]);
    }
} catch (Exception $e) {
    error_log("Candidate Auth Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en autenticación de candidato',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
