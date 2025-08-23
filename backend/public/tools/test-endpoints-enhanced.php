<?php
// Configuración para mostrar salida web 
set_time_limit(300); // Aumentar tiempo límite a 5 minutos
ini_set('max_execution_time', 300);
ob_start();
$startTime = microtime(true);
$baseUrl = 'http://localhost:8000/api';

// Configuración de paralelización y auth
define('PARALLEL_GETS', isset($_GET['parallel']) && $_GET['parallel'] == '1');
$authBearer = getenv('AUTH_BEARER') ?: null; // Lee del entorno
// Verificar que el servidor esté corriendo (silencioso para web)
$serverStatus = 'unknown';
$healthData = null;
$healthCheck = @file_get_contents('http://localhost:8000/api/test');
if ($healthCheck === false) {
  $serverStatus = 'warning';
} else {
  $serverStatus = 'ok';
  $healthData = json_decode($healthCheck, true);
}
// Valores de prueba para IDs y paths (ajustados para nuestro proyecto) 
$testIds = [
  'id' => 1, // ID genérico para la mayoría de endpoints
  'applications' => 1,
  'candidates' => 1,
  'companies' => 1,
  'jobs' => 1,
  'job-categories' => 1,
  'users' => 1,
  'notifications' => 1,
  'candidato_id' => 1, // Para applications/{candidato_id}/status 
  'department_id' => 1, // Para jobs/by-department/{department_id} 
  'path' => 'testfile.pdf', // Para files/{path} 
  'conversation_id' => 'test_123',
  'node_id' => 'welcome_node',
  'option_id' => 'opt_1',
  'files' => 'testfile.pdf',
  'chatbot_nodes' => 'node_123',
  'chatbot_conversations' => 'conv_456',
  'departments' => 1,
  'candidate_profiles' => 1,
  'language_code' => 'es', // Para language endpoints
  'pdf_filename' => 'curriculum.pdf', // Para PDF processing 
  'file_paths' => [
    'documents/cv.pdf',
    'images/logo.png',
    'uploads/document.docx',
    'temp/analysis.json'
  ]
];

$endpoints = [
  // 1. ApplicationController (10 endpoints)
  ['method' => 'GET', 'url' => '/applications'],
  ['method' => 'POST', 'url' => '/applications'],
  ['method' => 'GET', 'url' => '/applications/{id}'],
  ['method' => 'PUT', 'url' => '/applications/{id}'],
  ['method' => 'DELETE', 'url' => '/applications/{id}'],
  ['method' => 'PATCH', 'url' => '/applications/{candidato_id}/status'],
  ['method' => 'GET', 'url' => '/applications/table-data'],
  ['method' => 'POST', 'url' => '/applications/save-partial'],
  ['method' => 'PATCH', 'url' => '/applications'],
  ['method' => 'DELETE', 'url' => '/applications'],

  // 2. AuthController (8 endpoints)
  ['method' => 'POST', 'url' => '/auth/login'],
  ['method' => 'POST', 'url' => '/auth/social-login'],
  ['method' => 'GET', 'url' => '/auth/me'],
  ['method' => 'POST', 'url' => '/auth/refresh'],
  ['method' => 'POST', 'url' => '/auth/set-cookie'],
  ['method' => 'POST', 'url' => '/auth/remove-cookie'],
  ['method' => 'GET', 'url' => '/auth/check-session'],

  // 3. CandidateController (8 endpoints)
  ['method' => 'GET', 'url' => '/candidates'],
  ['method' => 'POST', 'url' => '/candidates/register'],
  ['method' => 'GET', 'url' => '/candidates/{id}'],
  ['method' => 'PUT', 'url' => '/candidates/{id}'],
  ['method' => 'DELETE', 'url' => '/candidates/{id}'],
  ['method' => 'POST', 'url' => '/candidates/upload-cv'],
  ['method' => 'GET', 'url' => '/candidates/profile/{id}'],
  ['method' => 'PATCH', 'url' => '/candidates/{id}/status'],

  // === CONTROLADORES ELIMINADOS ===
  // CompanyController eliminado - no aplica para agencia única
  ['method' => 'GET', 'url' => '/companies'],
  ['method' => 'POST', 'url' => '/companies'],
  ['method' => 'GET', 'url' => '/companies/{id}'],
  ['method' => 'PUT', 'url' => '/companies/{id}'],
  ['method' => 'DELETE', 'url' => '/companies/{id}'],

  // 5. JobController (8 endpoints)
  ['method' => 'GET', 'url' => '/jobs'],
  ['method' => 'POST', 'url' => '/jobs'],
  ['method' => 'GET', 'url' => '/jobs/{id}'],
  ['method' => 'PUT', 'url' => '/jobs/{id}'],
  ['method' => 'DELETE', 'url' => '/jobs/{id}'],
  ['method' => 'GET', 'url' => '/jobs/available'],
  ['method' => 'POST', 'url' => '/jobs/search'],
  ['method' => 'GET', 'url' => '/jobs/by-department/{department_id}'],

  // 6. JobCategoryController (5 endpoints)
  ['method' => 'GET', 'url' => '/job-categories'],
  ['method' => 'POST', 'url' => '/job-categories'],
  ['method' => 'GET', 'url' => '/job-categories/{id}'],
  ['method' => 'PUT', 'url' => '/job-categories/{id}'],
  ['method' => 'DELETE', 'url' => '/job-categories/{id}'],

  // 7. ChatbotController (5 endpoints)
  ['method' => 'GET', 'url' => '/chatbot/data'],
  ['method' => 'GET', 'url' => '/chatbot/node'],
  ['method' => 'POST', 'url' => '/chatbot/node'],
  ['method' => 'POST', 'url' => '/chatbot/interaction'],
  ['method' => 'GET', 'url' => '/chatbot/analytics'],

  // 8. CVController (4 endpoints)
  ['method' => 'POST', 'url' => '/cv/analyze-file'],
  ['method' => 'POST', 'url' => '/cv/analyze-text'],
  ['method' => 'POST', 'url' => '/cv/extract-text'],
  ['method' => 'POST', 'url' => '/cv/process'],

  // 9. AIController (8 endpoints)
  ['method' => 'GET', 'url' => '/ai/health'],
  ['method' => 'POST', 'url' => '/ai/analyze-pdf'],
  ['method' => 'POST', 'url' => '/ai/parse-cv-file'],
  ['method' => 'POST', 'url' => '/ai/parse-cv'],
  ['method' => 'POST', 'url' => '/ai/calculate-matching'],
  ['method' => 'POST', 'url' => '/ai/chatbot'],
  ['method' => 'POST', 'url' => '/ai/analyze-personality'],
  ['method' => 'POST', 'url' => '/ai/predict-performance'],

  // 10. UserController (5 endpoints)
  ['method' => 'GET', 'url' => '/users'],
  ['method' => 'POST', 'url' => '/users'],
  ['method' => 'GET', 'url' => '/users/{id}'],
  ['method' => 'PUT', 'url' => '/users/{id}'],
  ['method' => 'DELETE', 'url' => '/users/{id}'],

  // 11. NotificationController (4 endpoints)
  ['method' => 'GET', 'url' => '/notifications'],
  ['method' => 'POST', 'url' => '/notifications'],
  ['method' => 'PATCH', 'url' => '/notifications/{id}/read'],
  ['method' => 'DELETE', 'url' => '/notifications/{id}'],

  // 12. AdminController (3 endpoints)
  ['method' => 'GET', 'url' => '/admin/dashboard'],
  ['method' => 'GET', 'url' => '/admin/stats'],
  ['method' => 'POST', 'url' => '/admin/bulk-actions'],

  // 13. UploadController (1 endpoint)
  ['method' => 'POST', 'url' => '/upload'],

  // 14. FileController (2 endpoints)
  ['method' => 'GET', 'url' => '/files/{path}'],
  ['method' => 'DELETE', 'url' => '/files/{path}'],

  // 15. HealthController (1 endpoint)
  ['method' => 'GET', 'url' => '/health'],

  // 16. TestController (1 endpoint)
  ['method' => 'GET', 'url' => '/test'],

  // 17. LanguageController (2 endpoints)
  ['method' => 'GET', 'url' => '/language'],
  ['method' => 'POST', 'url' => '/language'],

  // 18. PDFController (1 endpoint)
  ['method' => 'POST', 'url' => '/pdf/parse']
];

// Payloads más realistas para POST, PUT y PATCH según nuestros controladores 
$payloads = [
  'POST' => json_encode([
    'test' => 'data',
    'name' => 'Test Entry',
    'email' => 'test@example.com',
    'status' => 'active'
  ]),
  'PUT' => json_encode([
    'id' => 1,
    'name' => 'Updated Test Entry',
    'status' => 'updated'
  ]),
  'PATCH' => json_encode([
    'status' => 'modified',
    'updated_at' => date('Y-m-d H:i:s')
  ])
];

// Función auxiliar para rellenar IDs en URL 
function fillUrl($url, $testIds)
{
  foreach ($testIds as $key => $value) {
    if (strpos($url, '{' . $key . '}') !== false) {
      $url = str_replace('{' . $key . '}', $value, $url);
    }
  }
  // Reemplazar otros {id} genéricos con primer valor válido 
  if (preg_match('/\{[^\}]+\}/', $url)) {
    $url = preg_replace('/\{[^\}]+\}/', reset($testIds), $url);
  }
  return $url;
}

// Mejorar la función makeRequest con mejor manejo de errores y auth Bearer
function makeRequest($url, $method, $payload = null, $authBearer = null)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  // Timeout de 5 segundos 
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

  $headers = [
    'Content-Type: application/json',
    'Accept: application/json'
  ];

  // Agregar Authorization header si hay token
  if ($authBearer) {
    $headers[] = 'Authorization: Bearer ' . $authBearer;
  }

  if (in_array($method, ['POST', 'PUT', 'PATCH']) && $payload) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $headers[] = 'Content-Length: ' . strlen($payload);
  }

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  $response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if (curl_errno($ch)) {
    $error = 'Curl error: ' . curl_error($ch);
    curl_close($ch);
    return ['error' => $error, 'status' => 0];
  }
  curl_close($ch);
  return ['status' => $status, 'response' => $response];
}

// Contadores para estadísticas 
$totalTests = 0;
$successfulTests = 0;
$warningTests = 0;
$errorTests = 0;
$results = [];
$logEntries = [];

// Función para ejecutar GETs en paralelo
function executeParallelGets($endpoints, $baseUrl, $testIds, $authBearer = null)
{
  $getEndpoints = array_filter($endpoints, function ($ep) {
    return $ep['method'] === 'GET';
  });
  $multiHandle = curl_multi_init();
  $curlHandles = [];
  $results = [];

  foreach ($getEndpoints as $index => $endpoint) {
    $url = fillUrl($endpoint['url'], $testIds);
    $fullUrl = $baseUrl . $url;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($authBearer) {
      $headers[] = 'Authorization: Bearer ' . $authBearer;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    curl_multi_add_handle($multiHandle, $ch);
    $curlHandles[$index] = $ch;
  }

  // Ejecutar todas las peticiones en paralelo
  $running = null;
  do {
    curl_multi_exec($multiHandle, $running);
    curl_multi_select($multiHandle);
  } while ($running > 0);

  // Recoger resultados
  foreach ($curlHandles as $index => $ch) {
    $response = curl_multi_getcontent($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
      $results[$index] = ['error' => curl_error($ch), 'status' => 0];
    } else {
      $results[$index] = ['status' => $status, 'response' => $response];
    }

    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
  }

  curl_multi_close($multiHandle);
  return $results;
}

// Ejecutar todos los tests 
if (PARALLEL_GETS) {
  // Modo paralelo: ejecutar GETs en paralelo, luego mutadores secuenciales
  $parallelResults = [];
  $getStartTime = microtime(true);

  // Ejecutar GETs en paralelo
  $getEndpoints = array_filter($endpoints, function ($ep, $idx) {
    return $ep['method'] === 'GET';
  }, ARRAY_FILTER_USE_BOTH);

  if (!empty($getEndpoints)) {
    $parallelResults = executeParallelGets($getEndpoints, $baseUrl, $testIds, $authBearer);
  }

  $getEndTime = microtime(true);

  // Procesar todos los endpoints (GETs con resultados paralelos, mutadores secuenciales)
  foreach ($endpoints as $index => $endpoint) {
    $totalTests++;
    $testStartTime = microtime(true);
    $url = fillUrl($endpoint['url'], $testIds);
    $fullUrl = $baseUrl . $url;
    $method = $endpoint['method'];
    $payload = $payloads[$method] ?? null;

    // Usar resultado paralelo para GETs o ejecutar secuencialmente para mutadores
    if ($method === 'GET' && isset($parallelResults[$index])) {
      $result = $parallelResults[$index];
      $testEndTime = $getEndTime; // Tiempo compartido para GETs paralelos
    } else {
      $result = makeRequest($fullUrl, $method, $payload, $authBearer);
      $testEndTime = microtime(true);
    }

    $testDuration = round(($testEndTime - $testStartTime) * 1000, 2);

    // [Resto del procesamiento de resultados permanece igual]
    // Categorizar resultado 
    $category = 'error';
    $statusText = 'Error';
    $responsePreview = '';
    if (isset($result['error'])) {
      $errorTests++;
      $statusText = 'Connection Error';
      $responsePreview = $result['error'];
    } else {
      $status = $result['status'];
      if ($status >= 200 && $status < 300) {
        $successfulTests++;
        $category = 'success';
        $statusText = "HTTP $status";
      } elseif ($status >= 400 && $status < 500) {
        $warningTests++;
        $category = 'warning';
        $statusText = "HTTP $status";
      } elseif ($status >= 500) {
        $errorTests++;
        $category = 'error';
        $statusText = "HTTP $status";
      } else {
        $warningTests++;
        $category = 'info';
        $statusText = "HTTP $status";
      }

      // Crear preview de la respuesta 
      if (!empty($result['response'])) {
        $responseData = json_decode($result['response'], true);
        if ($responseData) {
          $responsePreview = json_encode($responseData, JSON_PRETTY_PRINT);
        } else {
          $responsePreview = substr($result['response'], 0, 200) . (strlen($result['response']) > 200 ? '...' : '');
        }
      }
    }

    $results[] = [
      'index' => $index + 1,
      'method' => $method,
      'url' => $url,
      'fullUrl' => $fullUrl,
      'status' => $result['status'] ?? 0,
      'statusText' => $statusText,
      'category' => $category,
      'error' => $result['error'] ?? null,
      'response' => $result['response'] ?? null,
      'responsePreview' => $responsePreview,
      'payload' => $payload,
      'duration_ms' => $testDuration
    ];

    // Añadir entrada al log
    $logEntries[] = date('Y-m-d H:i:s') . " | $method | $fullUrl | " . ($result['status'] ?? 0) . " | {$testDuration}ms";
  }
} else {
  // Modo secuencial (comportamiento original)
  foreach ($endpoints as $index => $endpoint) {
    $totalTests++;
    $testStartTime = microtime(true);
    $url = fillUrl($endpoint['url'], $testIds);
    $fullUrl = $baseUrl . $url;
    $method = $endpoint['method'];
    $payload = $payloads[$method] ?? null;
    $result = makeRequest($fullUrl, $method, $payload, $authBearer);
    $testEndTime = microtime(true);
    $testDuration = round(($testEndTime - $testStartTime) * 1000, 2);

    // Categorizar resultado 
    $category = 'error';
    $statusText = 'Error';
    $responsePreview = '';
    if (isset($result['error'])) {
      $errorTests++;
      $statusText = 'Connection Error';
      $responsePreview = $result['error'];
    } else {
      $status = $result['status'];
      if ($status >= 200 && $status < 300) {
        $successfulTests++;
        $category = 'success';
        $statusText = "HTTP $status";
      } elseif ($status >= 400 && $status < 500) {
        $warningTests++;
        $category = 'warning';
        $statusText = "HTTP $status";
      } elseif ($status >= 500) {
        $errorTests++;
        $category = 'error';
        $statusText = "HTTP $status";
      } else {
        $warningTests++;
        $category = 'info';
        $statusText = "HTTP $status";
      }

      // Crear preview de la respuesta 
      if (!empty($result['response'])) {
        $responseData = json_decode($result['response'], true);
        if ($responseData) {
          $responsePreview = json_encode($responseData, JSON_PRETTY_PRINT);
        } else {
          $responsePreview = substr($result['response'], 0, 200) . (strlen($result['response']) > 200 ? '...' : '');
        }
      }
    }

    $results[] = [
      'index' => $index + 1,
      'method' => $method,
      'url' => $url,
      'fullUrl' => $fullUrl,
      'status' => $result['status'] ?? 0,
      'statusText' => $statusText,
      'category' => $category,
      'error' => $result['error'] ?? null,
      'response' => $result['response'] ?? null,
      'responsePreview' => $responsePreview,
      'payload' => $payload,
      'duration_ms' => $testDuration
    ];

    // Añadir entrada al log
    $logEntries[] = date('Y-m-d H:i:s') . " | $method | $fullUrl | " . ($result['status'] ?? 0) . " | {$testDuration}ms";
  }
}

$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

// Generar reportes
$slowestEndpoints = $results;
usort($slowestEndpoints, function ($a, $b) {
  return $b['duration_ms'] <=> $a['duration_ms'];
});
$top5Slowest = array_slice($slowestEndpoints, 0, 5);

// JSON Summary
$summary = [
  'execution_time' => $executionTime,
  'total_tests' => $totalTests,
  'successful_tests' => $successfulTests,
  'warning_tests' => $warningTests,
  'error_tests' => $errorTests,
  'success_rate' => round(($successfulTests / $totalTests) * 100, 1),
  'top_5_slowest_endpoints' => array_map(function ($item) {
    return [
      'method' => $item['method'],
      'url' => $item['url'],
      'status' => $item['status'],
      'duration_ms' => $item['duration_ms']
    ];
  }, $top5Slowest),
  'timestamp' => date('Y-m-d H:i:s')
];

// Guardar reportes
file_put_contents(__DIR__ . '/../reports/endpoints-summary.json', json_encode($summary, JSON_PRETTY_PRINT));
file_put_contents(__DIR__ . '/../reports/endpoints-run.log', implode("\n", $logEntries));

// Limpiar el buffer y empezar la salida HTML 
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🚀 Testing Endpoints - Bubble of Talents</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .header {
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
      color: white;
      padding: 30px 40px;
      text-align: center;
    }

    .header h1 {
      font-size: 2.5rem;
      margin-bottom: 10px;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .header .subtitle {
      font-size: 1.2rem;
      opacity: 0.9;
    }

    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      padding: 30px 40px;
      background: #f8fafc;
      border-bottom: 1px solid #e2e8f0;
    }

    .stat-card {
      background: white;
      padding: 25px;
      border-radius: 15px;
      text-align: center;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border-left: 4px solid;
    }

    .stat-card.success {
      border-left-color: #10b981;
    }

    .stat-card.warning {
      border-left-color: #f59e0b;
    }

    .stat-card.error {
      border-left-color: #ef4444;
    }

    .stat-card.info {
      border-left-color: #3b82f6;
    }

    .stat-number {
      font-size: 2.5rem;
      font-weight: bold;
      margin-bottom: 5px;
    }

    .stat-card.success .stat-number {
      color: #10b981;
    }

    .stat-card.warning .stat-number {
      color: #f59e0b;
    }

    .stat-card.error .stat-number {
      color: #ef4444;
    }

    .stat-card.info .stat-number {
      color: #3b82f6;
    }

    .stat-label {
      color: #64748b;
      font-weight: 500;
    }

    .filters {
      padding: 20px 40px;
      background: white;
      border-bottom: 1px solid #e2e8f0;
    }

    .filter-buttons {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }

    .filter-btn {
      padding: 10px 20px;
      border: 2px solid #e2e8f0;
      background: white;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.3s ease;
      position: relative;
    }

    .filter-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .filter-btn.active {
      color: white;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .filter-btn.all.active {
      background: #3b82f6;
      border-color: #3b82f6;
    }

    .filter-btn.success.active {
      background: #10b981;
      border-color: #10b981;
    }

    .filter-btn.warning.active {
      background: #f59e0b;
      border-color: #f59e0b;
    }

    .filter-btn.error.active {
      background: #ef4444;
      border-color: #ef4444;
    }

    .results {
      padding: 0 40px 40px;
    }

    .endpoint-card {
      background: white;
      margin-bottom: 15px;
      border-radius: 15px;
      border-left: 4px solid;
      transition: all 0.3s ease;
      overflow: hidden;
    }

    .endpoint-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .endpoint-card.success {
      border-left-color: #10b981;
    }

    .endpoint-card.warning {
      border-left-color: #f59e0b;
    }

    .endpoint-card.error {
      border-left-color: #ef4444;
    }

    .endpoint-card.info {
      border-left-color: #3b82f6;
    }

    .endpoint-header {
      padding: 20px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .method-badge {
      padding: 6px 12px;
      border-radius: 6px;
      font-weight: bold;
      font-size: 0.9rem;
      color: white;
      min-width: 70px;
      text-align: center;
    }

    .method-get {
      background: #10b981;
    }

    .method-post {
      background: #3b82f6;
    }

    .method-put {
      background: #f59e0b;
    }

    .method-delete {
      background: #ef4444;
    }

    .method-patch {
      background: #8b5cf6;
    }

    .endpoint-url {
      flex: 1;
      font-family: 'Courier New', monospace;
      font-weight: 500;
      color: #374151;
    }

    .status-badge {
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: bold;
      font-size: 0.9rem;
    }

    .status-success {
      background: #dcfce7;
      color: #166534;
    }

    .status-warning {
      background: #fef3c7;
      color: #92400e;
    }

    .status-error {
      background: #fee2e2;
      color: #991b1b;
    }

    .endpoint-details {
      padding: 0 20px 20px;
      border-top: 1px solid #f1f5f9;
      background: #fafafa;
      display: none;
    }

    .endpoint-details.show {
      display: block;
    }

    .detail-section {
      margin: 15px 0;
    }

    .detail-title {
      font-weight: bold;
      color: #374151;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .detail-content {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 12px;
      font-family: 'Courier New', monospace;
      font-size: 0.9rem;
      white-space: pre-wrap;
      max-height: 300px;
      overflow-y: auto;
    }

    .server-status {
      padding: 15px;
      border-radius: 10px;
      margin: 20px 40px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .server-status.ok {
      background: #dcfce7;
      color: #166534;
      border: 1px solid #bbf7d0;
    }

    .server-status.warning {
      background: #fef3c7;
      color: #92400e;
      border: 1px solid #fde68a;
    }

    .footer {
      text-align: center;
      padding: 20px;
      color: #64748b;
      font-size: 0.9rem;
      border-top: 1px solid #e2e8f0;
      background: #f8fafc;
    }

    @media (max-width: 768px) {
      .container {
        margin: 10px;
        border-radius: 15px;
      }

      .header {
        padding: 20px;
      }

      .header h1 {
        font-size: 2rem;
      }

      .stats,
      .results,
      .filters {
        padding: 20px;
      }

      .endpoint-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <h1><i class="fas fa-rocket"></i> API Endpoint Testing</h1>
      <div class="subtitle">Bubble of Talents - Resultados del Testing Completo</div>
    </div>
    <!-- Server Status -->
    <div class="server-status <?php echo $serverStatus; ?>">
      <i class="fas fa-<?php echo $serverStatus === 'ok' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
      <?php if ($serverStatus === 'ok'): ?>
        <strong>Servidor funcionando correctamente</strong> - Conectado a localhost:8000
      <?php else: ?>
        <strong>Advertencia:</strong> No se pudo verificar el servidor. Asegúrate de que esté ejecutando: <code>php -S localhost:8000 router.php</code>
      <?php endif; ?>
    </div>
    <!-- Statistics -->
    <div class="stats">
      <div class="stat-card success">
        <div class="stat-number"><?php echo $successfulTests; ?></div>
        <div class="stat-label"><i class="fas fa-check-circle"></i> Exitosos</div>
      </div>
      <div class="stat-card warning">
        <div class="stat-number"><?php echo $warningTests; ?></div>
        <div class="stat-label"><i class="fas fa-exclamation-triangle"></i> Advertencias</div>
      </div>
      <div class="stat-card error">
        <div class="stat-number"><?php echo $errorTests; ?></div>
        <div class="stat-label"><i class="fas fa-times-circle"></i> Errores</div>
      </div>
      <div class="stat-card info">
        <div class="stat-number"><?php echo round(($successfulTests / $totalTests) * 100, 1); ?>%</div>
        <div class="stat-label"><i class="fas fa-chart-line"></i> Tasa de Éxito</div>
      </div>
    </div>
    <!-- Filters -->
    <div class="filters">
      <div class="filter-buttons">
        <button class="filter-btn all active" onclick="filterResults('all')">
          <i class="fas fa-list"></i> Todos (<?php echo $totalTests; ?>)
        </button>
        <button class="filter-btn success" onclick="filterResults('success')">
          <i class="fas fa-check"></i> Exitosos (<?php echo $successfulTests; ?>)
        </button>
        <button class="filter-btn warning" onclick="filterResults('warning')">
          <i class="fas fa-exclamation-triangle"></i> Advertencias (<?php echo $warningTests; ?>)
        </button>
        <button class="filter-btn error" onclick="filterResults('error')">
          <i class="fas fa-times"></i> Errores (<?php echo $errorTests; ?>)
        </button>
      </div>
    </div>
    <!-- Results -->
    <div class="results">
      <?php foreach ($results as $result): ?>
        <div class="endpoint-card <?php echo $result['category']; ?>" data-category="<?php echo $result['category']; ?>">
          <div class="endpoint-header" onclick="toggleDetails(<?php echo $result['index']; ?>)">
            <span class="method-badge method-<?php echo strtolower($result['method']); ?>">
              <?php echo $result['method']; ?>
            </span>
            <span class="endpoint-url"><?php echo $result['url']; ?></span>
            <span class="status-badge status-<?php echo $result['category']; ?>">
              <?php echo $result['statusText']; ?>
            </span>
            <span style="color: #9ca3af; font-size: 0.9rem;"><?php echo $result['duration_ms']; ?>ms</span>
            <i class="fas fa-chevron-down" style="color: #9ca3af; transition: transform 0.3s;"></i>
          </div>
          <div class="endpoint-details" id="details-<?php echo $result['index']; ?>">
            <div class="detail-section">
              <div class="detail-title">
                <i class="fas fa-link"></i> URL Completa
              </div>
              <div class="detail-content"><?php echo htmlspecialchars($result['fullUrl']); ?></div>
            </div>
            <?php if ($result['payload']): ?>
              <div class="detail-section">
                <div class="detail-title">
                  <i class="fas fa-upload"></i> Payload Enviado
                </div>
                <div class="detail-content"><?php echo htmlspecialchars($result['payload']); ?></div>
              </div>
            <?php endif; ?>
            <?php if ($result['responsePreview']): ?>
              <div class="detail-section">
                <div class="detail-title">
                  <i class="fas fa-download"></i> Respuesta del Servidor
                </div>
                <div class="detail-content"><?php echo htmlspecialchars($result['responsePreview']); ?></div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <!-- Footer -->
    <div class="footer">
      <i class="fas fa-clock"></i> Ejecutado en <?php echo $executionTime; ?>s |
      <i class="fas fa-calendar"></i> <?php echo date('Y-m-d H:i:s'); ?> |
      <i class="fas fa-code"></i> Bubble of Talents API Testing Suite
    </div>
  </div>
  <script>
    function filterResults(category) {
      const cards = document.querySelectorAll('.endpoint-card');
      const buttons = document.querySelectorAll('.filter-btn');

      // Update active button 
      buttons.forEach(btn => btn.classList.remove('active'));
      document.querySelector('.filter-btn.' + category).classList.add('active');

      // Filter cards 
      cards.forEach(card => {
        if (category === 'all' || card.dataset.category === category) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }

    function toggleDetails(index) {
      const details = document.getElementById(`details-${index}`);
      const chevron = details.parentElement.querySelector('.fa-chevron-down');
      if (details.classList.contains('show')) {
        details.classList.remove('show');
        chevron.style.transform = 'rotate(0deg)';
      } else {
        details.classList.add('show');
        chevron.style.transform = 'rotate(180deg)';
      }
    }

    // Auto-expand first error 
    document.addEventListener('DOMContentLoaded', function() {
      const firstError = document.querySelector('.endpoint-card.error');
      if (firstError) {
        const index = firstError.querySelector('.endpoint-details').id.split('-')[1];
        toggleDetails(index);
      }
    });
  </script>
</body>

</html>