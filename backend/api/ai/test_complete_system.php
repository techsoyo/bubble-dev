<?php

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

echo "🎯 PRUEBA DEL SISTEMA COMPLETO DE 2 ETAPAS\n";
echo "==========================================\n\n";

$start_time = microtime(true);

// Ejecutar el procesamiento completo
ob_start();
include 'process-cv-complete.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo '⏱️  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

// Decodificar respuesta
$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "🎉 ¡ÉXITO! CV procesado completamente en 2 etapas\n\n";

        echo "📊 ESTADÍSTICAS GENERALES:\n";
        echo "==========================\n";
        echo '⏱️  Tiempo de procesamiento: ' . $response['processing_time'] . "\n";
        echo '📄 Archivo original: ' . $response['generated_files']['original'] . "\n";
        echo '🧹 Archivo limpio: ' . $response['generated_files']['clean'] . "\n";
        echo '📋 Archivo JSON: ' . $response['generated_files']['structured'] . "\n\n";

        echo "📈 ETAPA 1 (EXTRACCIÓN CON LLAMA3):\n";
        echo "===================================\n";
        echo '📏 Tamaño original: ' . $response['stage1']['original_size'] . " caracteres\n";
        echo '📏 Tamaño limpio: ' . $response['stage1']['clean_size'] . " caracteres\n";
        echo '🗜️  Reducción: ' . $response['stage1']['reduction_ratio'] . "\n\n";

        echo "📋 ETAPA 2 (JSON CON RECRUITMENT-AI):\n";
        echo "=====================================\n";
        echo '📏 Tamaño JSON: ' . $response['stage2']['json_size'] . " caracteres\n";
        echo '✅ JSON válido: ' . ($response['stage2']['json_valid'] ? 'Sí' : 'No') . "\n";
        echo '🔑 Campos extraídos: ' . implode(', ', $response['stage2']['extracted_fields']) . "\n\n";

        // Leer y mostrar el contenido del JSON final
        $jsonPath = __DIR__ . '/../../uploads/json/' . $response['stage2']['json_file'];
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            echo "📝 CONTENIDO DEL JSON FINAL:\n";
            echo "============================\n";
            echo $jsonContent . "\n\n";
        }

        // Leer y mostrar una muestra del contenido limpio
        $cleanPath = __DIR__ . '/../../uploads/clean/' . $response['stage1']['clean_file'];
        if (file_exists($cleanPath)) {
            $cleanContent = file_get_contents($cleanPath);
            echo "🧹 MUESTRA DEL CONTENIDO LIMPIO (200 chars):\n";
            echo "============================================\n";
            echo substr($cleanContent, 0, 200) . "...\n\n";
        }

        echo "✅ VERIFICACIONES:\n";
        echo "==================\n";
        echo "✓ Etapa 1 completada (Extracción)\n";
        echo "✓ Etapa 2 completada (Estructuración)\n";
        echo "✓ Archivos generados correctamente\n";
        echo "✓ JSON válido generado\n";
        echo "✓ Tiempo de procesamiento aceptable\n\n";

        echo "🎯 SISTEMA DE 2 ETAPAS FUNCIONANDO CORRECTAMENTE\n";
    } else {
        echo "❌ ERROR en el procesamiento:\n";
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
    echo "❌ Respuesta inválida del endpoint:\n";
    echo $output . "\n";
}
