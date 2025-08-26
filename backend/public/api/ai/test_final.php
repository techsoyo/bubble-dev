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
// Test final con timeout aumentado
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "Ã°Å¸Å¡â‚¬ PRUEBA FINAL CON TIMEOUT AUMENTADO\n";
echo "====================================\n\n";

$start_time = microtime(true);

// Capturar la salida del parse-cv-file.php actualizado
ob_start();
include 'parse-cv-file.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo 'Ã¢ÂÂ±Ã¯Â¸Â  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "Ã°Å¸Å½â€° Ã‚Â¡Ãƒâ€°XITO! parse-cv-file.php funcionÃƒÂ³ correctamente\n";
        echo "Ã°Å¸â€œÅ  ESTADÃƒÂSTICAS:\n";
        echo "================\n";
        echo 'Ã¢ÂÂ±Ã¯Â¸Â  Tiempo de procesamiento: ' . round($total_time, 2) . "s\n";
        echo 'Ã°Å¸â€œÂ Longitud de respuesta: ' . strlen($response['ollama_response']) . " caracteres\n\n";

        // Intentar parsear la respuesta de Ollama
        $cvData = json_decode($response['ollama_response'], true);
        if ($cvData) {
            echo "Ã¢Å“â€¦ Respuesta de Ollama es JSON vÃƒÂ¡lido\n";
            echo 'Ã°Å¸Å½Â¯ NOMBRE EXTRAÃƒÂDO: ' . ($cvData['nombre'] ?? 'No detectado') . "\n";
            echo 'Ã°Å¸Å½Â¯ EMAIL: ' . ($cvData['email'] ?? 'No detectado') . "\n";
            echo 'Ã°Å¸Å½Â¯ CATEGORÃƒÂA: ' . ($cvData['categoria'] ?? 'No detectada') . "\n";
            echo 'Ã°Å¸Å½Â¯ SUBCATEGORÃƒÂA: ' . ($cvData['subcategoria'] ?? 'No detectada') . "\n";

            if (isset($cvData['puestos_anteriores']) && is_array($cvData['puestos_anteriores'])) {
                echo 'Ã°Å¸â€™Â¼ PUESTOS ANTERIORES: ' . count($cvData['puestos_anteriores']) . " registros\n";
            }

            if (isset($cvData['tecnologias_herramientas']) && is_array($cvData['tecnologias_herramientas'])) {
                echo 'Ã°Å¸â€Â§ TECNOLOGÃƒÂAS: ' . count($cvData['tecnologias_herramientas']) . " encontradas\n";
            }
        } else {
            echo "Ã¢Å¡Â Ã¯Â¸Â La respuesta de Ollama no es JSON puro\n";
            echo "Ã°Å¸â€Â Primeros 200 caracteres:\n";
            echo substr($response['ollama_response'], 0, 200) . "...\n";
        }

        echo "\nÃ¢Å“â€¦ PASO 2 COMPLETADO EXITOSAMENTE\n";
        echo "Ã°Å¸Å½Â¯ READY PARA PASO 3: Limpiar y validar JSON\n";
    } else {
        echo "Ã¢ÂÅ’ ERROR en parse-cv-file.php:\n";
        echo 'Error: ' . ($response['error'] ?? 'Desconocido') . "\n";
        if (isset($response['timeout'])) {
            echo "Ã¢Å¡Â Ã¯Â¸Â CAUSA: Timeout - el modelo tardÃƒÂ³ demasiado\n";
            echo "Ã°Å¸â€™Â¡ SOLUCIÃƒâ€œN: Usar un modelo mÃƒÂ¡s pequeÃƒÂ±o o implementar streaming\n";
        }
    }
} else {
    echo "Ã¢ÂÅ’ Respuesta invÃƒÂ¡lida del endpoint:\n";
    echo $output . "\n";
}

