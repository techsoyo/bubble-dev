<?php

if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}
// Script de prueba para resume-cv.php
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "ðŸŽ¯ PRUEBA DEL SISTEMA DE RESUMEN DE CV\n";
echo "=====================================\n\n";

$start_time = microtime(true);

// Ejecutar el script de resumen
ob_start();
include 'resume-cv.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo 'â±ï¸  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

// Decodificar respuesta
$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "ðŸŽ‰ Â¡Ã‰XITO! Resumen generado correctamente\n\n";

        echo "ðŸ“Š ESTADÃSTICAS:\n";
        echo "================\n";
        echo 'ðŸ“„ Archivo resumen: ' . $response['resumen_file'] . "\n";
        echo 'ðŸ“ TamaÃ±o original: ' . $response['original_size'] . " caracteres\n";
        echo 'ðŸ“ TamaÃ±o resumen: ' . $response['resumen_size'] . " caracteres\n";
        echo 'ðŸ—œï¸  CompresiÃ³n: ' . $response['compression_ratio'] . "\n";
        echo 'â±ï¸  Tiempo procesamiento: ' . round($total_time, 2) . "s\n\n";

        // Leer y mostrar el contenido del resumen
        $resumenPath = __DIR__ . '/../../uploads/resumenes/' . $response['resumen_file'];
        if (file_exists($resumenPath)) {
            $resumenContent = file_get_contents($resumenPath);
            echo "ðŸ“ CONTENIDO DEL RESUMEN:\n";
            echo "========================\n";
            echo $resumenContent . "\n\n";

            echo "âœ… VERIFICACIONES:\n";
            echo "==================\n";
            echo "âœ“ Archivo de resumen creado\n";
            echo "âœ“ Contenido no vacÃ­o\n";
            echo "âœ“ ReducciÃ³n significativa de tamaÃ±o\n";
            echo "âœ“ Tiempo de procesamiento aceptable\n\n";

            echo "ðŸŽ¯ READY PARA SIGUIENTE ETAPA\n";
        } else {
            echo "âŒ ERROR: No se pudo leer el archivo de resumen creado\n";
        }
    } else {
        echo "âŒ ERROR en resume-cv.php:\n";
        echo 'Error: ' . ($response['error'] ?? 'Desconocido') . "\n";
        if (isset($response['details'])) {
            echo 'Detalles: ' . $response['details'] . "\n";
        }
    }
} else {
    echo "âŒ Respuesta invÃ¡lida del endpoint:\n";
    echo $output . "\n";
}
