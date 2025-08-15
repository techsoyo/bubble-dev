<?php

/**
 * Script simple para cambiar la contraseña de un candidato
 * Uso: php change_password_simple.php [CANDIDATE_ID] [NEW_PASSWORD]
 * Ejemplo: php change_password_simple.php cnd-203 nuevaPassword123
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

function listCandidates($pdo)
{
  echo "📋 CANDIDATOS DISPONIBLES:\n";
  echo str_repeat("=", 80) . "\n";

  $stmt = $pdo->query("
        SELECT id, first_name, last_name, email, status 
        FROM bt_candidates 
        ORDER BY created_at DESC 
        LIMIT 10
    ");

  $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($candidates)) {
    echo "❌ No se encontraron candidatos.\n";
    return;
  }

  foreach ($candidates as $candidate) {
    printf(
      "ID: %-10s | %s %s | %s | %s\n",
      $candidate['id'],
      $candidate['first_name'],
      $candidate['last_name'],
      $candidate['email'],
      $candidate['status']
    );
  }
  echo str_repeat("=", 80) . "\n\n";
}

function changePassword($pdo, $candidateId, $newPassword)
{
  // Verificar candidato
  $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM bt_candidates WHERE id = ?");
  $stmt->execute([$candidateId]);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$candidate) {
    echo "❌ Error: Candidato no encontrado con ID: $candidateId\n";
    return false;
  }

  echo "📋 CANDIDATO SELECCIONADO:\n";
  echo "   ID: {$candidate['id']}\n";
  echo "   Nombre: {$candidate['first_name']} {$candidate['last_name']}\n";
  echo "   Email: {$candidate['email']}\n\n";

  // Generar hash
  $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

  // Actualizar
  $updateStmt = $pdo->prepare("UPDATE bt_candidates SET password_hash = ? WHERE id = ?");

  try {
    $result = $updateStmt->execute([$passwordHash, $candidateId]);

    if ($result) {
      echo "✅ CONTRASEÑA ACTUALIZADA EXITOSAMENTE!\n";
      echo "🔑 Nueva contraseña: $newPassword\n";
      echo "📧 Email: {$candidate['email']}\n";
      echo "💡 El candidato ya puede iniciar sesión con la nueva contraseña.\n\n";
      return true;
    } else {
      echo "❌ Error al actualizar la contraseña.\n";
      return false;
    }
  } catch (PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    return false;
  }
}

// Función principal
try {
  $pdo = getDBConnection();

  echo "🔐 CAMBIO DE CONTRASEÑA PARA CANDIDATOS\n";
  echo str_repeat("=", 50) . "\n\n";

  // Si no hay argumentos, mostrar ayuda
  if (!isset($argv[1]) || !isset($argv[2])) {
    echo "📖 USO DEL SCRIPT:\n";
    echo "   php change_password_simple.php [CANDIDATE_ID] [NEW_PASSWORD]\n\n";
    echo "📝 EJEMPLO:\n";
    echo "   php change_password_simple.php cnd-203 nuevaPassword123\n\n";

    listCandidates($pdo);

    echo "💡 SUGERENCIAS:\n";
    echo "   • Copia el ID exacto del candidato de la lista\n";
    echo "   • La contraseña debe tener al menos 6 caracteres\n";
    echo "   • Usa una contraseña segura\n\n";

    exit(0);
  }

  $candidateId = $argv[1];
  $newPassword = $argv[2];

  // Validar contraseña
  if (strlen($newPassword) < 6) {
    echo "❌ Error: La contraseña debe tener al menos 6 caracteres.\n";
    exit(1);
  }

  // Cambiar contraseña
  if (changePassword($pdo, $candidateId, $newPassword)) {
    echo "🎉 ¡Operación completada!\n";
    exit(0);
  } else {
    echo "❌ Falló la operación.\n";
    exit(1);
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
