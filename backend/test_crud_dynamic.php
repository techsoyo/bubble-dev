<?php
// Test dinámico de completitud CRUD - Lee rutas reales

echo "=== TEST DINÁMICO DE COMPLETITUD CRUD ===\n\n";

// Leer rutas reales del archivo routes.php
$routesFile = __DIR__ . '/config/routes.php';
if (!file_exists($routesFile)) {
  echo "❌ ERROR: No se encontró el archivo routes.php\n";
  exit(1);
}

$routes = require $routesFile;

// Organizar rutas por endpoint
$endpoints = [];
foreach ($routes as $route) {
  $method = $route[0];
  $path = $route[1];
  $handler = $route[2];

  // Extraer el endpoint base (ej: /api/users/{id} -> users)
  if (preg_match('/\/api\/([^\/\{]+)/', $path, $matches)) {
    $endpointName = $matches[1];

    if (!isset($endpoints[$endpointName])) {
      $endpoints[$endpointName] = [];
    }

    $endpoints[$endpointName][] = [
      'method' => $method,
      'path' => $path,
      'handler' => $handler
    ];
  }
}

// Métodos CRUD esperados
$expectedCrudMethods = ['GET', 'POST', 'PUT', 'DELETE'];

// Analizar cada endpoint
$summary = [
  'completos' => 0,
  'casi_completos' => 0,
  'incompletos' => 0,
  'total' => 0
];

foreach ($endpoints as $endpointName => $endpointRoutes) {
  $summary['total']++;

  echo "📋 ENDPOINT: /$endpointName\n";

  // Verificar métodos CRUD disponibles
  $availableMethods = [];
  $hasIndex = false;
  $hasShow = false;
  $hasStore = false;
  $hasUpdate = false;
  $hasDelete = false;

  foreach ($endpointRoutes as $route) {
    $method = $route['method'];
    $path = $route['path'];
    $handler = $route['handler'];

    if ($method === 'GET') {
      if (strpos($path, '{id}') !== false) {
        $hasShow = true;
        echo "  ✅ GET $path ($handler) - SHOW\n";
      } else {
        $hasIndex = true;
        echo "  ✅ GET $path ($handler) - INDEX\n";
      }
    } elseif ($method === 'POST' && strpos($path, '{id}') === false) {
      $hasStore = true;
      echo "  ✅ POST $path ($handler) - STORE\n";
    } elseif ($method === 'PUT') {
      $hasUpdate = true;
      echo "  ✅ PUT $path ($handler) - UPDATE\n";
    } elseif ($method === 'DELETE' && strpos($path, '{id}') !== false) {
      $hasDelete = true;
      echo "  ✅ DELETE $path ($handler) - DELETE\n";
    } else {
      echo "  🔧 $method $path ($handler) - ESPECIAL\n";
    }
  }

  // Determinar estado del endpoint
  $crudCount = 0;
  if ($hasIndex) $crudCount++;
  if ($hasShow) $crudCount++;
  if ($hasStore) $crudCount++;
  if ($hasUpdate) $crudCount++;
  if ($hasDelete) $crudCount++;

  $missing = [];
  if (!$hasIndex) $missing[] = 'INDEX (GET /)';
  if (!$hasShow) $missing[] = 'SHOW (GET /{id})';
  if (!$hasStore) $missing[] = 'STORE (POST /)';
  if (!$hasUpdate) $missing[] = 'UPDATE (PUT /{id})';
  if (!$hasDelete) $missing[] = 'DELETE (DELETE /{id})';

  if ($crudCount >= 5) {
    echo "Estado: ✅ COMPLETO\n";
    $summary['completos']++;
  } elseif ($crudCount >= 3) {
    echo "Estado: ⚠️ CASI COMPLETO\n";
    if (!empty($missing)) {
      echo "❌ Faltantes: " . implode(', ', $missing) . "\n";
    }
    $summary['casi_completos']++;
  } else {
    echo "Estado: ❌ INCOMPLETO\n";
    if (!empty($missing)) {
      echo "❌ Faltantes: " . implode(', ', $missing) . "\n";
    }
    $summary['incompletos']++;
  }

  echo "\n" . str_repeat('-', 80) . "\n\n";
}

// Resumen final
echo "=== RESUMEN FINAL DINÁMICO ===\n";
echo "✅ COMPLETOS: {$summary['completos']}/{$summary['total']} endpoints\n";
echo "⚠️ CASI COMPLETOS: {$summary['casi_completos']}/{$summary['total']} endpoints\n";
echo "❌ INCOMPLETOS: {$summary['incompletos']}/{$summary['total']} endpoints\n\n";

$completeness = round(($summary['completos'] / $summary['total']) * 100, 1);
echo "🎯 COMPLETITUD TOTAL: $completeness%\n\n";

if ($completeness >= 75) {
  echo "🎉 ¡EXCELENTE TRABAJO! La API está muy bien completada.\n";
} elseif ($completeness >= 50) {
  echo "👍 Buen progreso, pero aún hay trabajo por hacer.\n";
} else {
  echo "⚠️ Se necesita más trabajo para completar la API.\n";
}
