<?php

// Test manual para el endpoint de candidate login
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/candidate-login';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost:3002';

// Simular input JSON
$json_input = json_encode([
  'email' => 'test@example.com',
  'password' => 'test123'
]);

// Mockear php://input usando stream
$stream = fopen('php://temp', 'w+');
fwrite($stream, $json_input);
rewind($stream);

// Reemplazar php://input temporalmente para test
$old_input_stream = 'php://input';

require_once __DIR__ . '/router.php';
