<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/departments
 * Gestiona operaciones CRUD de departamentos de forma segura
 *
 * @package API
 * @author Bubble Talents Development Team
 * @version 2.0.0 - Security Enhanced
 */

// Configurar headers de seguridad mejorados con CSP avanzado
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Cross-Origin-Embedder-Policy: require-corp');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// CSP avanzado para API de departments
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'none'; img-src 'self' data: https:; font-src 'none'; connect-src 'self'; media-src 'none'; object-src 'none'; frame-src 'none'; frame-ancestors 'none'; form-action 'self'; upgrade-insecure-requests; block-all-mixed-content");

// Headers adicionales de seguridad avanzada
header('X-Permitted-Cross-Domain-Policies: none');
header('X-Download-Options: noopen');
header('X-DNS-Prefetch-Control: off');
header('X-Requested-With: XMLHttpRequest');
header('X-Permitted-Cross-Domain-Policies: none');
header('X-Download-Options: noopen');
header('X-DNS-Prefetch-Control: off');
header('X-Requested-With: XMLHttpRequest');

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

// Configurar rate limiting avanzado para operaciones críticas de departments
$clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Implementar rate limiting avanzado con seguimiento por operación
class DepartmentsRateLimiter
{
    private static $limits = [
        'read' => ['max_requests' => 60, 'window_seconds' => 3600],    // 60 por hora
        'create' => ['max_requests' => 3, 'window_seconds' => 3600],   // 3 por hora
        'update' => ['max_requests' => 10, 'window_seconds' => 3600],  // 10 por hora
        'delete' => ['max_requests' => 2, 'window_seconds' => 3600]    // 2 por hora
    ];

    public static function checkLimit(string $operation, string $identifier): array
    {
        $limit = self::$limits[$operation] ?? ['max_requests' => 10, 'window_seconds' => 3600];
        $key = "departments_{$operation}_" . md5($identifier);
        $tempFile = sys_get_temp_dir() . "/{$key}.json";

        // Leer datos existentes o crear nuevos
        if (file_exists($tempFile)) {
            $data = json_decode(file_get_contents($tempFile), true);
            if (!$data || time() > $data['reset_time']) {
                $data = ['count' => 0, 'reset_time' => time() + $limit['window_seconds']];
            }
        } else {
            $data = ['count' => 0, 'reset_time' => time() + $limit['window_seconds']];
        }

        $data['count']++;
        $allowed = $data['count'] <= $limit['max_requests'];
        $remainingRequests = max(0, $limit['max_requests'] - $data['count']);
        $remainingTime = max(0, $data['reset_time'] - time());

        // Guardar datos actualizados
        file_put_contents($tempFile, json_encode($data));

        return [
            'allowed' => $allowed,
            'remaining_requests' => $remainingRequests,
            'remaining_time' => $remainingTime,
            'reset_time' => $data['reset_time']
        ];
    }
}

// Rate limiting más estricto para operaciones de departments
$createLimit = DepartmentsRateLimiter::checkLimit('create', $clientIP);
$updateLimit = DepartmentsRateLimiter::checkLimit('update', $clientIP);
$deleteLimit = DepartmentsRateLimiter::checkLimit('delete', $clientIP);
$readLimit = DepartmentsRateLimiter::checkLimit('read', $clientIP);

// Autenticación requerida para todas las operaciones
$userPayload = \Middleware\JWTMiddleware::requireAuth();
if (!$userPayload) {
    exit; // El middleware ya maneja la respuesta de error
}

$userId = (int)$userPayload['user_id'];
$userRole = $userPayload['role'] ?? 'candidate';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
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
 * Función helper para generar logs de auditoría de seguridad
 */
function logSecurityEvent(string $event, array $context): void
{
    if (!class_exists('\Utils\Logger')) {
        return;
    }

    $securityContext = array_merge($context, [
        'event_type' => 'security',
        'api_endpoint' => 'departments',
        'timestamp' => date('Y-m-d H:i:s'),
        'session_id' => session_id(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    \Utils\Logger::info("Security Event: {$event}", $securityContext);
}

/**
 * Sanitiza datos de departamento para salida segura
 */
function sanitizeDepartmentData(array $dept): array
{
    $sanitized = [];

    // Campos numéricos - asegurar tipo correcto
    $numericFields = ['id', 'created_by', 'updated_by', 'deleted_by'];
    foreach ($numericFields as $field) {
        if (isset($dept[$field])) {
            $sanitized[$field] = (int)$dept[$field];
        }
    }

    // Campos booleanos
    if (isset($dept['active'])) {
        $sanitized['active'] = (bool)$dept['active'];
    }

    // Campos de texto - sanitización robusta contra XSS
    $textFields = ['name', 'description', 'code'];
    foreach ($textFields as $field) {
        if (isset($dept[$field])) {
            // Doble sanitización: strip_tags primero, luego htmlspecialchars
            $cleaned = strip_tags($dept[$field]);
            $sanitized[$field] = htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    // Fechas - sin cambios, pero validar formato si es necesario
    $dateFields = ['created_at', 'updated_at', 'deleted_at'];
    foreach ($dateFields as $field) {
        if (isset($dept[$field])) {
            $sanitized[$field] = $dept[$field];
        }
    }

    // Categorías - sanitizar recursivamente
    if (isset($dept['categories']) && is_array($dept['categories'])) {
        $sanitized['categories'] = array_map('sanitizeDepartmentCategoryData', $dept['categories']);
    }

    return $sanitized;
}

/**
 * Sanitiza datos de categoría de departamento
 */
function sanitizeDepartmentCategoryData(array $category): array
{
    $sanitized = [];

    // Campos numéricos
    $numericFields = ['id', 'department_id'];
    foreach ($numericFields as $field) {
        if (isset($category[$field])) {
            $sanitized[$field] = (int)$category[$field];
        }
    }

    // Campos de texto
    $textFields = ['name', 'description', 'code'];
    foreach ($textFields as $field) {
        if (isset($category[$field])) {
            $cleaned = strip_tags($category[$field]);
            $sanitized[$field] = htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    // Fechas
    $dateFields = ['created_at', 'updated_at'];
    foreach ($dateFields as $field) {
        if (isset($category[$field])) {
            $sanitized[$field] = $category[$field];
        }
    }

    return $sanitized;
}

/**
 * Valida y sanitiza entrada de departamento con validaciones avanzadas
 */
function validateDepartmentInput(array $input, bool $isUpdate = false): array
{
    $errors = [];
    $sanitized = [];

    // Campos requeridos para creación con validación estricta
    $requiredFields = $isUpdate ? [] : ['name'];

    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            $errors[] = "Campo requerido faltante o vacío: {$field}";
        }
    }

    // Validaciones avanzadas con expresiones regulares mejoradas
    $validations = [
        'name' => [
            'required' => !$isUpdate,
            'type' => 'string',
            'min_length' => 2,
            'max_length' => 100,
            'pattern' => '/^[a-zA-ZÀ-ÿ0-9\s\-\.,\(\)\p{L}]+$/u', // Soporte Unicode completo
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'description' => [
            'required' => false,
            'max_length' => 500,
            'allow_html' => false,
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'code' => [
            'required' => false,
            'min_length' => 2,
            'max_length' => 20,
            'pattern' => '/^[A-Z0-9\-_]+$/',
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'active' => [
            'required' => false,
            'type' => 'boolean'
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

        // Validar tipo de dato
        if (isset($rules['type'])) {
            switch ($rules['type']) {
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($value === null) {
                        $errors[] = "Campo {$field} debe ser un valor booleano";
                        continue 2;
                    }
                    break;
            }
        }

        // Validar límites de longitud
        if (isset($rules['min_length']) && is_string($value) && strlen($value) < $rules['min_length']) {
            $errors[] = "Campo {$field} debe tener al menos {$rules['min_length']} caracteres";
            continue;
        }

        if (isset($rules['max_length']) && is_string($value) && strlen($value) > $rules['max_length']) {
            $errors[] = "Campo {$field} excede el límite de {$rules['max_length']} caracteres";
            continue;
        }

        // Validar patrón regex
        if (isset($rules['pattern']) && is_string($value) && !preg_match($rules['pattern'], $value)) {
            $errors[] = "Campo {$field} contiene caracteres no válidos";
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
            if (is_string($value)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $errors[] = "Campo {$field} contiene caracteres potencialmente peligrosos";
                        continue 2;
                    }
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

/**
 * Función helper para obtener conexión a BD segura
 */
function getSecureDatabaseConnection()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = \Utils\Database::getInstance()->getConnection();
        } catch (Exception $e) {
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::error('Database connection failed in departments.php', [
                    'error' => $e->getMessage()
                ]);
            }
            jsend(false, 'Error de conexión a base de datos', null, 500);
            exit;
        }
    }
    return $pdo;
}

/**
 * Maneja GET - Listar departamentos o departamento específico con control de acceso
 */
function handleGetDepartments($db, $userId, $userRole)
{
    try {
        // Validar y sanitizar parámetros de entrada
        $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 1), 100) : 20;
        $offset = ($page - 1) * $limit;
        $activeOnly = isset($_GET['active_only']) ? filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN) : false;

        // Si se solicita un departamento específico
        if ($id !== null && $id > 0) {
            $sql = "SELECT * FROM bt_departments WHERE id = ?";
            $params = [$id];

            if ($activeOnly) {
                $sql .= " AND active = 1";
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $dept = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dept) {
                jsend(false, 'Departamento no encontrado', null, 404);
                return;
            }

            // Obtener categorías del departamento
            $catStmt = $db->prepare("SELECT * FROM bt_department_categories WHERE department_id = ? ORDER BY id ASC");
            $catStmt->execute([$id]);
            $dept['categories'] = $catStmt->fetchAll(PDO::FETCH_ASSOC);

            $sanitizedDept = sanitizeDepartmentData($dept);

            // Log de consulta
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::info('Departamento consultado', [
                    'department_id' => $id,
                    'user_id' => $userId,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            jsend(true, 'Departamento encontrado', $sanitizedDept);
            return;
        }

        // Listar todos los departamentos con paginación
        $sql = "SELECT * FROM bt_departments WHERE 1=1";
        $params = [];

        if ($activeOnly) {
            $sql .= " AND active = 1";
        }

        $sql .= " ORDER BY id ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Obtener categorías para cada departamento
        foreach ($departments as &$dept) {
            $catStmt = $db->prepare("SELECT * FROM bt_department_categories WHERE department_id = ? ORDER BY id ASC");
            $catStmt->execute([$dept['id']]);
            $dept['categories'] = $catStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $sanitizedDepartments = array_map('sanitizeDepartmentData', $departments);

        // Obtener total para paginación
        $countSql = "SELECT COUNT(*) FROM bt_departments WHERE 1=1";
        $countParams = [];

        if ($activeOnly) {
            $countSql .= " AND active = 1";
        }

        $countStmt = $db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();

        // Log de consulta de lista
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Lista de departamentos consultada', [
                'total_results' => count($departments),
                'total_count' => $total,
                'page' => $page,
                'limit' => $limit,
                'active_only' => $activeOnly,
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        jsend(true, 'Departamentos obtenidos exitosamente', [
            'departments' => $sanitizedDepartments,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
    } catch (Throwable $e) {
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error consultando departamentos', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}

/**
 * Maneja POST - Crear departamento con control de acceso mejorado y rate limiting
 */
function handleCreateDepartment($db, $userId, $userRole)
{
    try {
        // CONTROL DE ACCESO: Solo admin y hr pueden crear departamentos
        if (!in_array($userRole, ['admin', 'hr'])) {
            logSecurityEvent('unauthorized_department_creation', [
                'user_id' => $userId,
                'user_role' => $userRole,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            jsend(false, 'No autorizado para crear departamentos', null, 403);
            return;
        }

        // Rate limiting check
        global $createLimit;
        if (!$createLimit['allowed']) {
            logSecurityEvent('rate_limit_exceeded_department_creation', [
                'user_id' => $userId,
                'remaining_time' => $createLimit['remaining_time'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            jsend(false, 'Límite de creación de departamentos excedido. Intente más tarde.', null, 429);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            jsend(false, 'Datos inválidos - JSON malformado', null, 400);
            return;
        }

        // Usar validación robusta
        $validation = validateDepartmentInput($input, false);
        if (!empty($validation['errors'])) {
            \Utils\Logger::warning('Validación fallida al crear departamento', [
                'errors' => $validation['errors'],
                'user_id' => $userId
            ]);
            jsend(false, 'Errores de validación: ' . implode(', ', $validation['errors']), null, 400);
            return;
        }

        $data = $validation['sanitized'];

        // Verificar que no exista un departamento con el mismo nombre
        $dupeStmt = $db->prepare('SELECT id FROM bt_departments WHERE name = ? AND active = 1');
        $dupeStmt->execute([$data['name']]);

        if ($dupeStmt->fetch()) {
            \Utils\Logger::warning('Intento de crear departamento con nombre existente', [
                'name' => $data['name'],
                'user_id' => $userId
            ]);
            jsend(false, 'Ya existe un departamento con ese nombre', null, 409);
            return;
        }

        // Crear departamento con prepared statement seguro
        $sql = "INSERT INTO bt_departments (name, description, code, active, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $db->prepare($sql);
        $success = $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['code'] ?? null,
            $data['active'] ?? true,
            $userId
        ]);

        if (!$success) {
            jsend(false, 'Error al crear el departamento', null, 500);
            return;
        }

        $deptId = $db->lastInsertId();

        // Log de auditoría seguro
        logSecurityEvent('department_created', [
            'department_id' => $deptId,
            'created_by' => $userId,
            'user_role' => $userRole,
            'department_name' => $data['name'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Departamento creado exitosamente', [
                'department_id' => $deptId,
                'created_by' => $userId,
                'user_role' => $userRole,
                'department_name' => $data['name'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        jsend(true, 'Departamento creado exitosamente', [
            'id' => $deptId,
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'code' => $data['code'] ?? null,
            'active' => $data['active'] ?? true,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ], 201);
    } catch (Throwable $e) {
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error creando departamento', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}

/**
 * Maneja PUT - Actualizar departamento con control de acceso mejorado y rate limiting
 */
function handleUpdateDepartment($db, $userId, $userRole)
{
    try {
        // CONTROL DE ACCESO: Solo admin y hr pueden actualizar departamentos
        if (!in_array($userRole, ['admin', 'hr'])) {
            logSecurityEvent('unauthorized_department_update', [
                'user_id' => $userId,
                'user_role' => $userRole,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            jsend(false, 'No autorizado para actualizar departamentos', null, 403);
            return;
        }

        $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
        if (!$id) {
            jsend(false, 'ID de departamento requerido', null, 400);
            return;
        }

        // Rate limiting check
        global $updateLimit;
        if (!$updateLimit['allowed']) {
            logSecurityEvent('rate_limit_exceeded_department_update', [
                'user_id' => $userId,
                'department_id' => $id,
                'remaining_time' => $updateLimit['remaining_time'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            jsend(false, 'Límite de actualización de departamentos excedido. Intente más tarde.', null, 429);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            jsend(false, 'Datos inválidos - JSON malformado', null, 400);
            return;
        }

        // Usar validación robusta
        $validation = validateDepartmentInput($input, true);
        if (!empty($validation['errors'])) {
            \Utils\Logger::warning('Validación fallida al actualizar departamento', [
                'errors' => $validation['errors'],
                'department_id' => $id,
                'user_id' => $userId
            ]);
            jsend(false, 'Errores de validación: ' . implode(', ', $validation['errors']), null, 400);
            return;
        }

        $data = $validation['sanitized'];

        // Verificar que el departamento existe
        $stmt = $db->prepare('SELECT id, name FROM bt_departments WHERE id = ?');
        $stmt->execute([$id]);
        $existingDept = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingDept) {
            jsend(false, 'Departamento no encontrado', null, 404);
            return;
        }

        // Verificar que no exista otro departamento con el mismo nombre
        if (isset($data['name'])) {
            $dupeStmt = $db->prepare('SELECT id FROM bt_departments WHERE name = ? AND id != ? AND active = 1');
            $dupeStmt->execute([$data['name'], $id]);

            if ($dupeStmt->fetch()) {
                \Utils\Logger::warning('Intento de actualizar departamento con nombre existente', [
                    'department_id' => $id,
                    'name' => $data['name'],
                    'user_id' => $userId
                ]);
                jsend(false, 'Ya existe otro departamento con ese nombre', null, 409);
                return;
            }
        }

        // Preparar actualización
        $updateFields = [];
        $params = [];

        $allowedFields = ['name', 'description', 'code', 'active'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updateFields)) {
            jsend(false, 'No hay campos para actualizar', null, 400);
            return;
        }

        $params[] = $id;
        $sql = 'UPDATE bt_departments SET ' . implode(', ', $updateFields) . ', updated_at = NOW(), updated_by = ? WHERE id = ?';
        $params[] = $userId;

        $stmt = $db->prepare($sql);
        $success = $stmt->execute($params);

        if (!$success) {
            jsend(false, 'Error al actualizar el departamento', null, 500);
            return;
        }

        // Log de auditoría seguro
        logSecurityEvent('department_updated', [
            'department_id' => $id,
            'updated_by' => $userId,
            'user_role' => $userRole,
            'fields_updated' => array_keys($data),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Departamento actualizado exitosamente', [
                'department_id' => $id,
                'updated_by' => $userId,
                'user_role' => $userRole,
                'fields_updated' => array_keys($data),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        jsend(true, 'Departamento actualizado exitosamente');
    } catch (Throwable $e) {
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error actualizando departamento', [
                'error' => $e->getMessage(),
                'department_id' => $id ?? null,
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}

/**
 * Maneja DELETE - Eliminar departamento con soft delete y control de acceso mejorado
 */
function handleDeleteDepartment($db, $userId, $userRole)
{
    try {
        // CONTROL DE ACCESO: Solo admin puede eliminar departamentos
        if ($userRole !== 'admin') {
            logSecurityEvent('unauthorized_department_delete', [
                'user_id' => $userId,
                'user_role' => $userRole,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            jsend(false, 'No autorizado para eliminar departamentos', null, 403);
            return;
        }

        $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
        if (!$id) {
            jsend(false, 'ID de departamento requerido', null, 400);
            return;
        }

        // Rate limiting check
        global $deleteLimit;
        if (!$deleteLimit['allowed']) {
            logSecurityEvent('rate_limit_exceeded_department_delete', [
                'user_id' => $userId,
                'department_id' => $id,
                'remaining_time' => $deleteLimit['remaining_time'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            jsend(false, 'Límite de eliminación de departamentos excedido. Intente más tarde.', null, 429);
            return;
        }

        // Verificar que el departamento existe y está activo
        $stmt = $db->prepare('SELECT id, name, active FROM bt_departments WHERE id = ?');
        $stmt->execute([$id]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$department) {
            jsend(false, 'Departamento no encontrado', null, 404);
            return;
        }

        if (!$department['active']) {
            jsend(false, 'El departamento ya está inactivo', null, 400);
            return;
        }

        // Verificar si hay dependencias (empleados, trabajos, etc.)
        $dependencyChecks = [
            'bt_candidates' => 'department_id',
            'bt_jobs' => 'department_id',
            'bt_applications' => 'department_id'
        ];

        $dependencies = [];
        foreach ($dependencyChecks as $table => $column) {
            $depStmt = $db->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ? AND active = 1");
            $depStmt->execute([$id]);
            $count = $depStmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($count > 0) {
                $dependencies[] = "{$table}: {$count} registros";
            }
        }

        if (!empty($dependencies)) {
            logSecurityEvent('department_delete_blocked_dependencies', [
                'user_id' => $userId,
                'department_id' => $id,
                'dependencies' => $dependencies,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            jsend(false, 'No se puede eliminar el departamento porque tiene dependencias activas: ' . implode(', ', $dependencies), null, 409);
            return;
        }

        // Soft delete: marcar como inactivo en lugar de eliminar físicamente
        $stmt = $db->prepare('UPDATE bt_departments SET active = 0, deleted_at = NOW(), deleted_by = ? WHERE id = ?');
        $success = $stmt->execute([$userId, $id]);

        if (!$success) {
            jsend(false, 'Error al eliminar el departamento', null, 500);
            return;
        }

        // Log de auditoría seguro
        logSecurityEvent('department_deleted', [
            'department_id' => $id,
            'department_name' => $department['name'],
            'deleted_by' => $userId,
            'user_role' => $userRole,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Departamento eliminado exitosamente (soft delete)', [
                'department_id' => $id,
                'department_name' => $department['name'],
                'deleted_by' => $userId,
                'user_role' => $userRole,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        jsend(true, 'Departamento eliminado exitosamente');
    } catch (Throwable $e) {
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error eliminando departamento', [
                'error' => $e->getMessage(),
                'department_id' => $id ?? null,
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}

// Routing principal
try {
    $db = getSecureDatabaseConnection();

    switch ($method) {
        case 'GET':
            handleGetDepartments($db, $userId, $userRole);
            break;

        case 'POST':
            if (!$createLimit['allowed']) {
                jsend(false, 'Límite de creación de departamentos excedido. Intente más tarde.', null, 429);
                exit;
            }
            handleCreateDepartment($db, $userId, $userRole);
            break;

        case 'PUT':
            if (!$updateLimit['allowed']) {
                jsend(false, 'Límite de actualización de departamentos excedido. Intente más tarde.', null, 429);
                exit;
            }
            handleUpdateDepartment($db, $userId, $userRole);
            break;

        case 'DELETE':
            if (!$deleteLimit['allowed']) {
                jsend(false, 'Límite de eliminación de departamentos excedido. Intente más tarde.', null, 429);
                exit;
            }
            handleDeleteDepartment($db, $userId, $userRole);
            break;

        default:
            jsend(false, 'Método no permitido', null, 405);
            break;
    }
} catch (Throwable $e) {
    // Log de error seguro sin exponer información sensible
    if (class_exists('\Utils\Logger')) {
        \Utils\Logger::error('Error en departments API', [
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
