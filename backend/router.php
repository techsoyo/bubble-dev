<?php

/**
 * Router principal usando AltoRouter para manejo centralizado de rutas
 * Este archivo es usado por el servidor PHP integrado
 * 
 * Uso: php -S localhost:8000 -t . router.php
 */

// Si estamos usando el servidor embebido (cli-server), delegar archivos existentes al servidor
if (PHP_SAPI === 'cli-server') {
  $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  // Cuando se ejecuta con "-t public", los recursos están bajo /public
  $publicPath = __DIR__ . '/public' . $uriPath;
  if (is_file($publicPath)) {
    // Devolver false indica al servidor embebido que sirva el archivo directamente
    return false;
  }

  // Soportar rutas tipo /api/xyz sin .php apuntando a public/api/xyz.php
  if (str_starts_with($uriPath, '/api/')) {
    $apiScript = __DIR__ . '/public' . $uriPath;
    if (pathinfo($apiScript, PATHINFO_EXTENSION) !== 'php') {
      $apiScriptPhp = $apiScript . '.php';
      if (is_file($apiScriptPhp)) {
        // Incluir el script de la API y terminar
        require $apiScriptPhp;
        return true;
      }
    }
  }
}

// Cargar autoloader de Composer
require_once __DIR__ . '/vendor/autoload.php';

// Cargar bootstrap con configuraciones
require_once __DIR__ . '/config/bootstrap.php';

use Router\AppRouter;

// Crear instancia del router sin basePath para simplificar
$router = new AppRouter();

// Procesar la solicitud
$router->dispatch();
