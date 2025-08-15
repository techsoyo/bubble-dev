<?php

/**
 * Script para cambiar contraseña con generación automática
 * Uso: php auto_change_password.php [CANDIDATE_ID]
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

function generateSecurePassword($length = 12)
{
  $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
  $password = '';
  for ($i = 0; $i < $length; $i++) {
    $password .= $chars[random_int(0, strlen($chars) - 1)];
  }
  return $password;
}

try {
  if (!isset($argv[1])) {
    echo "📖 USO DEL SCRIPT:\n";
    echo "   php auto_change_password.php [CANDIDATE_ID]\n\n";
    echo "📝 EJEMPLO:\n";
    echo "   php auto_change_password.php cnd-201\n\n";
    echo "💡 Este script genera automáticamente una contraseña segura.\n\n";

    // Mostrar candidatos disponibles
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, first_name, last_name, email FROM bt_candidates ORDER BY created_at DESC LIMIT 5");
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📋 ÚLTIMOS CANDIDATOS:\n";
    foreach ($candidates as $candidate) {
      echo "   ID: {$candidate['id']} | {$candidate['first_name']} {$candidate['last_name']} | {$candidate['email']}\n";
    }
    exit(0);
  }

  $pdo = getDBConnection();
  $candidateId = $argv[1];

  echo "🎲 GENERACIÓN AUTOMÁTICA DE CONTRASEÑA\n";
  echo str_repeat("=", 45) . "\n\n";

  // Verificar candidato
  $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM bt_candidates WHERE id = ?");
  $stmt->execute([$candidateId]);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$candidate) {
    echo "❌ Candidato no encontrado con ID: $candidateId\n";
    exit(1);
  }

  echo "📋 CANDIDATO:\n";
  echo "   ID: {$candidate['id']}\n";
  echo "   Nombre: {$candidate['first_name']} {$candidate['last_name']}\n";
  echo "   Email: {$candidate['email']}\n\n";

  // Generar contraseña aleatoria
  $newPassword = generateSecurePassword(10);
  $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

  // Actualizar contraseña
  $updateStmt = $pdo->prepare("UPDATE bt_candidates SET password_hash = ? WHERE id = ?");
  $result = $updateStmt->execute([$passwordHash, $candidateId]);

  if ($result) {
    echo "✅ ¡CONTRASEÑA GENERADA Y ACTUALIZADA!\n\n";
    echo "🔑 NUEVA CONTRASEÑA: $newPassword\n";
    echo "📧 Email: {$candidate['email']}\n";
    echo "👤 Candidato: {$candidate['first_name']} {$candidate['last_name']}\n\n";
    echo "💡 Guarda esta información de forma segura.\n";
    echo "💡 El candidato puede iniciar sesión inmediatamente.\n";
  } else {
    echo "❌ Error al actualizar la contraseña.\n";
    exit(1);
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
