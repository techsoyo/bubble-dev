<?php
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}
echo "Test file works!";
echo "\nCurrent directory: " . __DIR__;
echo "\nFiles in directory:";
foreach (glob("*.php") as $file) {
  echo "\n- " . $file;
}
