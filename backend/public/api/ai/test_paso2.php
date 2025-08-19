<?php

if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}
// Script de prueba para el PASO 2 - Envío a Ollama
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "🚀 PRUEBA DEL PASO 2 - ENVÍO A OLLAMA\n";
echo "=====================================\n\n";

// Incluir el archivo parse-cv-file.php
ob_start();
include 'parse-cv-file.php';
$output = ob_get_clean();

echo "📤 RESPUESTA RECIBIDA:\n";
echo "======================\n";
echo $output . "\n\n";

// Decodificar y mostrar información estructurada
$response = json_decode($output, true);
if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok' && isset($response['ollama_response'])) {
        echo "✅ ÉXITO: Ollama respondió correctamente\n";
        echo '📋 LONGITUD DE RESPUESTA: ' . strlen($response['ollama_response']) . " caracteres\n";
        echo "🔍 PRIMEROS 200 CARACTERES:\n";
        echo substr($response['ollama_response'], 0, 200) . "...\n\n";

        // Intentar decodificar la respuesta de Ollama como JSON
        $ollamaJson = json_decode($response['ollama_response'], true);
        if ($ollamaJson) {
            echo "✅ La respuesta de Ollama es JSON válido\n";
            echo "📊 CAMPOS DETECTADOS:\n";
            foreach (array_keys($ollamaJson) as $key) {
                echo "  - $key\n";
            }
        } else {
            echo "⚠️  La respuesta de Ollama no es JSON válido (puede necesitar limpieza)\n";
        }
    } else {
        echo '❌ ERROR: ' . ($response['error'] ?? 'Error desconocido') . "\n";
    }
} else {
    echo "❌ ERROR: Respuesta no válida del endpoint\n";
}
