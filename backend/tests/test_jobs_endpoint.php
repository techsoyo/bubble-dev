<?php
// Test directo del endpoint jobs.php
try {
  $_SERVER['REQUEST_METHOD'] = 'GET';
  $_GET['limit'] = 5;
  $_GET['offset'] = 0;

  ob_start();
  include 'public/api/jobs.php';
  $output = ob_get_clean();

  echo "Output: " . $output . PHP_EOL;
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . PHP_EOL;
}
