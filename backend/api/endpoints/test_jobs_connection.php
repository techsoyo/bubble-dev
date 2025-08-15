<?php

require_once __DIR__ . '/../bootstrap.php';
if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}
// preflightHandle(); // ELIMINADO: Preflight se maneja automáticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../src/Utils/ResponseHelper.php';

use Utils\ResponseHelper as Res;

try {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo) {
        throw new Exception('No hay conexión PDO');
    }
    $st = $pdo->prepare('SELECT * FROM bt_jobs LIMIT 5');
    $st->execute();
    $jobs = $st->fetchAll(PDO::FETCH_ASSOC);
    Res::success('Conexión exitosa', ['jobs' => $jobs]);
} catch (Throwable $e) {
    http_response_code(500);
    Res::error('Error de conexión', 500);
}
