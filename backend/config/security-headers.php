<?php

/**
 * Headers de Seguridad Mejorados para Bubble of Talents
 * 
 * Configuración robusta de CSP y headers de seguridad para producción
 * con soporte diferenciado para desarrollo y producción.
 * 
 * @author Bubble of Talents Security Team
 * @version 2.0.0
 */

require_once __DIR__ . '/config.php';

/**
 * Genera nonce criptográficamente seguro
 */
function generateNonce(): string
{
  return base64_encode(random_bytes(16));
}

/**
 * Configura headers CORS usando variables de entorno
 */
function configureCors(): void
{
  // Obtener configuración desde variables de entorno con valores por defecto
  $allowedMethods = getenv('CORS_ALLOWED_METHODS') ?: 'GET,POST,PUT,DELETE,OPTIONS';
  $allowedOrigins = getenv('CORS_ALLOWED_ORIGINS') ?: '*';
  $allowedHeaders = getenv('CORS_ALLOWED_HEADERS') ?: 'Content-Type,Authorization,X-Requested-With';

  // Configurar headers CORS
  header('Access-Control-Allow-Origin: ' . $allowedOrigins);
  header('Access-Control-Allow-Methods: ' . $allowedMethods);
  header('Access-Control-Allow-Headers: ' . $allowedHeaders);
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Max-Age: 3600');

  // Manejar preflight requests
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
  }
}

/**
 * Establece headers de seguridad según el entorno
 * @return string El nonce generado para CSP
 */
function setSecurityHeaders(?string $environment = null): string
{
  $environment = $environment ?? (isDevelopment() ? 'development' : 'production');
  $isDev = ($environment === 'development');
  $nonce = generateNonce();

  // Content Security Policy seguro para producción
  $cspDirectives = [
    "default-src 'self'",
    $isDev
      ? "script-src 'self' 'unsafe-inline' 'unsafe-eval'"
      : "script-src 'self' 'nonce-$nonce'",
    $isDev
      ? "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com"
      : "style-src 'self' 'nonce-$nonce' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
    $isDev
      ? "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com"
      : "style-src-elem 'self' 'nonce-$nonce' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
    $isDev
      ? "img-src 'self' data: https:"
      : "img-src 'self' https:",
    "connect-src 'self' http://localhost:8000 https: wss: ws:",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "object-src 'none'",
    "upgrade-insecure-requests"
  ];

  // Aplicar CSP
  header('Content-Security-Policy: ' . implode('; ', $cspDirectives));

  // Headers de seguridad adicionales
  header('X-Frame-Options: DENY');
  header('X-XSS-Protection: 1; mode=block');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

  // Headers para evitar caché de páginas sensibles
  header('Cache-Control: no-cache, no-store, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');

  // Configurar CORS
  configureCors();

  return $nonce;
}

// Aplicar headers de seguridad automáticamente solo si no se está ejecutando desde CLI
if (PHP_SAPI !== 'cli' && !defined('SECURITY_HEADERS_APPLIED')) {
  define('SECURITY_HEADERS_APPLIED', true);
  $environment = ($_ENV['NODE_ENV'] ?? 'production');
  $nonce = setSecurityHeaders($environment);

  // Hacer el nonce disponible globalmente
  $GLOBALS['csp_nonce'] = $nonce;
}
