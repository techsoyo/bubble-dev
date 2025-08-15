<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__);
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

try {
  // Verificar si hay sesión activa
  if (empty($_SESSION['candidate_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No authenticated']);
    exit();
  }

  $candidateId = $_SESSION['candidate_id'];

  // Obtener las notificaciones del candidato
  $stmt = $pdo->prepare("
        SELECT id, message, created_at 
        FROM bt_notifications 
        WHERE candidate_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
  $stmt->execute([$candidateId]);
  $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'success' => true,
    'data' => $notifications,
    'count' => count($notifications)
  ]);
} catch (Exception $e) {
  error_log("Error en candidate-notifications.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error interno del servidor'
  ]);
}
