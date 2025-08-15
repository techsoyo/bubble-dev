<?php

/**
 * Test del sistema de matching de candidatos con ofertas
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/src/Services/Matching/JobMatchingService.php';

use Services\Matching\JobMatchingService;

echo "=== PRUEBA DEL SISTEMA DE MATCHING ===\n\n";

try {
  // Datos de ejemplo de un candidato (como los que procesa la IA)
  $candidateData = [
    'nombre' => 'María García Pérez',
    'email' => 'maria.garcia@email.com',
    'hard_skills' => ['JavaScript', 'React', 'Node.js', 'TypeScript', 'PostgreSQL', 'AWS', 'Docker'],
    'soft_skills' => ['Liderazgo', 'Trabajo en equipo', 'Comunicación'],
    'idiomas' => [
      ['idioma' => 'Español', 'nivel' => 'Nativo'],
      ['idioma' => 'Inglés', 'nivel' => 'C1'],
      ['idioma' => 'Francés', 'nivel' => 'B2']
    ],
    'puestos_anteriores' => [
      [
        'puesto' => 'Desarrolladora Full Stack Senior',
        'empresa' => 'TechCorp Solutions',
        'fecha_inicio' => '2021-01-01',
        'fecha_fin' => '2024-12-31',
        'descripcion' => 'Desarrollo de aplicaciones web con React y Node.js'
      ]
    ],
    'educacion' => [
      [
        'titulo' => 'Ingeniería Informática',
        'institucion' => 'Universidad Politécnica de Madrid',
        'fecha_inicio' => '2015',
        'fecha_fin' => '2019'
      ]
    ]
  ];

  // Datos de ejemplo de una oferta de trabajo
  $jobData = [
    'title' => 'Desarrollador Frontend Senior',
    'description' => 'Buscamos un desarrollador frontend con experiencia en React y TypeScript para unirse a nuestro equipo',
    'requirements' => [
      'Mínimo 3 años de experiencia en desarrollo frontend',
      'Conocimientos avanzados en React, TypeScript y JavaScript',
      'Experiencia con herramientas de testing',
      'Inglés conversacional (B2 mínimo)',
      'Conocimientos en AWS es un plus'
    ],
    'skills_required' => ['React', 'TypeScript', 'JavaScript', 'HTML', 'CSS', 'Testing'],
    'languages_required' => ['Inglés B2'],
    'experience_years' => 3,
    'education_required' => 'Ingeniería Informática o similar'
  ];

  echo "1. Datos del candidato preparados ✓\n";
  echo "   - Skills: " . implode(', ', $candidateData['hard_skills']) . "\n";
  echo "   - Experiencia: " . count($candidateData['puestos_anteriores']) . " puesto(s)\n";
  echo "   - Idiomas: " . count($candidateData['idiomas']) . " idioma(s)\n\n";

  echo "2. Datos de la oferta preparados ✓\n";
  echo "   - Puesto: " . $jobData['title'] . "\n";
  echo "   - Skills requeridas: " . implode(', ', $jobData['skills_required']) . "\n";
  echo "   - Experiencia requerida: " . $jobData['experience_years'] . " años\n\n";

  echo "3. Calculando matching...\n";
  $matchingService = new JobMatchingService();
  $result = $matchingService->evaluateMatch($candidateData, $jobData);

  echo "✅ Matching calculado exitosamente!\n\n";

  echo "🎯 RESULTADOS DEL MATCHING:\n";
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
  echo "📊 Porcentaje de compatibilidad: " . $result['match_percentage'] . "%\n\n";

  echo "💪 FORTALEZAS:\n";
  foreach ($result['strengths'] as $strength) {
    echo "   ✓ $strength\n";
  }

  if (!empty($result['weaknesses'])) {
    echo "\n⚠️  ÁREAS DE MEJORA:\n";
    foreach ($result['weaknesses'] as $weakness) {
      echo "   • $weakness\n";
    }
  }

  echo "\n💡 RECOMENDACIONES:\n";
  foreach ($result['recommendations'] as $recommendation) {
    echo "   → $recommendation\n";
  }

  echo "\n📋 DETALLES DEL ANÁLISIS:\n";
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

  $details = $result['details'];
  echo "🔧 Habilidades técnicas: " . $details['skills_match']['percentage'] . "%\n";
  if (!empty($details['skills_match']['matches'])) {
    echo "   Coincidencias: " . implode(', ', $details['skills_match']['matches']) . "\n";
  }
  if (!empty($details['skills_match']['missing'])) {
    echo "   Faltantes: " . implode(', ', $details['skills_match']['missing']) . "\n";
  }

  echo "\n👔 Experiencia: " . $details['experience_match']['percentage'] . "%\n";
  echo "💡 Educación: " . $details['education_match']['percentage'] . "%\n";
  echo "🌍 Idiomas: " . $details['language_match']['percentage'] . "%\n";

  echo "\n🚀 CONCLUSIÓN:\n";
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

  if ($result['match_percentage'] >= 80) {
    echo "🟢 CANDIDATO ALTAMENTE COMPATIBLE - Proceder con entrevista\n";
  } elseif ($result['match_percentage'] >= 60) {
    echo "🟡 CANDIDATO COMPATIBLE - Revisar detalles y considerar entrevista\n";
  } elseif ($result['match_percentage'] >= 40) {
    echo "🟠 CANDIDATO PARCIALMENTE COMPATIBLE - Necesita desarrollo adicional\n";
  } else {
    echo "🔴 CANDIDATO NO COMPATIBLE - No recomendado para este puesto\n";
  }

  echo "\n✅ SISTEMA DE MATCHING FUNCIONANDO CORRECTAMENTE\n";
  echo "   El análisis de compatibilidad SÍ está implementado y operativo\n";
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "Traza: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";
