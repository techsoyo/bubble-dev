<?php
// Test directo de Ollama con su API nativa
require_once 'config/bootstrap.php';

echo "=== TEST OLLAMA DIRECTO (15/08/2025) ===\n\n";

// Aumentar timeout de PHP
ini_set('max_execution_time', 180); // 3 minutos

$ollamaUrl = "http://localhost:11434/api/generate";
$model = "recruitment-ai";

// Prompt optimizado para análisis de CV
$prompt = "Analiza este CV y extrae la información en formato JSON con los siguientes campos:
{
  \"personal_info\": {
    \"full_name\": \"\",
    \"email\": \"\",
    \"phone\": \"\"
  },
  \"work_experience\": [
    {
      \"company\": \"\",
      \"position\": \"\",
      \"duration\": \"\"
    }
  ],
  \"skills\": [],
  \"education\": []
}

CV: Juan Pérez - Desarrollador Full Stack con 3 años de experiencia en PHP, JavaScript y React. Licenciado en Informática. Email: juan@example.com

Responde SOLO con el JSON:";

$data = [
  'model' => $model,
  'prompt' => $prompt,
  'stream' => false,
  'format' => 'json'
];

echo "1. Enviando prompt a Ollama (modelo: $model)...\n";

$ch = curl_init($ollamaUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode($data),
  CURLOPT_TIMEOUT => 120 // 2 minutos para análisis
]);

$startTime = microtime(true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$duration = round((microtime(true) - $startTime), 2);

if (curl_errno($ch)) {
  echo "❌ Error cURL: " . curl_error($ch) . "\n";
  curl_close($ch);
  exit;
}

curl_close($ch);

echo "2. Tiempo de respuesta: {$duration}s\n";
echo "3. HTTP Code: $httpCode\n";

if ($httpCode === 200) {
  $result = json_decode($response, true);
  if ($result && isset($result['response'])) {
    echo "4. Ollama respondió exitosamente\n";

    // Intentar parsear el JSON de la respuesta
    $jsonResponse = trim($result['response']);
    $cvData = json_decode($jsonResponse, true);

    if ($cvData) {
      echo "✅ JSON válido extraído:\n";
      echo "   👤 Nombre: " . ($cvData['personal_info']['full_name'] ?? 'No detectado') . "\n";
      echo "   📧 Email: " . ($cvData['personal_info']['email'] ?? 'No detectado') . "\n";
      echo "   💼 Empresa: " . ($cvData['work_experience'][0]['company'] ?? 'No detectada') . "\n";
      echo "\n✅ OLLAMA FUNCIONANDO PERFECTAMENTE ✅\n";
    } else {
      echo "⚠️ Respuesta no es JSON válido:\n";
      echo substr($jsonResponse, 0, 200) . "...\n";
    }
  } else {
    echo "❌ Respuesta inválida: " . substr($response, 0, 200) . "\n";
  }
} else {
  echo "❌ Error HTTP $httpCode: $response\n";
}
