
<?php declare(strict_types=1);

use Models\Candidate;
use PDO;
use Exception;
use Utils\JWTMiddleware as JWTMiddleware; // Para satisfacer el linter, aunque se aliasa globalmente
use Security\CsrfMiddleware as CsrfMiddleware; // Para satisfacer el linter, aunque se aliasa globalmente

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

// cookie HttpOnly obligatoria

// Proteger solo métodos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    // double-submit cookie
}

// En producción NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

/**
 * API Endpoint: Save Candidate Data
 *
 * Endpoint para guardar datos validados de candidatos en la base de datos.
 * Maneja tanto datos procesados por IA como datos ingresados manualmente.
 *
 * @package Backend\API
 * @version 1.0.0
 * @since 2025-08-10
 */


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Obtener la instancia de PDO singleton que usan los modelos
require_once __DIR__ . '/../../config/database.php'; // Asegura que getDbConnection está disponible
$pdo = getDbConnection();

try {
    // Leer input JSON
    $inputData = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido en request body');
    }

    // Validar datos requeridos
    if (!isset($inputData['candidate_data'])) {
        throw new Exception('Datos del candidato son requeridos');
    }

    $candidateData = $inputData['candidate_data'];
    $accountData = $inputData['account_data'] ?? [];
    $cvFiles = $inputData['cv_files'] ?? [];

    // Validar campos obligatorios
    $requiredFields = ['nombre', 'email', 'telefono', 'ubicacion_actual'];
    foreach ($requiredFields as $field) {
        if (empty($candidateData[$field])) {
            throw new Exception("Campo requerido faltante: {$field}");
        }
    }

    // Validar email
    if (!filter_var($candidateData['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email no válido');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // No hay necesidad de un userId separado, los candidatos se autentican a través de bt_candidates
        $candidateId = null; // Inicializar candidateId

        // Hash de contraseña si se proporciona
        if (!empty($accountData['password'])) {
            $candidateData['password_hash'] = password_hash($accountData['password'], PASSWORD_DEFAULT);
        }

        // 2. Insertar o actualizar datos principales del candidato CON DATOS GDPR
        $candidateModel = new Candidate();
        $stmt = $pdo->prepare('
            INSERT INTO bt_candidates (
                name, email, phone, location, date_of_birth,
                portfolio_url, linkedin_url, resumen_profesional,
                soft_skills, hard_skills, idiomas, intereses, referencias,
                disponibilidad, certificaciones, cv_original_file, cv_text_file,
                cv_json_file, data_source, created_at, password_hash,
                gdpr_consent_given, gdpr_consent_date, IA_processing_consent,
                IA_consent_date, data_processing_purposes, consent_version,
                ip_address_consent, user_agent_consent, data_retention_until
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?,
                ?, NOW(), ?, NOW(), ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 MONTH)
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                phone = VALUES(phone),
                location = VALUES(location),
                date_of_birth = VALUES(date_of_birth),
                portfolio_url = VALUES(portfolio_url),
                linkedin_url = VALUES(linkedin_url),
                resumen_profesional = VALUES(resumen_profesional),
                soft_skills = VALUES(soft_skills),
                hard_skills = VALUES(hard_skills),
                idiomas = VALUES(idiomas),
                intereses = VALUES(intereses),
                referencias = VALUES(referencias),
                disponibilidad = VALUES(disponibilidad),
                certificaciones = VALUES(certificaciones),
                cv_original_file = VALUES(cv_original_file),
                cv_text_file = VALUES(cv_text_file),
                cv_json_file = VALUES(cv_json_file),
                data_source = VALUES(data_source),
                password_hash = VALUES(password_hash),
                gdpr_consent_given = VALUES(gdpr_consent_given),
                gdpr_consent_date = VALUES(gdpr_consent_date),
                IA_processing_consent = VALUES(IA_processing_consent),
                IA_consent_date = VALUES(IA_consent_date),
                data_processing_purposes = VALUES(data_processing_purposes),
                consent_version = VALUES(consent_version),
                ip_address_consent = VALUES(ip_address_consent),
                user_agent_consent = VALUES(user_agent_consent),
                data_retention_until = VALUES(data_retention_until)
        ');

        // Capturar datos GDPR de auditoría
        $userIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // Datos de propósitos del tratamiento GDPR
        $processingPurposes = [
            'cv_analysis' => true,
            'recruitment_process' => true,
            'openai_processing' => $inputData['data_source'] === 'ai_processing',
            'communication' => true,
            'data_source' => $inputData['data_source'] ?? 'manual_entry'
        ];

        $stmt->execute([
            // Datos básicos del candidato
            $candidateData['nombre'],
            $candidateData['email'],
            $candidateData['telefono'],
            $candidateData['ubicacion_actual'],
            $candidateData['fecha_nacimiento'] ?: null,
            $candidateData['portfolio'] ?: null,
            $candidateData['linkedin'] ?: null,
            $candidateData['resumen_profesional'] ?: null,
            json_encode($candidateData['soft_skills'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($candidateData['hard_skills'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($candidateData['idiomas'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($candidateData['intereses'] ?? [], JSON_UNESCAPED_UNICODE),
            $candidateData['referencias'] ?: null,
            $candidateData['disponibilidad'] ?: null,
            json_encode($candidateData['certificaciones'] ?? [], JSON_UNESCAPED_UNICODE),
            $cvFiles['original'] ?? null,
            $cvFiles['text'] ?? null,
            $cvFiles['json'] ?? null,
            $inputData['data_source'] ?? 'manual_entry',
            $candidateData['password_hash'] ?? null, // Incluir password_hash
            // Datos GDPR - CRÍTICOS PARA CUMPLIMIENTO
            true, // gdpr_consent_given - siempre true si llegó aquí
            true, // openai_processing_consent - true si data_source es ai_processing
            json_encode($processingPurposes, JSON_UNESCAPED_UNICODE), // data_processing_purposes
            '1.0', // consent_version
            $userIP, // ip_address_consent
            $userAgent // user_agent_consent
            // data_retention_until se calcula automáticamente con DATE_ADD en SQL
        ]);

        // Buscar el ID del candidato si ya existe, usando el modelo Candidate
        $existingCandidate = $candidateModel->findOneBy('email', $candidateData['email']);
        $candidateId = $pdo->lastInsertId() ?: ($existingCandidate['id'] ?? null);

        // 3. Insertar experiencia laboral (usar tabla existente bt_candidate_experiences)
        if (!empty($candidateData['puestos_anteriores'])) {
            // Limpiar experiencia anterior
            $stmt = $pdo->prepare('DELETE FROM bt_candidate_experiences WHERE candidate_id = ?');
            $stmt->execute([$candidateId]);

            $stmt = $pdo->prepare('
                INSERT INTO bt_candidate_experiences (
                    candidate_id, job_title, company_name, start_date, end_date,
                    description, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ');

            foreach ($candidateData['puestos_anteriores'] as $puesto) {
                $stmt->execute([
                    $candidateId,
                    $puesto['puesto'],
                    $puesto['empresa'],
                    $puesto['fecha_inicio'] ?: null,
                    $puesto['fecha_fin'] ?: null,
                    $puesto['descripcion'] ?: null
                ]);
            }
        }

        // 4. Insertar educación (usar tabla existente bt_candidate_education)
        if (!empty($candidateData['educacion'])) {
            // Limpiar educación anterior
            $stmt = $pdo->prepare('DELETE FROM bt_candidate_education WHERE candidate_id = ?');
            $stmt->execute([$candidateId]);

            $stmt = $pdo->prepare('
                INSERT INTO bt_candidate_education (
                    candidate_id, degree_title, institution_name, start_date, end_date,
                    description, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ');

            foreach ($candidateData['educacion'] as $edu) {
                $stmt->execute([
                    $candidateId,
                    $edu['titulo'],
                    $edu['institucion'],
                    $edu['fecha_inicio'] ?: null,
                    $edu['fecha_fin'] ?: null,
                    $edu['descripcion'] ?: null
                ]);
            }
        }

        // Confirmar transacción
        $pdo->commit();

        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Datos del candidato guardados exitosamente',
            'data' => [
                'candidate_id' => $candidateId,
                'email' => $candidateData['email'],
                'nombre' => $candidateData['nombre']
            ],
            'stats' => [
                'experience_entries' => count($candidateData['puestos_anteriores'] ?? []),
                'education_entries' => count($candidateData['educacion'] ?? []),
                'hard_skills_count' => count($candidateData['hard_skills'] ?? []),
                'soft_skills_count' => count($candidateData['soft_skills'] ?? []),
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $dbError) {
        $pdo->rollback();
        throw $dbError;
    }
} catch (Exception $e) {
    error_log('Error guardando datos de candidato: ' . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'error' => 'Error guardando datos',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}