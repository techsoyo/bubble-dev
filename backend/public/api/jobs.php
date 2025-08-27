<?php

declare(strict_types=1);

use Security\CsrfMiddleware;
// Usar alias global de JWTMiddleware creado en bootstrap.php


require_once __DIR__ . '/./bootstrap.php';
// JWTMiddleware::requireAuth(); // Movido a donde se necesita específicamente

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
  CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized (cookie required)']);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized (cookie required)']);
  exit;
}

// 1. Cargo el bootstrap que pone los headers CORS

// 2. Usar Database singleton para consistencia

// 3. Incluir autoloader de Composer para Google Translate

use Stichoza\GoogleTranslate\GoogleTranslate;

/**
 * Clase JobTranslate - Maneja la traducciÃƒÆ’Ã‚Â³n de contenido de trabajos
 */
class JobTranslate
{

  /**
   * Traduce texto usando Google Translate
   * 
   * @param string $text Texto a traducir
   * @param string $targetLanguage Idioma objetivo (por defecto 'en')
   * @param string $sourceLanguage Idioma origen (por defecto 'es')
   * @return array Resultado de la traducciÃƒÆ’Ã‚Â³n
   */
  public static function translateText($text, $targetLanguage = 'en', $sourceLanguage = 'es')
  {
    try {
      if (empty($text)) {
        throw new Exception('Text is required');
      }

      // Si el idioma objetivo es el mismo que el origen, devolver el texto original
      if ($targetLanguage === $sourceLanguage) {
        return [
          'success' => true,
          'translation' => $text,
          'original' => $text,
          'targetLanguage' => $targetLanguage,
          'sourceLanguage' => $sourceLanguage,
          'cached' => false
        ];
      }

      // Configurar Google Translate
      $translator = new GoogleTranslate();
      $translator->setSource($sourceLanguage);
      $translator->setTarget($targetLanguage);

      // Configurar opciones para evitar rate limiting y problemas SSL
      $translator->setOptions([
        'timeout' => 10,
        'headers' => [
          'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ],
        'verify' => false, // Desactivar verificaciÃƒÆ’Ã‚Â³n SSL para desarrollo local
        'http_errors' => false
      ]);

      // Realizar la traducciÃƒÆ’Ã‚Â³n
      $translation = $translator->translate($text);

      if (empty($translation)) {
        throw new Exception('Translation failed - empty result');
      }

      // Log para debugging (opcional)
      error_log("Google Translate: '$text' ($sourceLanguage) -> '$translation' ($targetLanguage)");

      return [
        'success' => true,
        'translation' => $translation,
        'original' => $text,
        'targetLanguage' => $targetLanguage,
        'sourceLanguage' => $sourceLanguage,
        'cached' => false
      ];
    } catch (Exception $e) {
      error_log("Google Translate Error: " . $e->getMessage());

      // En caso de error, devolver el texto original como fallback
      return [
        'success' => false,
        'error' => $e->getMessage(),
        'translation' => $text ?? '',
        'original' => $text ?? '',
        'fallback' => true
      ];
    }
  }

  /**
   * Maneja las peticiones HTTP para traducciÃƒÆ’Ã‚Â³n
   */
  public static function handleTranslationRequest()
  {
    $method = $_SERVER['REQUEST_METHOD'];

    // Aceptar tanto GET como POST
    if ($method !== 'POST' && $method !== 'GET') {
      http_response_code(405);
      echo json_encode(['success' => false, 'error' => 'Method not allowed']);
      exit;
    }

    // Obtener datos segÃƒÆ’Ã‚Âºn el mÃƒÆ’Ã‚Â©todo
    if ($method === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);
      if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        exit;
      }
      $text = $input['text'] ?? '';
      $targetLanguage = $input['targetLanguage'] ?? 'en';
      $sourceLanguage = $input['sourceLanguage'] ?? 'es';
    } else {
      // GET method - usar parÃƒÆ’Ã‚Â¡metros de consulta con valores por defecto para testing
      $text = $_GET['text'] ?? 'Hello World';
      $targetLanguage = $_GET['targetLanguage'] ?? 'es';
      $sourceLanguage = $_GET['sourceLanguage'] ?? 'en';
    }

    $result = self::translateText($text, $targetLanguage, $sourceLanguage);

    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
  }
}

function db()
{
  static $pdo = null;
  if ($pdo === null) {
    try {
      $database = \Utils\Database::getInstance();
      $pdo = $database->getConnection();
    } catch (Exception $e) {
      error_log('Database connection failed in jobs.php: ' . $e->getMessage());
      http_response_code(500);
      echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
      ]);
      exit;
    }
  }
  return $pdo;
}

// Manejo de rutas
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'] ?? '';

// Si es una peticiÃƒÆ’Ã‚Â³n para traducir
if (strpos($path, '/translate') !== false || isset($_GET['action']) && $_GET['action'] === 'translate') {
  JobTranslate::handleTranslationRequest();
  exit;
}

// 3. Devuelvo los trabajos (funcionalidad original y por ID)
if ($method === 'GET') {
  try {
    // Si se pasa un ID, devolver solo ese trabajo
    if (isset($_GET['id'])) {
      $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
      if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'id inválido']);
        exit;
      }

      $sql = "SELECT * FROM bt_jobs WHERE id = ? AND status='open'";
      $stmt = db()->prepare($sql);
      $stmt->bindValue(1, $id, PDO::PARAM_INT);
      $stmt->execute();
      $job = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$job) {
        http_response_code(404);
        echo json_encode([
          'success' => false,
          'message' => 'Trabajo no encontrado'
        ]);
        exit;
      }

      $result = [
        'id' => $job['id'],
        'title' => $job['title'],
        'company' => $job['company_name'] ?? '',
        'location' => $job['location'] ?? '',
        'description' => $job['description'] ?? '',
        'requirements' => $job['requirements'] ?? '',
        'benefits' => $job['benefits'] ?? '',
        'salary_range' => $job['salary_range'] ?? '',
        'job_type' => $job['job_type'] ?? '',
        'department' => $job['department'] ?? '',
        'experience_level' => $job['experience_level'] ?? '',
        'created_at' => $job['created_at'] ?? '',
        'deadline' => $job['deadline'] ?? ''
      ];

      header('Content-Type: application/json');
      echo json_encode([
        'success' => true,
        'data' => $result
      ]);
      exit;
    }

    // Si no se pasa ID, devolver lista de trabajos
    $limit  = (int)($_GET['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? 0);

    $sql = "SELECT * FROM bt_jobs WHERE status='open' ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($jobs as $row) {
      $result[] = [
        'id'   => $row['id'],
        'title' => $row['title'],
        'company' => $row['company_name'] ?? '',
        'location' => $row['location'] ?? '',
        'description' => $row['description'] ?? ''
      ];
    }

    header('Content-Type: application/json');
    echo json_encode([
      'success' => true,
      'data'    => $result
    ]);
    exit;
  } catch (Exception $e) {
    error_log('Error in jobs.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'message' => 'Database query failed',
      'error' => $e->getMessage()
    ]);
    exit;
  }
}

// Manejo de POST para crear trabajos - AHORA CON AUTENTICACION JWT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // REQUERIR AUTENTICACION JWT
  $userPayload = JWTMiddleware::requireAuth();
  if (!$userPayload) {
    // JWTMiddleware ya envió la respuesta de error
    exit;
  }

  // Solo usuarios con rol admin/hr pueden crear trabajos
  $allowedRoles = ['admin', 'hr', 'recruiter'];
  if (!in_array($userPayload['role'] ?? 'candidate', $allowedRoles)) {
    http_response_code(403);
    echo json_encode([
      'success' => false,
      'message' => 'Solo administradores y RRHH pueden crear trabajos',
      'error_code' => 'INSUFFICIENT_PERMISSIONS'
    ]);
    exit;
  }

  $input = json_decode(file_get_contents('php://input'), true);

  // VALIDACIÃƒÆ’Ã¢â‚¬Å“N ROBUSTA DE INPUTS
  if (!$input || empty($input['title'])) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'message' => 'TÃƒÆ’Ã‚Â­tulo del trabajo es requerido',
      'error_code' => 'MISSING_TITLE'
    ]);
    exit;
  }

  // Sanitizar y validar inputs
  $title = trim($input['title']);
  $description = trim($input['description'] ?? '');
  $location = trim($input['location'] ?? '');
  $salary_range = trim($input['salary_range'] ?? '');
  $department = trim($input['department'] ?? '');

  if (strlen($title) < 5 || strlen($title) > 100) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'message' => 'TÃƒÆ’Ã‚Â­tulo debe tener entre 5 y 100 caracteres',
      'error_code' => 'INVALID_TITLE_LENGTH'
    ]);
    exit;
  }

  try {
    // USAR BASE DE DATOS REAL CON PREPARED STATEMENTS
    $db = getDbConnection();

    $sql = "INSERT INTO bt_jobs (title, description, location, salary_range, department, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'open', ?, NOW())";

    $stmt = $db->prepare($sql);
    $success = $stmt->execute([
      $title,
      $description,
      $location,
      $salary_range,
      $department,
      $userPayload['user_id']
    ]);

    if (!$success) {
      throw new Exception('Error al insertar trabajo en la base de datos');
    }

    // Obtener ID del trabajo reciÃƒÆ’Ã‚Â©n creado
    $jobId = $db->lastInsertId();

    // Log de auditorÃƒÆ’Ã‚Â­a
    error_log("JOB CREATED: ID $jobId by user {$userPayload['user_id']} - Title: $title");

    echo json_encode([
      'success' => true,
      'message' => 'Trabajo creado exitosamente',
      'data' => [
        'id' => $jobId,
        'title' => $title,
        'status' => 'open',
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => $userPayload['user_id']
      ]
    ]);
    exit;
  } catch (Exception $e) {
    error_log("CREATE JOB ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'message' => 'Error al crear el trabajo',
      'error_code' => 'DATABASE_ERROR'
    ]);
    exit;
  }
}
