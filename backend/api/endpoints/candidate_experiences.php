<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 2); // endpoints → api → backend
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

require_once __DIR__ . '/../../src/Utils/ResponseHelper.php';
require_once __DIR__ . '/../../src/Utils/Validator.php';
require_once __DIR__ . '/../../src/Utils/Request.php';
require_once __DIR__ . '/../../src/Utils/JWT.php';
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
                Res::error('Validación fallida', 422, ['errors' => $errs]);
            }
            Sec::assertReadAccessForCandidate((string)$candidateId, $authUser);
            $st = $pdo->prepare('SELECT id, candidate_id, job_title, company_name, start_date, end_date, description, created_at FROM ' . T('candidate_experiences') . ' WHERE candidate_id = ? ORDER BY COALESCE(end_date, start_date) DESC, created_at DESC');
            $st->execute([$candidateId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            Res::success('OK', ['items' => $rows]);
            break;
        }
        case 'POST': {
            $payload = Request::json();
            ['ok' => $ok, 'errors' => $errs] = Val::validate($payload, [
                'candidate_id'  => 'required|string:1,36|regex:/^cnd-\d+$/',
                'job_title'     => 'required|string:1,255',
                'company_name'  => 'required|string:1,255',
                'start_date'    => 'required|date',
                'end_date'      => 'string:0,10',
                'description'   => 'string:0,1000'
            ]);
            if (!$ok) {
                Res::error('Validación fallida', 422, ['errors' => $errs]);
            }
            Sec::assertWriteAccessForCandidate((string)$payload['candidate_id'], $authUser);
            $st = $pdo->prepare('INSERT INTO ' . T('candidate_experiences') . ' (id, candidate_id, job_title, company_name, start_date, end_date, description, created_at) VALUES (UUID(), ?, ?, ?, ?, ?, ?, NOW())');
            $st->execute([
                $payload['candidate_id'],
                trim($payload['job_title']),
                trim($payload['company_name']),
                $payload['start_date'],
                $payload['end_date'] ?? null,
                $payload['description'] ?? null
            ]);
            Res::success('Experiencia agregada', null, 201);
            break;
        }
        default:
            Res::error('Método no permitido', 405);
    }
} catch (\Throwable $e) {
    Res::exception($e);
}
