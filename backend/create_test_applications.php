<?php

/**
 * Script para crear aplicaciones de prueba si no existen
 */
require_once __DIR__ . '/api/bootstrap.php';

try {
  $pdo = getDBConnection();

  // Verificar si ya existen aplicaciones
  $stmt = $pdo->query('SELECT COUNT(*) as count FROM bt_applications');
  $count = $stmt->fetch()['count'];

  echo "Aplicaciones existentes: $count\n";

  if ($count < 5) {
    echo "Creando aplicaciones de prueba...\n";

    // Obtener algunos candidatos y trabajos
    $candidates = $pdo->query('SELECT id FROM bt_candidates LIMIT 5')->fetchAll();
    $jobs = $pdo->query('SELECT id FROM bt_jobs LIMIT 3')->fetchAll();

    if (empty($candidates)) {
      echo "No hay candidatos disponibles\n";
      exit;
    }

    if (empty($jobs)) {
      echo "No hay trabajos disponibles\n";
      exit;
    }

    // Crear aplicaciones de prueba
    $statuses = ['Received', 'Under Review', 'Interview', 'Offer', 'Hired'];

    for ($i = 0; $i < 10; $i++) {
      $candidateId = $candidates[array_rand($candidates)]['id'];
      $jobId = $jobs[array_rand($jobs)]['id'];
      $status = $statuses[array_rand($statuses)];
      $score = rand(60, 100);

      // Verificar si ya existe esta combinación
      $checkStmt = $pdo->prepare('SELECT id FROM bt_applications WHERE candidate_id = ? AND job_id = ?');
      $checkStmt->execute([$candidateId, $jobId]);

      if (!$checkStmt->fetch()) {
        $insertStmt = $pdo->prepare('
                    INSERT INTO bt_applications (candidate_id, job_id, status, score, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ');
        $insertStmt->execute([$candidateId, $jobId, $status, $score]);
        echo "Aplicación creada: Candidato $candidateId -> Trabajo $jobId ($status, Score: $score)\n";
      }
    }

    echo "Aplicaciones de prueba creadas.\n";
  } else {
    echo "Ya existen suficientes aplicaciones.\n";
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
