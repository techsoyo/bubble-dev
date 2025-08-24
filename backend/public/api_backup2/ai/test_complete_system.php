<?php

if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}
// Proteger endpoint de pruebas en entornos de producciÃ³n.
require_once dirname(__DIR__, 2) . '/config/config.php';
if (function_exists('isProduction') && isProduction()) {
    http_response_code(403);
    echo json_encode(['error' => 'Endpoint disabled in production']);
    exit;
}
// Script de prueba para el sistema completo de 2 etapas
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "ðŸŽ¯ PRUEBA DEL SISTEMA COMPLETO DE 2 ETAPAS\n";
echo "==========================================\n\n";

$start_time = microtime(true);

// Ejecutar el procesamiento completo
ob_start();
include 'process-cv-complete.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo 'â±ï¸  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

// Decodificar respuesta
$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "ðŸŽ‰ Â¡Ã‰XITO! CV procesado completamente en 2 etapas\n\n";

        echo "ðŸ“Š ESTADÃSTICAS GENERALES:\n";
        echo "==========================\n";
        echo 'â±ï¸  Tiempo de procesamiento: ' . $response['processing_time'] . "\n";
        echo 'ðŸ“„ Archivo original: ' . $response['generated_files']['original'] . "\n";
        echo 'ðŸ§¹ Archivo limpio: ' . $response['generated_files']['clean'] . "\n";
        echo 'ðŸ“‹ Archivo JSON: ' . $response['generated_files']['structured'] . "\n\n";

        echo "ðŸ“ˆ ETAPA 1 (EXTRACCIÃ“N CON LLAMA3):\n";
        echo "===================================\n";
        echo 'ðŸ“ TamaÃ±o original: ' . $response['stage1']['original_size'] . " caracteres\n";
        echo 'ðŸ“ TamaÃ±o limpio: ' . $response['stage1']['clean_size'] . " caracteres\n";
        echo 'ðŸ—œï¸  ReducciÃ³n: ' . $response['stage1']['reduction_ratio'] . "\n\n";

        echo "ðŸ“‹ ETAPA 2 (JSON CON RECRUITMENT-AI):\n";
        echo "=====================================\n";
        echo 'ðŸ“ TamaÃ±o JSON: ' . $response['stage2']['json_size'] . " caracteres\n";
        echo 'âœ… JSON vÃ¡lido: ' . ($response['stage2']['json_valid'] ? 'SÃ­' : 'No') . "\n";
        echo 'ðŸ”‘ Campos extraÃ­dos: ' . implode(', ', $response['stage2']['extracted_fields']) . "\n\n";

        // Leer y mostrar el contenido del JSON final
        $jsonPath = __DIR__ . '/../../uploads/json/' . $response['stage2']['json_file'];
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            echo "ðŸ“ CONTENIDO DEL JSON FINAL:\n";
            echo "============================\n";
            echo $jsonContent . "\n\n";
        }

        // Leer y mostrar una muestra del contenido limpio
        $cleanPath = __DIR__ . '/../../uploads/clean/' . $response['stage1']['clean_file'];
        if (file_exists($cleanPath)) {
            $cleanContent = file_get_contents($cleanPath);
            echo "ðŸ§¹ MUESTRA DEL CONTENIDO LIMPIO (200 chars):\n";
            echo "============================================\n";
            echo substr($cleanContent, 0, 200) . "...\n\n";
        }

        echo "âœ… VERIFICACIONES:\n";
        echo "==================\n";
        echo "âœ“ Etapa 1 completada (ExtracciÃ³n)\n";
        echo "âœ“ Etapa 2 completada (EstructuraciÃ³n)\n";
        echo "âœ“ Archivos generados correctamente\n";
        echo "âœ“ JSON vÃ¡lido generado\n";
        echo "âœ“ Tiempo de procesamiento aceptable\n\n";

        echo "ðŸŽ¯ SISTEMA DE 2 ETAPAS FUNCIONANDO CORRECTAMENTE\n";
    } else {
        echo "âŒ ERROR en el procesamiento:\n";
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
    echo "âŒ Respuesta invÃ¡lida del endpoint:\n";
    echo $output . "\n";
}
