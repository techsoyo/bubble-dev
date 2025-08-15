<?php

require_once __DIR__ . '/../bootstrap.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        jsonResponse(405, ['ok' => false, 'message' => 'Método no permitido', 'data' => null]);
    }
    $db = pdo();
    $jobId = $_GET['jobId'] ?? null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;
    if ($jobId) {
        $st = $db->prepare('SELECT id, job_id, skill FROM bt_job_skills WHERE job_id = ? ORDER BY id ASC LIMIT ? OFFSET ?');
        $st->execute([$jobId, $limit, $offset]);
        $list = $st->fetchAll();
    } else {
        $st = $db->prepare('SELECT id, job_id, skill FROM bt_job_skills ORDER BY job_id ASC, id ASC LIMIT ? OFFSET ?');
        $st->execute([$limit, $offset]);
        $list = $st->fetchAll();
    }
    jsonResponse(200, ['ok' => true, 'message' => 'OK', 'data' => $list]);
} catch (Throwable $e) {
    jsonResponse(500, ['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}
