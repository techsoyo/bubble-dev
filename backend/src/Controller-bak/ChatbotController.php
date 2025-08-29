<?php

declare(strict_types=1);

namespace Controllers;

use Models\ChatbotNode;
use Models\ChatbotOption;
use Models\ChatbotAnalytics;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

class ChatbotController extends BaseController
{
    private ChatbotNode $nodeModel;
    private ChatbotOption $optionModel;
    private ChatbotAnalytics $analyticsModel;

    public function __construct()
    {
        parent::__construct();
        $this->nodeModel = new ChatbotNode();
        $this->optionModel = new ChatbotOption();
        $this->analyticsModel = new ChatbotAnalytics();
    }

    public function getChatbotData(Request $request, array $params = [])
    {
        try {
            // Obtener nodos activos del chatbot
            $nodes = $this->nodeModel->getActiveNodes();
            $rootNodes = $this->nodeModel->getByType('root');
            $rootNode = !empty($rootNodes) ? $rootNodes[0] : null;

            return ResponseHelper::success('Datos iniciales del chatbot', [
                'welcome' => $rootNode ? $rootNode['content'] : 'Hola, soy tu asistente virtual. Ãƒâ€šÃ‚Â¿En qué puedo ayudarte hoy?',
                'root_node' => $rootNode,
                'total_nodes' => count($nodes)
            ]);
        } catch (\Throwable $e) {
            Logger::error('Error getting chatbot data', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Error al obtener datos del chatbot', $e, 500);
        }
    }

    public function show(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail('ID no proporcionado', 400);
            }

            // Obtener nodo del chatbot por ID
            $node = $this->nodeModel->getById((int)$id);
            if (!$node) {
                return ResponseHelper::fail('Nodo de chatbot no encontrado', 404);
            }

            // Obtener opciones del nodo
            $options = $this->optionModel->getOptionsByNode((int)$id);
            $node['options'] = $options;

            Logger::info('Chatbot node retrieved', ['id' => $id]);
            return ResponseHelper::success('Nodo de chatbot encontrado', $node, 200);
        } catch (\Throwable $e) {
            Logger::error('Error retrieving chatbot node', ['id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error('Error al obtener nodo de chatbot', $e, 500);
        }
    }

    public function getNode(Request $request, array $params = [])
    {
        try {
            $nodeId = $request->input('node_id') ?? 'root';

            // Obtener el nodo de la base de datos
            $node = $this->nodeModel->findById($nodeId);

            if (!$node) {
                return ResponseHelper::fail('Nodo no encontrado', 404);
            }

            // Obtener las opciones asociadas a este nodo
            $options = $this->optionModel->getOptionsByNode($nodeId);

            $result = [
                'node_id' => $node['id'],
                'content' => $node['content'],
                'options' => $options
            ];

            return ResponseHelper::success('Nodo recuperado', $result);
        } catch (\Throwable $e) {
            Logger::error('Error al obtener nodo', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Error al recuperar el nodo', $e);
        }
    }

    public function processInteraction(Request $request, array $params = [])
    {
        try {
            $message = $request->input('message');
            $currentNodeId = $request->input('current_node_id') ?? 'root';
            $selectedOptionId = $request->input('selected_option_id');

            if (!$message) {
                return ResponseHelper::fail('Mensaje no proporcionado', 400);
            }

            // Registrar interacción en analí­ticas
            $this->analyticsModel->store([
                'message' => $message,
                'node_id' => $currentNodeId,
                'selected_option_id' => $selectedOptionId,
                'timestamp' => time()
            ]);

            // Procesar la opción seleccionada y obtener el siguiente nodo
            $result = $this->optionModel->processOptionSelection($selectedOptionId ?? '');

            if (!$result) {
                // Si no hay opción seleccionada o no es ví¡lida, devolver el nodo raí­z
                $nextNode = $this->nodeModel->findById('root');
                $options = $this->optionModel->getOptionsByNode('root');
            } else {
                $nextNode = $this->nodeModel->findById($result['next_node_id']);
                $options = $this->optionModel->getOptionsByNode($result['next_node_id']);
            }

            return ResponseHelper::success('Interacción procesada', [
                'input' => $message,
                'reply' => $nextNode['content'] ?? "Te he entendido: $message",
                'next_node' => $nextNode['id'] ?? 'root',
                'options' => $options
            ]);
        } catch (\Throwable $e) {
            Logger::error('Error al procesar interacción', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Error al procesar la interacción', $e);
        }
    }

    public function getAnalytics(Request $request, array $params = [])
    {
        try {
            $days = (int)($request->input('days') ?? 30);
            $type = $request->input('type') ?? 'general';

            switch ($type) {
                case 'sessions':
                    $data = $this->analyticsModel->getSessionAnalytics(['days' => $days]);
                    break;
                case 'nodes':
                    $data = $this->analyticsModel->getNodeStatistics(['days' => $days]);
                    break;
                case 'options':
                    $data = $this->analyticsModel->getOptionStatistics(['days' => $days]);
                    break;
                case 'paths':
                    $data = $this->analyticsModel->getMostPopularPaths(['days' => $days]);
                    break;
                case 'abandonment':
                    $data = $this->analyticsModel->getAbandonmentRate(['days' => $days]);
                    break;
                case 'general':
                default:
                    $data = $this->analyticsModel->generateAnalyticsReport(['days' => $days]);
                    break;
            }

            return ResponseHelper::success('Analí­ticas de uso del chatbot', $data);
        } catch (\Throwable $e) {
            Logger::error('Error al obtener analí­ticas', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Error al obtener analí­ticas', $e);
        }
    }

    public function trackAnalyticsEvent(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            $event = $data['event'] ?? null;

            if (!$event) {
                return ResponseHelper::fail('Evento no proporcionado', 400);
            }

            // Simular guardado de evento
            Logger::info('Chatbot analytics event tracked', ['event' => $event, 'data' => $data]);

            return ResponseHelper::success('Evento de analytics registrado', [
                'event' => $event,
                'timestamp' => date('Y-m-d H:i:s'),
                'success' => true
            ], 201);
        } catch (\Throwable $e) {
            Logger::error('Error tracking analytics event', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Error al registrar evento', $e, 500);
        }
    }

    public function createNode(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            $text = $data['text'] ?? null;
            $type = $data['type'] ?? 'default';
            $content = $data['content'] ?? $text;
            $isActive = $data['is_active'] ?? true;

            if (!$text || !$content) {
                return ResponseHelper::fail('Texto y contenido del nodo son requeridos', 400);
            }

            $nodeData = [
                'text' => $text,
                'type' => $type,
                'content' => $content,
                'is_active' => $isActive,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $nodeId = $this->nodeModel->store($nodeData);
            Logger::info('Chatbot node created', ['id' => $nodeId]);

            $createdNode = $this->nodeModel->findById($nodeId);

            return ResponseHelper::success('Nodo creado exitosamente', $createdNode);
        } catch (\Throwable $e) {
            Logger::error('Error al crear nodo', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Error al crear nodo', $e);
        }
    }

    public function createOption(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            $text = $data['text'] ?? null;
            $nodeId = $data['node_id'] ?? null;
            $nextNodeId = $data['next_node_id'] ?? null;
            $order = $data['order'] ?? null;

            if (!$text || !$nodeId) {
                return ResponseHelper::fail('Texto y node_id son requeridos', 400);
            }

            $optionData = [
                'text' => $text,
                'node_id' => $nodeId,
                'next_node_id' => $nextNodeId,
                'order' => $order,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $optionId = $this->optionModel->store($optionData);
            Logger::info('Chatbot option created', ['id' => $optionId, 'node_id' => $nodeId]);

            $createdOption = $this->optionModel->findById($optionId);

            return ResponseHelper::success('Opción creada exitosamente', $createdOption, 201);
        } catch (\Throwable $e) {
            Logger::error('Error al crear opción', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Error al crear opción', $e);
        }
    }

    public function updateNode(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            $data = $request->getBody();

            if (!$id) {
                return ResponseHelper::fail('ID no proporcionado', 400);
            }

            $node = $this->nodeModel->findById($id);
            if (!$node) {
                return ResponseHelper::fail('Nodo no encontrado', 404);
            }

            $success = $this->nodeModel->update($id, $data);
            Logger::info('Chatbot node updated', ['id' => $id, 'data' => $data]);

            return ResponseHelper::success("Nodo $id actualizado", ['success' => $success], 200);
        } catch (\Throwable $e) {
            Logger::error('Error al actualizar nodo', ['id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error('Error al actualizar nodo', $e);
        }
    }

    public function updateOption(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            $data = $request->getBody();

            if (!$id) {
                return ResponseHelper::fail('ID no proporcionado', 400);
            }

            $option = $this->optionModel->findById($id);
            if (!$option) {
                return ResponseHelper::fail('Opción no encontrada', 404);
            }

            $success = $this->optionModel->update($id, $data);
            Logger::info('Chatbot option updated', ['id' => $id, 'data' => $data]);

            return ResponseHelper::success("Opción $id actualizada", ['success' => $success], 200);
        } catch (\Throwable $e) {
            Logger::error('Error al actualizar opción', ['id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error('Error al actualizar opción', $e);
        }
    }

    public function deleteNode(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail('ID no proporcionado', 400);
            }

            $node = $this->nodeModel->findById($id);
            if (!$node) {
                return ResponseHelper::fail('Nodo no encontrado', 404);
            }

            $success = $this->nodeModel->delete($id);
            Logger::info('Chatbot node deleted', ['id' => $id]);

            return ResponseHelper::success("Nodo $id eliminado", ['success' => $success], 204);
        } catch (\Throwable $e) {
            $id = $params['id'] ?? 'unknown';
            Logger::error('Error al eliminar nodo', ['id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error('Error al eliminar nodo', $e);
        }
    }

    public function deleteOption(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail('ID no proporcionado', 400);
            }

            $option = $this->optionModel->findById($id);
            if (!$option) {
                return ResponseHelper::fail('Opción no encontrada', 404);
            }

            $success = $this->optionModel->delete($id);
            Logger::info('Chatbot option deleted', ['id' => $id]);

            return ResponseHelper::success("Opción $id eliminada", ['success' => $success], 204);
        } catch (\Throwable $e) {
            $id = $params['id'] ?? 'unknown';
            Logger::error('Error al eliminar opción', ['id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error('Error al eliminar opción', $e);
        }
    }
}
