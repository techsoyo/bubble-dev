<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';
\Middleware\JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    \Middleware\CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// Configurar headers seguros
header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido',
            'error_code' => 'METHOD_NOT_ALLOWED'
        ]);
        exit;
    }

    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception('No hay conexión PDO');
    }

    // ✅ FIXED: Usar prepared statements seguros
    $stmtUsers = $pdo->prepare('SELECT COUNT(*) as total FROM bt_users');
    $stmtUsers->execute();
    $totalUsers = $stmtUsers->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmtJobs = $pdo->prepare('SELECT COUNT(*) as total FROM bt_jobs');
    $stmtJobs->execute();
    $totalJobs = $stmtJobs->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmtApplications = $pdo->prepare('SELECT COUNT(*) as total FROM bt_applications');
    $stmtApplications->execute();
    $totalApplications = $stmtApplications->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stats = [
        'total_users' => (int)$totalUsers,
        'total_jobs' => (int)$totalJobs,
        'total_applications' => (int)$totalApplications
    ];

    echo json_encode([
        'success' => true,
        'message' => 'OK',
        'data' => $stats
    ]);
} catch (Throwable $e) {
    error_log("Statistics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
