<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

echo "=== TEST DE CONEXIÓN A BD ===\n";

try {
  $pdo = getDBConnection();
  echo "✅ Conexión exitosa\n";

  $stmt = $pdo->query('SELECT COUNT(*) as total FROM bt_candidates');
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  echo "📊 Total candidatos: " . $result['total'] . "\n";

  $stmt = $pdo->prepare('SELECT id, first_name, last_name, email FROM bt_candidates LIMIT 3');
  $stmt->execute();
  $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "📋 Primeros candidatos:\n";
  foreach ($candidates as $candidate) {
    echo "  - {$candidate['id']}: {$candidate['first_name']} {$candidate['last_name']} ({$candidate['email']})\n";
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
