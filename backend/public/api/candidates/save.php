<?php



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

/**
 * Endpoint para guardar datos de candidato extraÃƒÂ­dos por IA
 * 
 * Guarda toda la informaciÃƒÂ³n estructurada del candidato en las tablas correspondientes
 */

// cookie HttpOnly obligatoria
// POST requiere CSRF

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
  if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
  }
}

use Utils\ResponseHelper;
use Utils\Database;

// Solo permitir mÃƒÂ©todo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  ResponseHelper::error('MÃƒÂ©todo no permitido', 405);
  exit;
}

try {
  // Obtener el contenido JSON
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    ResponseHelper::error('JSON invÃƒÂ¡lido: ' . json_last_error_msg(), 400);
    exit;
  }

  // Validar campos mÃƒÂ­nimos requeridos
  if (empty($data['nombre']) || empty($data['email'])) {
    ResponseHelper::error('Faltan campos requeridos: nombre y email', 400);
    exit;
  }

  $db = Database::getInstance();
  $pdo = $db->getConnection();

  // Iniciar transacciÃƒÂ³n
  $pdo->beginTransaction();

  // Insertar candidato principal (mapear campos a la estructura real)
  $stmt = $pdo->prepare("
        INSERT INTO bt_candidates (
            id, name, email, phone, location, date_of_birth,
            portfolio_url, linkedin_url, professional_summary,
            soft_skills, hard_skills, languages, interests, `references`,
            availability, data_source, cv_original_file, cv_text_file, cv_json_file,
            created_at
        ) VALUES (
            UUID(), :name, :email, :phone, :location, :date_of_birth,
            :portfolio_url, :linkedin_url, :professional_summary,
            :soft_skills, :hard_skills, :languages, :interests, :references,
            :availability, :data_source, :cv_original_file, :cv_text_file, :cv_json_file,
            NOW()
        )
    ");
  $stmt->execute([
    'name' => $data['nombre'],
    'email' => $data['email'],
    'phone' => $data['telefono'] ?? null,
    'location' => $data['ubicacion_actual'] ?? null,
    'date_of_birth' => $data['fecha_nacimiento'] ?? null,
    'portfolio_url' => $data['portfolio'] ?? null,
    'linkedin_url' => $data['linkedin'] ?? null,
    'professional_summary' => $data['resumen_profesional'] ?? null,
    'soft_skills' => isset($data['soft_skills']) ? json_encode($data['soft_skills']) : null,
    'hard_skills' => isset($data['hard_skills']) ? json_encode($data['hard_skills']) : null,
    'languages' => isset($data['idiomas']) ? json_encode($data['idiomas']) : null,
    'interests' => isset($data['intereses']) ? json_encode($data['intereses']) : null,
    'references' => $data['referencias'] ?? null,
    'availability' => $data['disponibilidad'] ?? null,
    'data_source' => $data['data_source'] ?? 'manual_entry',
    'cv_original_file' => $data['cv_original_file'] ?? null,
    'cv_text_file' => $data['cv_text_file'] ?? null,
    'cv_json_file' => $data['cv_json_file'] ?? null
  ]);

  // Obtener el ID del candidato insertado
  $candidateStmt = $pdo->prepare("SELECT id FROM bt_candidates WHERE email = ? ORDER BY created_at DESC LIMIT 1");
  $candidateStmt->execute([$data['email']]);
  $candidateResult = $candidateStmt->fetch();
  $candidateId = $candidateResult['id'];

  // Insertar experiencias laborales (mapear campos)
  if (!empty($data['puestos_anteriores']) && is_array($data['puestos_anteriores'])) {
    $expStmt = $pdo->prepare("
            INSERT INTO bt_candidate_experiences (
                candidate_id, position, company, start_date, end_date,
                description, location, current
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

    foreach ($data['puestos_anteriores'] as $exp) {
      $expStmt->execute([
        $candidateId,
        $exp['puesto'] ?? '',
        $exp['empresa'] ?? '',
        $exp['fecha_inicio'] ?? null,
        $exp['fecha_fin'] ?? null,
        $exp['descripcion'] ?? '',
        $exp['ubicacion'] ?? null,
        isset($exp['actual']) ? ($exp['actual'] ? 1 : 0) : 0
      ]);
    }
  }

  // Confirmar transacciÃƒÂ³n
  $pdo->commit();

  ResponseHelper::success('Candidato creado exitosamente', [
    'candidate_id' => $candidateId,
    'data_source' => $data['data_source'] ?? 'manual_entry'
  ], 201);
} catch (Exception $e) {
  // Revertir transacciÃƒÂ³n en caso de error
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log("Error guardando candidato: " . $e->getMessage());
  ResponseHelper::error('Error interno del servidor: ' . $e->getMessage(), 500);
}

