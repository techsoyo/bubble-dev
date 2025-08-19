<?php
// Endpoint de test simple para HR Dashboard

// Headers CORS importantes
header('Content-Type: application/json');
// SECURITY: Restrict origins for test endpoint instead of wildcard
$allowedOrigins = ['http://localhost:3002', 'http://localhost:3000', 'http://127.0.0.1:3002'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
} else {
  header('Access-Control-Allow-Origin: http://localhost:3002'); // Default fallback
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

try {
  // Test simple sin bootstrap para evitar warnings
  $host = '192.168.1.40';
  $port = '3306';
  $dbname = 'bubble_talents_DB';
  $username = 'user';
  $password = 'user123';

  $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
  $pdo = new PDO($dsn, $username, $password);

  // Consulta simple
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM bt_applications");
  $applications = $stmt->fetch();

  $stmt = $pdo->query("SELECT COUNT(*) as total FROM bt_candidates");
  $candidates = $stmt->fetch();

  $stmt = $pdo->query("SELECT COUNT(*) as total FROM bt_jobs");
  $jobs = $stmt->fetch();

  // Respuesta exitosa
  $response = [
    'success' => true,
    'message' => 'HR Dashboard backend funcionando correctamente',
    'data' => [
      'applications_count' => $applications['total'],
      'candidates_count' => $candidates['total'],
      'jobs_count' => $jobs['total'],
      'timestamp' => date('Y-m-d H:i:s'),
      'server_info' => [
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
      ]
    ]
  ];

  echo json_encode($response);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage(),
    'timestamp' => date('Y-m-d H:i:s')
  ]);
}
