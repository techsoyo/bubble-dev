<?php declare(strict_types=1);
require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// Importar JWT helper

// Configurar headers CORS primera lÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­nea
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Rate limiting bÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡sico por IP
session_start();
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$attemptsKey = "login_attempts_$clientIP";

if (!isset($_SESSION[$attemptsKey])) {
    $_SESSION[$attemptsKey] = ['count' => 0, 'last_attempt' => time()];
}

// Resetear contador si han pasado mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s de 15 minutos
if (time() - $_SESSION[$attemptsKey]['last_attempt'] > 900) {
    $_SESSION[$attemptsKey] = ['count' => 0, 'last_attempt' => time()];
}

// Bloquear si hay mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s de 5 intentos en 15 minutos
if ($_SESSION[$attemptsKey]['count'] >= 5) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Demasiados intentos de login. Intenta en 15 minutos.',
        'error_code' => 'RATE_LIMIT_EXCEEDED'
    ]);
    exit;
}

try {
    $db = getDbConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        // ValidaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s robusta
        if (!$input || !isset($input['email']) || !isset($input['password'])) {
            $_SESSION[$attemptsKey]['count']++;
            $_SESSION[$attemptsKey]['last_attempt'] = time();

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Email y password requeridos',
                'error_code' => 'MISSING_CREDENTIALS'
            ]);
            exit;
        }

        $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
        $password = $input['password'];

        // Validar formato de email
        if (!$email) {
            $_SESSION[$attemptsKey]['count']++;
            $_SESSION[$attemptsKey]['last_attempt'] = time();

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Formato de email invÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lido',
                'error_code' => 'INVALID_EMAIL_FORMAT'
            ]);
            exit;
        }

        // Validar longitud de password
        if (strlen($password) < 6) {
            $_SESSION[$attemptsKey]['count']++;
            $_SESSION[$attemptsKey]['last_attempt'] = time();

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Password debe tener al menos 6 caracteres',
                'error_code' => 'PASSWORD_TOO_SHORT'
            ]);
            exit;
        }

        // Consulta a la base de datos con mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s campos necesarios
        $stmt = $db->prepare('SELECT id, email, name, password_hash, role, status, created_at FROM bt_candidates WHERE email = ? AND status = "active"');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Login exitoso - GENERAR JWT TOKEN
            unset($user['password_hash']); // Remover password del response

            // Crear payload para JWT
            $payload = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'candidate',
                'iat' => time(),
                'exp' => time() + (int)$_ENV['JWT_EXPIRY']
            ];

            // Generar JWT token
            $token = JWTHelper::generateToken($payload);

            // Resetear contador de intentos fallidos
            unset($_SESSION[$attemptsKey]);

            // Log exitoso (para auditoria)
            error_log("LOGIN SUCCESS: User ID {$user['id']} - Email: {$user['email']} - IP: $clientIP");

            echo json_encode([
                'success' => true,
                'message' => 'Login exitoso',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'expires_in' => (int)$_ENV['JWT_EXPIRY']
                ]
            ]);
        } else {
            // Login fallido
            $_SESSION[$attemptsKey]['count']++;
            $_SESSION[$attemptsKey]['last_attempt'] = time();

            // Log fallido (para seguridad)
            error_log("LOGIN FAILED: Email: $email - IP: $clientIP - Attempts: " . $_SESSION[$attemptsKey]['count']);

            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Credenciales invÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lidas',
                'error_code' => 'INVALID_CREDENTIALS'
            ]);
        }
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'MÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©todo no permitido',
            'error_code' => 'METHOD_NOT_ALLOWED'
        ]);
    }
} catch (Exception $e) {
    error_log("AUTH ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}


