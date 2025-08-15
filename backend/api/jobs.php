<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 1);             // ajusta salto de nivel según carpeta
$BOOT = $ROOT . '/config/bootstrap.php'; // si estás en /backend/public, sube 1 nivel; si estás en /backend/api, también 1
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

// API para gestión de ofertas de trabajo (jobs)

function connectDatabase()
{
    $servername = config('DB_HOST');
    $username = config('DB_USER');
    $password = config('DB_PASS');
    $dbname   = config('DB_NAME');
    $port     = (int)config('DB_PORT');

    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }
    return $conn;
}

function transformJobData($row)
{
    return [
        'id' => $row['id'],
        'title' => $row['title'],
        'company' => $row['company_name'] ?? 'No especificada',
        'location' => $row['location'],
        'type' => $row['job_type'] ?? $row['type'] ?? 'Full-time',
        'category' => $row['category'] ?? 'No especificada',
        'level' => $row['level'] ?? 'Mid-level',
        'description' => $row['description'],
        'requirements' => $row['requirements'] ? explode(',', $row['requirements']) : [],
        'skills' => $row['skills'] ? array_map('trim', explode(',', $row['skills'])) : [],
        'salary' => formatSalary($row),
        'posted_date' => $row['posted_at'] ?? $row['created_at'] ?? date('Y-m-d'),
        'expires_at' => $row['expires_at'] ?? null,
        'status' => $row['status'] ?? 'open',
        'is_featured' => (bool)($row['is_featured'] ?? false),
        'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s')
    ];
}

function formatSalary($row)
{
    if (!empty($row['salary_min']) && !empty($row['salary_max'])) {
        $currency = $row['salary_currency'] ?? 'EUR';
        $period = $row['salary_period'] ?? 'year';

        // Convertir a float para evitar errores
        $minSalary = floatval($row['salary_min']);
        $maxSalary = floatval($row['salary_max']);

        return number_format($minSalary) . ' - ' . number_format($maxSalary) . ' ' . $currency . '/' . $period;
    }
    return 'A negociar';
}

function getJobs($limit = 10, $offset = 0, $filters = [])
{
    $conn = connectDatabase();

    $whereConditions = ['bj.status = "open"']; // Solo trabajos abiertos por defecto
    $params = [];
    $types = '';

    // Filtros opcionales
    if (!empty($filters['category'])) {
        $whereConditions[] = 'bd.name = ?';
        $params[] = $filters['category'];
        $types .= 's';
    }

    if (!empty($filters['location'])) {
        $whereConditions[] = 'bj.location LIKE ?';
        $params[] = '%' . $filters['location'] . '%';
        $types .= 's';
    }

    if (!empty($filters['type'])) {
        $whereConditions[] = 'bj.job_type = ?';
        $params[] = $filters['type'];
        $types .= 's';
    }

    if (!empty($filters['experience_level'])) {
        $whereConditions[] = 'bj.experience_level = ?';
        $params[] = $filters['experience_level'];
        $types .= 's';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    // Query principal con JOINs (corregido con estructura real de bt_jobs)
    $sql = "
        SELECT 
            bj.id,
            bj.title,
            bj.company_name,
            bj.location,
            bj.type as job_type,
            bj.level,
            bj.category,
            bj.description,
            bj.salary_min,
            bj.salary_max,
            bj.salary_currency,
            bj.salary_period,
            bj.status,
            bj.created_at,
            bj.posted_at,
            bj.expires_at,
            bj.is_featured,
            GROUP_CONCAT(DISTINCT bjs.skill ORDER BY bjs.skill) as skills,
            GROUP_CONCAT(DISTINCT bjr.requirement ORDER BY bjr.requirement) as requirements
        FROM bt_jobs bj
        LEFT JOIN bt_job_skills bjs ON bj.id = bjs.job_id
        LEFT JOIN bt_job_requirements bjr ON bj.id = bjr.job_id
        {$whereClause}
        GROUP BY bj.id
        ORDER BY bj.is_featured DESC, bj.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    // Agregar limit y offset a los parámetros
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobs[] = transformJobData($row);
    }

    // Contar total para paginación (sin bt_skills)
    $countSql = "
        SELECT COUNT(DISTINCT bj.id) as total
        FROM bt_jobs bj
        LEFT JOIN bt_job_skills bjs ON bj.id = bjs.job_id
        {$whereClause}
    ";

    $countStmt = $conn->prepare($countSql);
    if (!empty($params) && count($params) > 2) { // Excluimos limit y offset del count
        $countParams = array_slice($params, 0, -2);
        $countTypes = substr($types, 0, -2);
        $countStmt->bind_param($countTypes, ...$countParams);
    }

    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];

    $stmt->close();
    $countStmt->close();
    $conn->close();

    return [
        'success' => true,
        'data' => $jobs,
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'pages' => ceil($total / $limit)
        ]
    ];
}

function createJob($data)
{
    $conn = connectDatabase();

    // Obtener department_id si se proporciona el nombre
    $department_id = null;
    if (!empty($data['department'])) {
        $deptStmt = $conn->prepare('SELECT id FROM bt_departments WHERE name = ?');
        $deptStmt->bind_param('s', $data['department']);
        $deptStmt->execute();
        $deptResult = $deptStmt->get_result();
        if ($deptRow = $deptResult->fetch_assoc()) {
            $department_id = $deptRow['id'];
        }
        $deptStmt->close();
    }

    $sql = 'INSERT INTO bt_jobs (
        title, company, location, job_type, department_id, salary_range,
        description, requirements, experience_level, application_deadline, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = $conn->prepare($sql);

    $requirements = is_array($data['requirements']) ? implode(',', $data['requirements']) : $data['requirements'];
    $status = $data['status'] ?? 'active';

    $stmt->bind_param(
        'ssssissssss',
        $data['title'],
        $data['company'],
        $data['location'],
        $data['type'],
        $department_id,
        $data['salary'],
        $data['description'],
        $requirements,
        $data['experience_level'],
        $data['deadline'],
        $status
    );

    if ($stmt->execute()) {
        $jobId = $conn->insert_id;

        // Insertar skills si se proporcionan (directo en bt_job_skills)
        if (!empty($data['skills']) && is_array($data['skills'])) {
            $skillStmt = $conn->prepare('
                INSERT INTO bt_job_skills (job_id, skill) VALUES (?, ?)
            ');

            foreach ($data['skills'] as $skill) {
                $skillStmt->bind_param('ss', $jobId, trim($skill));
                $skillStmt->execute();
            }
            $skillStmt->close();
        }

        $stmt->close();
        $conn->close();

        return ['success' => true, 'id' => $jobId, 'message' => 'Job created successfully'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        throw new Exception('Error creating job: ' . $error);
    }
}

function updateJob($id, $data)
{
    $conn = connectDatabase();

    // Obtener department_id si se proporciona el nombre
    $department_id = null;
    if (!empty($data['department'])) {
        $deptStmt = $conn->prepare('SELECT id FROM bt_departments WHERE name = ?');
        $deptStmt->bind_param('s', $data['department']);
        $deptStmt->execute();
        $deptResult = $deptStmt->get_result();
        if ($deptRow = $deptResult->fetch_assoc()) {
            $department_id = $deptRow['id'];
        }
        $deptStmt->close();
    }

    $sql = 'UPDATE bt_jobs SET 
        title = ?, company = ?, location = ?, job_type = ?, department_id = ?,
        salary_range = ?, description = ?, requirements = ?, experience_level = ?,
        application_deadline = ?, status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?';

    $stmt = $conn->prepare($sql);

    $requirements = is_array($data['requirements']) ? implode(',', $data['requirements']) : $data['requirements'];

    $stmt->bind_param(
        'ssssissssssi',
        $data['title'],
        $data['company'],
        $data['location'],
        $data['type'],
        $department_id,
        $data['salary'],
        $data['description'],
        $requirements,
        $data['experience_level'],
        $data['deadline'],
        $data['status'],
        $id
    );

    if ($stmt->execute()) {
        // Actualizar skills
        if (isset($data['skills'])) {
            // Eliminar skills existentes
            $deleteSkillsStmt = $conn->prepare('DELETE FROM bt_job_skills WHERE job_id = ?');
            $deleteSkillsStmt->bind_param('i', $id);
            $deleteSkillsStmt->execute();
            $deleteSkillsStmt->close();

            // Insertar nuevas skills (directo en bt_job_skills)
            if (!empty($data['skills']) && is_array($data['skills'])) {
                $skillStmt = $conn->prepare('
                    INSERT INTO bt_job_skills (job_id, skill) VALUES (?, ?)
                ');

                foreach ($data['skills'] as $skill) {
                    $skillStmt->bind_param('ss', $id, trim($skill));
                    $skillStmt->execute();
                }
                $skillStmt->close();
            }
        }

        $stmt->close();
        $conn->close();

        return ['success' => true, 'message' => 'Job updated successfully'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        throw new Exception('Error updating job: ' . $error);
    }
}

function deleteJob($id)
{
    $conn = connectDatabase();

    // Eliminar skills asociados primero
    $deleteSkillsStmt = $conn->prepare('DELETE FROM bt_job_skills WHERE job_id = ?');
    $deleteSkillsStmt->bind_param('i', $id);
    $deleteSkillsStmt->execute();
    $deleteSkillsStmt->close();

    // Eliminar job
    $stmt = $conn->prepare('DELETE FROM bt_jobs WHERE id = ?');
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        $conn->close();

        if ($affected > 0) {
            return ['success' => true, 'message' => 'Job deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Job not found'];
        }
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        throw new Exception('Error deleting job: ' . $error);
    }
}

// Manejo de requests
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['PATH_INFO'] ?? '';

    switch ($method) {
        case 'GET':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            $filters = [];
            if (isset($_GET['category'])) {
                $filters['category'] = $_GET['category'];
            }
            if (isset($_GET['location'])) {
                $filters['location'] = $_GET['location'];
            }
            if (isset($_GET['type'])) {
                $filters['type'] = $_GET['type'];
            }
            if (isset($_GET['experience_level'])) {
                $filters['experience_level'] = $_GET['experience_level'];
            }

            $result = getJobs($limit, $offset, $filters);
            echo json_encode($result);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON data');
            }

            $result = createJob($input);
            echo json_encode($result);
            break;

        case 'PUT':
            if (preg_match('/\/(\d+)$/', $path, $matches)) {
                $id = $matches[1];
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) {
                    throw new Exception('Invalid JSON data');
                }

                $result = updateJob($id, $input);
                echo json_encode($result);
            } else {
                throw new Exception('Invalid job ID');
            }
            break;

        case 'DELETE':
            if (preg_match('/\/(\d+)$/', $path, $matches)) {
                $id = $matches[1];
                $result = deleteJob($id);
                echo json_encode($result);
            } else {
                throw new Exception('Invalid job ID');
            }
            break;

        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
