<?php

// backend/api/endpoints/chatbot_analytics.php
require_once __DIR__ . '/bootstrap.php';

use Utils\Database;

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        // Listar analíticas (opcional: filtrar por node_id, option_id, event_name)
        $where = [];
        $params = [];
        if (isset($_GET['node_id'])) {
            $where[] = 'node_id = ?';
            $params[] = $_GET['node_id'];
        }
        if (isset($_GET['option_id'])) {
            $where[] = 'option_id = ?';
            $params[] = $_GET['option_id'];
        }
        if (isset($_GET['event_name'])) {
            $where[] = 'event_name = ?';
            $params[] = $_GET['event_name'];
        }
        $sql = 'SELECT * FROM bt_chatbot_analytics';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY timestamp DESC LIMIT 100';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($analytics);
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare('INSERT INTO bt_chatbot_analytics (conversation_id, event_name, event_data, node_id, option_id, user_ip, user_agent, session_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['conversation_id'],
            $data['event_name'],
            json_encode($data['event_data'] ?? []),
            $data['node_id'] ?? null,
            $data['option_id'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $data['session_id'] ?? null
        ]);
        echo json_encode(['success' => true]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}
