<?php

declare(strict_types=1);



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

// Cargar dependencias especÃƒÆ’Ã‚Â­ficas
require_once __DIR__ . '/../../src/Middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../../src/Middleware/ValidationMiddleware.php';

use Middleware\ValidationMiddleware;
use Utils\Logger;
use Utils\ResponseHelper;

// Inicializar logger
Logger::init();

// ConfiguraciÃƒÆ’Ã‚Â³n de seguridad especÃƒÆ’Ã‚Â­fica para candidatos
$securityConfig = [
    'rate_limit_type' => 'default',
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'max_size' => 2097152, // 2MB
    'csrf_protection' => false // HabilitarÃƒÆ’Ã‚Â­amos en producciÃƒÆ’Ã‚Â³n con frontend preparado
];

// Aplicar middleware de seguridad

// Si tienes un middleware de seguridad real, colÃƒÆ’Ã‚Â³calo aquÃƒÆ’Ã‚Â­. Si no, omite esta llamada.

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();

    switch ($method) {
        case 'GET':
            handleGetRequest($pdo);
            break;

        case 'POST':
            handlePostRequest($pdo);
            break;

        case 'PUT':
            handlePutRequest($pdo);
            break;

        case 'DELETE':
            handleDeleteRequest($pdo);
            break;

        default:
            ResponseHelper::error('MÃƒÆ’Ã‚Â©todo no permitido', 405);
            break;
    }
} catch (PDOException $e) {
    Logger::error('Error de base de datos en candidates.php', [], $e);
    ResponseHelper::error('Error de base de datos', 500);
} catch (Exception $e) {
    Logger::error('Error general en candidates.php', [], $e);
    ResponseHelper::error('Error interno del servidor', 500);
}

/**
 * Maneja solicitudes GET
 */
function handleGetRequest($pdo)
{
    if (isset($_GET['id'])) {
        getSingleCandidate($pdo, $_GET['id']);
    } else {
        getAllCandidates($pdo);
    }
}

/**
 * Obtiene un candidato especÃƒÆ’Ã‚Â­fico
 */
function getSingleCandidate($pdo, $id)
{
    // Validar ID
    if (!filter_var($id, FILTER_VALIDATE_INT) && !preg_match('/^cnd-[a-f0-9]+$/', $id)) {
        ResponseHelper::error('ID de candidato invÃƒÆ’Ã‚Â¡lido', 400);
        return;
    }

    $stmt = $pdo->prepare('
SELECT c.*,
d.name as department_name,
dc.name as department_category_name,
GROUP_CONCAT(DISTINCT cs.skill) as skills
FROM bt_candidates c
LEFT JOIN bt_departments d ON c.department_id = d.id
LEFT JOIN bt_department_categories dc ON c.department_category_id = dc.id
LEFT JOIN bt_candidate_skills cs ON c.id = cs.candidate_id
WHERE c.id = :id
GROUP BY c.id, d.name, dc.name
');

    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();
    $candidate = $stmt->fetch();

    if ($candidate) {
        // Sanitizar datos de salida
        $candidateFormatted = sanitizeCandidateOutput($candidate);

        Logger::info('Candidato consultado', ['candidate_id' => $id]);
        ResponseHelper::success('Candidato encontrado', $candidateFormatted);
    } else {
        Logger::warning('Candidato no encontrado', ['candidate_id' => $id]);
        ResponseHelper::error('Candidato no encontrado', 404);
    }
}

/**
 * Obtiene todos los candidatos con paginaciÃƒÆ’Ã‚Â³n
 */
function getAllCandidates($pdo)
{
    // Validar parÃƒÆ’Ã‚Â¡metros de paginaciÃƒÆ’Ã‚Â³n
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
    $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]) ?: 10;
    $offset = ($page - 1) * $limit;

    // Construir filtros seguros
    $whereConditions = [];
    $params = [':limit' => $limit, ':offset' => $offset];

    // Filtro por estado
    if (!empty($_GET['status']) && in_array($_GET['status'], ['active', 'inactive', 'pending'])) {
        $whereConditions[] = 'c.status = :status';
        $params[':status'] = $_GET['status'];
    }

    // Filtro por departamento
    if (!empty($_GET['department_id']) && filter_var($_GET['department_id'], FILTER_VALIDATE_INT)) {
        $whereConditions[] = 'c.department_id = :department_id';
        $params[':department_id'] = $_GET['department_id'];
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $stmt = $pdo->prepare("
SELECT c.*,
d.name as department_name,
dc.name as department_category_name,
GROUP_CONCAT(DISTINCT cs.skill) as skills
FROM bt_candidates c
LEFT JOIN bt_departments d ON c.department_id = d.id
LEFT JOIN bt_department_categories dc ON c.department_category_id = dc.id
LEFT JOIN bt_candidate_skills cs ON c.id = cs.candidate_id
{$whereClause}
GROUP BY c.id, d.name, dc.name
ORDER BY c.created_at DESC
LIMIT :limit OFFSET :offset
");

    // Bind parameters con tipos especÃƒÆ’Ã‚Â­ficos
    foreach ($params as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->execute();
    $candidates = $stmt->fetchAll();

    // Sanitizar todos los candidatos
    $candidatesFormatted = array_map('sanitizeCandidateOutput', $candidates);

    // Contar total para paginaciÃƒÆ’Ã‚Â³n
    $countParams = array_filter($params, function ($key) {
        return !in_array($key, [':limit', ':offset']);
    }, ARRAY_FILTER_USE_KEY);

    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM bt_candidates c {$whereClause}");
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    Logger::info('Lista de candidatos consultada', [
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);

    ResponseHelper::success('Candidatos obtenidos exitosamente', [
        'candidates' => $candidatesFormatted,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Maneja solicitudes POST
 */
function handlePostRequest($pdo)
{
    $input = ResponseHelper::getJsonInput(1048576); // 1MB mÃƒÆ’Ã‚Â¡ximo
    if ($input === null) {
        return; // El ResponseHelper ya enviÃƒÆ’Ã‚Â³ el error
    }

    // ValidaciÃƒÆ’Ã‚Â³n exhaustiva con middleware mejorado
    $validationRules = [
        'name' => [
            'required' => true,
            'min_length' => 2,
            'max_length' => 100,
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'email' => [
            'required' => true,
            'email' => true,
            'max_length' => 255,
            'no_xss' => true
        ],
        'phone' => [
            'required' => false,
            'phone' => true,
            'max_length' => 20
        ],
        'password' => [
            'required' => false,
            'password_strength' => 'medium',
            'no_xss' => true
        ],
        'location' => [
            'required' => false,
            'max_length' => 255,
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'status' => [
            'required' => false,
            'max_length' => 20,
            'in_array' => ['active', 'inactive', 'pending', 'reviewed']
        ],
        'department_id' => [
            'required' => false,
            'integer' => true
        ],
        'department_category_id' => [
            'required' => false,
            'integer' => true
        ],
        'experience_years' => [
            'required' => false,
            'integer' => true
        ],
        'salary_expectation' => [
            'required' => false,
            'numeric' => true
        ]
    ];

    // Validar con middleware de validaciÃƒÆ’Ã‚Â³n mejorado
    if (!ValidationMiddleware::handle($input, $validationRules)) {
        return; // El middleware ya enviÃƒÆ’Ã‚Â³ la respuesta de error
    }

    // Validar archivos si estÃƒÆ’Ã‚Â¡n presentes
    if (!empty($_FILES)) {
        $fileRules = [
            'cv_file' => [
                'required' => false,
                'max_size' => 5 * 1024 * 1024, // 5MB
                'allowed_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ],
                'allowed_extensions' => ['pdf', 'doc', 'docx']
            ],
            'profile_photo' => [
                'required' => false,
                'max_size' => 2 * 1024 * 1024, // 2MB
                'allowed_types' => [
                    'image/jpeg',
                    'image/png',
                    'image/jpg'
                ],
                'allowed_extensions' => ['jpg', 'jpeg', 'png']
            ]
        ];

        $fileErrors = ValidationMiddleware::validateFiles($_FILES, $fileRules);
        if (!empty($fileErrors)) {
            Logger::warning('ValidaciÃƒÆ’Ã‚Â³n de archivos fallida al crear candidato', $fileErrors);
            ResponseHelper::error('Error de validaciÃƒÆ’Ã‚Â³n de archivos', 422, $fileErrors);
            return;
        }
    }

    // Los datos ya estÃƒÆ’Ã‚Â¡n validados y seguros para usar
    $validData = $input;

    // Verificar email ÃƒÆ’Ã‚Âºnico
    $emailCheck = $pdo->prepare('SELECT id FROM bt_candidates WHERE email = :email');
    $emailCheck->bindParam(':email', $validData['email']);
    $emailCheck->execute();

    if ($emailCheck->fetch()) {
        Logger::warning('Intento de crear candidato con email existente', ['email' => $validData['email']]);
        ResponseHelper::error('El email ya estÃƒÆ’Ã‚Â¡ registrado', 409);
        return;
    }

    // Generar ID ÃƒÆ’Ã‚Âºnico seguro
    $candidateId = 'cnd-' . bin2hex(random_bytes(16));

    // Procesar nombre
    $nameParts = explode(' ', trim($validData['name']), 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';

    // Hash seguro de contraseÃƒÆ’Ã‚Â±a
    $passwordHash = password_hash($validData['password'] ?? bin2hex(random_bytes(8)), PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        // Insertar candidato
        $stmt = $pdo->prepare('
INSERT INTO bt_candidates (
id, name, first_name, last_name, email, password_hash,
phone, location, status, registration_source, created_at
) VALUES (:id, :name, :first_name, :last_name, :email, :password_hash,
:phone, :location, :status, :registration_source, NOW())
');

        $stmt->execute([
            ':id' => $candidateId,
            ':name' => $validData['name'],
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':email' => $validData['email'],
            ':password_hash' => $passwordHash,
            ':phone' => $validData['phone'] ?? null,
            ':location' => $validData['location'] ?? null,
            ':status' => $validData['status'] ?? 'active',
            ':registration_source' => 'api'
        ]);

        // Insertar skills si existen
        if (!empty($input['skills']) && is_array($input['skills'])) {
            $skillStmt = $pdo->prepare('INSERT INTO bt_candidate_skills (candidate_id, skill) VALUES (:candidate_id, :skill)');

            foreach ($input['skills'] as $skill) {
                $cleanSkill = htmlspecialchars(trim($skill), ENT_QUOTES, 'UTF-8');
                if (!empty($cleanSkill) && strlen($cleanSkill) <= 100) {
                    $skillStmt->execute([
                        ':candidate_id' => $candidateId,
                        ':skill' => $cleanSkill
                    ]);
                }
            }
        }

        $pdo->commit();

        Logger::info('Candidato creado exitosamente', ['candidate_id' => $candidateId, 'email' => $validData['email']]);
        ResponseHelper::success('Candidato creado exitosamente', ['id' => $candidateId], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        Logger::error('Error al crear candidato', ['email' => $validData['email']], $e);
        ResponseHelper::error('Error al crear candidato', 500);
    }
}

/**
 * Maneja solicitudes PUT
 */
function handlePutRequest($pdo)
{
    if (!isset($_GET['id'])) {
        ResponseHelper::error('ID requerido para actualizaciÃƒÆ’Ã‚Â³n', 400);
        return;
    }

    $id = $_GET['id'];
    if (!filter_var($id, FILTER_VALIDATE_INT) && !preg_match('/^cnd-[a-f0-9]+$/', $id)) {
        ResponseHelper::error('ID de candidato invÃƒÆ’Ã‚Â¡lido', 400);
        return;
    }

    $input = ResponseHelper::getJsonInput();
    if ($input === null) {
        return;
    }

    // ValidaciÃƒÆ’Ã‚Â³n para actualizaciÃƒÆ’Ã‚Â³n con middleware mejorado (campos opcionales)
    $updateValidationRules = [
        'name' => [
            'required' => false,
            'min_length' => 2,
            'max_length' => 100,
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'email' => [
            'required' => false,
            'email' => true,
            'max_length' => 255,
            'no_xss' => true
        ],
        'phone' => [
            'required' => false,
            'phone' => true,
            'max_length' => 20
        ],
        'location' => [
            'required' => false,
            'max_length' => 255,
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'status' => [
            'required' => false,
            'max_length' => 20,
            'in_array' => ['active', 'inactive', 'pending', 'reviewed']
        ],
        'department_id' => [
            'required' => false,
            'integer' => true
        ],
        'department_category_id' => [
            'required' => false,
            'integer' => true
        ],
        'experience_years' => [
            'required' => false,
            'integer' => true
        ],
        'salary_expectation' => [
            'required' => false,
            'numeric' => true
        ],
        'skills' => [
            'required' => false
            // Skills will be validated separately as array
        ]
    ];

    // Validar con middleware de validaciÃƒÆ’Ã‚Â³n mejorado
    if (!ValidationMiddleware::handle($input, $updateValidationRules)) {
        return; // El middleware ya enviÃƒÆ’Ã‚Â³ la respuesta de error
    }

    // ValidaciÃƒÆ’Ã‚Â³n adicional para skills si estÃƒÆ’Ã‚Â¡ presente
    if (isset($input['skills'])) {
        if (!is_array($input['skills'])) {
            ResponseHelper::error('Skills debe ser un array', 422);
            return;
        }

        foreach ($input['skills'] as $index => $skill) {
            $skillValidation = ValidationMiddleware::validateType($skill, 'alpha', [
                'allow_spaces' => true
            ]);

            if (!$skillValidation['isValid']) {
                ResponseHelper::error("Skill en posiciÃƒÆ’Ã‚Â³n $index es invÃƒÆ’Ã‚Â¡lida: " . $skillValidation['error'], 422);
                return;
            }

            if (strlen($skill) > 100) {
                ResponseHelper::error("Skill en posiciÃƒÆ’Ã‚Â³n $index excede 100 caracteres", 422);
                return;
            }
        }
    }

    $validData = $input;

    try {
        $pdo->beginTransaction();

        // Verificar que el candidato existe
        $checkStmt = $pdo->prepare('SELECT id FROM bt_candidates WHERE id = :id');
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();

        if (!$checkStmt->fetch()) {
            $pdo->rollBack();
            ResponseHelper::error('Candidato no encontrado', 404);
            return;
        }

        // Construir query de actualizaciÃƒÆ’Ã‚Â³n dinÃƒÆ’Ã‚Â¡micamente
        $updateFields = [];
        $params = [':id' => $id];

        foreach ($validData as $field => $value) {
            $updateFields[] = "$field = :$field";
            $params[":$field"] = $value;
        }

        if (!empty($updateFields)) {
            $updateQuery = 'UPDATE bt_candidates SET ' . implode(', ', $updateFields) . ' WHERE id = :id';
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute($params);
        }

        // Actualizar skills si se proporcionaron
        if (isset($input['skills']) && is_array($input['skills'])) {
            // Borrar skills existentes
            $deleteSkillsStmt = $pdo->prepare('DELETE FROM bt_candidate_skills WHERE candidate_id = :id');
            $deleteSkillsStmt->bindParam(':id', $id);
            $deleteSkillsStmt->execute();

            // Insertar nuevos skills
            $skillStmt = $pdo->prepare('INSERT INTO bt_candidate_skills (candidate_id, skill) VALUES (:candidate_id, :skill)');

            foreach ($input['skills'] as $skill) {
                $cleanSkill = htmlspecialchars(trim($skill), ENT_QUOTES, 'UTF-8');
                if (!empty($cleanSkill) && strlen($cleanSkill) <= 100) {
                    $skillStmt->execute([
                        ':candidate_id' => $id,
                        ':skill' => $cleanSkill
                    ]);
                }
            }
        }

        $pdo->commit();

        Logger::info('Candidato actualizado exitosamente', ['candidate_id' => $id]);
        ResponseHelper::success('Candidato actualizado exitosamente');
    } catch (Exception $e) {
        $pdo->rollBack();
        Logger::error('Error al actualizar candidato', ['candidate_id' => $id], $e);
        ResponseHelper::error('Error al actualizar candidato', 500);
    }
}

/**
 * Maneja solicitudes DELETE
 */
function handleDeleteRequest($pdo)
{
    if (!isset($_GET['id'])) {
        ResponseHelper::error('ID requerido para eliminaciÃƒÆ’Ã‚Â³n', 400);
        return;
    }

    $id = $_GET['id'];
    if (!filter_var($id, FILTER_VALIDATE_INT) && !preg_match('/^cnd-[a-f0-9]+$/', $id)) {
        ResponseHelper::error('ID de candidato invÃƒÆ’Ã‚Â¡lido', 400);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Verificar que el candidato existe
        $checkStmt = $pdo->prepare('SELECT id FROM bt_candidates WHERE id = :id');
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();

        if (!$checkStmt->fetch()) {
            $pdo->rollBack();
            ResponseHelper::error('Candidato no encontrado', 404);
            return;
        }

        // Borrar skills relacionados primero (foreign key)
        $deleteSkillsStmt = $pdo->prepare('DELETE FROM bt_candidate_skills WHERE candidate_id = :id');
        $deleteSkillsStmt->bindParam(':id', $id);
        $deleteSkillsStmt->execute();

        // Borrar candidato
        $deleteCandidateStmt = $pdo->prepare('DELETE FROM bt_candidates WHERE id = :id');
        $deleteCandidateStmt->bindParam(':id', $id);
        $success = $deleteCandidateStmt->execute();

        if ($success && $deleteCandidateStmt->rowCount() > 0) {
            $pdo->commit();
            Logger::info('Candidato eliminado exitosamente', ['candidate_id' => $id]);
            ResponseHelper::success('Candidato eliminado exitosamente');
        } else {
            $pdo->rollBack();
            ResponseHelper::error('No se pudo eliminar el candidato', 500);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        Logger::error('Error al eliminar candidato', ['candidate_id' => $id], $e);
        ResponseHelper::error('Error al eliminar candidato', 500);
    }
}

/**
 * Sanitiza los datos de salida de un candidato
 */
function sanitizeCandidateOutput($candidate)
{
    return [
        'id' => htmlspecialchars($candidate['id'], ENT_QUOTES, 'UTF-8'),
        'name' => htmlspecialchars($candidate['name'], ENT_QUOTES, 'UTF-8'),
        'email' => htmlspecialchars($candidate['email'], ENT_QUOTES, 'UTF-8'),
        'phone' => htmlspecialchars($candidate['phone'] ?? '', ENT_QUOTES, 'UTF-8'),
        'title' => htmlspecialchars($candidate['department_category_name'] ?? 'Sin categorÃƒÆ’Ã‚Â­a', ENT_QUOTES, 'UTF-8'),
        'department' => htmlspecialchars($candidate['department_name'] ?? 'Sin departamento', ENT_QUOTES, 'UTF-8'),
        'skills' => $candidate['skills'] ?
            array_map(function ($skill) {
                return htmlspecialchars(trim($skill), ENT_QUOTES, 'UTF-8');
            }, explode(',', $candidate['skills'])) : [],
        'status' => htmlspecialchars($candidate['status'], ENT_QUOTES, 'UTF-8'),
        'location' => htmlspecialchars($candidate['location'] ?? '', ENT_QUOTES, 'UTF-8'),
        'created' => $candidate['created_at']
    ];
}


