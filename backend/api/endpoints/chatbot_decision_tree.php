<?php

// backend/api/endpoints/chatbot_decision_tree.php
require_once __DIR__ . '/../../config/bootstrap.php';

use Utils\Database;

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        // Devuelve el árbol de decisión completo (nodos + opciones)
        $stmt1 = $db->query('SELECT * FROM bt_chatbot_nodes WHERE is_active = 1');
        $nodes = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        $stmt2 = $db->query('SELECT * FROM bt_chatbot_options WHERE is_active = 1');
        $options = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['nodes' => $nodes, 'options' => $options]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}
