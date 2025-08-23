<?php
try {
  require_once 'config/config.php';
  loadEnvironmentVars();
  echo 'Environment loaded successfully' . PHP_EOL;
  echo 'DB_HOST: ' . getenv('DB_HOST') . PHP_EOL;
  echo 'DB_USER: ' . getenv('DB_USER') . PHP_EOL;
  echo 'DB_NAME: ' . getenv('DB_NAME') . PHP_EOL;
  echo 'DB_PASSWORD set: ' . (getenv('DB_PASSWORD') ? 'YES' : 'NO') . PHP_EOL;

  require_once 'src/Utils/Database.php';
  $db = \Utils\Database::getInstance();
  echo 'SUCCESS: Database connection established' . PHP_EOL;
} catch (Exception $e) {
  echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
  exit(1);
}
