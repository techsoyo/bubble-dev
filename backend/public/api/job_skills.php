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
    // Mock data ya que la tabla real puede no existir o tener estructura diferente  
    $mockData = [
        ['id' => 1, 'job_id' => 'job-101', 'skill' => 'JavaScript'],
        ['id' => 2, 'job_id' => 'job-101', 'skill' => 'React'],
        ['id' => 3, 'job_id' => 'job-102', 'skill' => 'PHP'],
        ['id' => 4, 'job_id' => 'job-103', 'skill' => 'Python'],
        ['id' => 5, 'job_id' => 'job-104', 'skill' => 'Node.js'],
        ['id' => 6, 'job_id' => 'job-105', 'skill' => 'MySQL']
    ];

    if ($jobId) {
        $list = array_filter($mockData, function ($item) use ($jobId) {
            return $item['job_id'] == $jobId;
        });
        $list = array_values($list);
    } else {
        $list = array_slice($mockData, $offset, $limit);
    }
    echo json_encode(['ok' => true, 'message' => 'OK', 'data' => $list]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}

