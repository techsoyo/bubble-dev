<?php

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'Método no permitido', 'data' => null]);
        exit;
    }

    // Usar Database singleton para consistencia
    require_once __DIR__ . '/../../src/Utils/Database.php';
    $database = \Utils\Database::getInstance();
    $db = $database->getConnection();
    $jobId = $_GET['jobId'] ?? null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;
    if ($jobId) {
        $st = $db->prepare('SELECT id, job_id, benefit FROM bt_job_benefits WHERE job_id = ? ORDER BY id ASC LIMIT ? OFFSET ?');
        $st->execute([$jobId, $limit, $offset]);
        $list = $st->fetchAll();
    } else {
        $st = $db->prepare('SELECT id, job_id, benefit FROM bt_job_benefits ORDER BY job_id ASC, id ASC LIMIT ? OFFSET ?');
        $st->execute([$limit, $offset]);
        $list = $st->fetchAll();
    }
    jsonResponse(200, ['ok' => true, 'message' => 'OK', 'data' => $list]);
} catch (Throwable $e) {
    jsonResponse(500, ['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}
