<?php declare(strict_types=1);
// @public  
/**
 * OAuth Start Endpoint - Inicia el flujo OAuth
 * URL: /auth/oauth/start.php?provider=google&job=123
 */

require_once __DIR__ . '/../../bootstrap.php';
// NO JWTMiddleware::requireAuth() aquÃ­ - endpoint pÃºblico
// NO CsrfMiddleware::protect() aquÃ­ - inicia flujo OAuth (no modifica estado de usuario aÃºn)

require_once __DIR__ . '/OAuthHandler.php';

header('Content-Type: application/json');

try {
  $provider = $_GET['provider'] ?? null;
  $jobId = $_GET['job'] ?? null;

  if (!$provider || !in_array($provider, ['google', 'linkedin'])) {
    throw new Exception('Proveedor OAuth no vÃƒÂ¡lido');
  }

  $oauth = new OAuthHandler();

  if (!$oauth->isConfigured($provider)) {
    throw new Exception("OAuth no configurado para $provider. Revisa tu archivo .env.oauth");
  }

  $authUrl = getOAuthAuthUrl($provider, $jobId);

  // Redirigir al proveedor OAuth
  header("Location: $authUrl");
  exit;
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage(),
    'help' => 'Revisa la guÃƒÂ­a OAUTH_SETUP_GUIDE.md para configurar OAuth'
  ]);
}
