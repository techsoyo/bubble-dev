<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 2);
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

try {
  $method = $_SERVER['REQUEST_METHOD'];

  switch ($method) {
    case 'GET':
      // Verificar sesión actual
      if (session_status() === PHP_SESSION_NONE) {
        session_start();
      }

      // Verificar si hay una sesión activa
      $user_id = $_SESSION['user_id'] ?? null;

      if ($user_id) {
        // Obtener información del usuario desde la base de datos
        $db = getDbConnection();
        $stmt = $db->prepare('SELECT id, email, name, first_name, last_name FROM bt_candidates WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
          http_response_code(200);
          echo json_encode([
            'success' => true,
            'user' => [
              'id' => $user['id'],
              'email' => $user['email'],
              'name' => $user['name'],
              'first_name' => $user['first_name'],
              'last_name' => $user['last_name'],
              'role' => 'candidate'
            ],
            'message' => 'Session valid'
          ]);
          exit;
        }
      }

      // No hay sesión válida
      http_response_code(401);
      echo json_encode([
        'success' => false,
        'message' => 'No active session'
      ]);
      break;

    case 'DELETE':
      // Cerrar sesión
      if (session_status() === PHP_SESSION_NONE) {
        session_start();
      }

      session_destroy();

      http_response_code(200);
      echo json_encode([
        'success' => true,
        'message' => 'Session closed'
      ]);
      break;

    default:
      http_response_code(405);
      echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
      ]);
      break;
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Internal server error',
    'error' => isDevelopment() ? $e->getMessage() : null
  ]);
}
