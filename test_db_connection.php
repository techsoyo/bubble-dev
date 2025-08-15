<?php
try {
  // Probar conexión directa con nuevas credenciales
  $pdo = new PDO('mysql:host=192.168.1.40;dbname=bubble_talents_DB', 'user', 'user123', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  echo 'Conexión exitosa con MySQL remoto' . PHP_EOL;
  $stmt = $pdo->query('SELECT COUNT(*) as count FROM bt_applications');
  $result = $stmt->fetch();
  echo 'Aplicaciones en BD: ' . $result['count'] . PHP_EOL;

  // Probar candidatos
  $stmt2 = $pdo->query('SELECT COUNT(*) as count FROM bt_candidates');
  $result2 = $stmt2->fetch();
  echo 'Candidatos en BD: ' . $result2['count'] . PHP_EOL;

  // Probar trabajos
  $stmt3 = $pdo->query('SELECT COUNT(*) as count FROM bt_jobs');
  $result3 = $stmt3->fetch();
  echo 'Trabajos en BD: ' . $result3['count'] . PHP_EOL;
} catch (Exception $e) {
  echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
