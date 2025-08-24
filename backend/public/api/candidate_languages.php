<?php

declare(strict_types=1);

 

require_once __DIR__ . '/bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÂ¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÂ¡ticamente en bootstrap.php

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
                Res::error('ValidaciÃƒÂ³n fallida', 422, ['errors' => $errs]);
            }

            Sec::assertReadAccessForCandidate((string)$candidateId, $authUser);

            $st = $pdo->prepare('SELECT id, candidate_id, language, proficiency_level, created_at
         FROM ' . T('candidate_languages') . '
         WHERE candidate_id = ?
         ORDER BY created_at DESC');
            $st->execute([$candidateId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            Res::success('OK', ['items' => $rows]);
            break;
        }

        case 'POST': {
            $payload = Request::json();

            ['ok' => $ok, 'errors' => $errs] = Val::validate($payload, [
                'candidate_id'      => 'required|string:1,36|regex:/^cnd-\d+$/',
                'language'          => 'required|string:1,100',
                'proficiency_level' => 'required|string:1,50'
            ]);
            if (!$ok) {
                Res::error('ValidaciÃƒÂ³n fallida', 422, ['errors' => $errs]);
            }

            Sec::assertWriteAccessForCandidate((string)$payload['candidate_id'], $authUser);

            $st = $pdo->prepare('INSERT INTO ' . T('candidate_languages') . '
        (id, candidate_id, language, proficiency_level, created_at)
        VALUES (UUID(), ?, ?, ?, NOW())');
            $st->execute([
                $payload['candidate_id'],
                trim($payload['language']),
                trim($payload['proficiency_level'])
            ]);
            Res::success('Idioma agregado', null, 201);
            break;
        }

        default:
            Res::error('MÃƒÂ©todo no permitido', 405);
    }
} catch (\Throwable $e) {
    Res::error('Error', 500, ['detail' => $e->getMessage()]);
}

