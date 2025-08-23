<?php

// backend/api/endpoints/chatbot_options.php
require_once __DIR__ . '/bootstrap.php';

use Utils\Database;

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        // Listar todas las opciones o una por id
        if (isset($_GET['id'])) {
            $stmt = $db->prepare('SELECT * FROM bt_chatbot_options WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $option = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($option);
        } elseif (isset($_GET['node_id'])) {
            $stmt = $db->prepare('SELECT * FROM bt_chatbot_options WHERE node_id = ? ORDER BY order_position');
            $stmt->execute([$_GET['node_id']]);
            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($options);
        } else {
            $stmt = $db->query('SELECT * FROM bt_chatbot_options');
            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($options);
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare('INSERT INTO bt_chatbot_options (id, node_id, text, next_node_id, action_type, action_data, order_position, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['id'],
            $data['node_id'],
            $data['text'],
            $data['next_node_id'],
            $data['action_type'],
            json_encode($data['action_data'] ?? []),
            $data['order_position'] ?? 1,
            $data['is_active'] ?? 1
        ]);
        echo json_encode(['success' => true]);
        break;
    case 'PUT':
        parse_str(file_get_contents('php://input'), $data);
        $stmt = $db->prepare('UPDATE bt_chatbot_options SET node_id=?, text=?, next_node_id=?, action_type=?, action_data=?, order_position=?, is_active=? WHERE id=?');
        $stmt->execute([
            $data['node_id'],
            $data['text'],
            $data['next_node_id'],
            $data['action_type'],
            json_encode($data['action_data'] ?? []),
            $data['order_position'] ?? 1,
            $data['is_active'] ?? 1,
            $data['id']
        ]);
        echo json_encode(['success' => true]);
        break;
    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $db->prepare('DELETE FROM bt_chatbot_options WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID requerido']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}
