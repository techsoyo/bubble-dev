<?php declare(strict_types=1);
use Security\CsrfMiddleware;

require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// cookie HttpOnly obligatoria

// Proteger solo mÃƒÂ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    // double-submit cookie
}

// En producciÃƒÂ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// No dupliques CORS aquÃƒÂ­. El bootstrap ya los aplica.
// SÃƒÂ³lo fijamos Content-Type y atendemos preflight.
header('Content-Type: application/json; charset=UTF-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function jsend(bool $ok, string $message, $data = null, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Requiere JWT en todos los mÃ©todos
$userPayload = null; // Inicializar variable
// TODO: Implementar obtenciÃ³n del payload JWT desde cookie
if (!$userPayload) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - JWT required']);
    exit;
} // el middleware ya habrÃ¡ respondido

$userId   = $userPayload['user_id']   ?? null;
$userRole = $userPayload['role']      ?? null;            // 'admin' | 'hr' | 'recruiter' | 'candidate' ...
$userType = $userPayload['user_type'] ?? null;            // 'staff' | 'candidate' (segÃƒÂºn tu login)

try {
    $db = getDbConnection();
} catch (Throwable $e) {
    jsend(false, 'Error de conexiÃƒÂ³n a BD', null, 500);
}

/* ============================
   GET: listar aplicaciones
   ============================ */
if ($method === 'GET') {
    try {
        $candidateId = $_GET['candidate_id'] ?? null;
        $jobId       = $_GET['job_id']       ?? null;
        $status      = $_GET['status']       ?? null;
        $limit       = min((int)($_GET['limit'] ?? 20), 100);
        $offset      = (int)($_GET['offset'] ?? 0);

        // Control de acceso:
        // - Candidatos: sÃƒÂ³lo sus propias aplicaciones
        // - Staff con rol admin/hr/recruiter: pueden filtrar libremente
        $staffAllowed = in_array($userRole, ['admin', 'hr', 'recruiter'], true);

        if (!$staffAllowed) {
            // TrÃƒÂ¡talo como candidato: solo ve lo suyo
            $candidateId = $userId;
        }

        $where = [];
        $params = [];

        if ($candidateId) {
            $where[] = 'a.candidate_id = ?';
            $params[] = $candidateId;
        }
        if ($jobId) {
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
                c.name  AS candidate_name,
                c.email AS candidate_email,
                j.title AS job_title,
                j.company_name,
                j.location AS job_location,
                j.salary_range
            FROM bt_applications a
            LEFT JOIN bt_candidates c ON a.candidate_id = c.id
            LEFT JOIN bt_jobs j       ON a.job_id      = j.id
            $whereSql
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Saneado para candidatos (no exponer email de otros)
        if (!$staffAllowed) {
            foreach ($applications as &$app) {
                unset($app['candidate_email']);
                if (!empty($app['applied_at'])) {
                    $app['applied_at'] = date('Y-m-d H:i:s', strtotime($app['applied_at']));
                }
            }
        } else {
            foreach ($applications as &$app) {
                if (!empty($app['applied_at'])) {
                    $app['applied_at'] = date('Y-m-d H:i:s', strtotime($app['applied_at']));
                }
            }
        }

        // Conteo total
        $countSql = "SELECT COUNT(*) FROM bt_applications a $whereSql";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute(array_slice($params, 0, -2));
        $total = (int)$countStmt->fetchColumn();

        jsend(true, 'OK', [
            'applications' => $applications,
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
        error_log('GET APPLICATIONS ERROR: ' . $e->getMessage());
        jsend(false, 'Error al obtener aplicaciones', null, 500);
    }
}

/* ============================
   POST:
   - bulk_update (staff: admin/hr/recruiter)
   - crear aplicaciÃƒÂ³n (candidato)
   ============================ */
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Rama A: BULK UPDATE (staff)
    if (isset($input['bulk_update']) && is_array($input['bulk_update'])) {
        if (!in_array($userRole, ['admin', 'hr', 'recruiter'], true)) {
            jsend(false, 'Permisos insuficientes', null, 403);
        }

        $stmt = $db->prepare('UPDATE bt_applications SET status = ?, updated_at = NOW() WHERE id = ?');
        $results = [];

        foreach ($input['bulk_update'] as $row) {
            $id = $row['id']     ?? null;
            $st = $row['status'] ?? null;

            if (!$id || !$st) {
                $results[] = ['id' => $id, 'ok' => false, 'error' => 'id/status requeridos'];
                continue;
            }
            try {
                $ok = $stmt->execute([$st, $id]);
                $results[] = ['id' => $id, 'ok' => (bool)$ok];
            } catch (Throwable $e) {
                $results[] = ['id' => $id, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        jsend(true, 'ActualizaciÃƒÂ³n masiva realizada', ['results' => $results], 200);
    }

    // Rama B: crear aplicaciÃƒÂ³n (sÃƒÂ³lo candidatos)
    if (empty($input['job_id'])) {
        jsend(false, 'job_id es requerido', null, 400);
    }
    if ($userType !== 'candidate' && !in_array($userRole, ['candidate'], true)) {
        jsend(false, 'SÃƒÂ³lo candidatos pueden aplicar a un trabajo', null, 403);
    }

    $jobId       = $input['job_id'];
    $candidateId = $userId;
    $coverLetter = trim($input['cover_letter'] ?? '');

    try {
        // Verifica que el job exista y estÃƒÂ© abierto
        $jobStmt = $db->prepare('SELECT id, title FROM bt_jobs WHERE id = ? AND status = "open"');
        $jobStmt->execute([$jobId]);
        $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            jsend(false, 'El trabajo no existe o no estÃƒÂ¡ disponible', null, 404);
        }

        // Evita duplicados
        $dupe = $db->prepare('SELECT id FROM bt_applications WHERE candidate_id = ? AND job_id = ?');
        $dupe->execute([$candidateId, $jobId]);
        if ($dupe->fetch()) {
            jsend(false, 'Ya has aplicado a este trabajo anteriormente', null, 409);
        }

        // Inserta
        $ins = $db->prepare('
            INSERT INTO bt_applications (candidate_id, job_id, status, cover_letter, created_at, updated_at)
            VALUES (?, ?, "pending", ?, NOW(), NOW())
        ');
        $ok = $ins->execute([$candidateId, $jobId, $coverLetter]);
        if (!$ok) {
            jsend(false, 'Error al insertar aplicaciÃƒÂ³n', null, 500);
        }

        $appId = $db->lastInsertId();
        jsend(true, 'AplicaciÃƒÂ³n creada', [
            'id'            => $appId,
            'candidate_id'  => $candidateId,
            'job_id'        => $jobId,
            'job_title'     => $job['title'],
            'status'        => 'pending',
            'created_at'    => date('Y-m-d H:i:s')
        ], 201);
    } catch (Throwable $e) {
        error_log('CREATE APPLICATION ERROR: ' . $e->getMessage());
        jsend(false, 'Error al crear la aplicaciÃƒÂ³n', null, 500);
    }
}

jsend(false, 'MÃƒÂ©todo no permitido', null, 405);
