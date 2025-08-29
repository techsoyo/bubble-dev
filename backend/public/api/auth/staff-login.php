<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Utils\Logger;
use Security\Cookies;
use Middleware\CsrfMiddleware;
use Middleware\JWTMiddleware;

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



// Preflight CORS
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Solo POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'MÃƒÂ©todo no permitido']);
  exit;
}

try {
  // Lee JSON o form-urlencoded
  $raw = file_get_contents('php://input');
  $input = json_decode($raw, true);
  if (!is_array($input) || empty($input)) {
    $input = $_POST ?? [];
  }

  // Campos
  $email    = trim($input['email']    ?? '');
  $password = trim($input['password'] ?? '');

  if ($email === '' || $password === '') {
    throw new Exception('Email y contraseÃƒÂ±a son requeridos');
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Email no vÃƒÂ¡lido');
  }

  // (Opcional) forzar dominio por ENV
  $reqDomain = $_ENV['STAFF_EMAIL_DOMAIN'] ?? '@bubblegum.agency';
  if ($reqDomain && !str_ends_with(strtolower($email), strtolower($reqDomain))) {
    throw new Exception("Solo emails del dominio $reqDomain");
  }

  // ConexiÃƒÂ³n a la base de datos
  $db = getDbConnection();

  // Busca usuario activo
  $stmt = $db->prepare("
    SELECT id, email, role, password_hash, active, COALESCE(name,email) AS name
    FROM bt_staff_profiles
    WHERE email = ? AND active = 1
  ");
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user || !password_verify($password, $user['password_hash'])) {
    throw new Exception('Credenciales incorrectas');
  }

  // Genera JWT
  $jwt_secret = $_ENV['JWT_SECRET'] ?? 'cambia_esto_en_.env';
  $payload = [
    'user_id'   => $user['id'],
    'email'     => $user['email'],
    'role'      => $user['role'],
    'user_type' => 'staff',
    'iat'       => time(),
    'exp'       => time() + 86400,
  ];
  $token = JWT::encode($payload, $jwt_secret, 'HS256');

  // (Opcional) registrar sesiÃƒÂ³n en tabla si la usas
  // ...

  // Establecer cookie httpOnly usando clase centralizada
  Cookies::setJwt($token);

  // Generar y establecer token CSRF
  $csrfToken = CsrfMiddleware::generateToken();

  // En producciÃƒÂ³n, opcional: no devolver token en body por seguridad extra
  if (($_ENV['APP_ENV'] ?? 'development') === 'production' && ($_ENV['HIDE_TOKEN_IN_RESPONSE'] ?? false)) {
    echo json_encode([
      'success' => true,
      'message' => 'Login exitoso',
      'user' => [
        'id'   => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'name' => $user['name'],
        'user_type' => 'staff',
      ],
      'csrf_token' => $csrfToken // Para inicializar clientes legacy
      // token JWT omitido intencionalmente para mayor seguridad
    ]);
    exit;
  }

  // OK - respuesta completa con token (desarrollo y compatibilidad)
  echo json_encode([
    'success' => true,
    'message' => 'Login exitoso',
    'user' => [
      'id'   => $user['id'],
      'email' => $user['email'],
      'role' => $user['role'],
      'name' => $user['name'],
      'user_type' => 'staff',
    ],
    'token' => $token,
    'csrf_token' => $csrfToken // Para inicializar clientes legacy
  ]);
} catch (Exception $e) {
  error_log("Error en staff login: " . $e->getMessage());
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
