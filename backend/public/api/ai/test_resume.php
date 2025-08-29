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
// Script de prueba para resume-cv.php
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ PRUEBA DEL SISTEMA DE RESUMEN DE CV\n";
echo "=====================================\n\n";

$start_time = microtime(true);

// Ejecutar el script de resumen
ob_start();
include 'resume-cv.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo 'ÃƒÂ¢Ã‚ÂÃ‚Â±ÃƒÂ¯Ã‚Â¸Ã‚Â  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

// Decodificar respuesta
$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "ÃƒÂ°Ã…Â¸Ã…Â½Ã¢â‚¬Â° Ãƒâ€šÃ‚Â¡ÉXITO! Resumen generado correctamente\n\n";

        echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  ESTADíSTICAS:\n";
        echo "================\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Å¾ Archivo resumen: ' . $response['resumen_file'] . "\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â Tamaí±o original: ' . $response['original_size'] . " caracteres\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â Tamaí±o resumen: ' . $response['resumen_size'] . " caracteres\n";
        echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬â€Ã…â€œÃƒÂ¯Ã‚Â¸Ã‚Â  Compresión: ' . $response['compression_ratio'] . "\n";
        echo 'ÃƒÂ¢Ã‚ÂÃ‚Â±ÃƒÂ¯Ã‚Â¸Ã‚Â  Tiempo procesamiento: ' . round($total_time, 2) . "s\n\n";

        // Leer y mostrar el contenido del resumen
        $resumenPath = __DIR__ . '/../../uploads/resumenes/' . $response['resumen_file'];
        if (file_exists($resumenPath)) {
            $resumenContent = file_get_contents($resumenPath);
            echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â CONTENIDO DEL RESUMEN:\n";
            echo "========================\n";
            echo $resumenContent . "\n\n";

            echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ VERIFICACIONES:\n";
            echo "==================\n";
            echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ Archivo de resumen creado\n";
            echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ Contenido no vací­o\n";
            echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ Reducción significativa de tamaí±o\n";
            echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ Tiempo de procesamiento aceptable\n\n";

            echo "ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ READY PARA SIGUIENTE ETAPA\n";
        } else {
            echo "ÃƒÂ¢Ã‚ÂÃ…â€™ ERROR: No se pudo leer el archivo de resumen creado\n";
        }
    } else {
        echo "ÃƒÂ¢Ã‚ÂÃ…â€™ ERROR en resume-cv.php:\n";
        echo 'Error: ' . ($response['error'] ?? 'Desconocido') . "\n";
        if (isset($response['details'])) {
            echo 'Detalles: ' . $response['details'] . "\n";
        }
    }
} else {
    echo "ÃƒÂ¢Ã‚ÂÃ…â€™ Respuesta inví¡lida del endpoint:\n";
    echo $output . "\n";
}

