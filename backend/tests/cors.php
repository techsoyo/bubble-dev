<?php

/**
 * Configuración CORS segura para producción
 * 
 * Implementa configuración CORS restrictiva con verificación de orígenes
 * y headers de seguridad adicionales.
 * 
 * @author Bubble of Talents Security Team
 * @version 2.0.0
 */

declare(strict_types=1);
if (PHP_SAPI === 'cli') {
  return;
}                // no CORS en CLI
if (defined('CORS_APPLIED')) {
  return;
}           // idempotencia
define('CORS_APPLIED', true);

// Cargar configuración
require_once __DIR__ . '/config/config.php';

// ⚙️ Configuración CORS segura
$allowedOrigins = explode(',', config('CORS_ALLOWED_ORIGINS', 'http://localhost:3002'));
$allowCredentials = true;
$allowMethods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
$allowHeaders = 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token';
$maxAge = 600; // 10 minutos

// Detectar origen de la solicitud
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Validar origen contra lista permitida
if ($origin && in_array($origin, $allowedOrigins, true)) {
  header("Access-Control-Allow-Origin: $origin");

  if ($allowCredentials) {
    header('Access-Control-Allow-Credentials: true');
  }

  header('Vary: Origin');
} else {
  // En desarrollo, permitir localhost con validación
  if (isDevelopment() && $origin && (
    strpos($origin, 'http://localhost:') === 0 ||
    strpos($origin, 'http://127.0.0.1:') === 0
  )) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
  }
  // En producción, rechazar orígenes no autorizados
}

// Configurar métodos y headers permitidos
header("Access-Control-Allow-Methods: $allowMethods");
header("Access-Control-Allow-Headers: $allowHeaders");
header("Access-Control-Max-Age: $maxAge");

// Respuesta optimizada para preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204); // No Content
  exit;
}
