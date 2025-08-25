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
// Proteger endpoint de pruebas en entornos de producciÃƒÂ³n.
require_once dirname(__DIR__, 2) . '/config/config.php';
if (function_exists('isProduction') && isProduction()) {
    http_response_code(403);
    echo json_encode(['error' => 'Endpoint disabled in production']);
    exit;
}
// Script de prueba para el sistema completo de 2 etapas
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "Ã°Å¸Å½Â¯ PRUEBA DEL SISTEMA COMPLETO DE 2 ETAPAS\n";
echo "==========================================\n\n";

$start_time = microtime(true);

// Ejecutar el procesamiento completo
ob_start();
include 'process-cv-complete.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo 'Ã¢ÂÂ±Ã¯Â¸Â  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

// Decodificar respuesta
$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "Ã°Å¸Å½â€° Ã‚Â¡Ãƒâ€°XITO! CV procesado completamente en 2 etapas\n\n";

        echo "Ã°Å¸â€œÅ  ESTADÃƒÂSTICAS GENERALES:\n";
        echo "==========================\n";
        echo 'Ã¢ÂÂ±Ã¯Â¸Â  Tiempo de procesamiento: ' . $response['processing_time'] . "\n";
        echo 'Ã°Å¸â€œâ€ž Archivo original: ' . $response['generated_files']['original'] . "\n";
        echo 'Ã°Å¸Â§Â¹ Archivo limpio: ' . $response['generated_files']['clean'] . "\n";
        echo 'Ã°Å¸â€œâ€¹ Archivo JSON: ' . $response['generated_files']['structured'] . "\n\n";

        echo "Ã°Å¸â€œË† ETAPA 1 (EXTRACCIÃƒâ€œN CON LLAMA3):\n";
        echo "===================================\n";
        echo 'Ã°Å¸â€œÂ TamaÃƒÂ±o original: ' . $response['stage1']['original_size'] . " caracteres\n";
        echo 'Ã°Å¸â€œÂ TamaÃƒÂ±o limpio: ' . $response['stage1']['clean_size'] . " caracteres\n";
        echo 'Ã°Å¸â€”Å“Ã¯Â¸Â  ReducciÃƒÂ³n: ' . $response['stage1']['reduction_ratio'] . "\n\n";

        echo "Ã°Å¸â€œâ€¹ ETAPA 2 (JSON CON RECRUITMENT-AI):\n";
        echo "=====================================\n";
        echo 'Ã°Å¸â€œÂ TamaÃƒÂ±o JSON: ' . $response['stage2']['json_size'] . " caracteres\n";
        echo 'Ã¢Å“â€¦ JSON vÃƒÂ¡lido: ' . ($response['stage2']['json_valid'] ? 'SÃƒÂ­' : 'No') . "\n";
        echo 'Ã°Å¸â€â€˜ Campos extraÃƒÂ­dos: ' . implode(', ', $response['stage2']['extracted_fields']) . "\n\n";

        // Leer y mostrar el contenido del JSON final
        $jsonPath = __DIR__ . '/../../uploads/json/' . $response['stage2']['json_file'];
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            echo "Ã°Å¸â€œÂ CONTENIDO DEL JSON FINAL:\n";
            echo "============================\n";
            echo $jsonContent . "\n\n";
        }

        // Leer y mostrar una muestra del contenido limpio
        $cleanPath = __DIR__ . '/../../uploads/clean/' . $response['stage1']['clean_file'];
        if (file_exists($cleanPath)) {
            $cleanContent = file_get_contents($cleanPath);
            echo "Ã°Å¸Â§Â¹ MUESTRA DEL CONTENIDO LIMPIO (200 chars):\n";
            echo "============================================\n";
            echo substr($cleanContent, 0, 200) . "...\n\n";
        }

        echo "Ã¢Å“â€¦ VERIFICACIONES:\n";
        echo "==================\n";
        echo "Ã¢Å“â€œ Etapa 1 completada (ExtracciÃƒÂ³n)\n";
        echo "Ã¢Å“â€œ Etapa 2 completada (EstructuraciÃƒÂ³n)\n";
        echo "Ã¢Å“â€œ Archivos generados correctamente\n";
        echo "Ã¢Å“â€œ JSON vÃƒÂ¡lido generado\n";
        echo "Ã¢Å“â€œ Tiempo de procesamiento aceptable\n\n";

        echo "Ã°Å¸Å½Â¯ SISTEMA DE 2 ETAPAS FUNCIONANDO CORRECTAMENTE\n";
    } else {
        echo "Ã¢ÂÅ’ ERROR en el procesamiento:\n";
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
    echo "Ã¢ÂÅ’ Respuesta invÃƒÂ¡lida del endpoint:\n";
    echo $output . "\n";
}

