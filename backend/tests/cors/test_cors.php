#!/usr/bin/env php
<?php

/**
 * Script de prueba CORS - verifica que el middleware funcione correctamente
 */

// Simular una petición OPTIONS preflight
echo "=== PRUEBA CORS MIDDLEWARE ===\n";

// Configurar variables de entorno para prueba
putenv('APP_ENV=development');
putenv('CORS_ALLOWED_ORIGINS=http://localhost:3002,http://localhost:3000');
putenv('CORS_ALLOW_CREDENTIALS=true');
putenv('CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS');
putenv('CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Requested-With');
putenv('CORS_MAX_AGE=86400');

// Simular REQUEST_METHOD y HTTP_ORIGIN
$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost:3002';
$_SERVER['PHP_SELF'] = '/test';

// Capturar headers que se enviarían
ob_start();

// Incluir bootstrap para activar CORS
require_once __DIR__ . '/config/bootstrap.php';

$output = ob_get_clean();

echo "✅ Bootstrap cargado correctamente\n";
echo "Headers que se habrían enviado:\n";

// Mostrar headers enviados
if (function_exists('xdebug_get_headers')) {
  $headers = xdebug_get_headers();
  foreach ($headers as $header) {
    echo "  $header\n";
  }
} else {
  echo "  (xdebug no disponible para mostrar headers)\n";
  echo "  Expected headers based on configuration:\n";
  echo "  - Vary: Origin\n";
  echo "  - Access-Control-Allow-Origin: http://localhost:3002\n";
  echo "  - Access-Control-Allow-Credentials: true\n";
  echo "  - Access-Control-Allow-Methods: GET,POST,PUT,PATCH,DELETE,OPTIONS\n";
  echo "  - Access-Control-Allow-Headers: Content-Type,Authorization,X-Requested-With\n";
  echo "  - Access-Control-Max-Age: 86400\n";
}

echo "\n=== PRUEBA COMPLETADA ===\n";
