<?php
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}

require_once 'autoload.php';
require_once 'src/Domain/CvSchema.php';

use Domain\CvSchema;

// Datos de prueba para validar el schema
$testData = [
  'nombre' => 'Juan Pérez',
  'email' => 'juan.perez@local',
  'telefono' => '+34 666 777 888',
  'ubicacion_actual' => 'Madrid, España',
  'fecha_nacimiento' => '1990-05-15',
  'portfolio' => 'https://juanperez.dev',
  'linkedin' => 'https://linkedin.com/in/juanperez',
  'otras_redes' => ['https://github.com/juanperez', 'https://twitter.com/juanperez'],
  'resumen_profesional' => 'Desarrollador Full Stack con 5 años de experiencia...',
  'soft_skills' => ['Comunicación', 'Trabajo en equipo', 'Liderazgo'],
  'hard_skills' => ['JavaScript', 'PHP', 'React', 'MySQL'],
  'idiomas' => [
    ['idioma' => 'Español', 'nivel' => 'Nativo'],
    ['idioma' => 'Inglés', 'nivel' => 'B2']
  ],
  'intereses' => ['Tecnología', 'Deportes', 'Lectura'],
  'referencias' => 'Disponibles bajo petición',
  'disponibilidad' => 'Inmediata',
  'data_source' => 'ai_processing',
  'puestos_anteriores' => [
    [
      'puesto' => 'Desarrollador Senior',
      'empresa' => 'TechCorp',
      'fecha_inicio' => '2020-01-15',
      'fecha_fin' => '2024-03-30',
      'descripcion' => 'Desarrollo de aplicaciones web',
      'responsabilidades' => ['Liderazgo técnico', 'Mentoring'],
      'ubicacion' => 'Madrid',
      'actual' => false
    ]
  ],
  'educacion' => [
    [
      'titulo' => 'Ingeniería Informática',
      'campo_estudio' => 'Informática',
      'institucion' => 'Universidad Politécnica',
      'fecha_inicio' => '2015-09-01',
      'fecha_fin' => '2019-06-30',
      'nivel_educativo' => 'Grado',
      'descripcion' => 'Especialización en desarrollo web'
    ]
  ],
  'certificaciones_detalle' => [
    [
      'nombre_certificacion' => 'AWS Certified',
      'emisor' => 'Amazon',
      'fecha_emision' => '2023-01-15',
      'fecha_expiracion' => '2026-01-15'
    ]
  ],
  'proyectos' => [
    [
      'nombre' => 'E-commerce Platform',
      'descripcion' => 'Plataforma de comercio electrónico',
      'tecnologias' => ['React', 'Node.js', 'MongoDB'],
      'fecha_inicio' => '2023-01-01',
      'fecha_fin' => '2023-06-30',
      'url' => 'https://github.com/juanperez/ecommerce'
    ]
  ],
  'routing' => [
    'categoria_departamento_id' => 1,
    'departamento_id' => 5,
    'reclutador_id' => 'rec_001',
    'fuente' => 'ai',
    'razon' => 'Perfil técnico sólido',
    'fecha_asignacion' => '2024-08-11 10:30:00'
  ]
];

echo "=== PRUEBA DE DOMAIN\\CvSchema ===\n\n";

// 1. Probar el template
echo "1. TEMPLATE:\n";
echo json_encode(CvSchema::TEMPLATE, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// 2. Probar normalización de datos
echo "2. NORMALIZACIÓN DE DATOS:\n";
echo "Datos originales:\n";
echo json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$normalizedData = CvSchema::normalize($testData);
echo "Datos normalizados:\n";
echo json_encode($normalizedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// 3. Probar validación mínima
echo "3. VALIDACIÓN MÍNIMA:\n";
$validationErrors = CvSchema::validate($normalizedData);
if (empty($validationErrors)) {
  echo "✅ Validación exitosa - todos los campos mínimos están presentes\n\n";
} else {
  echo "❌ Errores de validación:\n";
  foreach ($validationErrors as $error) {
    echo "  - $error\n";
  }
  echo "\n";
}

// 4. Probar con datos inválidos
echo "4. PRUEBA CON DATOS INVÁLIDOS:\n";
$invalidData = [
  'nombre' => '',
  'email' => 'email-invalido',
  'telefono' => '<script>alert("xss")</script>',
  'fecha_nacimiento' => 'fecha-invalida',
  'portfolio' => 'url-invalida'
];

$normalizedInvalid = CvSchema::normalize($invalidData);
echo "Datos inválidos normalizados:\n";
echo json_encode($normalizedInvalid, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$invalidValidationErrors = CvSchema::validate($normalizedInvalid);
echo "Errores de validación para datos inválidos:\n";
foreach ($invalidValidationErrors as $error) {
  echo "  - $error\n";
}

echo "\n=== PRUEBA COMPLETADA ===\n";
