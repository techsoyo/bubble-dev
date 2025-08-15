<?php

/**
 * Script para verificar si una contraseña es correcta para un candidato
 * Uso: php verify_candidate_password.php [CANDIDATE_ID] [PASSWORD]
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

try {
  if (!isset($argv[1]) || !isset($argv[2])) {
    echo "📖 USO DEL SCRIPT:\n";
    echo "   php verify_candidate_password.php [CANDIDATE_ID] [PASSWORD]\n\n";
    echo "📝 EJEMPLO:\n";
    echo "   php verify_candidate_password.php cnd-201 test123456\n\n";
    exit(0);
  }

  $pdo = getDBConnection();
  $candidateId = $argv[1];
  $password = $argv[2];

  echo "🔍 VERIFICACIÓN DE CONTRASEÑA\n";
  echo str_repeat("=", 40) . "\n\n";

  // Obtener candidato y hash
  $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password_hash FROM bt_candidates WHERE id = ?");
  $stmt->execute([$candidateId]);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$candidate) {
    echo "❌ Candidato no encontrado con ID: $candidateId\n";
    exit(1);
  }

  echo "📋 Candidato: {$candidate['first_name']} {$candidate['last_name']}\n";
  echo "📧 Email: {$candidate['email']}\n";
  echo "🔑 Contraseña a verificar: $password\n\n";

  // Verificar contraseña
  if (password_verify($password, $candidate['password_hash'])) {
    echo "✅ ¡CONTRASEÑA CORRECTA!\n";
    echo "💡 El candidato puede iniciar sesión con esta contraseña.\n";
    exit(0);
  } else {
    echo "❌ CONTRASEÑA INCORRECTA\n";
    echo "💡 La contraseña no coincide con la almacenada en la base de datos.\n";
    exit(1);
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
