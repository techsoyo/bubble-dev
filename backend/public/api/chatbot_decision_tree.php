<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

use Utils\Database;

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        try {
            // Devuelve el árbol de decisión completo (nodos + opciones)
            $stmt1 = $db->query('SELECT * FROM bt_chatbot_nodes WHERE is_active = 1');
            $nodes = $stmt1->fetchAll(PDO::FETCH_ASSOC);
            $stmt2 = $db->query('SELECT * FROM bt_chatbot_options WHERE is_active = 1');
            $options = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            // Parsear metadata y action_data como JSON
            foreach ($nodes as &$node) {
                $node['metadata'] = json_decode($node['metadata'], true) ?? [];
            }
            foreach ($options as &$option) {
                $option['action_data'] = json_decode($option['action_data'], true) ?? [];
            }

            echo json_encode(['nodes' => $nodes, 'options' => $options]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}
