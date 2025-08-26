<?php declare(strict_types=1);

// @deprecated - archivo de test, deshabilitar en producciÃƒÂ³n
if ((\['APP_ENV'] ?? 'production') === 'production') {
    http_response_code(404);
    exit('Not found');
}



if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}

// Test simple para verificar que Mistral funciona
echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â TEST SIMPLE DE MISTRAL\n";
echo "========================\n\n";

$curl = curl_init('http://localhost:11434/api/generate');

curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'mistral',
        'prompt' => 'Hola, responde solo con "OK"',
        'stream' => false
    ]),
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

if (curl_errno($curl)) {
    echo 'ÃƒÂ¢Ã‚ÂÃ…â€™ Error cURL: ' . curl_error($curl) . "\n";
} else {
    echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ HTTP Code: $httpCode\n";

    $data = json_decode($response, true);
    if (isset($data['response'])) {
        echo 'ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Respuesta de Mistral: ' . trim($data['response']) . "\n";
        echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ MISTRAL FUNCIONA CORRECTAMENTE\n";
    } else {
        echo 'ÃƒÂ¢Ã‚ÂÃ…â€™ Respuesta invÃƒÆ’Ã‚Â¡lida: ' . substr($response, 0, 200) . "\n";
    }
}

curl_close($curl);

