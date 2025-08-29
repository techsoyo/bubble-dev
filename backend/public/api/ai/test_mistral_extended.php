<?php declare(strict_types=1);
// @deprecated - archivo de test, deshabilitar en producciÃƒÂ³n
if ((\['APP_ENV'] ?? 'production') === 'production') {
    http_response_code(404);
    exit('Not found');
}



// Test simple para verificar que Mistral funciona con timeout largo
echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â TEST DE MISTRAL CON TIMEOUT EXTENDIDO\n";
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

echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â¤ Enviando petición a Mistral...\n";

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$time_taken = microtime(true) - $start_time;

echo 'ÃƒÂ¢Ã‚ÂÃ‚Â±ÃƒÂ¯Ã‚Â¸Ã‚Â  Tiempo: ' . round($time_taken, 2) . " segundos\n";

if (curl_errno($curl)) {
    echo 'ÃƒÂ¢Ã‚ÂÃ…â€™ Error cURL: ' . curl_error($curl) . "\n";
} else {
    echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ HTTP Code: $httpCode\n";

    $data = json_decode($response, true);
    if (isset($data['response'])) {
        echo 'ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Respuesta de Mistral: ' . trim($data['response']) . "\n";
        echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ MISTRAL FUNCIONA CORRECTAMENTE\n\n";
        echo "ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ READY PARA PROBAR EL SISTEMA DE RESUMEN\n";
    } else {
        echo 'ÃƒÂ¢Ã‚ÂÃ…â€™ Respuesta inví¡lida: ' . substr($response, 0, 200) . "\n";
    }
}

curl_close($curl);

