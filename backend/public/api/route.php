<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Sube 1 nivel: api Ã¢â€ â€™ backend/

/**
 * Endpoint: POST /api/route
 * Objetivo: Ruteo automÃƒÂ¡tico de candidatos a reclutadores segÃƒÂºn reglas configurables
 *
 * Input: {
 *   "candidate_id": "string",
 *   "candidate_data": {object con skills, categoria, etc},
 *   "routing_rules": {
 *     "prefer_department_by_skills": true,
 *     "auto_assign_recruiter": true,
 *     "load_balancing": "round_robin|skills_match|random"
 *   },
 *   "feature_flags": {"auto_routing_enabled": true}
 * }
 *
 * Output: {
 *   "success": true,
 *   "data": {
 *     "routing_id": "uuid",
 *     "candidate_id": "string",
 *     "department": {"id": int, "name": string},
 *     "recruiter": {"id": string, "name": string, "email": string} | null,
 *     "routing_reason": "skills_match|default_assignment|manual_override",
 *     "assigned_at": "iso_date"
 *   },
 *   "meta": {
 *     "routing_method": "ai|skills_based|fallback",
 *     "processing_time_ms": number,
 *     "rules_applied": array
 *   }
 * }
 */

use Utils\Log;
use Utils\RequestId;
use Services\NotificationService;

$start_time = microtime(true);

// CORS ya configurado en bootstrap.php

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

// Validar Content-Type
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') === false) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Content-Type must be application/json']);
  exit;
}

try {
  // Leer y validar input JSON
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
  }

  // Validar campos requeridos
  if (!isset($data['candidate_id']) || empty($data['candidate_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required field: candidate_id']);
    exit;
  }

  $candidateId = $data['candidate_id'];
  $candidateData = $data['candidate_data'] ?? [];
  $routingRules = $data['routing_rules'] ?? [];
  $featureFlags = $data['feature_flags'] ?? [];

  // Feature flags (con valores por defecto seguros)
  $autoRoutingEnabled = ($featureFlags['auto_routing_enabled'] ?? false) === true;

  // Log inicio del proceso
  Log::json('info', [
    'endpoint' => '/api/route',
    'req_id' => RequestId::get(),
    'candidate_id' => $candidateId,
    'auto_routing_enabled' => $autoRoutingEnabled
  ]);

  if (!$autoRoutingEnabled) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'error' => 'Auto routing is disabled',
      'feature_flags' => $featureFlags
    ]);
    exit;
  }

  // Conectar a base de datos
  $pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME'),
    getenv('DB_USER'),
    getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  // Verificar si el candidato ya tiene routing
  $stmt = $pdo->prepare("SELECT id, department_id, recruiter_id, assigned_at FROM bt_candidate_routing WHERE candidate_id = ? ORDER BY assigned_at DESC LIMIT 1");
  $stmt->execute([$candidateId]);
  $existingRouting = $stmt->fetch();

  if ($existingRouting && !($routingRules['allow_reassignment'] ?? false)) {
    http_response_code(200);
    echo json_encode([
      'success' => true,
      'data' => [
        'routing_id' => $existingRouting['id'],
        'candidate_id' => $candidateId,
        'department' => ['id' => $existingRouting['department_id']],
        'recruiter_id' => $existingRouting['recruiter_id'],
        'assigned_at' => $existingRouting['assigned_at'],
        'status' => 'already_routed'
      ],
      'meta' => [
        'routing_method' => 'existing',
        'processing_time_ms' => round((microtime(true) - $start_time) * 1000, 2)
      ]
    ]);
    exit;
  }

  // 1. DETERMINAR DEPARTAMENTO
  $departmentId = null;
  $departmentName = null;
  $routingReason = '';
  $rulesApplied = [];

  // Estrategia 1: Basado en skills del candidato
  if ($routingRules['prefer_department_by_skills'] ?? true) {
    $candidateSkills = $candidateData['hard_skills'] ?? [];

    if (!empty($candidateSkills)) {
      // Buscar mapeo skills Ã¢â€ â€™ departamento
      $skillsStr = "'" . implode("', '", array_map(function ($skill) use ($pdo) {
        return $pdo->quote($skill);
      }, $candidateSkills)) . "'";

      $sql = "SELECT sdm.department_id, d.name as department_name, COUNT(*) as skill_matches
                    FROM bt_skill_department_map sdm
                    JOIN bt_departments d ON d.id = sdm.department_id  
                    WHERE LOWER(sdm.skill_name) IN (" . strtolower($skillsStr) . ")
                    GROUP BY sdm.department_id, d.name
                    ORDER BY skill_matches DESC, d.id ASC
                    LIMIT 1";

      $stmt = $pdo->query($sql);
      $skillMatch = $stmt->fetch();

      if ($skillMatch) {
        $departmentId = (int)$skillMatch['department_id'];
        $departmentName = $skillMatch['department_name'];
        $routingReason = 'skills_match';
        $rulesApplied[] = 'skills_based_department';
      }
    }
  }

  // Estrategia 2: Departamento por defecto basado en categorÃƒÂ­a
  if (!$departmentId && isset($candidateData['categoria'])) {
    $categoria = strtolower($candidateData['categoria']);

    // Mapeo bÃƒÂ¡sico categorÃƒÂ­a Ã¢â€ â€™ departamento
    $categoryMapping = [
      'frontend developer' => 2, // Engineering
      'backend developer' => 2,  // Engineering  
      'fullstack developer' => 2, // Engineering
      'digital & technology' => 2, // Engineering
      'marketing' => 4,           // Marketing
      'finance' => 5,             // Finance
      'hr' => 6,                  // HR
      'administration' => 1       // Administration
    ];

    foreach ($categoryMapping as $cat => $deptId) {
      if (strpos($categoria, $cat) !== false) {
        $departmentId = $deptId;
        // Obtener nombre del departamento
        $stmt = $pdo->prepare("SELECT name FROM bt_departments WHERE id = ?");
        $stmt->execute([$departmentId]);
        $dept = $stmt->fetch();
        $departmentName = $dept['name'] ?? 'Unknown';
        $routingReason = 'category_match';
        $rulesApplied[] = 'category_based_department';
        break;
      }
    }
  }

  // Estrategia 3: Departamento por defecto
  if (!$departmentId) {
    $departmentId = 2; // Engineering como default
    $departmentName = 'Engineering';
    $routingReason = 'default_assignment';
    $rulesApplied[] = 'default_department';
  }

  // 2. ASIGNAR RECLUTADOR (opcional)
  $recruiterId = null;
  $recruiterData = null;

  if ($routingRules['auto_assign_recruiter'] ?? true) {
    $loadBalancing = $routingRules['load_balancing'] ?? 'round_robin';

    // Buscar reclutadores del departamento
    $stmt = $pdo->prepare("
            SELECT id, name, email 
            FROM bt_staff_profiles 
            WHERE role = 'recruiter' 
            AND department_id = ? 
            AND active = 1 
            ORDER BY name ASC
        ");
    $stmt->execute([$departmentId]);
    $recruiters = $stmt->fetchAll();

    if (!empty($recruiters)) {
      switch ($loadBalancing) {
        case 'random':
          $recruiter = $recruiters[array_rand($recruiters)];
          break;
        case 'round_robin':
        default:
          // Seleccionar el primero por simplicidad
          $recruiter = $recruiters[0];
          break;
      }

      $recruiterId = $recruiter['id'];
      $recruiterData = [
        'id' => $recruiter['id'],
        'name' => $recruiter['name'],
        'email' => $recruiter['email']
      ];
      $rulesApplied[] = 'auto_assign_recruiter_' . $loadBalancing;
    }
  }

  // 3. CREAR REGISTRO DE ROUTING
  $routingId = bin2hex(random_bytes(18)); // UUID simplificado

  $stmt = $pdo->prepare("
        INSERT INTO bt_candidate_routing 
        (id, candidate_id, department_category_id, department_id, recruiter_id, source, reason, assigned_at)
        VALUES (?, ?, ?, ?, ?, 'ai', ?, NOW())
    ");

  $stmt->execute([
    $routingId,
    $candidateId,
    1, // department_category_id por defecto
    $departmentId,
    $recruiterId,
    $routingReason . ' - ' . implode(', ', $rulesApplied)
  ]);

  // 4. ENVIAR NOTIFICACIONES (opcional)
  if ($recruiterData && ($routingRules['send_notifications'] ?? true)) {
    try {
      $notificationService = new NotificationService();
      $subject = "Nuevo candidato asignado - " . ($candidateData['name'] ?? $candidateId);
      $body = "Se ha asignado un nuevo candidato a tu cartera.\n\nCandidato: " . ($candidateData['name'] ?? $candidateId) . "\nDepartamento: $departmentName\nRazÃƒÂ³n: $routingReason";

      $notificationService->sendEmail($recruiterData['email'], $subject, $body);
      $rulesApplied[] = 'notification_sent';
    } catch (Exception $e) {
      Log::json('warning', [
        'endpoint' => '/api/route',
        'req_id' => RequestId::get(),
        'notification_error' => $e->getMessage()
      ]);
    }
  }

  // Calcular tiempo de procesamiento
  $processingTime = round((microtime(true) - $start_time) * 1000, 2);

  // Respuesta exitosa
  $response = [
    'success' => true,
    'data' => [
      'routing_id' => $routingId,
      'candidate_id' => $candidateId,
      'department' => [
        'id' => $departmentId,
        'name' => $departmentName
      ],
      'recruiter' => $recruiterData,
      'routing_reason' => $routingReason,
      'assigned_at' => date('c'), // ISO 8601
      'rules_applied' => $rulesApplied
    ],
    'meta' => [
      'routing_method' => 'rules_based',
      'processing_time_ms' => $processingTime,
      'request_id' => RequestId::get(),
      'rules_applied' => $rulesApplied
    ]
  ];

  // Log ÃƒÂ©xito
  Log::json('info', [
    'endpoint' => '/api/route',
    'req_id' => RequestId::get(),
    'result' => 'success',
    'routing_id' => $routingId,
    'department_id' => $departmentId,
    'recruiter_id' => $recruiterId,
    'processing_time_ms' => $processingTime
  ]);

  http_response_code(200);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
  // Log error
  Log::json('error', [
    'endpoint' => '/api/route',
    'req_id' => RequestId::get(),
    'error' => $e->getMessage(),
    'trace' => (defined('APP_ENV') && APP_ENV === 'development') ? $e->getTraceAsString() : null
  ]);

  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Internal server error',
    'debug' => (defined('APP_ENV') && APP_ENV === 'development') ? $e->getMessage() : null
  ]);
}

