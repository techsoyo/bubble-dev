<?php
// Router para el servidor de desarrollo PHP
$request_uri = $_SERVER['REQUEST_URI'];
$file_path = __DIR__ . $request_uri;

// Si el archivo existe, servirlo directamente
if (file_exists($file_path) && !is_dir($file_path)) {
  return false; // Usar el servidor PHP integrado por defecto
}

// Redirigir requests de API a los endpoints correspondientes
if (strpos($request_uri, '/api/') === 0) {
  $file_path = __DIR__ . $request_uri;
  if (file_exists($file_path)) {
    include $file_path;
    return true;
  }
}

// Para todo lo demás, devolver 404
http_response_code(404);
echo "Endpoint no encontrado: " . $request_uri;
return true;
