<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

/**
 * Test de verificación del modelo Ollama optimizado para desarrollo
 * Verifica que el modelo CodeLlama funciona correctamente para matching CV
 */

echo "🔍 TEST OLLAMA DESARROLLO - Modelo Optimizado\n";
echo "=========================================\n\n";

try {
  // 1. Verificar configuración
  $provider = getenv('AI_PROVIDER');
  $model = getenv('OLLAMA_MODEL');
  $url = getenv('OLLAMA_API_URL');

  echo "1. 📋 CONFIGURACIÓN:\n";
  echo "   - Proveedor: {$provider}\n";
  echo "   - Modelo: {$model}\n";
  echo "   - URL: {$url}\n\n";

  if ($provider !== 'ollama') {
    echo "⚠️  ADVERTENCIA: AI_PROVIDER no está configurado como 'ollama'\n";
    echo "   Configurar AI_PROVIDER=ollama en .env para desarrollo\n\n";
  }

  // 2. Test de conectividad
  echo "2. 🔗 TEST CONECTIVIDAD:\n";
  $ch = curl_init($url . '/tags');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode === 200) {
    echo "   ✅ Ollama server accesible\n";
    $tags = json_decode($response, true);
    $modelExists = false;
    foreach ($tags['models'] ?? [] as $modelInfo) {
      if ($modelInfo['name'] === $model) {
        $modelExists = true;
        $size = round($modelInfo['size'] / (1024 * 1024 * 1024), 1);
        echo "   ✅ Modelo {$model} disponible ({$size}GB)\n";
        break;
      }
    }
    if (!$modelExists) {
      echo "   ❌ Modelo {$model} no encontrado\n";
      echo "   💡 Instalar con: ollama pull {$model}\n";
    }
  } else {
    echo "   ❌ Ollama server no accesible (HTTP {$httpCode})\n";
    echo "   💡 Iniciar con: ollama serve\n";
  }
  echo "\n";

  // 3. Test de matching simple
  if ($httpCode === 200) {
    echo "3. 🧠 TEST MATCHING IA:\n";

    $payload = [
      'model' => $model,
      'prompt' => 'Analiza este CV simple y dame un score de 0-100 para un trabajo de programador PHP:

CV: Juan Pérez, 3 años experiencia PHP, Laravel, MySQL, JavaScript.
TRABAJO: Desarrollador PHP Senior, Laravel required, 2+ años experiencia.

Responde SOLO con formato JSON: {"score": número, "reason": "texto breve"}',
      'stream' => false,
      'options' => [
        'temperature' => 0.1,
        'num_predict' => 200
      ]
    ];

    $start = microtime(true);
    $ch = curl_init($url . '/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $duration = round((microtime(true) - $start) * 1000);
    curl_close($ch);

    if ($httpCode === 200) {
      $data = json_decode($response, true);
      if ($data && isset($data['response'])) {
        echo "   ✅ Respuesta generada en {$duration}ms\n";
        echo "   📝 Output: " . substr($data['response'], 0, 150) . "...\n";

        // Intentar parsear JSON de la respuesta
        preg_match('/\{[^}]*"score"[^}]*\}/', $data['response'], $matches);
        if (!empty($matches)) {
          $jsonMatch = json_decode($matches[0], true);
          if ($jsonMatch && isset($jsonMatch['score'])) {
            echo "   ✅ Score extraído: {$jsonMatch['score']}/100\n";
            echo "   ✅ Razón: " . ($jsonMatch['reason'] ?? 'N/A') . "\n";
          }
        }
      } else {
        echo "   ❌ Respuesta malformada\n";
      }
    } else {
      echo "   ❌ Error en generación (HTTP {$httpCode})\n";
    }
  }

  echo "\n4. 📊 RECOMENDACIONES:\n";
  echo "   💡 Para desarrollo: Usar AI_PROVIDER=ollama con codellama\n";
  echo "   💡 Para producción: Cambiar a AI_PROVIDER=openai\n";
  echo "   💡 Fallback algorítmico siempre disponible sin IA\n";
  echo "   💡 Consultar SETUP_CLIENTE_PRODUCCION.md para cliente\n\n";
} catch (Exception $e) {
  echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "=========================================\n";
echo "Test completado: " . date('Y-m-d H:i:s') . "\n";
