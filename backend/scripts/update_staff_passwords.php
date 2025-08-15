<?php

declare(strict_types=1);

// Incluir configuración de la base de datos
$ROOT = dirname(__DIR__);
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  echo "Bootstrap no encontrado\n";
  exit(1);
}
require_once $BOOT;

try {
  // Conectar a la base de datos
  $db = getDBConnection();

  // Nuevas contraseñas para los perfiles de staff
  $staffPasswords = [
    'ana.torres@bubblegum.agency' => 'BubbleAdmin2025!',
    'miguel.ruiz@bubblegum.agency' => 'Recruit#2025M',
    'sofia.navarro@bubblegum.agency' => 'Sofia&Talents25',
    'carlos.vega@bubblegum.agency' => 'CarlosRec#2025',
    'elena.ruiz@bubblegum.agency' => 'Elena!Bubble25'
  ];

  echo "Actualizando contraseñas para " . count($staffPasswords) . " usuarios de staff...\n\n";

  foreach ($staffPasswords as $email => $plainPassword) {
    // Hashear la contraseña usando password_hash()
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    // Actualizar la contraseña en la base de datos
    $stmt = $db->prepare("UPDATE bt_staff_profiles SET password_hash = ? WHERE email = ?");
    $result = $stmt->execute([$hashedPassword, $email]);

    if ($result && $stmt->rowCount() > 0) {
      echo "✅ Contraseña actualizada para: $email\n";
      echo "   Nueva contraseña: $plainPassword\n";
      echo "   Hash generado: " . substr($hashedPassword, 0, 20) . "...\n\n";
    } else {
      echo "❌ Error al actualizar contraseña para: $email\n";
      echo "   Usuario no encontrado o error en la consulta\n\n";
    }
  }

  echo "Proceso completado.\n";
  echo "\n=== RESUMEN DE CONTRASEÑAS ===\n";
  foreach ($staffPasswords as $email => $password) {
    echo "$email => $password\n";
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
  exit(1);
}
