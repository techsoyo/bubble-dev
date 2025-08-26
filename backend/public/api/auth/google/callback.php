<?php declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

/**
 * OAuth Callback Endpoints - Google
 * URL: /auth/google/callback
 */

require_once __DIR__ . '/../OAuthHandler.php';

try {
  $code = $_GET['code'] ?? null;
  $state = $_GET['state'] ?? null;
  $error = $_GET['error'] ?? null;

  if ($error) {
    throw new Exception("OAuth error: $error");
  }

  if (!$code) {
    throw new Exception('CÃƒÆ’Ã‚Â³digo de autorizaciÃƒÆ’Ã‚Â³n no recibido');
  }

  $oauth = new OAuthHandler();
  $result = $oauth->handleCallback('google', $code, $state);

  if ($result['success']) {
    // Crear sesiÃƒÆ’Ã‚Â³n de usuario
    session_start();
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['user_email'] = $result['user']['email'];
    $_SESSION['oauth_provider'] = 'google';

    // Extraer job ID del state si existe
    $jobId = null;
    if ($state && strpos($state, 'job_') === 0) {
      $jobId = substr($state, 4);
    }

    // Redirigir segÃƒÆ’Ã‚Âºn el contexto
    if ($jobId) {
      $redirectUrl = "http://localhost:3002/jobs/$jobId?login=success";
    } else {
      $redirectUrl = "http://localhost:3002/dashboard?login=success";
    }

    header("Location: $redirectUrl");
    exit;
  } else {
    throw new Exception($result['error']);
  }
} catch (Exception $e) {
  // Redirigir a pÃƒÆ’Ã‚Â¡gina de error
  $errorUrl = "http://localhost:3002/auth/register?error=" . urlencode($e->getMessage());
  header("Location: $errorUrl");
  exit;
}

