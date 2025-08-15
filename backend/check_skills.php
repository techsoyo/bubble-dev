<?php
require_once 'config/bootstrap.php';
try {
  $pdo = getDBConnection();
  $stmt = $pdo->query('SELECT COUNT(*) as count FROM bt_candidate_skills');
  $result = $stmt->fetch();
  echo 'Skills en bt_candidate_skills: ' . $result['count'] . PHP_EOL;

  $stmt = $pdo->query('SELECT * FROM bt_candidate_skills LIMIT 5');
  $skills = $stmt->fetchAll();
  echo 'Primeros 5 skills:' . PHP_EOL;
  foreach ($skills as $skill) {
    echo $skill['candidate_id'] . ' - ' . $skill['skill'] . PHP_EOL;
  }
} catch (Exception $e) {
  echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
