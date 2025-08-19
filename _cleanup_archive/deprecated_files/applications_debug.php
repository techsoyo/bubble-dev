<?php

/**
 * Endpoint de aplicaciones - Versión debug
 */

// Headers básicos
header('Content-Type: application/json');
// SECURITY: Restrict origins for debug endpoint instead of wildcard
$allowedOrigins = ['http://localhost:3002', 'http://localhost:3000', 'http://127.0.0.1:3002'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
} else {
  header('Access-Control-Allow-Origin: http://localhost:3002'); // Default fallback
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

try {
  // Conexión directa a la base de datos
  $pdo = new PDO('mysql:host=localhost;dbname=bubble_talents_DB', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Consulta simple para obtener aplicaciones
    $sql = "
            SELECT 
                a.id as application_id,
                a.candidate_id,
                a.job_id,
                a.status,
                a.score,
                a.created_at as application_date,
                
                c.first_name,
                c.last_name,
                c.email as candidate_email,
                c.phone,
                c.location as candidate_location,
                
                j.title as job_title,
                j.description as job_description,
                j.location as job_location
                
            FROM bt_applications a
            LEFT JOIN bt_candidates c ON a.candidate_id = c.id
            LEFT JOIN bt_jobs j ON a.job_id = j.id
            ORDER BY a.created_at DESC
            LIMIT 50
        ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $applications = $stmt->fetchAll();

    // Procesar datos para el frontend
    $processedApplications = [];
    foreach ($applications as $app) {
      $processedApplications[] = [
        'id' => $app['application_id'],
        'candidate_id' => $app['candidate_id'],
        'candidate_email' => $app['candidate_email'],
        'job_id' => $app['job_id'],
        'status' => $app['status'] ?: 'Received',
        'score' => (float)($app['score'] ?: 0),
        'appliedDate' => $app['application_date'],
        'created_at' => $app['application_date'],

        'candidate' => [
          'id' => $app['candidate_id'],
          'name' => trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')),
          'email' => $app['candidate_email'],
          'phone' => $app['phone'],
          'location' => $app['candidate_location']
        ],

        'job' => [
          'id' => $app['job_id'],
          'title' => $app['job_title'],
          'description' => $app['job_description'],
          'location' => $app['job_location']
        ]
      ];
    }

    echo json_encode([
      'success' => true,
      'message' => 'Aplicaciones obtenidas correctamente',
      'data' => $processedApplications,
      'count' => count($processedApplications)
    ], JSON_UNESCAPED_UNICODE);
  } else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error en el servidor',
    'error' => $e->getMessage()
  ]);
}
