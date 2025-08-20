<?php

echo "=== TEST PRODUCCIÓN REAL - ENDPOINT HTTP ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Datos de CV para probar
$cvData = [
  'cv_text' => "Nombre: Javier Rodríguez Martínez
Email: javier.rodriguez@email.com  
Teléfono: +34 633 445 566
Ubicación: Madrid, España
LinkedIn: linkedin.com/in/javierrodriguezmartinez

Resumen Profesional:
Profesional del marketing con experiencia en branding, comunicación y estrategias de fidelización.

Experiencia Laboral:
Jefe de Producto | Consumer Brands S.L. | Mayo 2019 - Presente
- Desarrollo de estrategias de lanzamiento de productos
- Gestión de equipos de marketing y agencias externas
- Análisis de mercado y competencia

Coordinador de Marketing | Retail Solutions | Nov 2016 - Abril 2019  
- Creación de material promocional y publicitario
- Organización de ferias y eventos
- Seguimiento de presupuestos y ROI

Educación:
Máster en Dirección de Marketing | ESIC Business & Marketing School | 2018-2019
Grado en Publicidad y Relaciones Públicas | Universidad Rey Juan Carlos | 2012-2016

Habilidades:
Técnicas: Canva, Adobe Suite
Blandas: Gestión de proyectos, Liderazgo, Trabajo en equipo

Idiomas:
Español (Nativo), Inglés (Avanzado)"
];

// Configurar cURL
$url = 'http://localhost/bubble_of_talents_1.0/backend/public/api/ai/analyze-cv.php';
$headers = [
  'Content-Type: application/json',
  'Accept: application/json'
];

echo "🚀 Enviando petición a: $url\n";
echo "📊 Datos del CV: " . strlen($cvData['cv_text']) . " caracteres\n\n";

$curl = curl_init();
curl_setopt_array($curl, [
  CURLOPT_URL => $url,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode($cvData),
  CURLOPT_HTTPHEADER => $headers,
  CURLOPT_TIMEOUT => 120,
  CURLOPT_VERBOSE => true,
  CURLOPT_STDERR => fopen('php://temp', 'w+')
]);

$startTime = microtime(true);
$response = curl_exec($curl);
$duration = round((microtime(true) - $startTime) * 1000);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

if (curl_errno($curl)) {
  echo "❌ Error cURL: " . curl_error($curl) . "\n";
  curl_close($curl);
  exit(1);
}

curl_close($curl);

echo "⏱️ Tiempo de respuesta: {$duration}ms\n";
echo "📡 Código HTTP: $httpCode\n\n";

if ($httpCode === 200) {
  $result = json_decode($response, true);

  if ($result && isset($result['success']) && $result['success']) {
    echo "✅ ¡ÉXITO! CV analizado correctamente\n\n";

    // Los datos están en structured_data
    $data = $result['data']['structured_data'] ?? [];
    $processingInfo = $result['data']['processing_info'] ?? [];
    echo "📋 CAMPOS PRINCIPALES EXTRAÍDOS:\n";
    echo "===============================\n";

    // Mostrar campos básicos
    $basicFields = ['nombre', 'email', 'telefono', 'ubicacion_actual', 'resumen_profesional'];
    foreach ($basicFields as $field) {
      if (isset($data[$field]) && !empty($data[$field])) {
        echo "✅ " . ucfirst(str_replace('_', ' ', $field)) . ": " . $data[$field] . "\n";
      } else {
        echo "❌ " . ucfirst(str_replace('_', ' ', $field)) . ": NO ENCONTRADO\n";
      }
    }

    echo "\n📊 CAMPOS ESTRUCTURADOS:\n";
    echo "=======================\n";

    // Verificar arrays
    $arrayFields = [
      'hard_skills' => 'Habilidades Técnicas',
      'soft_skills' => 'Habilidades Blandas',
      'puestos_anteriores' => 'Experiencia Laboral',
      'educacion' => 'Educación',
      'idiomas' => 'Idiomas'
    ];

    foreach ($arrayFields as $field => $label) {
      if (isset($data[$field]) && is_array($data[$field])) {
        echo "✅ $label: " . count($data[$field]) . " elemento(s)\n";
        if (!empty($data[$field])) {
          foreach (array_slice($data[$field], 0, 2) as $item) {
            if (is_array($item)) {
              echo "   • " . json_encode($item, JSON_UNESCAPED_UNICODE) . "\n";
            } else {
              echo "   • $item\n";
            }
          }
          if (count($data[$field]) > 2) {
            echo "   ... y " . (count($data[$field]) - 2) . " más\n";
          }
        }
      } else {
        echo "❌ $label: NO ENCONTRADO\n";
      }
    }

    echo "\n🎯 CAMPOS ESPECÍFICOS DEL FORMULARIO:\n";
    echo "===================================\n";

    // Verificar campos específicos para el formulario
    $formFields = [
      'data_source' => 'Fuente de Datos',
      'routing' => 'Información de Routing',
      'certificaciones_detalle' => 'Certificaciones Detalladas',
      'idiomas_detalle' => 'Idiomas Detallados',
      'proyectos' => 'Proyectos',
      'referencias_detalle' => 'Referencias Detalladas',
      'habilidades_adicionales' => 'Habilidades Adicionales'
    ];

    foreach ($formFields as $field => $label) {
      if (isset($data[$field])) {
        if (is_array($data[$field])) {
          echo "✅ $label: " . count($data[$field]) . " elemento(s)\n";
        } else {
          echo "✅ $label: " . (is_string($data[$field]) ? $data[$field] : json_encode($data[$field])) . "\n";
        }
      } else {
        echo "❌ $label: NO ENCONTRADO\n";
      }
    }

    // Guardar resultado completo
    $filename = 'production_endpoint_test_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents(__DIR__ . '/' . $filename, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo "\n💾 Resultado completo guardado en: $filename\n";
    echo "📈 Total de campos en respuesta: " . count($data) . "\n";
  } else {
    echo "❌ ERROR en la respuesta:\n";
    echo $response . "\n";
  }
} else {
  echo "❌ Error HTTP $httpCode\n";
  echo "Respuesta: " . substr($response, 0, 500) . "\n";
}

echo "\n🎉 TEST DE ENDPOINT COMPLETADO\n";
echo "=============================\n";
echo "• Servicio: GroqApiService (Groq API)\n";
echo "• Velocidad: Ultra rápido con chips LPU\n";
echo "• Costo: 100% GRATUITO\n";
echo "• Estado: " . ($httpCode === 200 ? "✅ FUNCIONANDO" : "❌ ERROR") . "\n";
