<?php
header('Access-Control-Allow-Origin: http://localhost:3002');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

header('Content-Type: application/json');
echo json_encode([
  'status' => 'success',
  'message' => 'Backend is working!',
  'timestamp' => date('Y-m-d H:i:s'),
  'method' => $_SERVER['REQUEST_METHOD'],
  'path' => $_SERVER['REQUEST_URI']
]);
