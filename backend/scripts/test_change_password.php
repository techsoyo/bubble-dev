<?php

/**
 * Script de prueba para verificar el cambio de contraseña
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

try {
  // Verificar si existe el candidato cnd-203
  $db = getDBConnection();

  echo "🔍 VERIFICANDO CANDIDATO cnd-203\n";
  echo str_repeat("=", 50) . "\n\n";

  $stmt = $db->prepare("SELECT id, first_name, last_name, email, password_hash FROM bt_candidates WHERE id = ?");
  $stmt->execute(['cnd-203']);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$candidate) {
    echo "❌ Candidato cnd-203 no encontrado\n";
    echo "📝 Creando candidato de prueba...\n";

    // Crear candidato de prueba
    $insertStmt = $db->prepare("INSERT INTO bt_candidates (id, first_name, last_name, email, password_hash, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $testPassword = password_hash('test123', PASSWORD_DEFAULT);
    $result = $insertStmt->execute(['cnd-203', 'Test', 'User', 'test@example.com', $testPassword]);

    if ($result) {
      echo "✅ Candidato de prueba creado con éxito\n";
      echo "📧 Email: test@example.com\n";
      echo "🔑 Contraseña: test123\n\n";
    } else {
      echo "❌ Error al crear candidato de prueba\n";
      exit(1);
    }
  } else {
    echo "✅ Candidato encontrado:\n";
    echo "   ID: {$candidate['id']}\n";
    echo "   Nombre: {$candidate['first_name']} {$candidate['last_name']}\n";
    echo "   Email: {$candidate['email']}\n\n";
  }

  echo "✅ Sistema listo para probar cambio de contraseña\n";
  echo "💡 Puedes usar la interfaz web en: http://localhost:3002/dashboard/cddashboard\n";
  echo "💡 Pestaña 'Security' -> Cambiar contraseña\n";
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
