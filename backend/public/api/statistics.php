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

// cookie HttpOnly obligatoria

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');

use Utils\ResponseHelper as Res;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        Res::error('MÃƒÆ’Ã‚Â©todo no permitido', 405);
    }
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo) {
        throw new Exception('No hay conexiÃƒÆ’Ã‚Â³n PDO');
    }
    $totalUsers = $pdo->query('SELECT COUNT(*) FROM bt_users')->fetchColumn();
    $totalJobs = $pdo->query('SELECT COUNT(*) FROM bt_jobs')->fetchColumn();
    $totalApplications = $pdo->query('SELECT COUNT(*) FROM bt_applications')->fetchColumn();
    $stats = [
    'total_users' => (int)$totalUsers,
    'total_jobs' => (int)$totalJobs,
    'total_applications' => (int)$totalApplications
    ];
    Res::success('OK', $stats);
} catch (Throwable $e) {
    http_response_code(500);
    Res::error('Error', 500);
}


