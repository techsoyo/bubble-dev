<?php
// Router para el servidor de desarrollo PHP
$request_uri = $_SERVER['REQUEST_URI'];

// Separar la ruta del query string
$parsed_uri = parse_url($request_uri);
$path = $parsed_uri['path'] ?? '';
$file_path = __DIR__ . $path;

// Si el archivo existe, servirlo directamente
if (file_exists($file_path) && !is_dir($file_path)) {
  return false; // Usar el servidor PHP integrado por defecto
}

// Redirigir requests de API a los endpoints correspondientes
if (strpos($path, '/api/') === 0) {
  $file_path = __DIR__ . $path;

  // Si el archivo existe tal como está
  if (file_exists($file_path)) {
    include $file_path;
    return true;
  }

  // Si no existe, intentar con extensión .php
  $php_file_path = $file_path . '.php';
  if (file_exists($php_file_path)) {
    include $php_file_path;
    return true;
  }
}

// Redirigir requests de /endpoints/ a /api/endpoints/
if (strpos($path, '/endpoints/') === 0) {
  $redirected_path = '/api' . $path;
  $file_path = __DIR__ . $redirected_path;

  // Si el archivo existe tal como está
  if (file_exists($file_path)) {
    include $file_path;
    return true;
  }

  // Si no existe, intentar con extensión .php
  $php_file_path = $file_path . '.php';
  if (file_exists($php_file_path)) {
    include $php_file_path;
    return true;
  }
}

// Para todo lo demás, devolver 404
http_response_code(404);
echo "Endpoint no encontrado: " . $request_uri;
return true;
