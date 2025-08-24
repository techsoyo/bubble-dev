<?php

/**
 * OAuth Callback Endpoints - LinkedIn
 * URL: /auth/linkedin/callback
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
    throw new Exception('Código de autorización no recibido');
  }

  $oauth = new OAuthHandler();
  $result = $oauth->handleCallback('linkedin', $code, $state);

  if ($result['success']) {
    // Crear sesión de usuario
    session_start();
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['user_email'] = $result['user']['email'];
    $_SESSION['oauth_provider'] = 'linkedin';

    // Extraer job ID del state si existe
    $jobId = null;
    if ($state && strpos($state, 'job_') === 0) {
      $jobId = substr($state, 4);
    }

    // Redirigir según el contexto
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
  // Redirigir a página de error
  $errorUrl = "http://localhost:3002/auth/register?error=" . urlencode($e->getMessage());
  header("Location: $errorUrl");
  exit;
}
