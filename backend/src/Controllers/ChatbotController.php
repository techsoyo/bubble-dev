<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class ChatbotController extends BaseController
{
    public function getChatbotData(Request $request, array $params = [])
    {
        return ResponseHelper::success('Datos iniciales del chatbot', [
            'welcome' => 'Hola, soy tu asistente virtual. ¿En qué puedo ayudarte hoy?'
        ]);
    }

    public function getNode(Request $request, array $params = [])
    {
        $nodeId = $request->input('node_id');
        return ResponseHelper::success('Nodo recuperado', [
            'node_id' => $nodeId ?? 'root',
            'content' => 'Ejemplo de nodo de conversación'
        ]);
    }

    public function processInteraction(Request $request, array $params = [])
    {
        $message = $request->input('message');
        if (!$message) {
            return ResponseHelper::error('Mensaje no proporcionado', 400);
        }

        // TODO: IA para procesar conversación
        return ResponseHelper::success('Interacción procesada', [
            'input' => $message,
            'reply' => "Te he entendido: $message"
        ]);
    }

    public function getAnalytics(Request $request, array $params = [])
    {
        return ResponseHelper::success('Analíticas de uso del chatbot', [
            'sessions' => 120,
            'avg_length' => 8,
            'satisfaction' => 92
        ]);
    }
}
