<?php declare(strict_types=1);
// Rutas para la API de IA (MVP)

// Importar las clases necesarias
use Utils\Router;
use Controllers\AIController;
use Middleware\CorsMiddleware;
use Middleware\AuthMiddleware;

// Usamos el router ya inicializado en index.php
global $router;

// Rutas adicionales para la API de IA que podrÃ­an agregarse en el futuro
// Por ahora estas rutas son solo ejemplos y no se utilizan realmente
$router->addGroup('/api/ai/advanced', function ($router) {
  // Ejemplo: AnÃ¡lisis avanzado de personalidad
  $router->add('POST', '/analyze-personality', 'AIController', 'analyzePersonality');

  // Ejemplo: PredicciÃ³n de rendimiento
  $router->add('POST', '/predict-performance', 'AIController', 'predictPerformance');
}, [new CorsMiddleware()]);

// Nota: Las rutas principales de IA ya estÃ¡n definidas en index.php
