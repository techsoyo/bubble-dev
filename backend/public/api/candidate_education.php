<?php declare(strict_types=1);
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

// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡ticamente en bootstrap.php

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
                'candidate_id' => 'required|int'
            ]);
            if (!$ok) {
                Res::error('ValidaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n fallida', 422, ['errors' => $errs]);
            }

            Sec::assertReadAccessForCandidate((int)$candidateId, $authUser);

            $st = $pdo->prepare('SELECT id, candidate_id, degree, field_of_study, institution, start_date, end_date, education_level, created_at
         FROM ' . T('candidate_education') . '
         WHERE candidate_id = ?
         ORDER BY COALESCE(end_date, start_date) DESC, created_at DESC');
            $st->execute([$candidateId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            Res::success('OK', ['items' => $rows]);
            break;
        }

        case 'POST': {
            $payload = Request::json();

            ['ok' => $ok, 'errors' => $errs] = Val::validate($payload, [
                'candidate_id'     => 'required|int',
                'degree'           => 'required|string:1,255',
                'field_of_study'   => 'string:0,255',
                'institution'      => 'required|string:1,255',
                'start_date'       => 'required|date',
                'end_date'         => 'string:0,10',
                'education_level'  => 'string:0,100'
            ]);
            if (!$ok) {
                Res::error('ValidaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n fallida', 422, ['errors' => $errs]);
            }
            if (!empty($payload['end_date'])) {
                \Utils\Validator::date($payload['end_date'], 'end_date');
            }
            Sec::assertWriteAccessForCandidate((string)$payload['candidate_id'], $authUser);
            $st = $pdo->prepare('INSERT INTO ' . T('candidate_education') . '
        (id, candidate_id, degree, field_of_study, institution, start_date, end_date, education_level, created_at)
        VALUES ( ?, ?, ?, ?, ?, ?, ?, NOW())');
            $st->execute([
                $payload['candidate_id'],
                trim($payload['degree']),
                isset($payload['field_of_study']) ? trim($payload['field_of_study']) : null,
                trim($payload['institution']),
                $payload['start_date'],
                $payload['end_date'] ?? null,
                $payload['education_level'] ?? null
            ]);
            Res::success('Creado', null, 201);
            break;
        }

            // Si luego quieres PUT/DELETE, lo aÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â±adimos con las mismas validaciones

        default:
            Res::error('MÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©todo no permitido', 405);
    }
} catch (\Throwable $e) {
    Res::exception($e);
}


