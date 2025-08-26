<?php

/**
 * Script para cambiar la contraseña de un candidato específico
 * Uso: php change_candidate_password.php
 */

// Cargar configuración
require_once dirname(__DIR__) . '/config/bootstrap.php';

function showCandidates($pdo)
{
  echo "📋 Candidatos disponibles:\n";
  echo str_repeat("-", 80) . "\n";

  $stmt = $pdo->query("
        SELECT id, first_name, last_name, email, status, created_at 
        FROM bt_candidates 
        ORDER BY created_at DESC 
        LIMIT 20
    ");

  $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($candidates)) {
    echo "❌ No se encontraron candidatos en la base de datos.\n";
    return;
  }

  foreach ($candidates as $candidate) {
    printf(
      "ID: %s\nNombre: %s %s\nEmail: %s\nEstado: %s\nFecha: %s\n%s\n",
      $candidate['id'],
      $candidate['first_name'],
      $candidate['last_name'],
      $candidate['email'],
      $candidate['status'],
      $candidate['created_at'],
      str_repeat("-", 40)
    );
  }
}

function changePassword($pdo, $candidateId, $newPassword)
{
  // Verificar que el candidato existe
  $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM bt_candidates WHERE id = ?");
  $stmt->execute([$candidateId]);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$candidate) {
    echo "❌ Error: No se encontró un candidato con ID: $candidateId\n";
    return false;
  }

  echo "📋 Candidato encontrado:\n";
  echo "   Nombre: {$candidate['first_name']} {$candidate['last_name']}\n";
  echo "   Email: {$candidate['email']}\n";
  echo "   ID: {$candidate['id']}\n\n";

  // Generar hash de la nueva contraseña
  $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

  // Actualizar la contraseña
  $updateStmt = $pdo->prepare("UPDATE bt_candidates SET password_hash = ? WHERE id = ?");

  try {
    $result = $updateStmt->execute([$passwordHash, $candidateId]);

    if ($result) {
      echo "✅ Contraseña actualizada exitosamente!\n";
      echo "📧 Email: {$candidate['email']}\n";
      echo "🔑 Nueva contraseña: $newPassword\n";
      echo "🔐 Hash generado: " . substr($passwordHash, 0, 50) . "...\n";
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

function generateSecurePassword($length = 12)
{
  $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
  return substr(str_shuffle($chars), 0, $length);
}

// Función principal
function main()
{
  try {
    $pdo = getDBConnection();

    echo "🔐 CAMBIO DE CONTRASEÑA PARA CANDIDATOS\n";
    echo str_repeat("=", 50) . "\n\n";

    // Mostrar candidatos disponibles
    showCandidates($pdo);

    // Solicitar ID del candidato
    echo "\n💡 Ingresa el ID del candidato (o 'exit' para salir): ";
    $candidateId = trim(fgets(STDIN));

    if (strtolower($candidateId) === 'exit') {
      echo "👋 Saliendo...\n";
      return;
    }

    if (empty($candidateId)) {
      echo "❌ Error: Debes ingresar un ID válido.\n";
      return;
    }

    // Solicitar nueva contraseña
    echo "🔑 Ingresa la nueva contraseña (o 'auto' para generar una): ";
    $newPassword = trim(fgets(STDIN));

    if (strtolower($newPassword) === 'auto') {
      $newPassword = generateSecurePassword();
      echo "🎲 Contraseña generada automáticamente: $newPassword\n";
    }

    if (empty($newPassword)) {
      echo "❌ Error: La contraseña no puede estar vacía.\n";
      return;
    }

    // Validar longitud mínima
    if (strlen($newPassword) < 6) {
      echo "❌ Error: La contraseña debe tener al menos 6 caracteres.\n";
      return;
    }

    // Confirmar cambio
    echo "\n ¿Estás seguro de cambiar la contraseña? (s/N): ";
    $confirm = trim(fgets(STDIN));

    if (strtolower($confirm) !== 's' && strtolower($confirm) !== 'si') {
      echo "❌ Operación cancelada.\n";
      return;
    }

    // Realizar el cambio
    if (changePassword($pdo, $candidateId, $newPassword)) {
      echo "\n✅ ¡Operación completada exitosamente!\n";
      echo "💡 El candidato ya puede iniciar sesión con la nueva contraseña.\n";
    }
  } catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
  }
}

// Modo no interactivo para uso directo
if (isset($argv[1]) && isset($argv[2])) {
  try {
    $pdo = getDBConnection();
    $candidateId = $argv[1];
    $newPassword = $argv[2];

    echo "🔐 CAMBIO DE CONTRASEÑA (Modo no interactivo)\n";
    echo str_repeat("=", 50) . "\n";

    changePassword($pdo, $candidateId, $newPassword);
  } catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
  }
} else {
  // Modo interactivo
  main();
}
