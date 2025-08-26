<?php



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

// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
require_once __DIR__ . '/../../src/Middleware/SecurityMiddleware.php';

use Middleware\SecurityMiddleware as Sec;
use Utils\JWT;
use Utils\Request;
use Utils\ResponseHelper as Res;
use Utils\Validator as Val;

if (!function_exists('db')) {
    function db(): PDO
    {
        return $GLOBALS['pdo'];
    }
}
if (!function_exists('T')) {
    function T(string $n): string
    {
        return 'bt_' . $n;
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$authUser = null;
try {
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        $authUser = JWT::requireAuth();
    }
} catch (\Throwable $e) {
    Res::error('No autorizado', 401, ['detail' => $e->getMessage()]);
}

try {
    $pdo = db();
    switch ($method) {
        case 'GET': {
            $candidateId = $_GET['candidate_id'] ?? null;
            ['ok' => $ok, 'errors' => $errs] = Val::validate(['candidate_id' => $candidateId], [
                'candidate_id' => 'required|string:1,36|regex:/^cnd-\d+$/'
            ]);
            if (!$ok) {
                Res::error('ValidaciÃƒÆ’Ã‚Â³n fallida', 422, ['errors' => $errs]);
            }
            Sec::assertReadAccessForCandidate((string)$candidateId, $authUser);
            $st = $pdo->prepare('SELECT id, candidate_id, skill, created_at FROM ' . T('candidate_skills') . ' WHERE candidate_id = ? ORDER BY created_at DESC');
            $st->execute([$candidateId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            Res::success('OK', ['items' => $rows]);
            break;
        }
        case 'POST': {
            $payload = Request::json();
            ['ok' => $ok, 'errors' => $errs] = Val::validate($payload, [
                'candidate_id' => 'required|string:1,36|regex:/^cnd-\d+$/',
                'skill'        => 'required|string:1,255'
            ]);
            if (!$ok) {
                Res::error('ValidaciÃƒÆ’Ã‚Â³n fallida', 422, ['errors' => $errs]);
            }
            Sec::assertWriteAccessForCandidate((string)$payload['candidate_id'], $authUser);
            $st = $pdo->prepare('INSERT INTO ' . T('candidate_skills') . ' (id, candidate_id, skill, created_at) VALUES (UUID(), ?, ?, NOW())');
            $st->execute([
                $payload['candidate_id'],
                trim($payload['skill'])
            ]);
            Res::success('Skill agregado', null, 201);
            break;
        }
        default:
            Res::error('MÃƒÆ’Ã‚Â©todo no permitido', 405);
    }
} catch (\Throwable $e) {
    Res::exception($e);
}


