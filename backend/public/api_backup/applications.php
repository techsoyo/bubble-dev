<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Utils/JWTMiddleware.php';
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

// Configurar CORS y headers de seguridad
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function jsend($ok, $message, $data = null, $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
// ===== GET: AHORA CON AUTENTICACIÓN JWT =====
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ✅ REQUERIR AUTENTICACIÓN JWT
    $userPayload = JWTMiddleware::requireAuth();
    if (!$userPayload) {
        // JWTMiddleware ya envió la respuesta de error
        exit;
    }

    try {
        $db = getDbConnection();

        // Obtener parámetros de query
        $candidateId = $_GET['candidate_id'] ?? null;
        $jobId = $_GET['job_id'] ?? null;
        $status = $_GET['status'] ?? null;
        $limit = min((int)($_GET['limit'] ?? 20), 100); // Máximo 100
        $offset = (int)($_GET['offset'] ?? 0);

        // ✅ CONTROL DE ACCESO: Solo admins o el propio candidato puede ver aplicaciones
        $userRole = $userPayload['role'] ?? 'candidate';
        $userId = $userPayload['user_id'];

        if ($userRole === 'candidate') {
            // Los candidatos solo pueden ver sus propias aplicaciones
            $candidateId = $userId;
        } elseif (!in_array($userRole, ['admin', 'hr', 'recruiter'])) {
            jsend(false, 'Permisos insuficientes', null, 403);
            exit;
        }

        // Construir query con filtros
        $whereClauses = [];
        $params = [];

        if ($candidateId) {
            $whereClauses[] = 'a.candidate_id = ?';
            $params[] = $candidateId;
        }
        if ($jobId) {
            $whereClauses[] = 'a.job_id = ?';
            $params[] = $jobId;
        }
        if ($status) {
            $whereClauses[] = 'a.status = ?';
            $params[] = $status;
        }

        $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        // ✅ QUERY SEGURA CON JOINS Y PREPARED STATEMENTS
        $sql = "
            SELECT 
                a.id,
                a.candidate_id,
                a.job_id,
                a.status,
                a.score,
                a.created_at as applied_at,
                a.updated_at,
                c.name as candidate_name,
                c.email as candidate_email,
                j.title as job_title,
                j.company_name,
                j.location as job_location,
                j.salary_range
            FROM bt_applications a
            LEFT JOIN bt_candidates c ON a.candidate_id = c.id
            LEFT JOIN bt_jobs j ON a.job_id = j.id
            $whereSQL
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ✅ SANITIZAR DATOS SENSIBLES SEGÚN ROL
        foreach ($applications as &$app) {
            if ($userRole === 'candidate') {
                // Los candidatos no deben ver emails de otros candidatos
                unset($app['candidate_email']);
            }

            // Formatear fechas
            if ($app['applied_at']) {
                $app['applied_at'] = date('Y-m-d H:i:s', strtotime($app['applied_at']));
            }
        }

        // Obtener count total para paginación
        $countSQL = "SELECT COUNT(*) FROM bt_applications a $whereSQL";
        $countStmt = $db->prepare($countSQL);
        $countStmt->execute(array_slice($params, 0, -2)); // Excluir LIMIT y OFFSET
        $totalCount = (int)$countStmt->fetchColumn();

        jsend(true, 'Aplicaciones obtenidas exitosamente', [
            'applications' => $applications,
            'pagination' => [
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount
            ],
            'filters' => [
                'candidate_id' => $candidateId,
                'job_id' => $jobId,
                'status' => $status
            ]
        ], 200);
    } catch (Exception $e) {
        error_log("GET APPLICATIONS ERROR: " . $e->getMessage());
        jsend(false, 'Error al obtener aplicaciones', null, 500);
    }
    exit;
}

// ===== POST: TAMBIÉN REQUIERE AUTENTICACIÓN =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ REQUERIR AUTENTICACIÓN JWT
    $userPayload = JWTMiddleware::requireAuth();
    if (!$userPayload) {
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['job_id'])) {
        jsend(false, 'job_id es requerido', null, 400);
        exit;
    }

    $jobId = $input['job_id'];
    $candidateId = $userPayload['user_id']; // El candidato autenticado
    $coverLetter = trim($input['cover_letter'] ?? '');

    try {
        $db = getDbConnection();

        // ✅ VERIFICAR QUE EL TRABAJO EXISTE Y ESTÁ ACTIVO
        $jobStmt = $db->prepare('SELECT id, title FROM bt_jobs WHERE id = ? AND status = "open"');
        $jobStmt->execute([$jobId]);
        $job = $jobStmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            jsend(false, 'El trabajo no existe o no está disponible', null, 404);
            exit;
        }

        // ✅ VERIFICAR QUE NO HAYA APLICACIÓN DUPLICADA
        $existingStmt = $db->prepare('SELECT id FROM bt_applications WHERE candidate_id = ? AND job_id = ?');
        $existingStmt->execute([$candidateId, $jobId]);

        if ($existingStmt->fetch()) {
            jsend(false, 'Ya has aplicado a este trabajo anteriormente', null, 409);
            exit;
        }

        // ✅ CREAR APLICACIÓN CON PREPARED STATEMENTS
        $insertSQL = "INSERT INTO bt_applications (candidate_id, job_id, status, cover_letter, created_at, updated_at) VALUES (?, ?, 'pending', ?, NOW(), NOW())";

        $insertStmt = $db->prepare($insertSQL);
        $success = $insertStmt->execute([$candidateId, $jobId, $coverLetter]);

        if (!$success) {
            throw new Exception('Error al insertar aplicación');
        }

        $applicationId = $db->lastInsertId();

        // Log de auditoría
        error_log("APPLICATION CREATED: ID $applicationId - Candidate: $candidateId - Job: $jobId");

        jsend(true, 'Aplicación creada exitosamente', [
            'id' => $applicationId,
            'candidate_id' => $candidateId,
            'job_id' => $jobId,
            'job_title' => $job['title'],
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ], 201);
    } catch (Exception $e) {
        error_log("CREATE APPLICATION ERROR: " . $e->getMessage());
        jsend(false, 'Error al crear la aplicación', null, 500);
    }
    exit;
}

// Método no permitido
jsend(false, 'Método no permitido', null, 405);
