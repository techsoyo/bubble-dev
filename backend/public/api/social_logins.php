<?php

declare(strict_types=1);




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

// preflightHandle(); // ELIMINADO: Preflight se maneja automí¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automí¡ticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');

use Utils\ResponseHelper as Res;

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
try {
    $pdo = db();
    switch ($method) {
        case 'GET':
            if (isset($_GET['candidateId'])) {
                $candidateId = $_GET['candidateId'];
                $st = $pdo->prepare('SELECT * FROM ' . T('social_logins') . ' WHERE candidate_id = ? ORDER BY id ASC');
                $st->execute([$candidateId]);
                $list = $st->fetchAll(PDO::FETCH_ASSOC);
                Res::success('OK', ['items' => $list]);
            } else {
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
                $offset = ($page - 1) * $limit;
                $st = $pdo->prepare('SELECT * FROM ' . T('social_logins') . ' ORDER BY id ASC LIMIT ? OFFSET ?');
                $st->bindValue(1, $limit, PDO::PARAM_INT);
                $st->bindValue(2, $offset, PDO::PARAM_INT);
                $st->execute();
                $list = $st->fetchAll(PDO::FETCH_ASSOC);
                Res::success('OK', ['items' => $list, 'page' => $page, 'limit' => $limit]);
            }
            break;
        default:
            Res::error('Método no permitido', 405);
    }
} catch (Throwable $e) {
    Res::exception($e);
}
