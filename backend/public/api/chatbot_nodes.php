<?php


require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// cookie HttpOnly obligatoria

// Proteger solo mÃ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    // double-submit cookie
}

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
// backend/api/endpoints/chatbot_nodes.php
use Utils\Database;

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        // Listar todos los nodos o uno por id
        if (isset($_GET['id'])) {
            $stmt = $db->prepare('SELECT * FROM bt_chatbot_nodes WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $node = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($node);
        } else {
            $stmt = $db->query('SELECT * FROM bt_chatbot_nodes');
            $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($nodes);
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare('INSERT INTO bt_chatbot_nodes (id, type, content, metadata, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
          $data['id'],
          $data['type'],
          $data['content'],
          json_encode($data['metadata'] ?? []),
          $data['is_active'] ?? 1,
          $data['created_by'] ?? 'admin'
        ]);
        echo json_encode(['success' => true]);
        break;
    case 'PUT':
        parse_str(file_get_contents('php://input'), $data);
        $stmt = $db->prepare('UPDATE bt_chatbot_nodes SET type=?, content=?, metadata=?, is_active=?, created_by=? WHERE id=?');
        $stmt->execute([
          $data['type'],
          $data['content'],
          json_encode($data['metadata'] ?? []),
          $data['is_active'] ?? 1,
          $data['created_by'] ?? 'admin',
          $data['id']
        ]);
        echo json_encode(['success' => true]);
        break;
    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $db->prepare('DELETE FROM bt_chatbot_nodes WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID requerido']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'MÃƒÂ©todo no permitido']);
}


