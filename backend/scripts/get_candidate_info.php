<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

try {
  $db = getDBConnection();

  // Buscar candidato por email
  $stmt = $db->prepare("SELECT id, first_name, last_name, email, password FROM bt_candidates WHERE email = ?");
  $stmt->execute(['raul.campos@example.com']);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($candidate) {
    echo "Candidato encontrado:\n";
    echo "ID: " . $candidate['id'] . "\n";
    echo "Nombre: " . $candidate['first_name'] . " " . $candidate['last_name'] . "\n";
    echo "Email: " . $candidate['email'] . "\n";
    echo "Password hash: " . substr($candidate['password'], 0, 20) . "...\n";

    // Verificar password actual
    if (password_verify('nuevaPass123', $candidate['password'])) {
      echo "✓ Password 'nuevaPass123' es correcto\n";
    } else {
      echo "✗ Password 'nuevaPass123' NO coincide\n";
    }
  } else {
    echo "Candidato no encontrado con email raul.campos@example.com\n";
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
