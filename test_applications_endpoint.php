<?php
// Simular una petición GET al endpoint
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_ACCEPT'] = 'application/json';

ob_start();
include 'backend/api/applications.php';
$output = ob_get_clean();

echo "Respuesta del endpoint:\n";
echo $output;
