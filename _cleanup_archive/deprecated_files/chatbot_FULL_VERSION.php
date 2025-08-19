<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__);             // api -> backend/
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

use Controllers\ChatbotController;

try {
    $controller = new ChatbotController();
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];

    // Extraer la ruta después de /api/chatbot.php
    $path = parse_url($uri, PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));

    // Buscar el índice de 'chatbot.php' para obtener los parámetros después
    $chatbotIndex = array_search('chatbot.php', $pathParts);
    $action = isset($pathParts[$chatbotIndex + 1]) ? $pathParts[$chatbotIndex + 1] : '';
    $param = isset($pathParts[$chatbotIndex + 2]) ? $pathParts[$chatbotIndex + 2] : '';

    switch ($method) {
        case 'GET':
            switch ($action) {
                case '':
                case 'data':
                    // GET /api/chatbot.php/data - Obtener configuración completa del chatbot
                    echo $controller->getChatbotData();
                    break;

                case 'node':
                    if (empty($param)) {
                        echo $controller->getChatbotData();
                    } else {
                        // GET /api/chatbot.php/node/{id} - Obtener nodo específico
                        echo $controller->getNode($param);
                    }
                    break;

                case 'analytics':
                    // GET /api/chatbot.php/analytics - Obtener estadísticas
                    $dateFrom = $_GET['date_from'] ?? null;
                    $dateTo = $_GET['date_to'] ?? null;
                    echo $controller->getAnalytics($dateFrom, $dateTo);
                    break;

                default:
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Endpoint no encontrado',
                        'code' => 404
                    ]);
                    break;
            }
            break;

        case 'POST':
            switch ($action) {
                case 'interact':
                case 'interaction':
                    // POST /api/chatbot.php/interaction - Procesar interacción del usuario
                    echo $controller->processInteraction();
                    break;

                case 'analytics':
                case 'track':
                    // POST /api/chatbot.php/analytics - Registrar evento de analytics
                    echo $controller->trackAnalyticsEvent();
                    break;

                case 'node':
                    // POST /api/chatbot.php/node - Crear nuevo nodo (admin)
                    echo $controller->createNode();
                    break;

                case 'option':
                    // POST /api/chatbot.php/option - Crear nueva opción (admin)
                    echo $controller->createOption();
                    break;

                default:
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Endpoint no encontrado',
                        'code' => 404
                    ]);
                    break;
            }
            break;

        case 'PUT':
            // Endpoints para actualización (admin)
            switch ($action) {
                case 'node':
                    if (empty($param)) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'ID de nodo requerido',
                            'code' => 400
                        ]);
                    } else {
                        // PUT /api/chatbot.php/node/{id} - Actualizar nodo
                        echo $controller->updateNode($param);
                    }
                    break;

                case 'option':
                    if (empty($param)) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'ID de opción requerido',
                            'code' => 400
                        ]);
                    } else {
                        // PUT /api/chatbot.php/option/{id} - Actualizar opción
                        echo $controller->updateOption($param);
                    }
                    break;

                default:
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Endpoint no encontrado',
                        'code' => 404
                    ]);
                    break;
            }
            break;

        case 'DELETE':
            // Endpoints para eliminación (admin)
            switch ($action) {
                case 'node':
                    if (empty($param)) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'ID de nodo requerido',
                            'code' => 400
                        ]);
                    } else {
                        // DELETE /api/chatbot.php/node/{id} - Eliminar nodo
                        echo $controller->deleteNode($param);
                    }
                    break;

                case 'option':
                    if (empty($param)) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'ID de opción requerido',
                            'code' => 400
                        ]);
                    } else {
                        // DELETE /api/chatbot.php/option/{id} - Eliminar opción
                        echo $controller->deleteOption($param);
                    }
                    break;

                default:
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Endpoint no encontrado',
                        'code' => 404
                    ]);
                    break;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Método no permitido',
                'code' => 405
            ]);
            break;
    }
} catch (Exception $e) {
    error_log('Error en API chatbot: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'code' => 500,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
