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
// Script de prueba para el PASO 2 - Enví­o a Ollama
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "ÃƒÂ°Ã…Â¸Ã…Â¡Ã¢â€šÂ¬ PRUEBA DEL PASO 2 - ENVíO A OLLAMA\n";
echo "=====================================\n\n";

// Incluir el archivo parse-cv-file.php
ob_start();
include 'parse-cv-file.php';
$output = ob_get_clean();

echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â¤ RESPUESTA RECIBIDA:\n";
echo "======================\n";
echo $output . "\n\n";

// Decodificar y mostrar información estructurada
$response = json_decode($output, true);
if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok' && isset($response['ollama_response'])) {
        echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ ÉXITO: Ollama respondió correctamente\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Â¹ LONGITUD DE RESPUESTA: ' . strlen($response['ollama_response']) . " caracteres\n";
        echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â PRIMEROS 200 CARACTERES:\n";
        echo substr($response['ollama_response'], 0, 200) . "...\n\n";

        // Intentar decodificar la respuesta de Ollama como JSON
        $ollamaJson = json_decode($response['ollama_response'], true);
        if ($ollamaJson) {
            echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ La respuesta de Ollama es JSON ví¡lido\n";
            echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  CAMPOS DETECTADOS:\n";
            foreach (array_keys($ollamaJson) as $key) {
                echo "  - $key\n";
            }
        } else {
            echo "ÃƒÂ¢Ã…Â¡Ã‚Â ÃƒÂ¯Ã‚Â¸Ã‚Â  La respuesta de Ollama no es JSON ví¡lido (puede necesitar limpieza)\n";
        }
    } else {
        echo 'ÃƒÂ¢Ã‚ÂÃ…â€™ ERROR: ' . ($response['error'] ?? 'Error desconocido') . "\n";
    }
} else {
    echo "ÃƒÂ¢Ã‚ÂÃ…â€™ ERROR: Respuesta no ví¡lida del endpoint\n";
}

