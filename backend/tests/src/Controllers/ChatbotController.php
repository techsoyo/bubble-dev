<?php

// backend/src/Controllers/ChatbotController.php

namespace Controllers;

use Exception;
use Models\ChatbotAnalytics;
use Models\ChatbotNode;
use Models\ChatbotOption;
use Utils\InputValidator;
use Utils\ResponseHandler;

class ChatbotController
{
    private $chatbotNode;
    private $chatbotOption;
    private $chatbotAnalytics;
    private $responseHandler;
    private $inputValidator;

    public function __construct()
    {
        $this->chatbotNode = new ChatbotNode();
        $this->chatbotOption = new ChatbotOption();
        $this->chatbotAnalytics = new ChatbotAnalytics();
        $this->responseHandler = new ResponseHandler();
        $this->inputValidator = new InputValidator();
    }

    /**
     * Obtener la configuración completa del chatbot
     */
    public function getChatbotData()
    {
        try {
            // Obtener todos los nodos activos
            $nodes = $this->chatbotNode->getAllActive();

            // Obtener todas las opciones activas
            $options = $this->chatbotOption->getAllActive();

            // Estructurar la respuesta
            $chatbotData = [
                'nodes' => $nodes,
                'options' => $options,
                'config' => [
                    'default_node' => 'welcome',
                    'typing_speed' => 50,
                    'max_conversation_time' => 3600, // 1 hora
                    'analytics_enabled' => true
                ]
            ];

            return $this->responseHandler->success($chatbotData, 'Configuración del chatbot obtenida correctamente');
        } catch (Exception $e) {
            error_log('Error al obtener datos del chatbot: ' . $e->getMessage());
            return $this->responseHandler->error('Error interno del servidor', 500);
        }
    }

    /**
     * Obtener un nodo específico por ID
     */
    public function getNode($nodeId)
    {
        try {
            // Validar entrada
            if (!$this->inputValidator->validateRequired($nodeId)) {
                return $this->responseHandler->error('ID de nodo requerido', 400);
            }

            if (!$this->inputValidator->validateAlphanumeric($nodeId)) {
                return $this->responseHandler->error('ID de nodo inválido', 400);
            }

            // Obtener el nodo
            $node = $this->chatbotNode->getById($nodeId);

            if (!$node) {
                return $this->responseHandler->error('Nodo no encontrado', 404);
            }

            // Obtener las opciones del nodo
            $options = $this->chatbotOption->getByNodeId($nodeId);

            $response = [
                'node' => $node,
                'options' => $options
            ];

            return $this->responseHandler->success($response, 'Nodo obtenido correctamente');
        } catch (Exception $e) {
            error_log("Error al obtener nodo $nodeId: " . $e->getMessage());
            return $this->responseHandler->error('Error interno del servidor', 500);
        }
    }

    /**
     * Procesar una interacción del usuario (selección de opción)
     */
    public function processInteraction()
    {
        try {
            // Obtener datos de la petición
            $input = json_decode(file_get_contents('php://input'), true);

            // Validar datos requeridos
            $requiredFields = ['conversation_id', 'option_id', 'current_node_id'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    return $this->responseHandler->error("Campo requerido: $field", 400);
                }
            }

            $conversationId = $input['conversation_id'];
            $optionId = $input['option_id'];
            $currentNodeId = $input['current_node_id'];

            // Validar formato de IDs
            if (
                !$this->inputValidator->validateAlphanumeric($optionId) ||
                !$this->inputValidator->validateAlphanumeric($currentNodeId)
            ) {
                return $this->responseHandler->error('Formato de ID inválido', 400);
            }

            // Obtener la opción seleccionada
            $option = $this->chatbotOption->getById($optionId);

            if (!$option) {
                return $this->responseHandler->error('Opción no encontrada', 404);
            }

            // Verificar que la opción pertenece al nodo actual
            if ($option['node_id'] !== $currentNodeId) {
                return $this->responseHandler->error('Opción no válida para el nodo actual', 400);
            }

            // Registrar analytics de la interacción
            $this->trackInteraction($conversationId, $optionId, $currentNodeId, $input);

            // Procesar la acción según el tipo
            $result = $this->processAction($option, $conversationId);

            return $this->responseHandler->success($result, 'Interacción procesada correctamente');
        } catch (Exception $e) {
            error_log('Error al procesar interacción: ' . $e->getMessage());
            return $this->responseHandler->error('Error interno del servidor', 500);
        }
    }

    /**
     * Procesar acción según el tipo de opción
     */
    private function processAction($option, $conversationId)
    {
        $actionData = json_decode($option['action_data'], true) ?: [];

        $result = [
            'action_type' => $option['action_type'],
            'next_node_id' => $option['next_node_id'],
            'action_data' => $actionData
        ];

        switch ($option['action_type']) {
            case 'navigate':
                if (isset($actionData['url'])) {
                    // Redirección externa
                    $result['redirect_url'] = $actionData['url'];
                } elseif ($option['next_node_id']) {
                    // Navegación interna - obtener el siguiente nodo
                    $nextNode = $this->chatbotNode->getById($option['next_node_id']);
                    if ($nextNode) {
                        $result['next_node'] = $nextNode;
                        $result['next_options'] = $this->chatbotOption->getByNodeId($option['next_node_id']);
                    }
                }
                break;

            case 'restart':
                // Reiniciar conversación
                $welcomeNode = $this->chatbotNode->getById('welcome');
                if ($welcomeNode) {
                    $result['next_node'] = $welcomeNode;
                    $result['next_options'] = $this->chatbotOption->getByNodeId('welcome');
                }
                break;

            case 'submit':
                // Procesar formulario (implementar según necesidades)
                $result['form_processed'] = true;
                break;

            case 'end':
                // Finalizar conversación
                $result['conversation_ended'] = true;
                break;
        }

        return $result;
    }

    /**
     * Registrar analytics de interacción
     */
    private function trackInteraction($conversationId, $optionId, $currentNodeId, $input)
    {
        try {
            $analyticsData = [
                'conversation_id' => $conversationId,
                'event_name' => 'option_selected',
                'event_data' => json_encode([
                    'option_id' => $optionId,
                    'option_text' => $input['option_text'] ?? '',
                    'current_node_id' => $currentNodeId,
                    'user_input' => $input
                ]),
                'node_id' => $currentNodeId,
                'option_id' => $optionId,
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'session_id' => $input['session_id'] ?? session_id()
            ];

            $this->chatbotAnalytics->store($analyticsData);
        } catch (Exception $e) {
            // Log error but don't fail the interaction
            error_log('Error al registrar analytics: ' . $e->getMessage());
        }
    }

    /**
     * Registrar evento de analytics personalizado
     */
    public function trackAnalyticsEvent()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validar datos requeridos
            if (!isset($input['conversation_id']) || !isset($input['event_name'])) {
                return $this->responseHandler->error('conversation_id y event_name son requeridos', 400);
            }

            $analyticsData = [
                'conversation_id' => $input['conversation_id'],
                'event_name' => $input['event_name'],
                'event_data' => json_encode($input['event_data'] ?? []),
                'node_id' => $input['node_id'] ?? null,
                'option_id' => $input['option_id'] ?? null,
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'session_id' => $input['session_id'] ?? session_id()
            ];

            $this->chatbotAnalytics->store($analyticsData);

            return $this->responseHandler->success([], 'Evento registrado correctamente');
        } catch (Exception $e) {
            error_log('Error al registrar evento de analytics: ' . $e->getMessage());
            return $this->responseHandler->error('Error interno del servidor', 500);
        }
    }

    /**
     * Obtener estadísticas del chatbot
     */
    public function getAnalytics($dateFrom = null, $dateTo = null)
    {
        try {
            // Validar fechas si se proporcionan
            if ($dateFrom && !$this->inputValidator->validateDate($dateFrom)) {
                return $this->responseHandler->error('Formato de fecha inválido para dateFrom', 400);
            }

            if ($dateTo && !$this->inputValidator->validateDate($dateTo)) {
                return $this->responseHandler->error('Formato de fecha inválido para dateTo', 400);
            }

            $analytics = $this->chatbotAnalytics->getStats($dateFrom, $dateTo);

            return $this->responseHandler->success($analytics, 'Estadísticas obtenidas correctamente');
        } catch (Exception $e) {
            error_log('Error al obtener analytics: ' . $e->getMessage());
            return $this->responseHandler->error('Error interno del servidor', 500);
        }
    }

    /**
     * Crear nuevo nodo (para administración)
     */
    public function createNode()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validar datos requeridos
            $requiredFields = ['id', 'type', 'content'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    return $this->responseHandler->error("Campo requerido: $field", 400);
                }
            }

            // Validar tipos permitidos
            $allowedTypes = ['message', 'options', 'form', 'redirect'];
            if (!in_array($input['type'], $allowedTypes)) {
                return $this->responseHandler->error('Tipo de nodo inválido', 400);
            }

            $nodeData = [
                'id' => $input['id'],
                'type' => $input['type'],
                'content' => $input['content'],
                'metadata' => json_encode($input['metadata'] ?? []),
                'created_by' => $input['created_by'] ?? 'admin'
            ];

            $result = $this->chatbotNode->store($nodeData);

            if ($result) {
                return $this->responseHandler->success(['node_id' => $input['id']], 'Nodo creado correctamente');
            } else {
                return $this->responseHandler->error('Error al crear el nodo', 500);
            }
        } catch (Exception $e) {
            error_log('Error al crear nodo: ' . $e->getMessage());
            return $this->responseHandler->error('Error interno del servidor', 500);
        }
    }

    /**
     * Crear nueva opción (para administración)
     */
    public function createOption()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validar datos requeridos
            $requiredFields = ['id', 'node_id', 'text', 'action_type'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    return $this->responseHandler->error("Campo requerido: $field", 400);
                }
            }

            // Validar que el nodo existe
            $node = $this->chatbotNode->getById($input['node_id']);
            if (!$node) {
                return $this->responseHandler->error('Nodo padre no encontrado', 400);
            }

            // Validar tipos de acción permitidos
            $allowedActionTypes = ['navigate', 'submit', 'restart', 'end'];
            if (!in_array($input['action_type'], $allowedActionTypes)) {
                return $this->responseHandler->error('Tipo de acción inválido', 400);
            }

            $optionData = [
                'id' => $input['id'],
                'node_id' => $input['node_id'],
                'text' => $input['text'],
                'next_node_id' => $input['next_node_id'] ?? null,
                'action_type' => $input['action_type'],
                'action_data' => json_encode($input['action_data'] ?? []),
                'order_position' => $input['order_position'] ?? 1
            ];

            $result = $this->chatbotOption->store($optionData);

            if ($result) {
                return $this->responseHandler->success(['option_id' => $input['id']], 'Opción creada correctamente');
            } else {
                return $this->responseHandler->error('Error al crear la opción', 500);
            }
        } catch (Exception $e) {
            error_log('Error al crear opción: ' . $e->getMessage());
            return $this->responseHandler->error('Error interno del servidor', 500);
        }
    }

    /**
     * Actualizar nodo existente (para administración)
     */
    public function updateNode($nodeId)
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validar que el nodo existe
            $existingNode = $this->chatbotNode->getById($nodeId);
            if (!$existingNode) {
                return $this->responseHandler->error('Nodo no encontrado', 404);
            }

            // Validar datos de entrada
            $allowedFields = ['type', 'content', 'metadata', 'is_active'];
            $updateData = [];

            foreach ($input as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    if ($field === 'metadata' && is_array($value)) {
                        $updateData[$field] = json_encode($value);
                    } else {
                        $updateData[$field] = $value;
                    }
                }
            }

            if (empty($updateData)) {
                return $this->responseHandler->error('No hay datos válidos para actualizar', 400);
            }

            $result = $this->chatbotNode->update($nodeId, $updateData);

            if ($result) {
                return $this->responseHandler->success(['node_id' => $nodeId], 'Nodo actualizado correctamente');
            } else {
                return $this->responseHandler->error('Error al actualizar el nodo', 500);
            }
        } catch (Exception $e) {
            error_log("Error al actualizar nodo $nodeId: " . $e->getMessage());
            return $this->responseHandler->error('Error interno del servidor', 500);
        }
    }

    /**
     * Actualizar opción existente (para administración)
     */
    public function updateOption($optionId)
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validar que la opción existe
            $existingOption = $this->chatbotOption->getById($optionId);
            if (!$existingOption) {
                return $this->responseHandler->error('Opción no encontrada', 404);
            }

            // Validar datos de entrada
            $allowedFields = ['node_id', 'text', 'next_node_id', 'action_type', 'action_data', 'order_position', 'is_active'];
            $updateData = [];

            foreach ($input as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    if ($field === 'action_data' && is_array($value)) {
                        $updateData[$field] = json_encode($value);
                    } else {
                        $updateData[$field] = $value;
                    }
                }
            }

            if (empty($updateData)) {
                return $this->responseHandler->error('No hay datos válidos para actualizar', 400);
            }

            // Validar que el nodo padre existe si se está cambiando
            if (isset($updateData['node_id'])) {
                $parentNode = $this->chatbotNode->getById($updateData['node_id']);
                if (!$parentNode) {
                    return $this->responseHandler->error('Nodo padre no encontrado', 400);
                }
            }

            $result = $this->chatbotOption->update($optionId, $updateData);

            if ($result) {
                return $this->responseHandler->success(['option_id' => $optionId], 'Opción actualizada correctamente');
            } else {
                return $this->responseHandler->error('Error al actualizar la opción', 500);
            }
        } catch (Exception $e) {
            error_log("Error al actualizar opción $optionId: " . $e->getMessage());
            return $this->responseHandler->error('Error interno del servidor', 500);
        }
    }

    /**
     * Eliminar nodo (para administración)
     */
    public function deleteNode($nodeId)
    {
        try {
            // Validar entrada
            if (!$this->inputValidator->validateRequired($nodeId)) {
                return $this->responseHandler->error('ID de nodo requerido', 400);
            }

            // Validar que el nodo existe
            $existingNode = $this->chatbotNode->getById($nodeId);
            if (!$existingNode) {
                return $this->responseHandler->error('Nodo no encontrado', 404);
            }

            // No permitir eliminar el nodo welcome
            if ($nodeId === 'welcome') {
                return $this->responseHandler->error('No se puede eliminar el nodo de bienvenida', 403);
            }

            $result = $this->chatbotNode->delete($nodeId);

            if ($result) {
                return $this->responseHandler->success([], 'Nodo eliminado correctamente');
            } else {
                return $this->responseHandler->error('Error al eliminar el nodo', 500);
            }
        } catch (Exception $e) {
            error_log("Error al eliminar nodo $nodeId: " . $e->getMessage());
            return $this->responseHandler->error('Error interno del servidor', 500);
        }
    }

    /**
     * Eliminar opción (para administración)
     */
    public function deleteOption($optionId)
    {
        try {
            // Validar entrada
            if (!$this->inputValidator->validateRequired($optionId)) {
                return $this->responseHandler->error('ID de opción requerido', 400);
            }

            // Validar que la opción existe
            $existingOption = $this->chatbotOption->getById($optionId);
            if (!$existingOption) {
                return $this->responseHandler->error('Opción no encontrada', 404);
            }

            $result = $this->chatbotOption->delete($optionId);

            if ($result) {
                return $this->responseHandler->success([], 'Opción eliminada correctamente');
            } else {
                return $this->responseHandler->error('Error al eliminar la opción', 500);
            }
        } catch (Exception $e) {
            error_log("Error al eliminar opción $optionId: " . $e->getMessage());
            return $this->responseHandler->error('Error interno del servidor', 500);
        }
    }
}
