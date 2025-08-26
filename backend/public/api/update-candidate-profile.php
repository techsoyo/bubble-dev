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

// update-candidate-profile.php - Actualizar perfil bÃƒÂ¡sico del candidato

// cookie HttpOnly obligatoria

// Proteger solo mÃ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
  // double-submit cookie
}

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
  if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
  }
}

session_start();

// CORS headers (bootstrap ya las configura, pero mantenemos para compatibilidad)
header('Access-Control-Allow-Origin: http://localhost:3002');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
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

try {
  // Get current user from session
  if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
  }

  $userEmail = $_SESSION['user_email'];

  // Parse JSON input
  $input = json_decode(file_get_contents('php://input'), true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit();
  }

  // Extract profile data
  $professionalSummary = $input['professional_summary'] ?? null;
  $availability = $input['availability'] ?? null;
  $expectedSalary = $input['expected_salary'] ?? null;

  // Validate that at least one field is provided
  if (!$professionalSummary && !$availability && !$expectedSalary) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Al menos un campo debe ser proporcionado']);
    exit();
  }

  // Connect to database
  $db = getDBConnection();

  // Find candidate by email
  $stmt = $db->prepare("SELECT id FROM bt_candidates WHERE email = ?");
  $stmt->execute([$userEmail]);
  $candidate = $stmt->fetch();

  if (!$candidate) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Candidato no encontrado']);
    exit();
  }

  $candidateId = $candidate['id'];

  // Prepare update query
  $updateFields = [];
  $params = [];

  if ($professionalSummary !== null) {
    $updateFields[] = 'professional_summary = ?';
    $params[] = trim($professionalSummary);
  }

  if ($availability !== null) {
    $updateFields[] = 'availability = ?';
    $params[] = trim($availability);
  }

  // Note: expected_salary is not a direct field in bt_candidates table
  // We'll store it in the availability field or professional_summary
  if ($expectedSalary !== null) {
    if ($availability === null) {
      $updateFields[] = 'availability = ?';
      $params[] = "Salario esperado: Ã¢â€šÂ¬" . trim($expectedSalary);
    }
  }

  // Add candidate ID for WHERE clause
  $params[] = $candidateId;

  // Build and execute update query
  if (!empty($updateFields)) {
    $sql = "UPDATE bt_candidates SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
      echo json_encode([
        'success' => true,
        'message' => 'Perfil actualizado correctamente',
        'data' => [
          'candidate_id' => $candidateId,
          'updated_fields' => array_keys(array_filter([
            'professional_summary' => $professionalSummary !== null,
            'availability' => $availability !== null || $expectedSalary !== null
          ]))
        ]
      ]);
    } else {
      echo json_encode([
        'success' => false,
        'message' => 'No se realizaron cambios en el perfil'
      ]);
    }
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'No hay campos vÃƒÂ¡lidos para actualizar'
    ]);
  }
} catch (Exception $e) {
  error_log('Error updating candidate profile: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error interno del servidor',
    'error' => $e->getMessage()
  ]);
}

