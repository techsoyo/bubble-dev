<?php

require_once 'backend/config/bootstrap.php';

try {
  $db = getDBConnection();

  // Actualizar la contraseña de Raúl para que sea 'bubble123'
  $newPassword = 'bubble123';
  $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

  $stmt = $db->prepare("UPDATE bt_candidates SET password_hash = ? WHERE email = 'raul.campos@example.com'");
  $result = $stmt->execute([$hashedPassword]);

  if ($result) {
    echo "✅ Contraseña actualizada exitosamente para raul.campos@example.com\n";
    echo "Nueva contraseña: bubble123\n";
    echo "Hash generado: $hashedPassword\n\n";

    // Verificar que funciona
    $testResult = password_verify($newPassword, $hashedPassword);
    echo "Verificación de contraseña: " . ($testResult ? 'EXITOSA' : 'FALLIDA') . "\n";

    // Verificar en la base de datos
    $stmt = $db->prepare("SELECT password_hash FROM bt_candidates WHERE email = 'raul.campos@example.com'");
    $stmt->execute();
    $dbHash = $stmt->fetchColumn();

    $dbTestResult = password_verify($newPassword, $dbHash);
    echo "Verificación desde DB: " . ($dbTestResult ? 'EXITOSA' : 'FALLIDA') . "\n";
  } else {
    echo "❌ Error al actualizar la contraseña\n";
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
