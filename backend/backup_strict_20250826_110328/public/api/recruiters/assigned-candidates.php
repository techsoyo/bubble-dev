<?php declare(strict_types=1);



require_once __DIR__ . '/../bootstrap.php';
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

// assigned-candidates.php - Obtener candidatos asignados a un reclutador
session_start();

// CORS headers
header('Access-Control-Allow-Origin: http://localhost:3002');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
  // Get recruiter info from session or request
  $recruiterId = $_GET['recruiter_id'] ?? null;

  if (!$recruiterId && isset($_SESSION['user_id'])) {
    $recruiterId = $_SESSION['user_id'];
  }

  if (!$recruiterId && isset($_SESSION['user_email'])) {
    // Get recruiter ID from email
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id FROM bt_staff_profiles WHERE email = ? AND role = 'recruiter'");
    $stmt->execute([$_SESSION['user_email']]);
    $recruiter = $stmt->fetch();
    $recruiterId = $recruiter['id'] ?? null;
  }

  if (!$recruiterId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Reclutador no identificado']);
    exit();
  }

  // Connect to database
  $db = getDBConnection();

  // Query to get assigned candidates for the recruiter
  $query = "
        SELECT DISTINCT
            c.id as candidateId,
            c.name as candidateName,
            c.email,
            c.phone,
            c.location as candidateLocation,
            c.professional_summary,
            c.hard_skills,
            c.soft_skills,
            c.created_at,
            c.status,
            
            -- InformaciÃƒÆ’Ã‚Â³n de departamento del candidato
            cd.id as department_id,
            cd.name as candidateDepartment,
            cdc.name as department_category,
            
            -- InformaciÃƒÆ’Ã‚Â³n de asignaciÃƒÆ’Ã‚Â³n
            ca.assigned_at,
            ca.status as assignment_status,
            ca.notes as assignment_notes,
            
            -- InformaciÃƒÆ’Ã‚Â³n del reclutador
            sp.name as recruiterName,
            sp.email as recruiterEmail,
            sp.department as recruiterDepartment
            
        FROM bt_candidates c
        LEFT JOIN bt_candidate_assignments ca ON c.id = ca.candidate_id
        LEFT JOIN bt_staff_profiles sp ON ca.recruiter_id = sp.id
        LEFT JOIN bt_departments cd ON c.department_id = cd.id
        LEFT JOIN bt_department_categories cdc ON c.department_category_id = cdc.id
        
        WHERE ca.recruiter_id = ?
        AND ca.status = 'active'
        ORDER BY ca.assigned_at DESC
    ";

  $stmt = $db->prepare($query);
  $stmt->execute([$recruiterId]);
  $candidates = $stmt->fetchAll();

  // If no assigned candidates found, check if recruiter exists
  if (empty($candidates)) {
    $recruiterCheck = $db->prepare("SELECT id, name FROM bt_staff_profiles WHERE id = ? AND role = 'recruiter'");
    $recruiterCheck->execute([$recruiterId]);
    $recruiterExists = $recruiterCheck->fetch();

    if (!$recruiterExists) {
      http_response_code(404);
      echo json_encode(['success' => false, 'message' => 'Reclutador no encontrado']);
      exit();
    }

    // Recruiter exists but has no assigned candidates
    echo json_encode([
      'success' => true,
      'data' => [],
      'message' => 'No hay candidatos asignados a este reclutador',
      'recruiter' => [
        'id' => $recruiterId,
        'name' => $recruiterExists['name']
      ]
    ]);
    exit();
  }

  // Format the results
  $formattedCandidates = array_map(function ($candidate) {
    // Parse JSON skills
    $hardSkills = [];
    $softSkills = [];

    if ($candidate['hard_skills']) {
      $decoded = json_decode($candidate['hard_skills'], true);
      $hardSkills = is_array($decoded) ? $decoded : [];
    }

    if ($candidate['soft_skills']) {
      $decoded = json_decode($candidate['soft_skills'], true);
      $softSkills = is_array($decoded) ? $decoded : [];
    }

    return [
      'candidateId' => $candidate['candidateId'],
      'candidateName' => $candidate['candidateName'],
      'email' => $candidate['email'],
      'phone' => $candidate['phone'],
      'candidateLocation' => $candidate['candidateLocation'],
      'professional_summary' => $candidate['professional_summary'],
      'hardSkills' => $hardSkills,
      'softSkills' => $softSkills,
      'allSkills' => array_merge($hardSkills, $softSkills),
      'status' => $candidate['status'],
      'candidateDepartment' => $candidate['candidateDepartment'],
      'department_category' => $candidate['department_category'],
      'assignedDate' => $candidate['assigned_at'],
      'assignment_status' => $candidate['assignment_status'],
      'assignment_notes' => $candidate['assignment_notes'],
      'recruiterName' => $candidate['recruiterName'],
      'recruiterEmail' => $candidate['recruiterEmail'],
      'recruiterDepartment' => $candidate['recruiterDepartment'],
      'created_at' => $candidate['created_at']
    ];
  }, $candidates);

  echo json_encode([
    'success' => true,
    'data' => $formattedCandidates,
    'count' => count($formattedCandidates),
    'recruiter_id' => $recruiterId
  ]);
} catch (Exception $e) {
  error_log('Error getting assigned candidates: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error interno del servidor',
    'error' => $e->getMessage()
  ]);
}

