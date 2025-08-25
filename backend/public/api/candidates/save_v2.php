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

// cookie HttpOnly obligatoria

// Proteger solo mÃ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
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

// ORIGINAL CODE BELOW
/**
 * Endpoint para guardar datos de candidato extraÃƒÂ­dos por IA
 * VersiÃƒÂ³n actualizada con esquema de BD correcto
 */

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

  // Generar ID ÃƒÂºnico para el candidato
  $candidateId = uniqid('cnd-');

  // Extraer nombre y apellido del nombre completo
  $nombreParts = explode(' ', $data['nombre'], 2);
  $firstName = $nombreParts[0];
  $lastName = isset($nombreParts[1]) ? $nombreParts[1] : '';

  // Insertar candidato principal con campos correctos de la BD
  $stmt = $pdo->prepare("
        INSERT INTO bt_candidates (
            id, first_name, last_name, name, email, password_hash,
            phone, linkedin_url, portfolio_url, date_of_birth, location,
            professional_summary, soft_skills, hard_skills, languages, interests,
            `references`, availability, certifications, cv_original_file, cv_text_file, cv_json_file,
            data_source, status, registration_source, created_at,
            gdpr_consent_given, IA_processing_consent
        ) VALUES (
            :id, :first_name, :last_name, :name, :email, :password_hash,
            :phone, :linkedin_url, :portfolio_url, :date_of_birth, :location,
            :professional_summary, :soft_skills, :hard_skills, :languages, :interests,
            :references, :availability, :certifications, :cv_original_file, :cv_text_file, :cv_json_file,
            :data_source, :status, :registration_source, NOW(),
            :gdpr_consent, :ia_consent
        )
    ");

  $stmt->execute([
    'id' => $candidateId,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'name' => $data['nombre'],
    'email' => $data['email'],
    'password_hash' => password_hash('temp123', PASSWORD_DEFAULT), // Temporal
    'phone' => $data['telefono'] ?? null,
    'linkedin_url' => $data['linkedin'] ?? null,
    'portfolio_url' => $data['portfolio'] ?? null,
    'date_of_birth' => !empty($data['fecha_nacimiento']) ? $data['fecha_nacimiento'] : null,
    'location' => $data['ubicacion_actual'] ?? null,
    'professional_summary' => $data['resumen_profesional'] ?? null,
    'soft_skills' => isset($data['soft_skills']) ? json_encode($data['soft_skills']) : null,
    'hard_skills' => isset($data['hard_skills']) ? json_encode($data['hard_skills']) : null,
    'languages' => isset($data['idiomas']) ? json_encode($data['idiomas']) : null,
    'interests' => isset($data['intereses']) ? json_encode($data['intereses']) : null,
    'references' => $data['referencias'] ?? null,
    'availability' => $data['disponibilidad'] ?? null,
    'certifications' => isset($data['certificaciones']) ? json_encode($data['certificaciones']) : null,
    'cv_original_file' => $data['cv_original_file'] ?? null,
    'cv_text_file' => $data['cv_text_file'] ?? null,
    'cv_json_file' => $data['cv_json_file'] ?? null,
    'data_source' => $data['data_source'] ?? 'ai_processing',
    'status' => 'active',
    'registration_source' => 'cv_upload_ai',
    'gdpr_consent' => 1,
    'ia_consent' => 1
  ]);

  // Insertar experiencias laborales
  if (!empty($data['puestos_anteriores']) && is_array($data['puestos_anteriores'])) {
    $expStmt = $pdo->prepare("
            INSERT INTO bt_candidate_experiences (
                candidate_id, company, position, start_date, end_date,
                description, location, current
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

    foreach ($data['puestos_anteriores'] as $exp) {
      // Validar y limpiar fechas vacÃƒÂ­as
      $startDate = (!empty($exp['fecha_inicio']) && $exp['fecha_inicio'] !== '') ? $exp['fecha_inicio'] : null;
      $endDate = (!empty($exp['fecha_fin']) && $exp['fecha_fin'] !== '') ? $exp['fecha_fin'] : null;

      $expStmt->execute([
        $candidateId,
        $exp['empresa'] ?? '',
        $exp['puesto'] ?? '',
        $startDate,
        $endDate,
        $exp['descripcion'] ?? '',
        $exp['ubicacion'] ?? null,
        isset($exp['actual']) ? ($exp['actual'] ? 1 : 0) : 0
      ]);
    }
  }

  // Insertar educaciÃƒÂ³n
  if (!empty($data['educacion']) && is_array($data['educacion'])) {
    $eduStmt = $pdo->prepare("
            INSERT INTO bt_candidate_education (
                id, candidate_id, degree, field_of_study, institution,
                start_date, end_date, education_level
            ) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?)
        ");

    foreach ($data['educacion'] as $edu) {
      // Validar y limpiar fechas vacÃƒÂ­as
      $startDate = (!empty($edu['fecha_inicio']) && $edu['fecha_inicio'] !== '') ? $edu['fecha_inicio'] : null;
      $endDate = (!empty($edu['fecha_fin']) && $edu['fecha_fin'] !== '') ? $edu['fecha_fin'] : null;

      $eduStmt->execute([
        $candidateId,
        $edu['titulo'] ?? '',
        $edu['campo_estudio'] ?? null,
        $edu['institucion'] ?? '',
        $startDate,
        $endDate,
        $edu['nivel_educativo'] ?? null
      ]);
    }
  }

  // Insertar certificaciones detalladas
  if (!empty($data['certificaciones_detalle']) && is_array($data['certificaciones_detalle'])) {
    $certStmt = $pdo->prepare("
            INSERT INTO bt_candidate_certifications (
                id, candidate_id, certification_name, issuer,
                issue_date, expiry_date
            ) VALUES (UUID(), ?, ?, ?, ?, ?)
        ");

    foreach ($data['certificaciones_detalle'] as $cert) {
      // Validar y limpiar fechas vacÃƒÂ­as
      $issueDate = (!empty($cert['fecha_emision']) && $cert['fecha_emision'] !== '') ? $cert['fecha_emision'] : null;
      $expiryDate = (!empty($cert['fecha_expiracion']) && $cert['fecha_expiracion'] !== '') ? $cert['fecha_expiracion'] : null;

      $certStmt->execute([
        $candidateId,
        $cert['nombre_certificacion'] ?? '',
        $cert['emisor'] ?? null,
        $issueDate,
        $expiryDate
      ]);
    }
  }

  // Insertar idiomas detallados
  if (!empty($data['idiomas_detalle']) && is_array($data['idiomas_detalle'])) {
    $langStmt = $pdo->prepare("
            INSERT INTO bt_candidate_languages (
                id, candidate_id, language, proficiency_level
            ) VALUES (UUID(), ?, ?, ?)
        ");

    foreach ($data['idiomas_detalle'] as $idioma) {
      $langStmt->execute([
        $candidateId,
        $idioma['idioma'] ?? '',
        $idioma['nivel_competencia'] ?? null
      ]);
    }
  }

  // Insertar proyectos
  if (!empty($data['proyectos']) && is_array($data['proyectos'])) {
    $projStmt = $pdo->prepare("
            INSERT INTO bt_candidate_projects (
                candidate_id, project_name, description, tecnologies,
                start_date, end_date, url
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

    foreach ($data['proyectos'] as $proyecto) {
      // Validar y limpiar fechas vacÃƒÂ­as
      $startDate = (!empty($proyecto['fecha_inicio']) && $proyecto['fecha_inicio'] !== '') ? $proyecto['fecha_inicio'] : null;
      $endDate = (!empty($proyecto['fecha_fin']) && $proyecto['fecha_fin'] !== '') ? $proyecto['fecha_fin'] : null;

      $projStmt->execute([
        $candidateId,
        $proyecto['nombre'] ?? '',
        $proyecto['descripcion'] ?? '',
        isset($proyecto['tecnologias']) ? json_encode($proyecto['tecnologias']) : null,
        $startDate,
        $endDate,
        $proyecto['url'] ?? null
      ]);
    }
  }

  // Insertar referencias detalladas
  if (!empty($data['referencias_detalle']) && is_array($data['referencias_detalle'])) {
    $refStmt = $pdo->prepare("
            INSERT INTO bt_candidate_references (
                id, candidate_id, ref_name, ref_company,
                ref_email, ref_phone
            ) VALUES (UUID(), ?, ?, ?, ?, ?)
        ");

    foreach ($data['referencias_detalle'] as $ref) {
      $refStmt->execute([
        $candidateId,
        $ref['nombre_referencia'] ?? '',
        $ref['empresa_referencia'] ?? null,
        $ref['email_referencia'] ?? null,
        $ref['telefono_referencia'] ?? null
      ]);
    }
  }

  // Confirmar transacciÃƒÂ³n
  $pdo->commit();

  ResponseHelper::success('Candidato creado exitosamente', [
    'candidate_id' => $candidateId,
    'data_source' => $data['data_source'] ?? 'ai_processing'
  ], 201);
} catch (Exception $e) {
  // Revertir transacciÃƒÂ³n en caso de error
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log("Error guardando candidato: " . $e->getMessage());
  ResponseHelper::error('Error interno del servidor: ' . $e->getMessage(), 500);
}

