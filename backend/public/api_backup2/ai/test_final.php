<?php

if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}
// Test final con timeout aumentado
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "ðŸš€ PRUEBA FINAL CON TIMEOUT AUMENTADO\n";
echo "====================================\n\n";

$start_time = microtime(true);

// Capturar la salida del parse-cv-file.php actualizado
ob_start();
include 'parse-cv-file.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo 'â±ï¸  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "ðŸŽ‰ Â¡Ã‰XITO! parse-cv-file.php funcionÃ³ correctamente\n";
        echo "ðŸ“Š ESTADÃSTICAS:\n";
        echo "================\n";
        echo 'â±ï¸  Tiempo de procesamiento: ' . round($total_time, 2) . "s\n";
        echo 'ðŸ“ Longitud de respuesta: ' . strlen($response['ollama_response']) . " caracteres\n\n";

        // Intentar parsear la respuesta de Ollama
        $cvData = json_decode($response['ollama_response'], true);
        if ($cvData) {
            echo "âœ… Respuesta de Ollama es JSON vÃ¡lido\n";
            echo 'ðŸŽ¯ NOMBRE EXTRAÃDO: ' . ($cvData['nombre'] ?? 'No detectado') . "\n";
            echo 'ðŸŽ¯ EMAIL: ' . ($cvData['email'] ?? 'No detectado') . "\n";
            echo 'ðŸŽ¯ CATEGORÃA: ' . ($cvData['categoria'] ?? 'No detectada') . "\n";
            echo 'ðŸŽ¯ SUBCATEGORÃA: ' . ($cvData['subcategoria'] ?? 'No detectada') . "\n";

            if (isset($cvData['puestos_anteriores']) && is_array($cvData['puestos_anteriores'])) {
                echo 'ðŸ’¼ PUESTOS ANTERIORES: ' . count($cvData['puestos_anteriores']) . " registros\n";
            }

            if (isset($cvData['tecnologias_herramientas']) && is_array($cvData['tecnologias_herramientas'])) {
                echo 'ðŸ”§ TECNOLOGÃAS: ' . count($cvData['tecnologias_herramientas']) . " encontradas\n";
            }
        } else {
            echo "âš ï¸ La respuesta de Ollama no es JSON puro\n";
            echo "ðŸ” Primeros 200 caracteres:\n";
            echo substr($response['ollama_response'], 0, 200) . "...\n";
        }

        echo "\nâœ… PASO 2 COMPLETADO EXITOSAMENTE\n";
        echo "ðŸŽ¯ READY PARA PASO 3: Limpiar y validar JSON\n";
    } else {
        echo "âŒ ERROR en parse-cv-file.php:\n";
        echo 'Error: ' . ($response['error'] ?? 'Desconocido') . "\n";
        if (isset($response['timeout'])) {
            echo "âš ï¸ CAUSA: Timeout - el modelo tardÃ³ demasiado\n";
            echo "ðŸ’¡ SOLUCIÃ“N: Usar un modelo mÃ¡s pequeÃ±o o implementar streaming\n";
        }
    }
} else {
    echo "âŒ Respuesta invÃ¡lida del endpoint:\n";
    echo $output . "\n";
}
