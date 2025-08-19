<?php

require_once __DIR__ . '/../bootstrap.php';
// preflight(); // ELIMINADO: Preflight se maneja automáticamente en bootstrap.php
// sendCors(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'Método no permitido', 'data' => null]);
        exit;
    }
    $db = pdo();
    $jobId = $_GET['jobId'] ?? null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;
    if ($jobId) {
        $st = $db->prepare('SELECT id, job_id, requirement FROM bt_job_requirements WHERE job_id = ? ORDER BY id ASC LIMIT ? OFFSET ?');
        $st->execute([$jobId, $limit, $offset]);
        $list = $st->fetchAll();
    } else {
        $st = $db->prepare('SELECT id, job_id, requirement FROM bt_job_requirements ORDER BY job_id ASC, id ASC LIMIT ? OFFSET ?');
        $st->execute([$limit, $offset]);
        $list = $st->fetchAll();
    }
    echo json_encode(['ok' => true, 'message' => 'OK', 'data' => $list]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}
