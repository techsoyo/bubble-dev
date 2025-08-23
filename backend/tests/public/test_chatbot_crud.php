<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 1);             // ajusta salto de nivel según carpeta
$BOOT = $ROOT . '/config/bootstrap.php'; // si estás en /backend/public, sube 1 nivel; si estás en /backend/api, también 1
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

// public/test_chatbot_crud.php
if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// REMOVED: header('Content-Type: application/json'); // Use jsonResponse() instead

try {
    // Conexión a la base de datos
    require_once __DIR__ . '/../config/config.php';

    $host = config('DB_HOST');
    $dbname = config('DB_NAME');
    $username = config('DB_USER');
    $password = config('DB_PASSWORD');

    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Leer nodos y opciones
            $stmt = $pdo->query('SELECT * FROM bt_chatbot_nodes ORDER BY created_at');
            $nodes = $stmt->fetchAll();

            $stmt = $pdo->query('SELECT * FROM bt_chatbot_options ORDER BY order_position');
            $options = $stmt->fetchAll();

            echo json_encode([
              'success' => true,
              'data' => [
                'nodes' => $nodes,
                'options' => $options,
                'total_nodes' => count($nodes),
                'total_options' => count($options)
              ]
            ]);
            break;

        case 'POST':
            // Crear nuevo nodo
            $data = json_decode(file_get_contents('php://input'), true);

            if (isset($data['type']) && $data['type'] === 'node') {
                $stmt = $pdo->prepare('INSERT INTO bt_chatbot_nodes (id, type, content, metadata, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                $result = $stmt->execute([
                  $data['id'],
                  $data['node_type'],
                  $data['content'],
                  json_encode($data['metadata'] ?? []),
                  $data['is_active'] ? 1 : 0,
                  'test_user'
                ]);

                echo json_encode(['success' => $result, 'message' => 'Nodo creado correctamente']);
            } elseif (isset($data['type']) && $data['type'] === 'option') {
                $stmt = $pdo->prepare('INSERT INTO bt_chatbot_options (id, node_id, text, next_node_id, action_type, action_data, order_position, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $result = $stmt->execute([
                  $data['id'],
                  $data['node_id'],
                  $data['text'],
                  $data['next_node_id'],
                  $data['action_type'],
                  json_encode($data['action_data'] ?? []),
                  $data['order_position'],
                  $data['is_active'] ? 1 : 0
                ]);

                echo json_encode(['success' => $result, 'message' => 'Opción creada correctamente']);
            }
            break;

        case 'PUT':
            // Actualizar nodo existente
            $data = json_decode(file_get_contents('php://input'), true);

            if (isset($data['type']) && $data['type'] === 'node') {
                $stmt = $pdo->prepare('UPDATE bt_chatbot_nodes SET type = ?, content = ?, metadata = ?, is_active = ? WHERE id = ?');
                $result = $stmt->execute([
                  $data['node_type'],
                  $data['content'],
                  json_encode($data['metadata'] ?? []),
                  $data['is_active'] ? 1 : 0,
                  $data['id']
                ]);

                echo json_encode(['success' => $result, 'message' => 'Nodo actualizado correctamente']);
            } elseif (isset($data['type']) && $data['type'] === 'option') {
                $stmt = $pdo->prepare('UPDATE bt_chatbot_options SET text = ?, next_node_id = ?, action_type = ?, action_data = ?, order_position = ?, is_active = ? WHERE id = ?');
                $result = $stmt->execute([
                  $data['text'],
                  $data['next_node_id'],
                  $data['action_type'],
                  json_encode($data['action_data'] ?? []),
                  $data['order_position'],
                  $data['is_active'] ? 1 : 0,
                  $data['id']
                ]);

                echo json_encode(['success' => $result, 'message' => 'Opción actualizada correctamente']);
            }
            break;

        case 'DELETE':
            // Eliminar nodo o opción
            $nodeId = $_GET['node_id'] ?? null;
            $optionId = $_GET['option_id'] ?? null;

            if ($nodeId) {
                // Eliminar nodo y sus opciones asociadas
                $stmt = $pdo->prepare('DELETE FROM bt_chatbot_options WHERE node_id = ?');
                $stmt->execute([$nodeId]);

                $stmt = $pdo->prepare('DELETE FROM bt_chatbot_nodes WHERE id = ?');
                $result = $stmt->execute([$nodeId]);

                echo json_encode(['success' => $result, 'message' => 'Nodo eliminado correctamente']);
            } elseif ($optionId) {
                // Eliminar opción
                $stmt = $pdo->prepare('DELETE FROM bt_chatbot_options WHERE id = ?');
                $result = $stmt->execute([$optionId]);

                echo json_encode(['success' => $result, 'message' => 'Opción eliminada correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Método no soportado']);
    }
} catch (Exception $e) {
    echo json_encode([
      'success' => false,
      'error' => $e->getMessage()
    ]);
}
