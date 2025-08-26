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
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

error_log("=== INICIANDO chatbot_decision_tree.php ===");
 

use Utils\Database;

error_log("DespuÃƒÂ©s de require bootstrap y use Database");

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

if ($method === 'GET') {
    try {
        // Devuelve el ÃƒÂ¡rbol de decisiÃƒÂ³n completo (nodos + opciones)
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
        error_log("Antes de json_encode");

        $responseData = ['nodes' => $nodes, 'options' => $options];
        $jsonOutput = json_encode($responseData);

        // === VERIFICACIÃƒâ€œN CLAVE ===
        if ($jsonOutput === false) {
            // json_encode fallÃƒÂ³
            $jsonError = json_last_error_msg();
            error_log("Error en chatbot_decision_tree.php al codificar JSON: " . $jsonError);
            http_response_code(500);
            // Asegurar salida JSON vÃƒÂ¡lida incluso en error interno
            if (!headers_sent()) {
                // Verificar si Content-Type ya estÃƒÂ¡ establecido
                $headers_list = headers_list();
                $contentTypeSet = false;
                foreach ($headers_list as $header) {
                    if (stripos($header, 'Content-Type:') === 0) {
                        $contentTypeSet = true;
                        break;
                    }
                }
                if (!$contentTypeSet) {
                    header('Content-Type: application/json; charset=utf-8');
                }
            }
            
            echo json_encode(['error' => 'JSON Encoding Failed', 'details' => $jsonError]);
            return; // Salir inmediatamente
        }
        error_log("chatbot_decision_tree.php: About to echo JSON. Length: " . strlen($jsonOutput));
        echo $jsonOutput;
        error_log("chatbot_decision_tree.php: JSON echoed. Script ending normally."); // <-- LOG DESPUÃƒâ€°S DE ECHO

    } catch (Exception $e) {
        error_log("ExcepciÃƒÂ³n en chatbot_decision_tree.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
        error_log("chatbot_decision_tree.php: ExcepciÃƒÂ³n capturada y manejada: " . $e->getMessage());
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'MÃƒÂ©todo no permitido']);
}
error_log("=== FINALIZANDO chatbot_decision_tree.php ===");

?>

