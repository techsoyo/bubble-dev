<?php
try {
  $pdo = new PDO('mysql:host=192.168.1.40;dbname=bubble_talents_DB', 'user', 'user123');

  echo "=== REASIGNACIÓN INTELIGENTE DE DEPARTAMENTOS ===\n";

  // Candidatos con skills específicas que deben reasignarse
  $reassignments = [
    'cnd-206' => ['skills' => ['google analytics'], 'dept' => 4, 'cat' => 8, 'name' => 'Marketing'],
    'cnd-205' => ['skills' => ['kubernetes'], 'dept' => 2, 'cat' => 5, 'name' => 'Engineering/DevOps'],
    'cnd-204' => ['skills' => ['typescript'], 'dept' => 2, 'cat' => 4, 'name' => 'Engineering/Frontend'],
    'cnd-203' => ['skills' => ['airflow', 'python'], 'dept' => 2, 'cat' => 5, 'name' => 'Engineering/Data'],
    'cnd-202' => ['skills' => ['figma', 'ux research'], 'dept' => 2, 'cat' => 4, 'name' => 'Engineering/Design'],
    'cnd-201' => ['skills' => ['node.js', 'react'], 'dept' => 2, 'cat' => 4, 'name' => 'Engineering/Full Stack']
  ];

  $updated = 0;

  foreach ($reassignments as $candidateId => $assignment) {
    echo "\n--- Reasignando candidato: $candidateId ---\n";
    echo "🎯 Skills: " . implode(', ', $assignment['skills']) . "\n";
    echo "🏢 Nuevo departamento: " . $assignment['name'] . " (Dept: {$assignment['dept']}, Cat: {$assignment['cat']})\n";

    $updateStmt = $pdo->prepare("
            UPDATE bt_candidates 
            SET department_id = ?, department_category_id = ?
            WHERE id = ?
        ");

    if ($updateStmt->execute([$assignment['dept'], $assignment['cat'], $candidateId])) {
      $updated++;
      echo "✅ Candidato reasignado exitosamente\n";
    } else {
      echo "❌ Error al reasignar candidato\n";
    }
  }

  echo "\n=== RESUMEN DE REASIGNACIÓN ===\n";
  echo "✅ Candidatos reasignados: $updated\n";

  // Verificar distribución actualizada
  $stmt = $pdo->query("
        SELECT 
            d.name as department_name,
            dc.name as category_name,
            COUNT(*) as candidates_count
        FROM bt_candidates c
        LEFT JOIN bt_departments d ON c.department_id = d.id
        LEFT JOIN bt_department_categories dc ON c.department_category_id = dc.id
        WHERE c.department_id IS NOT NULL
        GROUP BY c.department_id, c.department_category_id, d.name, dc.name
        ORDER BY candidates_count DESC
    ");

  echo "\n=== DISTRIBUCIÓN ACTUALIZADA POR DEPARTAMENTO ===\n";
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf(
      "🏢 %s - %s: %d candidatos\n",
      $row['department_name'] ?: 'Unknown',
      $row['category_name'] ?: 'Unknown',
      $row['candidates_count']
    );
  }

  // Test del endpoint para verificar que todo funciona
  echo "\n=== TEST ENDPOINT CANDIDATES ===\n";
  $testUrl = 'http://localhost:8000/api/endpoints/candidates.php?limit=3';
  $response = @file_get_contents($testUrl);

  if ($response) {
    $data = json_decode($response, true);
    if ($data && $data['success']) {
      echo "✅ Endpoint candidates.php funciona correctamente\n";
      echo "📊 Candidatos retornados: " . count($data['data']['candidates']) . "\n";

      // Mostrar algunos ejemplos
      foreach (array_slice($data['data']['candidates'], 0, 2) as $candidate) {
        echo sprintf(
          "   - %s: %s (%s)\n",
          $candidate['name'],
          $candidate['department'] ?: 'Sin departamento',
          implode(', ', $candidate['skills']) ?: 'Sin skills'
        );
      }
    } else {
      echo "❌ Endpoint retorna error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
  } else {
    echo "❌ No se pudo conectar al endpoint\n";
  }
} catch (Exception $e) {
  echo '❌ Error: ' . $e->getMessage() . "\n";
}
