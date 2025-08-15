<?php
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}

// Forzar una petición POST al endpoint
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];

// Datos JSON válidos de prueba
$validData = [
  'nombre' => 'Juan Pérez',
  'email' => 'juan.perez@local',
  'telefono' => '+34 666 777 888',
  'ubicacion_actual' => 'Madrid, España',
  'data_source' => 'ai_processing',
  'puestos_anteriores' => [
    [
      'puesto' => 'Desarrollador Senior',
      'empresa' => 'TechCorp',
      'fecha_inicio' => '2020-01-15',
      'fecha_fin' => '2024-03-30',
      'descripcion' => 'Desarrollo de aplicaciones web'
    ]
  ]
];

// Input de PHP para pruebas
$inputData = json_encode($validData);
file_put_contents('php://input', $inputData);

// Capturar el input de prueba
$GLOBALS['__test_input'] = $inputData;

echo "=== PRUEBA POST (ENTORNO DE DESARROLLO) ===\n";
echo "Datos enviados:\n";
echo json_encode($validData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "Respuesta del endpoint:\n";

// Incluir el endpoint con datos de prueba
ob_start();

// Modificar el endpoint para usar datos de prueba
$originalInput = file_get_contents('php://input');
if (isset($GLOBALS['__test_input'])) {
  $input = $GLOBALS['__test_input'];
} else {
  $input = $originalInput;
}

require_once __DIR__ . '/autoload.php';

// Fallback si el autoload no funciona
if (!class_exists('Domain\\CvSchema')) {
  require_once __DIR__ . '/src/Domain/CvSchema.php';
}

use Domain\CvSchema;

try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $GLOBALS['__test_input'] ?? file_get_contents('php://input');

    if (empty($input)) {
      throw new Exception('No se recibieron datos JSON');
    }

    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new Exception('JSON inválido: ' . json_last_error_msg());
    }

    // Normalizar los datos
    $normalized = CvSchema::normalize($data);

    // Validar estructura
    $errors = CvSchema::validate($normalized);

    $response = [
      'success' => true,
      'data' => [
        'normalized' => $normalized,
        'validation_errors' => $errors,
        'is_valid' => empty($errors)
      ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  } else {
    throw new Exception('Método no permitido');
  }
} catch (Exception $e) {
  $response = [
    'success' => false,
    'error' => [
      'code' => 'VALIDATION_ERROR',
      'message' => $e->getMessage()
    ]
  ];

  header('Content-Type: application/json');
  http_response_code(400);
  echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

$output = ob_get_clean();
echo $output;
