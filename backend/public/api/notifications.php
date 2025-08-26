<?php



require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria
use Security\CsrfMiddleware;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// preflight(); // ELIMINADO: Preflight se maneja automÃƒÂ¡ticamente en bootstrap.php
// sendCors(); // ELIMINADO: CORS se configura automÃƒÂ¡ticamente en bootstrap.php
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
        // En producción, insertar en BD real
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            http_response_code(501);
            echo json_encode(['error' => 'Notification creation not implemented']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        $notification = [
            'id' => 2001, // ID fijo para desarrollo
            'title' => $input['title'] ?? 'Nueva notificación',
            'message' => $input['message'] ?? 'Mensaje de notificación',
            'type' => $input['type'] ?? 'info',
            'read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ];

        echo json_encode(['ok' => true, 'message' => 'NotificaciÃƒÂ³n creada exitosamente', 'data' => $notification]);
        exit;
    }

    // MÃƒÂ©todo no permitido
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'MÃƒÂ©todo no permitido', 'data' => null]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}
