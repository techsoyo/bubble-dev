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
            $st = $pdo->prepare('SELECT id, candidate_id, certification_name, issuer, issue_date, expiry_date, created_at
        FROM ' . T('candidate_certifications') . '
        WHERE candidate_id = ?
        ORDER BY issue_date DESC, created_at DESC');
            $st->execute([$candidateId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            Res::success('OK', ['items' => $rows]);
            break;
        }
        case 'POST': {
            $payload = Request::json();
            ['ok' => $ok, 'errors' => $errs] = Val::validate($payload, [
                'candidate_id'        => 'required|string:1,36|regex:/^cnd-\d+$/',
                'certification_name'  => 'required|string:1,255',
                'issuer'              => 'required|string:1,255',
                'issue_date'          => 'required|date',
                'expiry_date'         => 'string:0,10' // opcional
            ]);
            if (!$ok) {
                Res::error('ValidaciÃƒÆ’Ã‚Â³n fallida', 422, ['errors' => $errs]);
            }
            Sec::assertWriteAccessForCandidate((string)$payload['candidate_id'], $authUser);
            $st = $pdo->prepare('INSERT INTO ' . T('candidate_certifications') . '
        (id, candidate_id, certification_name, issuer, issue_date, expiry_date, created_at)
        VALUES (UUID(), ?, ?, ?, ?, ?, NOW())');
            $st->execute([
                $payload['candidate_id'],
                trim($payload['certification_name']),
                trim($payload['issuer']),
                $payload['issue_date'],
                $payload['expiry_date'] ?? null
            ]);
            Res::success('CertificaciÃƒÆ’Ã‚Â³n agregada', null, 201);
            break;
        }
        default:
            Res::error('MÃƒÆ’Ã‚Â©todo no permitido', 405);
    }
} catch (\Throwable $e) {
    Res::exception($e);
}


