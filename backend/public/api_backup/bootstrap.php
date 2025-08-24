<?php

declare(strict_types=1);

/**
 * Bootstrap puente para endpoints bajo public/api/*
 * Asegura que el bootstrap principal con CORS se cargue para todos los endpoints API
 */
// Evitar doble carga
if (!defined('API_BOOTSTRAPPED')) {
    define('API_BOOTSTRAPPED', true);

// Ruta al bootstrap principal con configuración CORS
$mainBootstrap = __DIR__ . '/../../config/bootstrap.php';

if (!file_exists($mainBootstrap)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Bootstrap principal no encontrado',
        'data' => null
    ]);
    exit;
}

// Incluir bootstrap principal - esto activa CORS, autoloader, configuración, etc.
require_once $mainBootstrap;
}