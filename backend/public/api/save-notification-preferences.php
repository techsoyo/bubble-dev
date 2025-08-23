<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$ROOT = dirname(dirname(dirname(__DIR__))); // Corregido: api -> public -> backend -> raiz
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

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

try {
  // Verificar si hay sesión activa
  if (empty($_SESSION['candidate_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No authenticated']);
    exit();
  }

  $candidateId = $_SESSION['candidate_id'];
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit();
  }

  $applicationUpdates = isset($input['applicationUpdates']) ? (int)(bool)$input['applicationUpdates'] : 1;
  $newJobs = isset($input['newJobs']) ? (int)(bool)$input['newJobs'] : 0;
  $reminders = isset($input['reminders']) ? (int)(bool)$input['reminders'] : 1;

  // Conectar a la base de datos
  $db = getDBConnection();

  // Verificar si ya existen preferencias para este candidato
  $stmt = $db->prepare("SELECT id FROM bt_notification_preferences WHERE candidate_id = ?");
  $stmt->execute([$candidateId]);
  $exists = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($exists) {
    // Actualizar preferencias existentes
    $stmt = $db->prepare("
            UPDATE bt_notification_preferences 
            SET application_updates = ?, new_jobs = ?, reminders = ?, updated_at = NOW()
            WHERE candidate_id = ?
        ");
    $stmt->execute([$applicationUpdates, $newJobs, $reminders, $candidateId]);
  } else {
    // Crear nuevas preferencias
    $preferenceId = 'pref-' . substr(uniqid(), -8);
    $stmt = $db->prepare("
            INSERT INTO bt_notification_preferences (id, candidate_id, application_updates, new_jobs, reminders)
            VALUES (?, ?, ?, ?, ?)
        ");
    $stmt->execute([$preferenceId, $candidateId, $applicationUpdates, $newJobs, $reminders]);
  }

  echo json_encode([
    'success' => true,
    'message' => 'Preferences saved successfully',
    'data' => [
      'applicationUpdates' => (bool)$applicationUpdates,
      'newJobs' => (bool)$newJobs,
      'reminders' => (bool)$reminders
    ]
  ]);
} catch (Exception $e) {
  error_log("Error en save-notification-preferences.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error interno del servidor'
  ]);
}

