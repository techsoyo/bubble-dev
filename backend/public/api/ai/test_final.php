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
// Test final con timeout aumentado
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "ÃƒÂ°Ã…Â¸Ã…Â¡Ã¢â€šÂ¬ PRUEBA FINAL CON TIMEOUT AUMENTADO\n";
echo "====================================\n\n";

$start_time = microtime(true);

// Capturar la salida del parse-cv-file.php actualizado
ob_start();
include 'parse-cv-file.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo 'ÃƒÂ¢Ã‚ÂÃ‚Â±ÃƒÂ¯Ã‚Â¸Ã‚Â  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "ÃƒÂ°Ã…Â¸Ã…Â½Ã¢â‚¬Â° Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã¢â‚¬Â°XITO! parse-cv-file.php funcionÃƒÆ’Ã‚Â³ correctamente\n";
        echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  ESTADÃƒÆ’Ã‚ÂSTICAS:\n";
        echo "================\n";
        echo 'ÃƒÂ¢Ã‚ÂÃ‚Â±ÃƒÂ¯Ã‚Â¸Ã‚Â  Tiempo de procesamiento: ' . round($total_time, 2) . "s\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â Longitud de respuesta: ' . strlen($response['ollama_response']) . " caracteres\n\n";

        // Intentar parsear la respuesta de Ollama
        $cvData = json_decode($response['ollama_response'], true);
        if ($cvData) {
            echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Respuesta de Ollama es JSON vÃƒÆ’Ã‚Â¡lido\n";
            echo 'ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ NOMBRE EXTRAÃƒÆ’Ã‚ÂDO: ' . ($cvData['nombre'] ?? 'No detectado') . "\n";
            echo 'ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ EMAIL: ' . ($cvData['email'] ?? 'No detectado') . "\n";
            echo 'ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ CATEGORÃƒÆ’Ã‚ÂA: ' . ($cvData['categoria'] ?? 'No detectada') . "\n";
            echo 'ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ SUBCATEGORÃƒÆ’Ã‚ÂA: ' . ($cvData['subcategoria'] ?? 'No detectada') . "\n";

            if (isset($cvData['puestos_anteriores']) && is_array($cvData['puestos_anteriores'])) {
                echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬â„¢Ã‚Â¼ PUESTOS ANTERIORES: ' . count($cvData['puestos_anteriores']) . " registros\n";
            }

            if (isset($cvData['tecnologias_herramientas']) && is_array($cvData['tecnologias_herramientas'])) {
                echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â§ TECNOLOGÃƒÆ’Ã‚ÂAS: ' . count($cvData['tecnologias_herramientas']) . " encontradas\n";
            }
        } else {
            echo "ÃƒÂ¢Ã…Â¡Ã‚Â ÃƒÂ¯Ã‚Â¸Ã‚Â La respuesta de Ollama no es JSON puro\n";
            echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â Primeros 200 caracteres:\n";
            echo substr($response['ollama_response'], 0, 200) . "...\n";
        }

        echo "\nÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ PASO 2 COMPLETADO EXITOSAMENTE\n";
        echo "ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ READY PARA PASO 3: Limpiar y validar JSON\n";
    } else {
        echo "ÃƒÂ¢Ã‚ÂÃ…â€™ ERROR en parse-cv-file.php:\n";
        echo 'Error: ' . ($response['error'] ?? 'Desconocido') . "\n";
        if (isset($response['timeout'])) {
            echo "ÃƒÂ¢Ã…Â¡Ã‚Â ÃƒÂ¯Ã‚Â¸Ã‚Â CAUSA: Timeout - el modelo tardÃƒÆ’Ã‚Â³ demasiado\n";
            echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬â„¢Ã‚Â¡ SOLUCIÃƒÆ’Ã¢â‚¬Å“N: Usar un modelo mÃƒÆ’Ã‚Â¡s pequeÃƒÆ’Ã‚Â±o o implementar streaming\n";
        }
    }
} else {
    echo "ÃƒÂ¢Ã‚ÂÃ…â€™ Respuesta invÃƒÆ’Ã‚Â¡lida del endpoint:\n";
    echo $output . "\n";
}

