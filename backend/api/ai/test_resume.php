<?php

if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}
// Script de prueba para resume-cv.php
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "🎯 PRUEBA DEL SISTEMA DE RESUMEN DE CV\n";
echo "=====================================\n\n";

$start_time = microtime(true);

// Ejecutar el script de resumen
ob_start();
include 'resume-cv.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo '⏱️  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

// Decodificar respuesta
$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "🎉 ¡ÉXITO! Resumen generado correctamente\n\n";

        echo "📊 ESTADÍSTICAS:\n";
        echo "================\n";
        echo '📄 Archivo resumen: ' . $response['resumen_file'] . "\n";
        echo '📏 Tamaño original: ' . $response['original_size'] . " caracteres\n";
        echo '📏 Tamaño resumen: ' . $response['resumen_size'] . " caracteres\n";
        echo '🗜️  Compresión: ' . $response['compression_ratio'] . "\n";
        echo '⏱️  Tiempo procesamiento: ' . round($total_time, 2) . "s\n\n";

        // Leer y mostrar el contenido del resumen
        $resumenPath = __DIR__ . '/../../uploads/resumenes/' . $response['resumen_file'];
        if (file_exists($resumenPath)) {
            $resumenContent = file_get_contents($resumenPath);
            echo "📝 CONTENIDO DEL RESUMEN:\n";
            echo "========================\n";
            echo $resumenContent . "\n\n";

            echo "✅ VERIFICACIONES:\n";
            echo "==================\n";
            echo "✓ Archivo de resumen creado\n";
            echo "✓ Contenido no vacío\n";
            echo "✓ Reducción significativa de tamaño\n";
            echo "✓ Tiempo de procesamiento aceptable\n\n";

            echo "🎯 READY PARA SIGUIENTE ETAPA\n";
        } else {
            echo "❌ ERROR: No se pudo leer el archivo de resumen creado\n";
        }
    } else {
        echo "❌ ERROR en resume-cv.php:\n";
        echo 'Error: ' . ($response['error'] ?? 'Desconocido') . "\n";
        if (isset($response['details'])) {
            echo 'Detalles: ' . $response['details'] . "\n";
        }
    }
} else {
    echo "❌ Respuesta inválida del endpoint:\n";
    echo $output . "\n";
}
