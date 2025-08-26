<?php declare(strict_types=1);

namespace Services\ChatbotService.php\Services;

/**
 * ChatbotService
 * Servicio para interactuar con un chatbot basado en OpenAI (o similar)
 */
class ChatbotService
{
    /**
     * EnvÃ­a un mensaje al chatbot y obtiene la respuesta
     * @param array $messages Array de mensajes (formato OpenAI Chat API)
     * @param array $options Opciones adicionales (modelo, temperatura, etc.)
     * @return string Respuesta del chatbot
     */
    public function chat(array $messages, array $options = []): string
    {
        $apiKey = getenv('OPENAI_API_KEY');
        $apiUrl = getenv('OPENAI_API_BASE') ?: 'https://api.openai.com/v1/chat/completions';
        $model = $options['model'] ?? getenv('OPENAI_MODEL') ?? 'gpt-3.5-turbo';
        $temperature = $options['temperature'] ?? 0.7;

        $payload = [
          'model' => $model,
          'messages' => $messages,
          'temperature' => $temperature
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Content-Type: application/json',
          'Authorization: Bearer ' . $apiKey
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            return 'Error al conectar con el chatbot: ' . curl_error($ch);
        }
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        return 'No se pudo obtener respuesta del chatbot.';
    }
}
