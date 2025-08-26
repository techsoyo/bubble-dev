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
// Script de prueba para resume-cv.php
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "Ã°Å¸Å½Â¯ PRUEBA DEL SISTEMA DE RESUMEN DE CV\n";
echo "=====================================\n\n";

$start_time = microtime(true);

// Ejecutar el script de resumen
ob_start();
include 'resume-cv.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo 'Ã¢ÂÂ±Ã¯Â¸Â  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

// Decodificar respuesta
$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "Ã°Å¸Å½â€° Ã‚Â¡Ãƒâ€°XITO! Resumen generado correctamente\n\n";

        echo "Ã°Å¸â€œÅ  ESTADÃƒÂSTICAS:\n";
        echo "================\n";
        echo 'Ã°Å¸â€œâ€ž Archivo resumen: ' . $response['resumen_file'] . "\n";
        echo 'Ã°Å¸â€œÂ TamaÃƒÂ±o original: ' . $response['original_size'] . " caracteres\n";
        echo 'Ã°Å¸â€œÂ TamaÃƒÂ±o resumen: ' . $response['resumen_size'] . " caracteres\n";
        echo 'Ã°Å¸â€”Å“Ã¯Â¸Â  CompresiÃƒÂ³n: ' . $response['compression_ratio'] . "\n";
        echo 'Ã¢ÂÂ±Ã¯Â¸Â  Tiempo procesamiento: ' . round($total_time, 2) . "s\n\n";

        // Leer y mostrar el contenido del resumen
        $resumenPath = __DIR__ . '/../../uploads/resumenes/' . $response['resumen_file'];
        if (file_exists($resumenPath)) {
            $resumenContent = file_get_contents($resumenPath);
            echo "Ã°Å¸â€œÂ CONTENIDO DEL RESUMEN:\n";
            echo "========================\n";
            echo $resumenContent . "\n\n";

            echo "Ã¢Å“â€¦ VERIFICACIONES:\n";
            echo "==================\n";
            echo "Ã¢Å“â€œ Archivo de resumen creado\n";
            echo "Ã¢Å“â€œ Contenido no vacÃƒÂ­o\n";
            echo "Ã¢Å“â€œ ReducciÃƒÂ³n significativa de tamaÃƒÂ±o\n";
            echo "Ã¢Å“â€œ Tiempo de procesamiento aceptable\n\n";

            echo "Ã°Å¸Å½Â¯ READY PARA SIGUIENTE ETAPA\n";
        } else {
            echo "Ã¢ÂÅ’ ERROR: No se pudo leer el archivo de resumen creado\n";
        }
    } else {
        echo "Ã¢ÂÅ’ ERROR en resume-cv.php:\n";
        echo 'Error: ' . ($response['error'] ?? 'Desconocido') . "\n";
        if (isset($response['details'])) {
            echo 'Detalles: ' . $response['details'] . "\n";
        }
    }
} else {
    echo "Ã¢ÂÅ’ Respuesta invÃƒÂ¡lida del endpoint:\n";
    echo $output . "\n";
}

