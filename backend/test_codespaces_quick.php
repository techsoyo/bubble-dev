<?php
require_once __DIR__ . '/config/bootstrap.php';

use Services\CodespacesService;

echo "🔍 Test CodespacesService..." . PHP_EOL;
$service = new CodespacesService();
echo "✅ Servicio instanciado correctamente" . PHP_EOL;

$test = $service->testConnection();
echo "📊 Resultado: " . json_encode($test, JSON_PRETTY_PRINT) . PHP_EOL;
