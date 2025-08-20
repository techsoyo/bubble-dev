<?php

require_once __DIR__ . '/config/bootstrap.php';

use Database\DatabaseConnection;

try {
  $db = DatabaseConnection::getInstance();
  $pdo = $db->getConnection();

  echo "=== CANDIDATOS EN LA BASE DE DATOS ===\n";

  $stmt = $pdo->query("SELECT candidate_id, first_name, last_name, email, created_at FROM bt_candidates ORDER BY created_at DESC LIMIT 10");
  $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($candidates)) {
    echo "✅ No hay candidatos en la base de datos\n";
  } else {
    foreach ($candidates as $candidate) {
      echo "ID: {$candidate['candidate_id']} | ";
      echo "Nombre: {$candidate['first_name']} {$candidate['last_name']} | ";
      echo "Email: {$candidate['email']} | ";
      echo "Creado: {$candidate['created_at']}\n";
    }
  }

  echo "\nTotal de candidatos: " . count($candidates) . "\n";

  // Mostrar también las tablas relacionadas
  $experienceCount = $pdo->query("SELECT COUNT(*) FROM bt_candidate_experiences")->fetchColumn();
  $educationCount = $pdo->query("SELECT COUNT(*) FROM bt_candidate_education")->fetchColumn();

  echo "Experiencias guardadas: $experienceCount\n";
  echo "Educaciones guardadas: $educationCount\n";
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
