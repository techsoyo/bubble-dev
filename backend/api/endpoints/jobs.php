<?php

declare(strict_types=1);
// Endpoint de gestión de trabajos (jobs)
// company_name se expone como "department" por compatibilidad frontend
$ROOT = dirname(__DIR__, 2); // endpoints → api → backend
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

$method = $_SERVER['REQUEST_METHOD'];

function validateJobInput($input, $isUpdate = false)
{
    $errors = [];
    $title = trim($input['title'] ?? '');
    if (!$isUpdate && $title === '') {
        $errors[] = 'title requerido';
    }
    if ($title && (mb_strlen($title) < 3 || mb_strlen($title) > 100)) {
        $errors[] = 'title longitud inválida';
    }
    $department = trim($input['department'] ?? '');
    if ($department && mb_strlen($department) > 100) {
        $errors[] = 'department muy largo';
    }
    $location = trim($input['location'] ?? '');
    if ($location && mb_strlen($location) > 100) {
        $errors[] = 'location muy largo';
    }
    $category = trim($input['category'] ?? '');
    if ($category && mb_strlen($category) > 100) {
        $errors[] = 'category muy largo';
    }
    $description = trim($input['description'] ?? '');
    if ($description && mb_strlen($description) > 2000) {
        $errors[] = 'description muy largo';
    }
    $status = trim($input['status'] ?? 'active');
    if ($status && !in_array($status, ['active', 'inactive', 'archived'])) {
        $errors[] = 'status inválido';
    }
    $requirements = $input['requirements'] ?? [];
    if ($requirements && (!is_array($requirements) || count($requirements) > 20)) {
        $errors[] = 'requirements inválido';
    }
    if (is_array($requirements)) {
        foreach ($requirements as $r) {
            if (!is_string($r) || mb_strlen($r) < 2 || mb_strlen($r) > 255) {
                $errors[] = 'requirement inválido';
            }
        }
    }
    return $errors;
}

try {
    $pdo = getDbConnection();
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = $_GET['id'];
                $stmt = $pdo->prepare('SELECT j.*, GROUP_CONCAT(DISTINCT jr.requirement) as requirements, GROUP_CONCAT(DISTINCT js.skill) as required_skills FROM ' . T('jobs') . ' j LEFT JOIN ' . T('job_requirements') . ' jr ON j.id = jr.job_id LEFT JOIN ' . T('job_skills') . ' js ON j.id = js.job_id WHERE j.id = ? GROUP BY j.id');
                $stmt->execute([$id]);
                $job = $stmt->fetch();
                if ($job) {
                    $jobFormatted = [
                        'id' => $job['id'],
                        'title' => $job['title'],
                        'department' => $job['company_name'] ?? 'Sin departamento',
                        'category' => $job['category'] ?? 'General',
                        'description' => $job['description'],
                        'requirements' => $job['requirements'] ? explode(',', $job['requirements']) : [],
                        'location' => $job['location'],
                        'salary' => $job['salary_min'] && $job['salary_max'] ? "{$job['salary_min']} - {$job['salary_max']}{$job['salary_currency']}" : 'A negociar',
                        'status' => $job['status'],
                        'created' => $job['created_at']
                    ];
                    http_response_code(200);
                    echo json_encode(['success' => true, 'data' => $jobFormatted]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Trabajo no encontrado']);
                }
            } else {
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
                $offset = ($page - 1) * $limit;
                $status = $_GET['status'] ?? null;
                $whereClause = $status ? 'WHERE j.status = ?' : '';
                $params = $status ? [$status] : [];
                $stmt = $pdo->prepare('SELECT j.*, GROUP_CONCAT(DISTINCT jr.requirement) as requirements, GROUP_CONCAT(DISTINCT js.skill) as required_skills FROM ' . T('jobs') . ' j LEFT JOIN ' . T('job_requirements') . ' jr ON j.id = jr.job_id LEFT JOIN ' . T('job_skills') . " js ON j.id = js.job_id $whereClause GROUP BY j.id ORDER BY j.created_at DESC LIMIT ? OFFSET ?");
                $params[] = $limit;
                $params[] = $offset;
                $stmt->execute($params);
                $jobs = $stmt->fetchAll();
                $jobsFormatted = array_map(function ($job) {
                    return [
                        'id' => $job['id'],
                        'title' => $job['title'],
                        'department' => $job['company_name'] ?? 'Sin departamento',
                        'category' => $job['category'] ?? 'General',
                        'description' => $job['description'],
                        'requirements' => $job['requirements'] ? explode(',', $job['requirements']) : [],
                        'location' => $job['location'],
                        'salary' => $job['salary_min'] && $job['salary_max'] ? "{$job['salary_min']} - {$job['salary_max']}{$job['salary_currency']}" : 'A negociar',
                        'status' => $job['status'],
                        'created' => $job['created_at']
                    ];
                }, $jobs);
                $countQuery = 'SELECT COUNT(*) FROM ' . T('jobs') . ' j' . ($status ? ' WHERE j.status = ?' : '');
                if ($status) {
                    $countStmt = $pdo->prepare($countQuery);
                    $countStmt->execute([$status]);
                } else {
                    $countStmt = $pdo->query($countQuery);
                }
                $total = $countStmt->fetchColumn();
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $jobsFormatted,
                    'pagination' => [
                        'total' => (int)$total,
                        'page' => $page,
                        'limit' => $limit,
                        'totalPages' => ceil($total / $limit)
                    ]
                ]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $errors = validateJobInput($input);
            if ($errors) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => implode('; ', $errors)]);
                break;
            }
            $jobId = 'job-' . uniqid();
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO ' . T('jobs') . ' (id, title, company_name, location, type, level, category, description, status, created_at, posted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
                $success = $stmt->execute([
                    $jobId,
                    $input['title'],
                    $input['department'] ?? 'Empresa',
                    $input['location'] ?? '',
                    'full_time',
                    'mid',
                    $input['category'] ?? 'General',
                    $input['description'] ?? '',
                    $input['status'] ?? 'active'
                ]);
                if (!empty($input['requirements']) && is_array($input['requirements'])) {
                    $reqStmt = $pdo->prepare('INSERT INTO ' . T('job_requirements') . ' (job_id, requirement) VALUES (?, ?)');
                    foreach ($input['requirements'] as $requirement) {
                        $reqStmt->execute([$jobId, trim($requirement)]);
                    }
                }
                $pdo->commit();
                http_response_code(201);
                echo json_encode(['success' => true, 'data' => ['id' => $jobId]]);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al crear trabajo']);
            }
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $jobId = $_GET['id'] ?? null;
            if (!$jobId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de trabajo requerido']);
                break;
            }
            $errors = validateJobInput($input, true);
            if ($errors) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => implode('; ', $errors)]);
                break;
            }
            $updateFields = [];
            $params = [];
            if (isset($input['title'])) {
                $updateFields[] = 'title = ?';
                $params[] = $input['title'];
            }
            if (isset($input['department'])) {
                $updateFields[] = 'company_name = ?';
                $params[] = $input['department'];
            }
            if (isset($input['location'])) {
                $updateFields[] = 'location = ?';
                $params[] = $input['location'];
            }
            if (isset($input['category'])) {
                $updateFields[] = 'category = ?';
                $params[] = $input['category'];
            }
            if (isset($input['description'])) {
                $updateFields[] = 'description = ?';
                $params[] = $input['description'];
            }
            if (isset($input['status'])) {
                $updateFields[] = 'status = ?';
                $params[] = $input['status'];
            }
            if (!empty($updateFields)) {
                $updateFields[] = 'updated_at = NOW()';
                $params[] = $jobId;
                $stmt = $pdo->prepare('UPDATE ' . T('jobs') . ' SET ' . implode(', ', $updateFields) . ' WHERE id = ?');
                $pdo->beginTransaction();
                try {
                    $success = $stmt->execute($params);
                    if ($success && isset($input['requirements']) && is_array($input['requirements'])) {
                        $pdo->prepare('DELETE FROM ' . T('job_requirements') . ' WHERE job_id = ?')->execute([$jobId]);
                        $reqStmt = $pdo->prepare('INSERT INTO ' . T('job_requirements') . ' (job_id, requirement) VALUES (?, ?)');
                        foreach ($input['requirements'] as $requirement) {
                            $reqStmt->execute([$jobId, trim($requirement)]);
                        }
                    }
                    $pdo->commit();
                    http_response_code(200);
                    echo json_encode(['success' => true, 'message' => 'Trabajo actualizado correctamente']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Error al actualizar trabajo']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No hay campos para actualizar']);
            }
            break;

        case 'DELETE':
            $jobId = $_GET['id'] ?? null;
            if (!$jobId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de trabajo requerido']);
                break;
            }
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM ' . T('job_requirements') . ' WHERE job_id = ?')->execute([$jobId]);
                $pdo->prepare('DELETE FROM ' . T('job_skills') . ' WHERE job_id = ?')->execute([$jobId]);
                $stmt = $pdo->prepare('DELETE FROM ' . T('jobs') . ' WHERE id = ?');
                $success = $stmt->execute([$jobId]);
                $pdo->commit();
                if ($success) {
                    http_response_code(204);
                    echo json_encode(['success' => true, 'message' => 'Trabajo eliminado correctamente']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Error al eliminar trabajo']);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al eliminar trabajo']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no implementado aún']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => isDevelopment() ? $e->getMessage() : 'Error interno',
        'debug' => isDevelopment() ? $e->getTraceAsString() : null
    ]);
}
