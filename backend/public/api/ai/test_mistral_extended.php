<?php
// @deprecated - archivo de test, deshabilitar en producciÃ³n
if ((\['APP_ENV'] ?? 'production') === 'production') {
    http_response_code(404);
    exit('Not found');
}



// Test simple para verificar que Mistral funciona con timeout largo
echo "Ã°Å¸â€Â TEST DE MISTRAL CON TIMEOUT EXTENDIDO\n";
echo "========================================\n\n";

$start_time = microtime(true);

$curl = curl_init('http://localhost:11434/api/generate');

curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'mistral',
        'prompt' => 'Responde con "MISTRAL OK"',
        'stream' => false
    ]),
    CURLOPT_TIMEOUT => 120 // 2 minutos
]);

echo "Ã°Å¸â€œÂ¤ Enviando peticiÃƒÂ³n a Mistral...\n";

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$time_taken = microtime(true) - $start_time;

echo 'Ã¢ÂÂ±Ã¯Â¸Â  Tiempo: ' . round($time_taken, 2) . " segundos\n";

if (curl_errno($curl)) {
    echo 'Ã¢ÂÅ’ Error cURL: ' . curl_error($curl) . "\n";
} else {
    echo "Ã¢Å“â€¦ HTTP Code: $httpCode\n";

    $data = json_decode($response, true);
    if (isset($data['response'])) {
        echo 'Ã¢Å“â€¦ Respuesta de Mistral: ' . trim($data['response']) . "\n";
        echo "Ã¢Å“â€¦ MISTRAL FUNCIONA CORRECTAMENTE\n\n";
        echo "Ã°Å¸Å½Â¯ READY PARA PROBAR EL SISTEMA DE RESUMEN\n";
    } else {
        echo 'Ã¢ÂÅ’ Respuesta invÃƒÂ¡lida: ' . substr($response, 0, 200) . "\n";
    }
}

curl_close($curl);

