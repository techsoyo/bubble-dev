<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

/**
 * Test completo del sistema IA en GitHub Codespaces
 * Verifica conectividad, matching y performance con GPU
 */

echo "🚀 BUBBLE OF TALENTS - TEST CODESPACES IA\n";
echo "==========================================\n\n";

use Services\CodespacesService;
use Services\MatchingService;

try {
  // 1. Verificar configuración
  echo "1. 📋 CONFIGURACIÓN ACTUAL:\n";
  $provider = getenv('AI_PROVIDER');
  $apiUrl = getenv('CODESPACES_AI_URL');
  $timeout = getenv('CODESPACES_TIMEOUT_MS');

  echo "   - Provider: {$provider}\n";
  echo "   - API URL: {$apiUrl}\n";
  echo "   - Timeout: {$timeout}ms\n\n";

  if ($provider !== 'codespaces') {
    echo "⚠️  NOTA: AI_PROVIDER configurado como '{$provider}'\n";
    echo "   Para usar Codespaces, configurar AI_PROVIDER=codespaces\n\n";
  }

  // 2. Test de conectividad
  echo "2. 🔗 TEST CONECTIVIDAD CODESPACES:\n";
  $codespacesService = new CodespacesService();
  $connectionTest = $codespacesService->testConnection();

  if ($connectionTest['success']) {
    echo "   ✅ Servidor IA accesible ({$connectionTest['response_time_ms']}ms)\n";
    echo "   📝 Modelo: {$connectionTest['model']}\n";
    echo "   🎮 GPU: " . ($connectionTest['gpu'] ? "Disponible" : "No disponible") . "\n";
    echo "   📊 Estado: {$connectionTest['status']}\n";
  } else {
    echo "   ❌ Error de conectividad: {$connectionTest['error']}\n";
    echo "   ⏱️  Tiempo: {$connectionTest['response_time_ms']}ms\n";
    echo "\n💡 SOLUCIONES:\n";
    echo "   1. Iniciar servidor IA: python ai_server_codespaces.py\n";
    echo "   2. Verificar CODESPACES_AI_URL en .env\n";
    echo "   3. Si estás en Codespaces local, usar puerto forwarding\n";
  }
  echo "\n";

  // 3. Test de matching (solo si conectividad OK)
  if ($connectionTest['success']) {
    echo "3. 🧠 TEST MATCHING CON IA:\n";

    // Datos de prueba realistas
    $testCandidate = [
      'id' => 'test-codespaces-001',
      'name' => 'Ana López',
      'hard_skills' => ['PHP', 'Laravel', 'MySQL', 'JavaScript', 'Vue.js', 'Docker', 'Git'],
      'experience' => [
        ['title' => 'Desarrolladora PHP Senior', 'company' => 'TechCorp', 'duration' => '3 años'],
        ['title' => 'Desarrolladora Full Stack', 'company' => 'InnovateSoft', 'duration' => '2 años'],
        ['title' => 'Junior Developer', 'company' => 'StartupXYZ', 'duration' => '1 año']
      ],
      'education' => [
        ['title' => 'Máster en Desarrollo Web', 'institution' => 'Universidad Politécnica'],
        ['title' => 'Grado en Informática', 'institution' => 'Universidad Complutense']
      ],
      'languages' => ['Español', 'Inglés', 'Francés básico']
    ];

    $testJob = [
      'title' => 'Lead Developer PHP',
      'level' => 'Senior',
      'required_skills' => ['PHP', 'Laravel', 'MySQL', 'API REST', 'Docker', 'Team Leadership'],
      'location' => 'Madrid (Remoto)',
      'experience_required' => '4+ años',
      'type' => 'Full-time'
    ];

    // Test matching individual
    $start = microtime(true);
    $matchingResult = $codespacesService->generateMatchingScore($testCandidate, $testJob);
    $duration = round((microtime(true) - $start) * 1000);

    if ($matchingResult) {
      echo "   ✅ Matching completado en {$duration}ms\n";
      echo "   📊 Score general: {$matchingResult['overall_score']}/100\n";
      echo "   🎯 Skills match: {$matchingResult['skills_match']}/100\n";
      echo "   💼 Experience match: {$matchingResult['experience_match']}/100\n";
      echo "   🎓 Education match: " . ($matchingResult['education_match'] ?? 'N/A') . "/100\n";
      echo "   💡 Recomendación: {$matchingResult['recommendation']}\n";
      echo "   📝 Explicación: " . substr($matchingResult['explanation'], 0, 100) . "...\n";

      // Verificar calidad de la respuesta
      if ($matchingResult['overall_score'] >= 70 && $matchingResult['recommendation'] !== 'REJECT') {
        echo "   ✅ Calidad de matching: EXCELENTE\n";
      } elseif ($matchingResult['overall_score'] >= 50) {
        echo "   ⚠️  Calidad de matching: ACEPTABLE\n";
      } else {
        echo "   ❌ Calidad de matching: BAJA (revisar prompt)\n";
      }
    } else {
      echo "   ❌ Error en matching - revisar logs del servidor IA\n";
    }
    echo "\n";

    // 4. Test batch matching
    echo "4. 📦 TEST BATCH MATCHING:\n";

    $batchCandidates = [
      $testCandidate,
      [
        'id' => 'test-002',
        'name' => 'Carlos Ruiz',
        'hard_skills' => ['Java', 'Spring', 'PostgreSQL', 'React'],
        'experience' => [['title' => 'Java Developer', 'duration' => '2 años']],
        'education' => [['title' => 'Grado en Informática']],
        'languages' => ['Español', 'Inglés']
      ],
      [
        'id' => 'test-003',
        'name' => 'María García',
        'hard_skills' => ['PHP', 'Laravel', 'MySQL', 'JavaScript', 'React', 'Node.js'],
        'experience' => [
          ['title' => 'Full Stack Developer', 'duration' => '4 años'],
          ['title' => 'Senior PHP Developer', 'duration' => '2 años']
        ],
        'education' => [['title' => 'Ingeniería Informática']],
        'languages' => ['Español', 'Inglés', 'Alemán']
      ]
    ];

    $batchStart = microtime(true);
    $batchResults = $codespacesService->rankCandidates($batchCandidates, $testJob);
    $batchDuration = round((microtime(true) - $batchStart) * 1000);

    if ($batchResults) {
      echo "   ✅ Batch processing completado en {$batchDuration}ms\n";
      echo "   📊 Candidatos procesados: " . count($batchResults) . "\n";
      echo "   ⚡ Tiempo promedio por candidato: " . round($batchDuration / count($batchResults)) . "ms\n\n";

      echo "   🏆 RANKING DE CANDIDATOS:\n";
      foreach ($batchResults as $index => $result) {
        $pos = $index + 1;
        $score = $result['matching']['overall_score'];
        $name = $result['candidate_id'];
        $recommendation = $result['matching']['recommendation'];
        echo "      {$pos}. {$name} - {$score}/100 ({$recommendation})\n";
      }
    } else {
      echo "   ❌ Error en batch matching\n";
    }
    echo "\n";

    // 5. Test performance y estabilidad
    echo "5. ⚡ TEST PERFORMANCE:\n";
    $performanceTests = [];

    for ($i = 1; $i <= 5; $i++) {
      $start = microtime(true);
      $result = $codespacesService->generateMatchingScore($testCandidate, $testJob);
      $time = round((microtime(true) - $start) * 1000);
      $performanceTests[] = $time;

      if ($result) {
        echo "   Test {$i}/5: {$time}ms ✅\n";
      } else {
        echo "   Test {$i}/5: {$time}ms ❌\n";
      }
    }

    $avgTime = round(array_sum($performanceTests) / count($performanceTests));
    $minTime = min($performanceTests);
    $maxTime = max($performanceTests);

    echo "\n   📊 ESTADÍSTICAS:\n";
    echo "      - Tiempo promedio: {$avgTime}ms\n";
    echo "      - Tiempo mínimo: {$minTime}ms\n";
    echo "      - Tiempo máximo: {$maxTime}ms\n";
    echo "      - Estabilidad: " . (($maxTime - $minTime) < 1000 ? "ESTABLE" : "VARIABLE") . "\n";
  }

  echo "\n6. 📋 RECOMENDACIONES:\n";
  echo "   💡 Para desarrollo: Usar Codespaces con GPU para testing real\n";
  echo "   💡 Para producción: Configurar AI_PROVIDER=openai para cliente\n";
  echo "   💡 Fallback: Sistema incluye matching algorítmico automático\n";
  echo "   💡 Documentación: Ver SETUP_CLIENTE_PRODUCCION.md\n";

  if ($connectionTest['success'] && isset($matchingResult) && $matchingResult) {
    echo "\n✅ SISTEMA IA CODESPACES: OPERATIVO Y VALIDADO\n";
  } else {
    echo "\n⚠️  SISTEMA IA CODESPACES: REQUIERE CONFIGURACIÓN\n";
  }
} catch (Exception $e) {
  echo "❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
  echo "📍 Archivo: " . $e->getFile() . " línea " . $e->getLine() . "\n";
}

echo "\n==========================================\n";
echo "Test completado: " . date('Y-m-d H:i:s') . "\n";
