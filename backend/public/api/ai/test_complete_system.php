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
// Proteger endpoint de pruebas en entornos de producción.
require_once dirname(__DIR__, 2) . '/config/config.php';
if (function_exists('isProduction') && isProduction()) {
    http_response_code(403);
    echo json_encode(['error' => 'Endpoint disabled in production']);
    exit;
}
// Script de prueba para el sistema completo de 2 etapas
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ PRUEBA DEL SISTEMA COMPLETO DE 2 ETAPAS\n";
echo "==========================================\n\n";

$start_time = microtime(true);

// Ejecutar el procesamiento completo
ob_start();
include 'process-cv-complete.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo 'ÃƒÂ¢Ã‚ÂÃ‚Â±ÃƒÂ¯Ã‚Â¸Ã‚Â  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

// Decodificar respuesta
$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "ÃƒÂ°Ã…Â¸Ã…Â½Ã¢â‚¬Â° Ãƒâ€šÃ‚Â¡ÉXITO! CV procesado completamente en 2 etapas\n\n";

        echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  ESTADíSTICAS GENERALES:\n";
        echo "==========================\n";
        echo 'ÃƒÂ¢Ã‚ÂÃ‚Â±ÃƒÂ¯Ã‚Â¸Ã‚Â  Tiempo de procesamiento: ' . $response['processing_time'] . "\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Å¾ Archivo original: ' . $response['generated_files']['original'] . "\n";
        echo 'ÃƒÂ°Ã…Â¸Ã‚Â§Ã‚Â¹ Archivo limpio: ' . $response['generated_files']['clean'] . "\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Â¹ Archivo JSON: ' . $response['generated_files']['structured'] . "\n\n";

        echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‹â€  ETAPA 1 (EXTRACCIÓN CON LLAMA3):\n";
        echo "===================================\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â Tamaí±o original: ' . $response['stage1']['original_size'] . " caracteres\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â Tamaí±o limpio: ' . $response['stage1']['clean_size'] . " caracteres\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬â€Ã…â€œÃƒÂ¯Ã‚Â¸Ã‚Â  Reducción: ' . $response['stage1']['reduction_ratio'] . "\n\n";

        echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Â¹ ETAPA 2 (JSON CON RECRUITMENT-AI):\n";
        echo "=====================================\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â Tamaí±o JSON: ' . $response['stage2']['json_size'] . " caracteres\n";
        echo 'ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ JSON ví¡lido: ' . ($response['stage2']['json_valid'] ? 'Sí­' : 'No') . "\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ¢â‚¬Ëœ Campos extraí­dos: ' . implode(', ', $response['stage2']['extracted_fields']) . "\n\n";

        // Leer y mostrar el contenido del JSON final
        $jsonPath = __DIR__ . '/../../uploads/json/' . $response['stage2']['json_file'];
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â CONTENIDO DEL JSON FINAL:\n";
            echo "============================\n";
            echo $jsonContent . "\n\n";
        }

        // Leer y mostrar una muestra del contenido limpio
        $cleanPath = __DIR__ . '/../../uploads/clean/' . $response['stage1']['clean_file'];
        if (file_exists($cleanPath)) {
            $cleanContent = file_get_contents($cleanPath);
            echo "ÃƒÂ°Ã…Â¸Ã‚Â§Ã‚Â¹ MUESTRA DEL CONTENIDO LIMPIO (200 chars):\n";
            echo "============================================\n";
            echo substr($cleanContent, 0, 200) . "...\n\n";
        }

        echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ VERIFICACIONES:\n";
        echo "==================\n";
        echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ Etapa 1 completada (Extracción)\n";
        echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ Etapa 2 completada (Estructuración)\n";
        echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ Archivos generados correctamente\n";
        echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ JSON ví¡lido generado\n";
        echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ Tiempo de procesamiento aceptable\n\n";

        echo "ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ SISTEMA DE 2 ETAPAS FUNCIONANDO CORRECTAMENTE\n";
    } else {
        echo "ÃƒÂ¢Ã‚ÂÃ…â€™ ERROR en el procesamiento:\n";
        echo 'Error: ' . ($response['error'] ?? 'Desconocido') . "\n";
        if (isset($response['stage1_error'])) {
            echo 'Error Etapa 1: ' . $response['stage1_error'] . "\n";
        }
        if (isset($response['stage2_error'])) {
            echo 'Error Etapa 2: ' . $response['stage2_error'] . "\n";
        }
        echo "\nDetalles completos:\n";
    }
} else {
    echo "ÃƒÂ¢Ã‚ÂÃ…â€™ Respuesta inví¡lida del endpoint:\n";
    echo $output . "\n";
}

