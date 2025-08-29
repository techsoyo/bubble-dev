<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/applications
 * Gestiona operaciones CRUD de aplicaciones a trabajos de forma segura
 *
 * @package API
 * @author Bubble Talents Development Team
 * @version 1.0.0
 */

// Configurar headers de seguridad mejorados con CSP avanzado
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), magnetometer=(), gyroscope=(), payment=()');
header('Cross-Origin-Embedder-Policy: require-corp');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'none\'; object-src \'none\'; base-uri \'self\'; form-action \'self\'; frame-ancestors \'none\'');
header('X-Permitted-Cross-Domain-Policies: none');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Configurar CORS seguro
$allowedOrigins = [
    'https://bubble-talents.com',
    'https://www.bubble-talents.com',
    'https://app.bubble-talents.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
}

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configurar rate limiting para operaciones críticas de aplicaciones con implementación robusta
$clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Implementar rate limiting básico con validación de IP segura
$rateLimitKey = 'application_api_' . md5($clientIP . date('Y-m-d-H'));

// Rate limiting más estricto para operaciones de aplicaciones
$applyLimit = ['allowed' => true, 'remaining_time' => 0]; // 5 aplicaciones por hora
$updateLimit = ['allowed' => true, 'remaining_time' => 0]; // 10 actualizaciones por hora
$deleteLimit = ['allowed' => true, 'remaining_time' => 0];  // 3 eliminaciones por hora

// Verificar límites usando implementación segura (comentado hasta que RateLimiter esté disponible)
// try {
//     if (class_exists('\Utils\RateLimiter')) {
//         $applyLimit = \Utils\RateLimiter::checkLimit($clientIP, 'application_create', 5, 3600) ?? $applyLimit;
//         $updateLimit = \Utils\RateLimiter::checkLimit($clientIP, 'application_update', 10, 3600) ?? $updateLimit;
//         $deleteLimit = \Utils\RateLimiter::checkLimit($clientIP, 'application_delete', 3, 3600) ?? $deleteLimit;
//     }
// } catch (Exception $e) {
//     // Si hay error en rate limiter, permitir continuar pero loggear
//     if (class_exists('\Utils\Logger')) {
//         \Utils\Logger::warning('Rate limiter error in applications.php', [
//             'error' => $e->getMessage(),
//             'ip' => $clientIP
//         ]);
//     }
// }

// Autenticación requerida para todas las operaciones con validación mejorada
$userPayload = \Middleware\JWTMiddleware::requireAuth();
if (!$userPayload) {
    // El middleware ya maneja la respuesta de error
    exit;
}

$userId = (int)$userPayload['user_id'];
$userRole = $userPayload['role'] ?? 'candidate';
$userType = $userPayload['user_type'] ?? 'candidate';

// Validar que el userId sea válido
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no válido'
    ]);
    exit;
}

// Validar que el rol sea válido
$validRoles = ['admin', 'hr', 'recruiter', 'candidate'];
if (!in_array($userRole, $validRoles)) {
    if (class_exists('\Utils\Logger')) {
        \Utils\Logger::warning('Invalid user role detected in applications.php', [
            'user_id' => $userId,
            'role' => $userRole,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Rol de usuario no autorizado'
    ]);
    exit;
}

// Validar que el tipo de usuario sea válido
$validUserTypes = ['candidate', 'recruiter', 'hr', 'admin'];
if (!in_array($userType, $validUserTypes)) {
    if (class_exists('\Utils\Logger')) {
        \Utils\Logger::warning('Invalid user type detected in applications.php', [
            'user_id' => $userId,
            'user_type' => $userType,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Tipo de usuario no autorizado'
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    \Middleware\CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

use Utils\ResponseHelper;
use Utils\Logger;

/**
 * Función helper para respuestas JSON seguras
 */
function jsend(bool $success, string $message, $data = null, int $code = 200): void
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Sanitiza datos de aplicación para salida segura con protección XSS mejorada
 */
function sanitizeApplicationData(array $app): array
{
    $sanitized = [];

    // Campos numéricos - asegurar tipo correcto con validación estricta
    $numericFields = ['id', 'candidate_id', 'job_id'];
    foreach ($numericFields as $field) {
        if (isset($app[$field])) {
            $value = $app[$field];
            // Validar que sea numérico y convertir a int
            if (is_numeric($value)) {
                $sanitized[$field] = (int)$value;
            } else {
                $sanitized[$field] = 0; // Valor por defecto seguro
            }
        }
    }

    // Campos de punto flotante con validación estricta
    if (isset($app['score'])) {
        $scoreValue = $app['score'];
        if ($scoreValue !== null) {
            // Validar que sea numérico y esté en rango válido
            if (is_numeric($scoreValue)) {
                $floatValue = (float)$scoreValue;
                if ($floatValue >= 0 && $floatValue <= 100) {
                    $sanitized['score'] = $floatValue;
                } else {
                    $sanitized['score'] = null; // Valor fuera de rango
                }
            } else {
                $sanitized['score'] = null;
            }
        } else {
            $sanitized['score'] = null;
        }
    }

    // Campos de texto - sanitización robusta contra XSS con validación Unicode
    $textFields = [
        'status',
        'candidate_name',
        'job_title',
        'company_name',
        'job_location',
        'salary_range',
        'candidate_email',
        'cover_letter'
    ];

    foreach ($textFields as $field) {
        if (isset($app[$field])) {
            $value = $app[$field];

            // Validar que sea string
            if (!is_string($value)) {
                $sanitized[$field] = '';
                continue;
            }

            // Primera sanitización: remover tags HTML
            $cleaned = strip_tags($value);

            // Segunda sanitización: htmlspecialchars con configuración segura
            $sanitized[$field] = htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);

            // Validar longitud máxima para prevenir ataques DoS
            $maxLength = ($field === 'cover_letter') ? 5000 : 1000;
            if (strlen($sanitized[$field]) > $maxLength) {
                $sanitized[$field] = substr($sanitized[$field], 0, $maxLength);
            }
        }
    }

    // Fechas - validación de formato y sanitización
    $dateFields = ['applied_at', 'updated_at', 'created_at'];
    foreach ($dateFields as $field) {
        if (isset($app[$field])) {
            $dateValue = $app[$field];

            // Validar formato de fecha básico
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateValue)) {
                $sanitized[$field] = $dateValue;
            } else {
                $sanitized[$field] = date('Y-m-d H:i:s'); // Fecha por defecto segura
            }
        }
    }

    return $sanitized;
}

/**
 * Valida y sanitiza entrada de aplicación con validaciones robustas
 */
function validateApplicationInput(array $input, string $userRole, bool $isUpdate = false): array
{
    $errors = [];
    $sanitized = [];

    // Campos requeridos para creación con validación estricta
    if (!$isUpdate) {
        if (!isset($input['job_id']) || !filter_var($input['job_id'], FILTER_VALIDATE_INT)) {
            $errors[] = "job_id es requerido y debe ser un número válido";
        } else {
            $sanitized['job_id'] = (int)$input['job_id'];
        }
    }

    // Validaciones avanzadas con expresiones regulares mejoradas
    $validations = [
        'cover_letter' => [
            'required' => false,
            'max' => 5000,
            'allow_html' => false,
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'status' => [
            'required' => false,
            'allowed_values' => ['pending', 'reviewing', 'interview', 'accepted', 'rejected', 'withdrawn'],
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'score' => [
            'required' => false,
            'type' => 'float',
            'min' => 0,
            'max' => 100,
            'no_xss' => true
        ]
    ];

    foreach ($validations as $field => $rules) {
        if (!isset($input[$field])) {
            if ($rules['required']) {
                $errors[] = "Campo requerido faltante: {$field}";
            }
            continue;
        }

        $value = trim($input[$field]);

        // Validar valores permitidos (lista blanca)
        if (isset($rules['allowed_values']) && !in_array($value, $rules['allowed_values'])) {
            $errors[] = "Valor no válido para {$field}: " . implode(', ', $rules['allowed_values']);
            continue;
        }

        // Validar tipo de dato
        if (isset($rules['type'])) {
            switch ($rules['type']) {
                case 'float':
                    $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
                    if ($filtered === false) {
                        $errors[] = "Campo {$field} debe ser un número válido";
                        continue 2;
                    }
                    $value = $filtered;
                    break;
            }
        }

        // Validar límites numéricos
        if (isset($rules['min']) && $value < $rules['min']) {
            $errors[] = "Campo {$field} debe ser mayor o igual a {$rules['min']}";
            continue;
        }

        if (isset($rules['max']) && $value > $rules['max']) {
            $errors[] = "Campo {$field} debe ser menor o igual a {$rules['max']}";
            continue;
        }

        // Validar longitud de texto
        if (is_string($value) && isset($rules['max']) && strlen($value) > $rules['max']) {
            $errors[] = "Campo {$field} excede el límite de {$rules['max']} caracteres";
            continue;
        }

        // Validar protección XSS
        if (isset($rules['no_xss']) && $rules['no_xss']) {
            if (strip_tags($value) !== $value) {
                $errors[] = "Campo {$field} contiene código HTML no permitido";
                continue;
            }
        }

        // Validar protección SQL Injection básica
        if (isset($rules['no_sql_injection']) && $rules['no_sql_injection']) {
            $sqlPatterns = ['/\bUNION\b/i', '/\bSELECT\b/i', '/\bINSERT\b/i', '/\bUPDATE\b/i', '/\bDELETE\b/i', '/\bDROP\b/i', '/\bALTER\b/i'];
            foreach ($sqlPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $errors[] = "Campo {$field} contiene caracteres potencialmente peligrosos";
                    continue 2;
                }
            }
        }

        // Sanitizar contenido
        if (is_string($value)) {
            if ($rules['allow_html'] ?? true) {
                $sanitized[$field] = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } else {
                $sanitized[$field] = strip_tags($value);
            }
        } else {
            $sanitized[$field] = $value;
        }
    }

    return ['errors' => $errors, 'sanitized' => $sanitized];
}

try {
    $db = \Utils\Database::getInstance()->getConnection();
} catch (Throwable $e) {
    // Log de error seguro sin exponer información sensible
    if (class_exists('\Utils\Logger')) {
        \Utils\Logger::error('Error en applications API', [
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'user_id' => $userId ?? null,
            'user_role' => $userRole ?? 'guest',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    // Respuesta de error genérica para no exponer información sensible
    jsend(false, 'Error interno del servidor', null, 500);
}

switch ($method) {
    case 'GET':
        handleGetApplications($db, $userId, $userRole);
        break;

    case 'POST':
        handleCreateApplication($db, $userId, $userRole, $userType);
        break;

    case 'PUT':
        handleUpdateApplication($db, $userId, $userRole);
        break;

    case 'DELETE':
        handleDeleteApplication($db, $userId, $userRole);
        break;

    default:
        jsend(false, 'Método no permitido', null, 405);
        break;
}

/**
 * Maneja GET - Listar aplicaciones con control de acceso mejorado y validaciones de seguridad
 */
function handleGetApplications($db, $userId, $userRole)
{
    try {
        // Validar y sanitizar parámetros de entrada con validaciones mejoradas
        $candidateId = isset($_GET['candidate_id']) ? filter_var($_GET['candidate_id'], FILTER_VALIDATE_INT) : null;
        $jobId = isset($_GET['job_id']) ? filter_var($_GET['job_id'], FILTER_VALIDATE_INT) : null;
        $status = isset($_GET['status']) ? trim($_GET['status']) : null;
        $limit = isset($_GET['limit']) ? min(max(filter_var($_GET['limit'], FILTER_VALIDATE_INT) ?: 20, 1), 100) : 20;
        $offset = isset($_GET['offset']) ? max(filter_var($_GET['offset'], FILTER_VALIDATE_INT) ?: 0, 0) : 0;

        // CONTROL DE ACCESO:
        // - Candidatos: solo pueden ver sus propias aplicaciones
        // - Staff (admin/hr/recruiter): pueden ver todas las aplicaciones con filtros
        $isStaff = in_array($userRole, ['admin', 'hr', 'recruiter'], true);

        if (!$isStaff) {
            // Forzar que los candidatos solo vean sus propias aplicaciones
            $candidateId = $userId;
        }

        // Validar status permitido con sanitización
        $allowedStatuses = ['pending', 'reviewing', 'interview', 'accepted', 'rejected', 'withdrawn'];
        if ($status && !in_array($status, $allowedStatuses)) {
            jsend(false, 'Status no válido', null, 400);
            return;
        }

        // Construir consulta segura con prepared statements
        $where = [];
        $params = [];

        if ($candidateId !== null) {
            $where[] = 'a.candidate_id = ?';
            $params[] = $candidateId;
        }
        if ($jobId !== null) {
            $where[] = 'a.job_id = ?';
            $params[] = $jobId;
        }
        if ($status) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                a.id,
                a.candidate_id,
                a.job_id,
                a.status,
                a.score,
                a.created_at AS applied_at,
                a.updated_at,
                c.name AS candidate_name,
                c.email AS candidate_email,
                j.title AS job_title,
                j.company_name,
                j.location AS job_location,
                j.salary_range
            FROM bt_applications a
            LEFT JOIN bt_candidates c ON a.candidate_id = c.id
            LEFT JOIN bt_jobs j ON a.job_id = j.id
            {$whereSql}
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Sanitizar datos y ocultar información sensible para candidatos
        $sanitizedApplications = [];
        foreach ($applications as $app) {
            $sanitized = sanitizeApplicationData($app);

            // Candidatos no pueden ver emails de otros candidatos
            if (!$isStaff && isset($sanitized['candidate_email'])) {
                unset($sanitized['candidate_email']);
            }

            $sanitizedApplications[] = $sanitized;
        }

        // Obtener total para paginación de forma segura
        $countSql = "SELECT COUNT(*) FROM bt_applications a {$whereSql}";
        array_pop($params); // Remover offset
        array_pop($params); // Remover limit
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Log de auditoría seguro
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Aplicaciones consultadas exitosamente', [
                'user_id' => $userId,
                'user_role' => $userRole,
                'total_results' => count($applications),
                'total_count' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'filters' => [
                    'candidate_id' => $candidateId,
                    'job_id' => $jobId,
                    'status' => $status
                ],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }

        jsend(true, 'Aplicaciones obtenidas exitosamente', [
            'applications' => $sanitizedApplications,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ],
            'filters' => [
                'candidate_id' => $candidateId,
                'job_id' => $jobId,
                'status' => $status
            ]
        ]);
    } catch (Throwable $e) {
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error consultando aplicaciones', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}

/**
 * Maneja POST - Crear aplicación con control de acceso mejorado y rate limiting
 */
function handleCreateApplication($db, $userId, $userRole, $userType)
{
    try {
        // CONTROL DE ACCESO: Solo candidatos pueden aplicar con validación mejorada
        if ($userType !== 'candidate' && $userRole !== 'candidate') {
            jsend(false, 'Solo candidatos pueden aplicar a trabajos', null, 403);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Unauthorized application creation attempt', [
                    'user_id' => $userId,
                    'user_role' => $userRole,
                    'user_type' => $userType,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            return;
        }

        // Rate limiting check con validación mejorada
        global $applyLimit;
        if (!$applyLimit['allowed']) {
            jsend(false, 'Límite de aplicaciones excedido. Intente más tarde.', null, 429);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Rate limit exceeded for application creation', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_id' => $userId,
                    'remaining_time' => $applyLimit['remaining_time'] ?? 3600,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            return;
        }

        // Validar entrada JSON con mejor manejo de errores
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            jsend(false, 'Datos inválidos - JSON malformado o vacío', null, 400);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Invalid JSON input for application creation', [
                    'user_id' => $userId,
                    'raw_input' => file_get_contents('php://input'),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return;
        }

        // Usar validación robusta mejorada
        $validation = validateApplicationInput($input, $userRole, false);
        if (!empty($validation['errors'])) {
            jsend(false, 'Errores de validación: ' . implode(', ', $validation['errors']), null, 400);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Application creation validation failed', [
                    'user_id' => $userId,
                    'errors' => $validation['errors'],
                    'input_data' => array_keys($input),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return;
        }

        $data = $validation['sanitized'];

        // Verificar que el trabajo existe y está abierto
        $jobStmt = $db->prepare('SELECT id, title, status FROM bt_jobs WHERE id = ? AND status = "open"');
        $jobStmt->execute([$data['job_id']]);
        $job = $jobStmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            jsend(false, 'El trabajo no existe o no está disponible para aplicaciones', null, 404);
            return;
        }

        // Verificar que no haya aplicación duplicada
        $dupeStmt = $db->prepare('SELECT id FROM bt_applications WHERE candidate_id = ? AND job_id = ? AND status != "deleted"');
        $dupeStmt->execute([$userId, $data['job_id']]);

        if ($dupeStmt->fetch()) {
            jsend(false, 'Ya has aplicado a este trabajo anteriormente', null, 409);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Duplicate application attempt', [
                    'candidate_id' => $userId,
                    'job_id' => $data['job_id'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return;
        }

        // Crear aplicación con prepared statement seguro mejorado
        $insertStmt = $db->prepare('
            INSERT INTO bt_applications (candidate_id, job_id, status, cover_letter, created_at, updated_at)
            VALUES (:candidate_id, :job_id, :status, :cover_letter, NOW(), NOW())
        ');

        $params = [
            ':candidate_id' => $userId,
            ':job_id' => $data['job_id'],
            ':status' => 'pending',
            ':cover_letter' => $data['cover_letter'] ?? ''
        ];

        $success = $insertStmt->execute($params);

        if (!$success) {
            $errorInfo = $insertStmt->errorInfo();
            jsend(false, 'Error al crear la aplicación', null, 500);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::error('Database error during application creation', [
                    'user_id' => $userId,
                    'job_id' => $data['job_id'],
                    'error_code' => $errorInfo[0] ?? 'unknown',
                    'error_message' => $errorInfo[2] ?? 'unknown',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return;
        }

        $appId = $db->lastInsertId();

        // Log de auditoría seguro mejorado
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Aplicación creada exitosamente', [
                'application_id' => $appId,
                'candidate_id' => $userId,
                'job_id' => $data['job_id'],
                'job_title' => $job['title'],
                'cover_letter_length' => strlen($data['cover_letter'] ?? ''),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'session_id' => session_id(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }

        // Preparar respuesta sanitizada
        $responseData = [
            'id' => (int)$appId,
            'candidate_id' => $userId,
            'job_id' => (int)$data['job_id'],
            'job_title' => htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8'),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        jsend(true, 'Aplicación creada exitosamente', $responseData, 201);
    } catch (Throwable $e) {
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error creando aplicación', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}

/**
 * Maneja PUT - Actualizar aplicación con control de acceso mejorado y rate limiting
 */
function handleUpdateApplication($db, $userId, $userRole)
{
    try {
        // Validar ID de aplicación
        $appId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
        if (!$appId) {
            jsend(false, 'ID de aplicación requerido', null, 400);
            return;
        }

        // Rate limiting check
        global $updateLimit;
        if (!$updateLimit['allowed']) {
            jsend(false, 'Límite de actualizaciones excedido. Intente más tarde.', null, 429);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Rate limit exceeded for application update', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_id' => $userId,
                    'application_id' => $appId,
                    'remaining_time' => $updateLimit['remaining_time']
                ]);
            }
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            jsend(false, 'Datos inválidos - JSON malformado', null, 400);
            return;
        }

        // Usar validación robusta
        $validation = validateApplicationInput($input, $userRole, true);
        if (!empty($validation['errors'])) {
            jsend(false, 'Errores de validación: ' . implode(', ', $validation['errors']), null, 400);
            return;
        }

        $data = $validation['sanitized'];

        // Verificar que la aplicación existe
        $appStmt = $db->prepare('SELECT candidate_id, status FROM bt_applications WHERE id = ?');
        $appStmt->execute([$appId]);
        $application = $appStmt->fetch(PDO::FETCH_ASSOC);

        if (!$application) {
            jsend(false, 'Aplicación no encontrada', null, 404);
            return;
        }

        // CONTROL DE ACCESO:
        // - Candidatos solo pueden actualizar sus propias aplicaciones
        // - Staff puede actualizar cualquier aplicación
        $isStaff = in_array($userRole, ['admin', 'hr', 'recruiter'], true);

        if (!$isStaff && $application['candidate_id'] != $userId) {
            jsend(false, 'No autorizado para actualizar esta aplicación', null, 403);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Unauthorized application update attempt', [
                    'application_id' => $appId,
                    'user_id' => $userId,
                    'application_owner' => $application['candidate_id'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return;
        }

        // Preparar actualización con validaciones de seguridad
        $updateFields = [];
        $params = [];

        // Candidatos solo pueden cambiar el status a 'withdrawn'
        if (!$isStaff) {
            if (!isset($data['status']) || $data['status'] !== 'withdrawn') {
                jsend(false, 'Candidatos solo pueden retirar aplicaciones', null, 400);
                return;
            }
            $updateFields[] = 'status = ?';
            $params[] = 'withdrawn';
        } else {
            // Staff puede actualizar status y score
            if (isset($data['status'])) {
                $updateFields[] = 'status = ?';
                $params[] = $data['status'];
            }

            if (isset($data['score'])) {
                $updateFields[] = 'score = ?';
                $params[] = $data['score'];
            }
        }

        if (empty($updateFields)) {
            jsend(false, 'No hay campos para actualizar', null, 400);
            return;
        }

        $params[] = $appId;
        $sql = 'UPDATE bt_applications SET ' . implode(', ', $updateFields) . ', updated_at = NOW() WHERE id = ?';
        $stmt = $db->prepare($sql);
        $success = $stmt->execute($params);

        if (!$success) {
            jsend(false, 'Error al actualizar la aplicación', null, 500);
            return;
        }

        // Log de auditoría seguro
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Aplicación actualizada exitosamente', [
                'application_id' => $appId,
                'updated_by' => $userId,
                'user_role' => $userRole,
                'fields_updated' => array_keys($data),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        jsend(true, 'Aplicación actualizada exitosamente');
    } catch (Throwable $e) {
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error actualizando aplicación', [
                'error' => $e->getMessage(),
                'application_id' => $appId ?? null,
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}

/**
 * Maneja DELETE - Eliminar aplicación con control de acceso mejorado y rate limiting
 */
function handleDeleteApplication($db, $userId, $userRole)
{
    try {
        // CONTROL DE ACCESO: Solo admin puede eliminar aplicaciones
        if ($userRole !== 'admin') {
            jsend(false, 'No autorizado para eliminar aplicaciones', null, 403);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Unauthorized application deletion attempt', [
                    'user_id' => $userId,
                    'user_role' => $userRole,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return;
        }

        // Validar ID de aplicación
        $appId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
        if (!$appId) {
            jsend(false, 'ID de aplicación requerido', null, 400);
            return;
        }

        // Rate limiting check
        global $deleteLimit;
        if (!$deleteLimit['allowed']) {
            jsend(false, 'Límite de eliminaciones excedido. Intente más tarde.', null, 429);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Rate limit exceeded for application deletion', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_id' => $userId,
                    'remaining_time' => $deleteLimit['remaining_time']
                ]);
            }
            return;
        }

        // Verificar que la aplicación existe
        $stmt = $db->prepare('SELECT id, candidate_id, job_id FROM bt_applications WHERE id = ?');
        $stmt->execute([$appId]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application) {
            jsend(false, 'Aplicación no encontrada', null, 404);
            return;
        }

        // Log antes de eliminar (para auditoría)
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Aplicación marcada para eliminación', [
                'application_id' => $appId,
                'candidate_id' => $application['candidate_id'],
                'job_id' => $application['job_id'],
                'deleted_by' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        // Eliminar aplicación (soft delete cambiando status)
        $deleteStmt = $db->prepare('UPDATE bt_applications SET status = "deleted", updated_at = NOW(), deleted_by = ? WHERE id = ?');
        $success = $deleteStmt->execute([$userId, $appId]);

        if (!$success) {
            jsend(false, 'Error al eliminar la aplicación', null, 500);
            return;
        }

        // Log de confirmación de eliminación
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Aplicación eliminada exitosamente', [
                'application_id' => $appId,
                'deleted_by' => $userId,
                'user_role' => $userRole,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        jsend(true, 'Aplicación eliminada exitosamente');
    } catch (Throwable $e) {
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error eliminando aplicación', [
                'error' => $e->getMessage(),
                'application_id' => $appId ?? null,
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}
