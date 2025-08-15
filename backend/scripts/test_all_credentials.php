<?php

declare(strict_types=1);

echo "=== PRUEBA DE TODAS LAS CREDENCIALES ===\n\n";

// Credenciales a probar
$credentials = [
  ['email' => 'ana.torres@bubblegum.agency', 'password' => 'BubbleAdmin2025!'],
  ['email' => 'miguel.ruiz@bubblegum.agency', 'password' => 'Recruit#2025M'],
  ['email' => 'sofia.navarro@bubblegum.agency', 'password' => 'Sofia&Talents25'],
  ['email' => 'carlos.vega@bubblegum.agency', 'password' => 'CarlosRec#2025'],
  ['email' => 'elena.ruiz@bubblegum.agency', 'password' => 'Elena!Bubble25']
];

$url = 'http://localhost:8000/auth/staff-login.php';

foreach ($credentials as $cred) {
  echo "Probando: {$cred['email']}\n";

  $testData = [
    'action' => 'staff_login',
    'email' => $cred['email'],
    'password' => $cred['password']
  ];

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
  ]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $decoded = json_decode($response, true);

  if ($httpCode === 200 && $decoded && $decoded['success']) {
    echo "  ✅ LOGIN EXITOSO - {$decoded['user']['name']} ({$decoded['user']['role']})\n";
  } else {
    echo "  ❌ LOGIN FALLIDO - HTTP: $httpCode\n";
    if ($decoded) {
      echo "     Mensaje: {$decoded['message']}\n";
    }
  }
  echo "\n";
}
