<?php

require_once __DIR__ . '/bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÂ¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÂ¡ticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');

use Utils\ResponseHelper as Res;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        Res::error('MÃƒÂ©todo no permitido', 405);
    }
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo) {
        throw new Exception('No hay conexiÃƒÂ³n PDO');
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

