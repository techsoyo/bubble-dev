<?php

if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}
// Test final con timeout aumentado
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "🚀 PRUEBA FINAL CON TIMEOUT AUMENTADO\n";
echo "====================================\n\n";

$start_time = microtime(true);

// Capturar la salida del parse-cv-file.php actualizado
ob_start();
include 'parse-cv-file.php';
$output = ob_get_clean();

$total_time = microtime(true) - $start_time;

echo '⏱️  TIEMPO TOTAL: ' . round($total_time, 2) . " segundos\n\n";

$response = json_decode($output, true);

if ($response && isset($response['status'])) {
    if ($response['status'] === 'ok') {
        echo "🎉 ¡ÉXITO! parse-cv-file.php funcionó correctamente\n";
        echo "📊 ESTADÍSTICAS:\n";
        echo "================\n";
        echo '⏱️  Tiempo de procesamiento: ' . round($total_time, 2) . "s\n";
        echo '📏 Longitud de respuesta: ' . strlen($response['ollama_response']) . " caracteres\n\n";

        // Intentar parsear la respuesta de Ollama
        $cvData = json_decode($response['ollama_response'], true);
        if ($cvData) {
            echo "✅ Respuesta de Ollama es JSON válido\n";
            echo '🎯 NOMBRE EXTRAÍDO: ' . ($cvData['nombre'] ?? 'No detectado') . "\n";
            echo '🎯 EMAIL: ' . ($cvData['email'] ?? 'No detectado') . "\n";
            echo '🎯 CATEGORÍA: ' . ($cvData['categoria'] ?? 'No detectada') . "\n";
            echo '🎯 SUBCATEGORÍA: ' . ($cvData['subcategoria'] ?? 'No detectada') . "\n";

            if (isset($cvData['puestos_anteriores']) && is_array($cvData['puestos_anteriores'])) {
                echo '💼 PUESTOS ANTERIORES: ' . count($cvData['puestos_anteriores']) . " registros\n";
            }

            if (isset($cvData['tecnologias_herramientas']) && is_array($cvData['tecnologias_herramientas'])) {
                echo '🔧 TECNOLOGÍAS: ' . count($cvData['tecnologias_herramientas']) . " encontradas\n";
            }
        } else {
            echo "⚠️ La respuesta de Ollama no es JSON puro\n";
            echo "🔍 Primeros 200 caracteres:\n";
            echo substr($response['ollama_response'], 0, 200) . "...\n";
        }

        echo "\n✅ PASO 2 COMPLETADO EXITOSAMENTE\n";
        echo "🎯 READY PARA PASO 3: Limpiar y validar JSON\n";
    } else {
        echo "❌ ERROR en parse-cv-file.php:\n";
        echo 'Error: ' . ($response['error'] ?? 'Desconocido') . "\n";
        if (isset($response['timeout'])) {
            echo "⚠️ CAUSA: Timeout - el modelo tardó demasiado\n";
            echo "💡 SOLUCIÓN: Usar un modelo más pequeño o implementar streaming\n";
        }
    }
} else {
    echo "❌ Respuesta inválida del endpoint:\n";
    echo $output . "\n";
}
