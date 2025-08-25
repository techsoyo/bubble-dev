<?php

require_once __DIR__ . '/bootstrap.php';
// preflight(); // ELIMINADO: Preflight se maneja automÃ¡ticamente en bootstrap.php
// sendCors(); // ELIMINADO: CORS se configura automÃ¡ticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');

try {
    // Manejo de GET para listar notificaciones
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $db = getDbConnection();
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
        exit;
    }

    // Manejo de POST para crear notificaciones
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        $notification = [
            'id' => rand(1000, 9999),
            'title' => $input['title'] ?? 'Nueva notificaciÃ³n',
            'message' => $input['message'] ?? 'Mensaje de notificaciÃ³n',
            'type' => $input['type'] ?? 'info',
            'read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ];

        echo json_encode(['ok' => true, 'message' => 'NotificaciÃ³n creada exitosamente', 'data' => $notification]);
        exit;
    }

    // MÃ©todo no permitido
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'MÃ©todo no permitido', 'data' => null]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}

