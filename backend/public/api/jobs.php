<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// 1. Cargo el bootstrap que pone los headers CORS
$BOOT = dirname(__DIR__, 2) . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

// 2. Usar Database singleton para consistencia
require_once dirname(__DIR__, 2) . '/src/Utils/Database.php';

// 3. Incluir autoloader de Composer para Google Translate
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Stichoza\GoogleTranslate\GoogleTranslate;

/**
 * Clase JobTranslate - Maneja la traducción de contenido de trabajos
 */
class JobTranslate
{

  /**
   * Traduce texto usando Google Translate
   * 
   * @param string $text Texto a traducir
   * @param string $targetLanguage Idioma objetivo (por defecto 'en')
   * @param string $sourceLanguage Idioma origen (por defecto 'es')
   * @return array Resultado de la traducción
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
        'verify' => false, // Desactivar verificación SSL para desarrollo local
        'http_errors' => false
      ]);

      // Realizar la traducción
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
   * Maneja las peticiones HTTP para traducción
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

    // Obtener datos según el método
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
      // GET method - usar parámetros de consulta con valores por defecto para testing
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

// Si es una petición para traducir
if (strpos($path, '/translate') !== false || isset($_GET['action']) && $_GET['action'] === 'translate') {
  JobTranslate::handleTranslationRequest();
  exit;
}

// 3. Devuelvo los trabajos (funcionalidad original y por ID)
if ($method === 'GET') {
  try {
    // Si se pasa un ID, devolver solo ese trabajo
    if (isset($_GET['id'])) {
      $id = $_GET['id'];
      $sql = "SELECT * FROM bt_jobs WHERE id = ? AND status='open'";
      $stmt = db()->prepare($sql);
      $stmt->bindValue(1, $id, PDO::PARAM_STR);
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

// Manejo de POST para crear trabajos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);

  $job = [
    'id' => 'job-' . rand(1000, 9999),
    'title' => $input['title'] ?? 'Nuevo trabajo',
    'company' => 'Bubblegum.agency',
    'location' => $input['location'] ?? 'Madrid',
    'description' => $input['description'] ?? 'Descripción del trabajo',
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s')
  ];

  echo json_encode([
    'success' => true,
    'message' => 'Trabajo creado exitosamente',
    'data' => $job
  ]);
  exit;
}

// 4. Si no es GET o POST, devuelvo error
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
