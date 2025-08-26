<?php declare(strict_types=1);

// @public
// social-login.php - Manejo de autenticaciÃƒÂ³n OAuth con proveedores externos

require_once dirname(__DIR__) . '/bootstrap.php';
session_start();

// CORS headers
header('Access-Control-Allow-Origin: http://localhost:3002');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit();
}

// Load database configuration
require_once dirname(__DIR__, 2) . '/config/database.php';

function getDBConnection()
{
  $host = $_ENV['DB_HOST'] ?? 'localhost';
  $name = $_ENV['DB_NAME'] ?? 'bubble_talents';
  $user = $_ENV['DB_USER'] ?? 'root';
  $pass = $_ENV['DB_PASSWORD'] ?? '';

  try {
    $pdo = new PDO(
      "mysql:host=$host;dbname=$name;charset=utf8mb4",
      $user,
      $pass,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]
    );
    return $pdo;
  } catch (PDOException $e) {
    throw new Exception('Database connection failed: ' . $e->getMessage());
  }
}

// FunciÃƒÂ³n para intercambiar cÃƒÂ³digo OAuth por tokens
function exchangeOAuthCode($provider, $code, $redirectUri)
{
  $clientId = '';
  $clientSecret = '';
  $tokenUrl = '';

  switch ($provider) {
    case 'google':
      $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
      $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
      $tokenUrl = 'https://oauth2.googleapis.com/token';
      break;

    case 'linkedin':
      $clientId = $_ENV['LINKEDIN_CLIENT_ID'] ?? '';
      $clientSecret = $_ENV['LINKEDIN_CLIENT_SECRET'] ?? '';
      $tokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';
      break;

    default:
      throw new Exception("Proveedor OAuth no soportado: $provider");
  }

  if (!$clientId || !$clientSecret) {
    throw new Exception("ConfiguraciÃƒÂ³n OAuth incompleta para $provider");
  }

  // Datos para el intercambio de cÃƒÂ³digo
  $postData = [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirectUri,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
  ];

  // Realizar peticiÃƒÂ³n POST
  $context = stream_context_store([
    'http' => [
      'method' => 'POST',
      'header' => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
      ],
      'content' => http_build_query($postData),
      'timeout' => 10
    ]
  ]);

  $response = file_get_contents($tokenUrl, false, $context);

  if ($response === FALSE) {
    throw new Exception("Error al comunicarse con $provider OAuth");
  }

  $tokenData = json_decode($response, true);

  if (!$tokenData || isset($tokenData['error'])) {
    throw new Exception("Error obteniendo token: " . ($tokenData['error_description'] ?? 'Error desconocido'));
  }

  return $tokenData;
}

// FunciÃƒÂ³n para obtener datos del usuario desde el proveedor OAuth
function getUserFromProvider($provider, $accessToken)
{
  $userApiUrl = '';

  switch ($provider) {
    case 'google':
      $userApiUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
      break;

    case 'linkedin':
      $userApiUrl = 'https://api.linkedin.com/v2/people/~:(id,localizedFirstName,localizedLastName,emailAddress)';
      break;

    default:
      throw new Exception("Proveedor no soportado para obtener datos de usuario: $provider");
  }

  // Hacer peticiÃƒÂ³n para obtener datos del usuario
  $context = stream_context_store([
    'http' => [
      'method' => 'GET',
      'header' => [
        "Authorization: Bearer $accessToken",
        'Accept: application/json'
      ],
      'timeout' => 10
    ]
  ]);

  $userResponse = file_get_contents($userApiUrl, false, $context);

  if ($userResponse === FALSE) {
    throw new Exception("Error obteniendo datos del usuario desde $provider");
  }

  $userData = json_decode($userResponse, true);

  if (!$userData) {
    throw new Exception("Respuesta invÃƒÂ¡lida del proveedor $provider");
  }

  // Normalizar datos segÃƒÂºn el proveedor
  $normalizedData = [];

  switch ($provider) {
    case 'google':
      $normalizedData = [
        'social_id' => $userData['id'],
        'email' => $userData['email'],
        'name' => $userData['name'],
        'first_name' => $userData['given_name'] ?? '',
        'last_name' => $userData['family_name'] ?? '',
        'avatar' => $userData['picture'] ?? null,
        'verified_email' => $userData['verified_email'] ?? false
      ];
      break;

    case 'linkedin':
      $normalizedData = [
        'social_id' => $userData['id'],
        'name' => ($userData['localizedFirstName'] ?? '') . ' ' . ($userData['localizedLastName'] ?? ''),
        'first_name' => $userData['localizedFirstName'] ?? '',
        'last_name' => $userData['localizedLastName'] ?? '',
        'email' => '', // LinkedIn requiere peticiÃƒÂ³n separada para email
        'avatar' => null,
        'verified_email' => false
      ];
      break;
  }

  return $normalizedData;
}

try {
  // Parse JSON input
  $input = json_decode(file_get_contents('php://input'), true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit();
  }

  $provider = $input['provider'] ?? null;
  $code = $input['code'] ?? null;
  $redirectUri = $input['redirect_uri'] ?? null;

  // ValidaciÃƒÂ³n bÃƒÂ¡sica
  if (!$provider || !$code || !$redirectUri) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ParÃƒÂ¡metros OAuth incompletos']);
    exit();
  }

  if (!in_array($provider, ['google', 'linkedin', 'apple'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Proveedor OAuth no vÃƒÂ¡lido']);
    exit();
  }

  // Paso 1: Intercambiar cÃƒÂ³digo por tokens
  $tokenData = exchangeOAuthCode($provider, $code, $redirectUri);
  $accessToken = $tokenData['access_token'];

  // Paso 2: Obtener datos del usuario
  $userData = getUserFromProvider($provider, $accessToken);

  // Paso 3: Buscar o crear usuario en la base de datos
  $db = getDBConnection();

  // Buscar usuario existente por email y proveedor social
  $stmt = $db->prepare("
        SELECT c.*, u.id as user_id, u.role 
        FROM bt_candidates c 
        LEFT JOIN usuarios u ON c.email = u.email 
        WHERE c.email = ? AND (c.social_provider = ? OR c.social_provider IS NULL)
    ");

  $stmt->execute([$userData['email'], $provider]);
  $existingUser = $stmt->fetch();

  if ($existingUser) {
    // Usuario existente - actualizar datos de OAuth si es necesario
    $updateStmt = $db->prepare("
            UPDATE bt_candidates 
            SET social_provider = ?, social_id = ?, updated_at = NOW() 
            WHERE email = ?
        ");
    $updateStmt->execute([$provider, $userData['social_id'], $userData['email']]);

    $user = [
      'id' => $existingUser['id'],
      'name' => $existingUser['name'],
      'email' => $existingUser['email'],
      'role' => $existingUser['role'] ?? 'candidate'
    ];
  } else {
    // Nuevo usuario - crear cuenta
    $candidateId = 'cand_' . uniqid();

    // Insertar en tabla de candidatos
    $insertCandidate = $db->prepare("
            INSERT INTO bt_candidates (
                id, name, email, social_provider, social_id, 
                status, registration_source, gdpr_consent_given, created_at
            ) VALUES (?, ?, ?, ?, ?, 'active', 'social_login', 1, NOW())
        ");

    $insertCandidate->execute([
      $candidateId,
      $userData['name'],
      $userData['email'],
      $provider,
      $userData['social_id']
    ]);

    // Insertar en tabla de usuarios para autenticaciÃƒÂ³n
    $userId = 'user_' . uniqid();
    $insertUser = $db->prepare("
            INSERT INTO usuarios (id, username, email, role, created_at) 
            VALUES (?, ?, ?, 'candidate', NOW())
        ");

    $insertUser->execute([$userId, $userData['email'], $userData['email']]);

    $user = [
      'id' => $candidateId,
      'name' => $userData['name'],
      'email' => $userData['email'],
      'role' => 'candidate'
    ];
  }

  // Paso 4: Establecer sesiÃƒÂ³n
  $_SESSION['user_id'] = $user['id'];
  $_SESSION['user_email'] = $user['email'];
  $_SESSION['user_role'] = $user['role'];
  $_SESSION['login_time'] = time();

  // Generar token JWT si estÃƒÂ¡ configurado
  $jwtToken = null; // Se puede implementar mÃƒÂ¡s adelante

  echo json_encode([
    'success' => true,
    'message' => 'AutenticaciÃƒÂ³n exitosa',
    'data' => [
      'user' => $user,
      'token' => $jwtToken,
      'provider' => $provider
    ]
  ]);
} catch (Exception $e) {
  error_log('Error en social login: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error en la autenticaciÃƒÂ³n OAuth',
    'error' => $e->getMessage()
  ]);
}
