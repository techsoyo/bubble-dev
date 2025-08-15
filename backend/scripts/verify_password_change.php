<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

try {
  $db = getDBConnection();

  // Buscar candidato por ID
  $stmt = $db->prepare("SELECT id, first_name, last_name, email, password_hash FROM bt_candidates WHERE id = ?");
  $stmt->execute(['cnd-203']);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($candidate) {
    echo "Candidato: " . $candidate['first_name'] . " " . $candidate['last_name'] . "\n";
    echo "Email: " . $candidate['email'] . "\n";
    echo "Hash actual: " . substr($candidate['password_hash'], 0, 30) . "...\n\n";

    // Verificar contraseña antigua
    if (password_verify('nuevaPass123', $candidate['password_hash'])) {
      echo "✓ Password anterior 'nuevaPass123' aún es válido\n";
    } else {
      echo "✗ Password anterior 'nuevaPass123' YA NO es válido\n";
    }

    // Verificar contraseña nueva
    if (password_verify('nuevaPass456', $candidate['password_hash'])) {
      echo "✓ Password nuevo 'nuevaPass456' ES VÁLIDO\n";
    } else {
      echo "✗ Password nuevo 'nuevaPass456' NO es válido\n";
    }
  } else {
    echo "Candidato no encontrado\n";
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
