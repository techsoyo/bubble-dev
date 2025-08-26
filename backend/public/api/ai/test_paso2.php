<?php
// @deprecated - archivo de test, deshabilitar en producciÃ³n
if ((\['APP_ENV'] ?? 'production') === 'production') {
    http_response_code(404);
    exit('Not found');
}



if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}
// Script de prueba para el PASO 2 - EnvÃƒÂ­o a Ollama
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "Ã°Å¸Å¡â‚¬ PRUEBA DEL PASO 2 - ENVÃƒÂO A OLLAMA\n";
echo "=====================================\n\n";

// Incluir el archivo parse-cv-file.php
ob_start();
include 'parse-cv-file.php';
$output = ob_get_clean();

echo "Ã°Å¸â€œÂ¤ RESPUESTA RECIBIDA:\n";
echo "======================\n";
echo $output . "\n\n";

// Decodificar y mostrar informaciÃƒÂ³n estructurada
$response = json_decode($output, true);
if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok' && isset($response['ollama_response'])) {
        echo "Ã¢Å“â€¦ Ãƒâ€°XITO: Ollama respondiÃƒÂ³ correctamente\n";
        echo 'Ã°Å¸â€œâ€¹ LONGITUD DE RESPUESTA: ' . strlen($response['ollama_response']) . " caracteres\n";
        echo "Ã°Å¸â€Â PRIMEROS 200 CARACTERES:\n";
        echo substr($response['ollama_response'], 0, 200) . "...\n\n";

        // Intentar decodificar la respuesta de Ollama como JSON
        $ollamaJson = json_decode($response['ollama_response'], true);
        if ($ollamaJson) {
            echo "Ã¢Å“â€¦ La respuesta de Ollama es JSON vÃƒÂ¡lido\n";
            echo "Ã°Å¸â€œÅ  CAMPOS DETECTADOS:\n";
            foreach (array_keys($ollamaJson) as $key) {
                echo "  - $key\n";
            }
        } else {
            echo "Ã¢Å¡Â Ã¯Â¸Â  La respuesta de Ollama no es JSON vÃƒÂ¡lido (puede necesitar limpieza)\n";
        }
    } else {
        echo 'Ã¢ÂÅ’ ERROR: ' . ($response['error'] ?? 'Error desconocido') . "\n";
    }
} else {
    echo "Ã¢ÂÅ’ ERROR: Respuesta no vÃƒÂ¡lida del endpoint\n";
}

