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
    $candidateId = $_GET['candidateId'] ?? null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;
    if ($candidateId) {
        $st = $db->prepare('SELECT id, candidate_id, message, type, created_at FROM bt_notifications WHERE candidate_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $st->execute([$candidateId, $limit, $offset]);
        $list = $st->fetchAll();
    } else {
        $st = $db->prepare('SELECT id, candidate_id, message, type, created_at FROM bt_notifications ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $st->execute([$limit, $offset]);
        $list = $st->fetchAll();
    }
    echo json_encode(['ok' => true, 'message' => 'OK', 'data' => $list]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}
