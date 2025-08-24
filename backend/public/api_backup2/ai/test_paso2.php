<?php

if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}
// Script de prueba para el PASO 2 - EnvÃ­o a Ollama
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "ðŸš€ PRUEBA DEL PASO 2 - ENVÃO A OLLAMA\n";
echo "=====================================\n\n";

// Incluir el archivo parse-cv-file.php
ob_start();
include 'parse-cv-file.php';
$output = ob_get_clean();

echo "ðŸ“¤ RESPUESTA RECIBIDA:\n";
echo "======================\n";
echo $output . "\n\n";

// Decodificar y mostrar informaciÃ³n estructurada
$response = json_decode($output, true);
if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok' && isset($response['ollama_response'])) {
        echo "âœ… Ã‰XITO: Ollama respondiÃ³ correctamente\n";
        echo 'ðŸ“‹ LONGITUD DE RESPUESTA: ' . strlen($response['ollama_response']) . " caracteres\n";
        echo "ðŸ” PRIMEROS 200 CARACTERES:\n";
        echo substr($response['ollama_response'], 0, 200) . "...\n\n";

        // Intentar decodificar la respuesta de Ollama como JSON
        $ollamaJson = json_decode($response['ollama_response'], true);
        if ($ollamaJson) {
            echo "âœ… La respuesta de Ollama es JSON vÃ¡lido\n";
            echo "ðŸ“Š CAMPOS DETECTADOS:\n";
            foreach (array_keys($ollamaJson) as $key) {
                echo "  - $key\n";
            }
        } else {
            echo "âš ï¸  La respuesta de Ollama no es JSON vÃ¡lido (puede necesitar limpieza)\n";
        }
    } else {
        echo 'âŒ ERROR: ' . ($response['error'] ?? 'Error desconocido') . "\n";
    }
} else {
    echo "âŒ ERROR: Respuesta no vÃ¡lida del endpoint\n";
}
