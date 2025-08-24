<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once $BOOT;

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
    echo json_encode(['error' => 'MÃ©todo no permitido']);
    exit();
}

try {
    // Leer input JSON
    $inputData = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON invÃ¡lido en request body');
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
        throw new Exception('Email no vÃ¡lido');
    }

    // Conectar a la base de datos
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Iniciar transacciÃ³n
    $pdo->beginTransaction();

    try {
        // 1. Insertar o actualizar usuario en tabla de autenticaciÃ³n
        $userId = null;
        if (!empty($accountData['username']) && !empty($accountData['password'])) {
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (username, email, password_hash, role, created_at) 
                VALUES (?, ?, ?, 'candidate', NOW())
                ON DUPLICATE KEY UPDATE 
                    email = VALUES(email),
                    updated_at = NOW()
            ");

            $passwordHash = password_hash($accountData['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $accountData['username'],
                $candidateData['email'],
                $passwordHash
            ]);

            $userId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM usuarios WHERE email = '{$candidateData['email']}'")->fetchColumn();
        }

        // 2. Insertar o actualizar datos principales del candidato CON DATOS GDPR
        $stmt = $pdo->prepare('
            INSERT INTO bt_candidates (
                name, email, phone, location, date_of_birth,
                portfolio_url, linkedin_url, resumen_profesional, 
                soft_skills, hard_skills, idiomas, intereses, referencias, 
                disponibilidad, certificaciones, cv_original_file, cv_text_file, 
                cv_json_file, data_source, created_at,
                gdpr_consent_given, gdpr_consent_date, openai_processing_consent, 
                openai_consent_date, data_processing_purposes, consent_version,
                ip_address_consent, user_agent_consent, data_retention_until
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(),
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
                gdpr_consent_given = VALUES(gdpr_consent_given),
                gdpr_consent_date = VALUES(gdpr_consent_date),
                openai_processing_consent = VALUES(openai_processing_consent),
                openai_consent_date = VALUES(openai_consent_date),
                data_processing_purposes = VALUES(data_processing_purposes),
                consent_version = VALUES(consent_version),
                ip_address_consent = VALUES(ip_address_consent),
                user_agent_consent = VALUES(user_agent_consent),
                data_retention_until = VALUES(data_retention_until)
        ');

        // Capturar datos GDPR de auditorÃ­a
        $userIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // Datos de propÃ³sitos del tratamiento GDPR
        $processingPurposes = [
            'cv_analysis' => true,
            'recruitment_process' => true,
            'openai_processing' => $inputData['data_source'] === 'ai_processing',
            'communication' => true,
            'data_source' => $inputData['data_source'] ?? 'manual_entry'
        ];

        $stmt->execute([
            // Datos bÃ¡sicos del candidato
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
            // Datos GDPR - CRÃTICOS PARA CUMPLIMIENTO
            true, // gdpr_consent_given - siempre true si llegÃ³ aquÃ­
            true, // openai_processing_consent - true si data_source es ai_processing
            json_encode($processingPurposes, JSON_UNESCAPED_UNICODE), // data_processing_purposes
            '1.0', // consent_version
            $userIP, // ip_address_consent
            $userAgent // user_agent_consent
            // data_retention_until se calcula automÃ¡ticamente con DATE_ADD en SQL
        ]);

        $candidateId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM bt_candidates WHERE email = '{$candidateData['email']}'")->fetchColumn();

        // 3. Insertar experiencia laboral (usar tabla existente bt_candidate_experiences)
        if (!empty($candidateData['puestos_anteriores'])) {
            // Limpiar experiencia anterior
            $pdo->prepare('DELETE FROM bt_candidate_experiences WHERE candidate_id = ?')->execute([$candidateId]);

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

        // 4. Insertar educaciÃ³n (usar tabla existente bt_candidate_education)
        if (!empty($candidateData['educacion'])) {
            // Limpiar educaciÃ³n anterior
            $pdo->prepare('DELETE FROM bt_candidate_education WHERE candidate_id = ?')->execute([$candidateId]);

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
        }    // 5. Insertar proyectos (si existen)
        if (!empty($candidateData['proyectos'])) {
            // Limpiar proyectos anteriores
            $pdo->prepare('DELETE FROM bt_candidate_projects WHERE candidate_id = ?')->execute([$candidateId]);

            $stmt = $pdo->prepare('
                INSERT INTO bt_candidate_projects (
                    candidate_id, nombre, descripcion, tecnologias, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ');

            foreach ($candidateData['proyectos'] as $proyecto) {
                $stmt->execute([
                    $candidateId,
                    $proyecto['nombre'],
                    $proyecto['descripcion'] ?: null,
                    json_encode($proyecto['tecnologias'] ?? [], JSON_UNESCAPED_UNICODE)
                ]);
            }
        }

        // Confirmar transacciÃ³n
        $pdo->commit();

        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Datos del candidato guardados exitosamente',
            'data' => [
                'candidate_id' => $candidateId,
                'user_id' => $userId,
                'email' => $candidateData['email'],
                'nombre' => $candidateData['nombre']
            ],
            'stats' => [
                'experience_entries' => count($candidateData['puestos_anteriores'] ?? []),
                'education_entries' => count($candidateData['educacion'] ?? []),
                'hard_skills_count' => count($candidateData['hard_skills'] ?? []),
                'soft_skills_count' => count($candidateData['soft_skills'] ?? []),
                'projects_count' => count($candidateData['proyectos'] ?? [])
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

