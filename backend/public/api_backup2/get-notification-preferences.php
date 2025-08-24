<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once $BOOT;

// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

try {
  // Verificar si hay sesiÃ³n activa
  if (empty($_SESSION['candidate_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No authenticated']);
    exit();
  }

  $candidateId = $_SESSION['candidate_id'];

  // Conectar a la base de datos
  $db = getDBConnection();

  // Obtener las preferencias de notificaciÃ³n del candidato
  $stmt = $db->prepare("SELECT application_updates, new_jobs, reminders FROM bt_notification_preferences WHERE candidate_id = ?");
  $stmt->execute([$candidateId]);
  $preferences = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($preferences) {
    // Convertir valores numÃ©ricos a booleanos
    $preferences['application_updates'] = (bool)$preferences['application_updates'];
    $preferences['new_jobs'] = (bool)$preferences['new_jobs'];
    $preferences['reminders'] = (bool)$preferences['reminders'];

    echo json_encode([
      'success' => true,
      'message' => 'Preferences loaded successfully',
      'data' => $preferences
    ]);
  } else {
    // Si no existen preferencias, devolver valores por defecto
    echo json_encode([
      'success' => true,
      'message' => 'Default preferences loaded',
      'data' => [
        'application_updates' => true,
        'new_jobs' => false,
        'reminders' => true
      ]
    ]);
  }
} catch (Exception $e) {
  error_log("Error en get-notification-preferences.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error interno del servidor'
  ]);
}


