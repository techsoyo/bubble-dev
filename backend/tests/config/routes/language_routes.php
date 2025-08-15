<?php

/**
 * Rutas para la API de idiomas
 */

// Importar las clases necesarias
use Utils\Router;
use Controllers\LanguageController;

// Usamos el router ya inicializado en index.php
global $router;

// Rutas para el controlador de idiomas
$router->add('GET', '/api/language', 'LanguageController', 'getLanguage');
$router->add('POST', '/api/language', 'LanguageController', 'setLanguage');
