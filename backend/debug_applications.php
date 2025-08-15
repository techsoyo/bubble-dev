<?php
// Test simplificado del endpoint de aplicaciones

require_once __DIR__ . '/config/bootstrap.php';

echo "=== DEBUG DEL ENDPOINT DE APLICACIONES ===\n\n";

try {
  // Conectar a la base de datos
  $pdo = getDBConnection();
  echo "✅ Conexión a BD exitosa\n";

  // Ejecutar la consulta principal
  $sql = "
        SELECT 
            a.id as application_id,
            a.candidate_id,
            a.job_id,
            a.status,
            a.score,
            a.created_at as application_date,
            
            -- Información del candidato
            c.first_name,
            c.last_name,
            c.email as candidate_email,
            
            -- Información del trabajo
            j.title as job_title,
            j.location as job_location
            
        FROM bt_applications a
        LEFT JOIN bt_candidates c ON a.candidate_id = c.id
        LEFT JOIN bt_jobs j ON a.job_id = j.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $applications = $stmt->fetchAll();

  echo "📊 Aplicaciones encontradas: " . count($applications) . "\n\n";

  foreach ($applications as $app) {
    echo "🎯 Aplicación #" . $app['application_id'] . "\n";
    echo "   Candidato: " . $app['first_name'] . " " . $app['last_name'] . "\n";
    echo "   Email: " . $app['candidate_email'] . "\n";
    echo "   Trabajo: " . $app['job_title'] . "\n";
    echo "   Estado: " . $app['status'] . "\n";
    echo "   Fecha: " . $app['application_date'] . "\n\n";
  }

  // Crear respuesta JSON
  $response = [
    'success' => true,
    'data' => $applications,
    'total' => count($applications),
    'message' => 'Aplicaciones obtenidas exitosamente'
  ];

  echo "✅ JSON Response:\n";
  echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
