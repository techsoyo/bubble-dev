<?php

// Test simple para verificar que Mistral funciona con timeout largo
echo "ðŸ” TEST DE MISTRAL CON TIMEOUT EXTENDIDO\n";
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

echo "ðŸ“¤ Enviando peticiÃ³n a Mistral...\n";

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$time_taken = microtime(true) - $start_time;

echo 'â±ï¸  Tiempo: ' . round($time_taken, 2) . " segundos\n";

if (curl_errno($curl)) {
    echo 'âŒ Error cURL: ' . curl_error($curl) . "\n";
} else {
    echo "âœ… HTTP Code: $httpCode\n";

    $data = json_decode($response, true);
    if (isset($data['response'])) {
        echo 'âœ… Respuesta de Mistral: ' . trim($data['response']) . "\n";
        echo "âœ… MISTRAL FUNCIONA CORRECTAMENTE\n\n";
        echo "ðŸŽ¯ READY PARA PROBAR EL SISTEMA DE RESUMEN\n";
    } else {
        echo 'âŒ Respuesta invÃ¡lida: ' . substr($response, 0, 200) . "\n";
    }
}

curl_close($curl);
