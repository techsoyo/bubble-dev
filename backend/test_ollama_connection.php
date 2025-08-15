<?php
// Test de conexión directa con Ollama
echo "=== TEST OLLAMA CONNECTION (15/08/2025) ===\n\n";

$ollamaUrl = "http://localhost:11434/api/generate";
$testData = [
  'model' => 'recruitment-ai',
  'prompt' => 'Test connection. Respond only: CONNECTED',
  'stream' => false
];

$ch = curl_init($ollamaUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode($testData),
  CURLOPT_TIMEOUT => 30
]);

echo "1. Conectando a Ollama en $ollamaUrl...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
  echo "❌ Error cURL: " . curl_error($ch) . "\n";
  curl_close($ch);
  exit;
}

curl_close($ch);

echo "2. HTTP Code: $httpCode\n";

if ($httpCode === 200) {
  $data = json_decode($response, true);
  if ($data && isset($data['response'])) {
    echo "✅ Ollama respondió: " . trim($data['response']) . "\n";
    echo "✅ Modelo recruitment-ai: FUNCIONANDO\n";
    echo "\n=== OLLAMA LISTO PARA USAR ===\n";
  } else {
    echo "❌ Respuesta inválida: " . substr($response, 0, 200) . "\n";
  }
} else {
  echo "❌ Error HTTP $httpCode: $response\n";
}
