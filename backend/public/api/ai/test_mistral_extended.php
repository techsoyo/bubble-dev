<?php

// Test simple para verificar que Mistral funciona con timeout largo
echo "🔍 TEST DE MISTRAL CON TIMEOUT EXTENDIDO\n";
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

echo "📤 Enviando petición a Mistral...\n";

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$time_taken = microtime(true) - $start_time;

echo '⏱️  Tiempo: ' . round($time_taken, 2) . " segundos\n";

if (curl_errno($curl)) {
    echo '❌ Error cURL: ' . curl_error($curl) . "\n";
} else {
    echo "✅ HTTP Code: $httpCode\n";

    $data = json_decode($response, true);
    if (isset($data['response'])) {
        echo '✅ Respuesta de Mistral: ' . trim($data['response']) . "\n";
        echo "✅ MISTRAL FUNCIONA CORRECTAMENTE\n\n";
        echo "🎯 READY PARA PROBAR EL SISTEMA DE RESUMEN\n";
    } else {
        echo '❌ Respuesta inválida: ' . substr($response, 0, 200) . "\n";
    }
}

curl_close($curl);
