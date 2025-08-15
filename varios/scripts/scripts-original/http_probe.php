<?php

declare(strict_types=1);

$uri = $argv[1] ?? 'http://127.0.0.1:8050/api/cv/parse.php';
$origin = $argv[2] ?? 'http://localhost:3000';
$method = $argv[3] ?? 'POST';

$opts = [
  'http' => [
    'method' => 'OPTIONS',
    'header' => [
      'Origin: ' . $origin,
      'Access-Control-Request-Method: ' . $method,
    ],
    'ignore_errors' => true,
    'timeout' => 5,
  ]
];
$ctx = stream_context_create($opts);
$body = @file_get_contents($uri, false, $ctx);
$headers = $http_response_header ?? [];

$code = 0;
if ($headers) {
  if (preg_match('#\s(\d{3})\s#', $headers[0], $m)) {
    $code = (int)$m[1];
  }
}

echo "STATUS: $code\n";
foreach ($headers as $h) {
  echo $h, "\n";
}
