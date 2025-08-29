<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';
\Middleware\JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    \Middleware\CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// Configurar headers seguros
header('Content-Type: application/json; charset=utf-8');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $db = getDbConnection();

    if ($method === 'GET') {
        // ✅ FIXED: Usar prepared statements seguros
        $stmt1 = $db->prepare('SELECT * FROM bt_chatbot_nodes WHERE is_active = ?');
        $stmt1->execute([1]);
        $nodes = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $db->prepare('SELECT * FROM bt_chatbot_options WHERE is_active = ?');
        $stmt2->execute([1]);
        $options = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Parsear metadata y action_data como JSON
        foreach ($nodes as &$node) {
            $node['metadata'] = json_decode($node['metadata'] ?? '{}', true) ?: [];
        }
        foreach ($options as &$option) {
            $option['action_data'] = json_decode($option['action_data'] ?? '{}', true) ?: [];
        }

        $responseData = ['nodes' => $nodes, 'options' => $options];
        $jsonOutput = json_encode($responseData);

        // Verificación de JSON encoding
        if ($jsonOutput === false) {
            $jsonError = json_last_error_msg();
            error_log("Error en chatbot_decision_tree.php al codificar JSON: " . $jsonError);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'JSON Encoding Failed',
                'details' => $jsonError,
                'error_code' => 'JSON_ENCODING_ERROR'
            ]);
            exit;
        }

        echo $jsonOutput;
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido',
            'error_code' => 'METHOD_NOT_ALLOWED'
        ]);
    }
} catch (Exception $e) {
    error_log("Excepción en chatbot_decision_tree.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => 'Error interno del servidor',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
