<?php
require_once 'config/database.php';

try {
  $pdo = getDbConnection();
  echo "=== Revisando tabla bt_notification ===\n";

  // Ver estructura de la tabla
  $stmt = $pdo->query('DESCRIBE bt_notification');
  $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo "Columnas de bt_notification:\n";
  foreach ($columns as $col) {
    echo "- {$col['Field']} ({$col['Type']})\n";
  }

  echo "\n=== Datos en bt_notification ===\n";
  $stmt = $pdo->query('SELECT * FROM bt_notification ORDER BY created_at DESC');
  $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($notifications) > 0) {
    foreach ($notifications as $notif) {
      echo "ID: {$notif['id']}\n";
      echo "Candidato: {$notif['candidate_id']}\n";
      echo "Mensaje: {$notif['message']}\n";
      echo "Fecha: {$notif['created_at']}\n";
      echo "---\n";
    }
  } else {
    echo "No hay notificaciones en la tabla.\n";
  }

  // Buscar específicamente para raul.campos
  echo "\n=== Notificaciones para raul.campos ===\n";
  $stmt = $pdo->prepare('
        SELECT n.*, c.email 
        FROM bt_notification n 
        JOIN bt_candidates c ON n.candidate_id = c.id 
        WHERE c.email = ?
        ORDER BY n.created_at DESC
    ');
  $stmt->execute(['raul.campos@example.com']);
  $raulNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($raulNotifications) > 0) {
    foreach ($raulNotifications as $notif) {
      echo "ID: {$notif['id']}\n";
      echo "Email: {$notif['email']}\n";
      echo "Candidato ID: {$notif['candidate_id']}\n";
      echo "Mensaje: {$notif['message']}\n";
      echo "Fecha: {$notif['created_at']}\n";
      echo "---\n";
    }
  } else {
    echo "No hay notificaciones para raul.campos@example.com\n";
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
