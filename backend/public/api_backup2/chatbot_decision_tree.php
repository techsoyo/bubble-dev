<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

error_log("=== INICIANDO chatbot_decision_tree.php ===");
 

use Utils\Database;

error_log("DespuÃ©s de require bootstrap y use Database");

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

if ($method === 'GET') {
    try {
        // Devuelve el Ã¡rbol de decisiÃ³n completo (nodos + opciones)
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

        // === VERIFICACIÃ“N CLAVE ===
        if ($jsonOutput === false) {
            // json_encode fallÃ³
            $jsonError = json_last_error_msg();
            error_log("Error en chatbot_decision_tree.php al codificar JSON: " . $jsonError);
            http_response_code(500);
            // Asegurar salida JSON vÃ¡lida incluso en error interno
            if (!headers_sent()) {
                // Verificar si Content-Type ya estÃ¡ establecido
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
        error_log("chatbot_decision_tree.php: JSON echoed. Script ending normally."); // <-- LOG DESPUÃ‰S DE ECHO

    } catch (Exception $e) {
        error_log("ExcepciÃ³n en chatbot_decision_tree.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
        error_log("chatbot_decision_tree.php: ExcepciÃ³n capturada y manejada: " . $e->getMessage());
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'MÃ©todo no permitido']);
}
error_log("=== FINALIZANDO chatbot_decision_tree.php ===");

?>
