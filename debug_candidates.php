<?php

require_once 'backend/config/bootstrap.php';

try {
  $db = getDBConnection();

  echo "=== DEBUG CANDIDATOS ===\n";

  // Listar todos los candidatos
  $stmt = $db->prepare("SELECT id, first_name, last_name, email, status, password_hash FROM bt_candidates ORDER BY id DESC LIMIT 10");
  $stmt->execute();
  $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Total candidatos encontrados: " . count($candidates) . "\n\n";

  foreach ($candidates as $candidate) {
    echo "ID: {$candidate['id']}\n";
    echo "Nombre: {$candidate['first_name']} {$candidate['last_name']}\n";
    echo "Email: {$candidate['email']}\n";
    echo "Estado: {$candidate['status']}\n";
    echo "Hash contraseña: " . substr($candidate['password_hash'], 0, 50) . "...\n";
    echo "---\n";
  }

  // Verificar específicamente raul.campos@example.com
  echo "\n=== VERIFICAR USUARIO ESPECÍFICO ===\n";
  $stmt = $db->prepare("SELECT * FROM bt_candidates WHERE email = ?");
  $stmt->execute(['raul.campos@example.com']);
  $raul = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($raul) {
    echo "Usuario encontrado: {$raul['first_name']} {$raul['last_name']}\n";
    echo "Email: {$raul['email']}\n";
    echo "Estado: {$raul['status']}\n";
    echo "Hash: {$raul['password_hash']}\n";

    // Probar verificación de contraseña
    $testPasswords = ['nuevaPass123', 'password123', 'raul123', 'bubble123'];
    foreach ($testPasswords as $testPass) {
      $result = password_verify($testPass, $raul['password_hash']);
      echo "Contraseña '$testPass': " . ($result ? 'VÁLIDA' : 'INVÁLIDA') . "\n";
    }
  } else {
    echo "Usuario raul.campos@example.com NO ENCONTRADO\n";
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
