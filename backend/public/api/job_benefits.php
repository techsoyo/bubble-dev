<?php

require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'MÃƒÂ©todo no permitido', 'data' => null]);
        exit;
    }

    // Usar Database singleton para consistencia
    $database = \Utils\Database::getInstance();
    $db = $database->getConnection();
    $jobId = $_GET['jobId'] ?? null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;
    if ($jobId) {
        $st = $db->prepare('SELECT id, job_id, benefit FROM bt_job_benefits WHERE job_id = ? ORDER BY id ASC LIMIT ? OFFSET ?');
        $st->bindValue(1, $jobId, PDO::PARAM_INT);
        $st->bindValue(2, $limit, PDO::PARAM_INT);
        $st->bindValue(3, $offset, PDO::PARAM_INT);
        $st->execute();
        $list = $st->fetchAll();
    } else {
        $st = $db->prepare('SELECT id, job_id, benefit FROM bt_job_benefits ORDER BY job_id ASC, id ASC LIMIT ? OFFSET ?');
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->bindValue(2, $offset, PDO::PARAM_INT);
        $st->execute();
        $list = $st->fetchAll();
    }
    echo json_encode(['ok' => true, 'message' => 'OK', 'data' => $list]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}

