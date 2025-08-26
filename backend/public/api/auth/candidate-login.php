<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Utils\Logger;
use Security\Cookies;
use Security\CsrfMiddleware;

require_once __DIR__ . '/../bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria
 


$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
  CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized (cookie required)']);
  exit;
}



// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

try {
  // Solo permitir mÃ©todo POST
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'MÃ©todo no permitido']);
    exit;
  }

  // Obtener datos del request
  $raw = file_get_contents('php://input');
  $input = json_decode($raw, true);
  if (!is_array($input) || empty($input)) {
    $input = $_POST ?? [];
  }

  $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
  $password = trim($input['password'] ?? '');

  if (!$email || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email y contraseÃ±a son requeridos']);
    exit;
  }

  // Conectar a la base de datos
  $db = getDbConnection();

  // Buscar candidato por email
  $stmt = $db->prepare("SELECT id, first_name, last_name, email, password_hash FROM bt_candidates WHERE email = ? AND status = 'active'");
  $stmt->execute([$email]);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$candidate) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Credenciales invÃ¡lidas']);
    exit;
  }

  // Verificar contraseÃ±a
  if (!password_verify($password, $candidate['password_hash'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Credenciales invÃ¡lidas']);
    exit;
  }

  // Genera JWT
  $jwt_secret = $_ENV['JWT_SECRET'] ?? 'cambia_esto_en_.env';
  $payload = [
    'user_id'   => $candidate['id'],
    'email'     => $candidate['email'],
    'role'      => 'candidate',
    'user_type' => 'candidate',
    'iat'       => time(),
    'exp'       => time() + 86400,
  ];
  $token = JWT::encode($payload, $jwt_secret, 'HS256');

  // Establecer cookie httpOnly usando clase centralizada
  Cookies::setJwt($token);

  // Generar y establecer token CSRF
  $csrfToken = CsrfMiddleware::generateToken();

  // En producciÃ³n, opcional: no devolver token en body por seguridad extra
  if (($_ENV['APP_ENV'] ?? 'development') === 'production' && ($_ENV['HIDE_TOKEN_IN_RESPONSE'] ?? false)) {
    http_response_code(200);
    echo json_encode([
      'success' => true,
      'user' => [
        'id' => $candidate['id'],
        'email' => $candidate['email'],
        'name' => $candidate['first_name'] . ' ' . $candidate['last_name'],
        'role' => 'candidate',
        'user_type' => 'candidate'
      ],
      'message' => 'Login exitoso',
      'csrf_token' => $csrfToken // Para inicializar clientes legacy
      // token JWT omitido intencionalmente para mayor seguridad
    ]);
    exit;
  }

  // Respuesta exitosa completa con token (desarrollo y compatibilidad)
  http_response_code(200);
  echo json_encode([
    'success' => true,
    'user' => [
      'id' => $candidate['id'],
      'email' => $candidate['email'],
      'name' => $candidate['first_name'] . ' ' . $candidate['last_name'],
      'role' => 'candidate',
      'user_type' => 'candidate'
    ],
    'token' => $token,
    'csrf_token' => $csrfToken, // Para inicializar clientes legacy
    'message' => 'Login exitoso'
  ]);
} catch (Exception $e) {
  error_log("Error in candidate-login.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
