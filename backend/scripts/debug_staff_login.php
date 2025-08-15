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

  echo "=== DIAGNÓSTICO DE USUARIOS STAFF ===\n\n";

  // Verificar usuarios en la tabla
  $stmt = $db->prepare("SELECT id, email, name, role, active, password_hash FROM bt_staff_profiles ORDER BY email");
  $stmt->execute();
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Usuarios encontrados en bt_staff_profiles:\n";
  echo str_repeat("-", 80) . "\n";

  foreach ($users as $user) {
    echo "ID: " . $user['id'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Nombre: " . $user['name'] . "\n";
    echo "Rol: " . $user['role'] . "\n";
    echo "Activo: " . ($user['active'] ? 'Sí' : 'No') . "\n";
    echo "Hash: " . substr($user['password_hash'], 0, 30) . "...\n";
    echo str_repeat("-", 80) . "\n";
  }
  echo "\n=== PRUEBA DE VERIFICACIÓN DE CONTRASEÑAS ===\n\n";

  // Contraseñas que deberían funcionar
  $testPasswords = [
    'ana.torres@bubblegum.agency' => 'BubbleAdmin2025!',
    'miguel.ruiz@bubblegum.agency' => 'Recruit#2025M',
    'sofia.navarro@bubblegum.agency' => 'Sofia&Talents25',
    'carlos.vega@bubblegum.agency' => 'CarlosRec#2025',
    'elena.ruiz@bubblegum.agency' => 'Elena!Bubble25'
  ];

  foreach ($testPasswords as $email => $password) {
    echo "Probando: $email\n";

    // Buscar usuario
    $stmt = $db->prepare("SELECT password_hash, active FROM bt_staff_profiles WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
      echo "  Usuario encontrado: " . ($user['active'] ? 'Activo' : 'Inactivo') . "\n";
      echo "  Hash almacenado: " . substr($user['password_hash'], 0, 30) . "...\n";

      // Verificar contraseña
      $isValid = password_verify($password, $user['password_hash']);
      echo "  Contraseña válida: " . ($isValid ? '✅ SÍ' : '❌ NO') . "\n";

      if (!$isValid) {
        // Generar nuevo hash para comparar
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        echo "  Hash que debería ser: " . substr($newHash, 0, 30) . "...\n";
      }
    } else {
      echo "  ❌ Usuario NO encontrado\n";
    }
    echo "\n";
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
  exit(1);
}
