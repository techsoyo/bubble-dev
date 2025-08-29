<?php

declare(strict_types=1);

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;
use Services\AuthService;
use Services\SocialLoginService;
use Services\RateLimitService;
use Services\SecurityLoggerService;
use Services\ValidationService;
use Domain\ValueObjects\OAuthToken;
use Domain\Exceptions\OAuthException;

class SocialLoginController
{
  private SocialLoginService $socialLoginService;
  private AuthService $authService;

  public function __construct()
  {
    $this->socialLoginService = new SocialLoginService();
    $this->authService = new AuthService();
  }

  /**
   * Lista proveedores de login social disponibles
   */
  public function index(Request $request, array $params = [])
  {
    try {
      $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

      // RATE LIMITING para consultas de proveedores
      if (!RateLimitService::canPerform('social_providers', $clientIP)) {
        $retryAfter = RateLimitService::getRetryAfter('social_providers', $clientIP);
        SecurityLoggerService::logRateLimitViolation('social_providers', $clientIP, 30, 30);

        ResponseHelper::fail('Demasiadas consultas. Intente nuevamente más tarde.', 429, [
          'retry_after' => $retryAfter
        ]);
        return;
      }

      RateLimitService::recordAttempt('social_providers', $clientIP);
      SecurityLoggerService::logSecurityEvent('social_providers_accessed', [
        'ip_address' => $clientIP
      ], 'INFO');

      $providers = $this->socialLoginService->getAvailableProviders();

      Logger::info('Social login providers listed', [
        'provider_count' => count($providers),
        'ip_address' => $clientIP
      ]);

      ResponseHelper::success('Proveedores de login social disponibles', [
        'providers' => $providers
      ]);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('social_providers_error', [
        'error' => $e->getMessage(),
        'ip_address' => $clientIP ?? 'unknown'
      ], 'ERROR');

      Logger::error('Error listing social providers', ['error' => $e->getMessage()]);
      ResponseHelper::error('Error al obtener proveedores', $e, 500);
    }
  }

  /**
   * Redirige al proveedor de OAuth para autenticación
   */
  public function redirect(Request $request, array $params = [])
  {
    try {
      $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

      // RATE LIMITING para redirecciones OAuth
      if (!RateLimitService::canPerform('social_redirect', $clientIP)) {
        $retryAfter = RateLimitService::getRetryAfter('social_redirect', $clientIP);
        SecurityLoggerService::logRateLimitViolation('social_redirect', $clientIP, 10, 10);

        ResponseHelper::fail('Demasiadas redirecciones. Intente nuevamente más tarde.', 429, [
          'retry_after' => $retryAfter
        ]);
        return;
      }

      $provider = $params['provider'] ?? null;
      if (!$provider) {
        SecurityLoggerService::logSecurityEvent('social_redirect_missing_provider', [
          'ip_address' => $clientIP,
          'params' => $params
        ], 'WARNING');

        ResponseHelper::error('Proveedor requerido', null, 400);
        return;
      }

      // Validar proveedor
      $allowedProviders = ['google', 'facebook', 'github', 'linkedin'];
      if (!in_array($provider, $allowedProviders)) {
        SecurityLoggerService::logSecurityEvent('social_redirect_invalid_provider', [
          'ip_address' => $clientIP,
          'provider' => $provider
        ], 'WARNING');

        ResponseHelper::error('Proveedor no válido', null, 400);
        return;
      }

      // Generar estado único para prevenir CSRF
      $state = bin2hex(random_bytes(32));
      $this->socialLoginService->storeState($state);

      // Generar URL de autorización
      $authUrl = $this->socialLoginService->getAuthorizationUrl($provider, $state);

      RateLimitService::recordAttempt('social_redirect', $clientIP);
      SecurityLoggerService::logSecurityEvent('social_redirect_initiated', [
        'ip_address' => $clientIP,
        'provider' => $provider,
        'state' => substr($state, 0, 8) . '...' // Log parcial por seguridad
      ], 'INFO');

      Logger::info('Social login redirect initiated', [
        'provider' => $provider,
        'ip_address' => $clientIP
      ]);

      ResponseHelper::success("Redirigiendo a $provider", [
        'url' => $authUrl,
        'state' => $state
      ]);
    } catch (OAuthException $e) {
      SecurityLoggerService::logSecurityEvent('social_redirect_oauth_error', [
        'ip_address' => $clientIP,
        'provider' => $provider ?? 'unknown',
        'error' => $e->getMessage()
      ], 'ERROR');

      Logger::error('OAuth redirect error', [
        'provider' => $provider ?? 'unknown',
        'error' => $e->getMessage()
      ]);
      ResponseHelper::error($e->getMessage(), null, 400);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('social_redirect_unexpected_error', [
        'ip_address' => $clientIP,
        'provider' => $provider ?? 'unknown',
        'error' => $e->getMessage()
      ], 'ERROR');

      Logger::error('Unexpected redirect error', [
        'provider' => $provider ?? 'unknown',
        'error' => $e->getMessage()
      ]);
      ResponseHelper::error('Error al generar URL de autorización', $e, 500);
    }
  }

  /**
   * Procesa el callback del proveedor OAuth de manera SEGURA
   * NO expone tokens en el frontend
   */
  public function callback(Request $request, array $params = [])
  {
    try {
      $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

      // RATE LIMITING para callbacks OAuth
      if (!RateLimitService::canPerform('social_callback', $clientIP)) {
        $retryAfter = RateLimitService::getRetryAfter('social_callback', $clientIP);
        SecurityLoggerService::logRateLimitViolation('social_callback', $clientIP, 5, 5);

        ResponseHelper::fail('Demasiados callbacks. Intente nuevamente más tarde.', 429, [
          'retry_after' => $retryAfter
        ]);
        return;
      }

      $provider = $params['provider'] ?? null;
      if (!$provider) {
        SecurityLoggerService::logSecurityEvent('social_callback_missing_provider', [
          'ip_address' => $clientIP,
          'params' => $params
        ], 'WARNING');

        ResponseHelper::error('Proveedor requerido', null, 400);
        return;
      }

      // Validar proveedor
      $allowedProviders = ['google', 'facebook', 'github', 'linkedin'];
      if (!in_array($provider, $allowedProviders)) {
        SecurityLoggerService::logSecurityEvent('social_callback_invalid_provider', [
          'ip_address' => $clientIP,
          'provider' => $provider
        ], 'WARNING');

        ResponseHelper::error('Proveedor no válido', null, 400);
        return;
      }

      $data = $request->all();
      $code = $data['code'] ?? null;
      $state = $data['state'] ?? null;
      $error = $data['error'] ?? null;

      // Verificar errores del proveedor
      if ($error) {
        $this->handleOAuthError($error, $data['error_description'] ?? '');
        return;
      }

      // Validar parámetros requeridos
      if (!$code || !$state) {
        SecurityLoggerService::logSecurityEvent('social_callback_missing_params', [
          'ip_address' => $clientIP,
          'provider' => $provider,
          'has_code' => !empty($code),
          'has_state' => !empty($state)
        ], 'WARNING');

        ResponseHelper::error('Código de autorización y estado son requeridos', null, 400);
        return;
      }

      // Verificar estado para prevenir CSRF
      if (!$this->socialLoginService->verifyState($state)) {
        SecurityLoggerService::logSecurityEvent('social_callback_invalid_state', [
          'ip_address' => $clientIP,
          'provider' => $provider,
          'state' => substr($state, 0, 8) . '...'
        ], 'WARNING');

        ResponseHelper::error('Parámetro de estado inválido', null, 400);
        return;
      }

      RateLimitService::recordAttempt('social_callback', $clientIP);
      SecurityLoggerService::logSecurityEvent('social_callback_processing', [
        'ip_address' => $clientIP,
        'provider' => $provider
      ], 'INFO');

      // Intercambiar código por tokens (SERVIDOR-SEGURO)
      $tokenData = $this->socialLoginService->exchangeCodeForTokens($provider, $code);

      // Obtener información del usuario
      $userInfo = $this->socialLoginService->getUserInfo($provider, $tokenData);

      // Crear o actualizar usuario en el sistema
      $user = $this->authService->findOrCreateUserFromSocialLogin($userInfo, $provider);

      // Generar tokens JWT del sistema
      $authTokens = $this->authService->generateAuthTokens($user);

      // Limpiar estado usado
      $this->socialLoginService->clearState($state);

      Logger::info('Social login successful', [
        'provider' => $provider,
        'user_id' => $user['id'] ?? 'unknown',
        'ip_address' => $clientIP
      ]);

      SecurityLoggerService::logSecurityEvent('social_callback_success', [
        'ip_address' => $clientIP,
        'provider' => $provider,
        'user_id' => $user['id'] ?? 'unknown'
      ], 'INFO');

      // Redirigir al frontend con tokens seguros (en lugar de exponerlos)
      $this->redirectToFrontendWithTokens($authTokens);
    } catch (OAuthException $e) {
      SecurityLoggerService::logSecurityEvent('social_callback_oauth_error', [
        'ip_address' => $clientIP,
        'provider' => $provider ?? 'unknown',
        'error' => $e->getMessage()
      ], 'ERROR');

      Logger::error('OAuth callback error', [
        'provider' => $provider ?? 'unknown',
        'error' => $e->getMessage()
      ]);
      ResponseHelper::error('Autenticación OAuth fallida: ' . $e->getMessage(), null, 400);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('social_callback_unexpected_error', [
        'ip_address' => $clientIP,
        'provider' => $provider ?? 'unknown',
        'error' => $e->getMessage()
      ], 'ERROR');

      Logger::error('Unexpected callback error', [
        'provider' => $provider ?? 'unknown',
        'error' => $e->getMessage()
      ]);
      ResponseHelper::error('Error en autenticación', $e, 500);
    }
  }

  /**
   * Maneja errores de OAuth
   */
  private function handleOAuthError(string $error, string $description = ''): void
  {
    $errorMessages = [
      'access_denied' => 'Usuario denegó acceso a su cuenta',
      'invalid_request' => 'Solicitud de autorización inválida',
      'unauthorized_client' => 'Cliente no autorizado',
      'unsupported_response_type' => 'Tipo de respuesta no soportado',
      'invalid_scope' => 'Alcance solicitado inválido',
      'server_error' => 'Error del servidor de autorización',
      'temporarily_unavailable' => 'Servidor de autorización temporalmente no disponible'
    ];

    $message = $errorMessages[$error] ?? 'Error de OAuth ocurrido';
    if ($description) {
      $message .= ': ' . $description;
    }

    SecurityLoggerService::logSecurityEvent('oauth_error', [
      'error_code' => $error,
      'error_description' => $description,
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ], 'WARNING');

    ResponseHelper::error($message, null, 400);
  }

  /**
   * Redirige al frontend con tokens seguros
   * Usa parámetros de URL temporales o headers seguros
   */
  private function redirectToFrontendWithTokens(array $authTokens): void
  {
    try {
      // Generar token temporal único para la sesión
      $sessionToken = bin2hex(random_bytes(32));

      // Almacenar tokens de forma segura en el servidor
      $this->authService->storeTemporarySession($sessionToken, $authTokens);

      // Redirigir al frontend con el token de sesión
      $frontendUrl = $_ENV['FRONTEND_URL'] ?? $_SERVER['FRONTEND_URL'] ?? 'http://localhost:3002';
      $redirectUrl = $frontendUrl . '/auth/callback?session=' . $sessionToken;

      Logger::info('Redirecting to frontend with session token', [
        'session_token' => substr($sessionToken, 0, 8) . '...',
        'redirect_url' => $redirectUrl
      ]);

      ResponseHelper::success('Autenticación exitosa', [
        'redirect_url' => $redirectUrl,
        'session_token' => $sessionToken
      ], 302);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('frontend_redirect_error', [
        'error' => $e->getMessage(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ], 'ERROR');

      Logger::error('Error redirecting to frontend', ['error' => $e->getMessage()]);
      ResponseHelper::error('Error en redirección', $e, 500);
    }
  }

  /**
   * Endpoint para que el frontend obtenga tokens usando el session token
   */
  public function getTokens(Request $request, array $params = [])
  {
    try {
      $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

      // RATE LIMITING para obtención de tokens
      if (!RateLimitService::canPerform('social_get_tokens', $clientIP)) {
        $retryAfter = RateLimitService::getRetryAfter('social_get_tokens', $clientIP);
        SecurityLoggerService::logRateLimitViolation('social_get_tokens', $clientIP, 10, 10);

        ResponseHelper::fail('Demasiadas solicitudes de tokens. Intente nuevamente más tarde.', 429, [
          'retry_after' => $retryAfter
        ]);
        return;
      }

      $sessionToken = $request->input('session_token');
      if (!$sessionToken) {
        SecurityLoggerService::logSecurityEvent('get_tokens_missing_session', [
          'ip_address' => $clientIP
        ], 'WARNING');

        ResponseHelper::error('Token de sesión requerido', null, 400);
        return;
      }

      // Validar formato del session token
      if (!preg_match('/^[a-f0-9]{64}$/', $sessionToken)) {
        SecurityLoggerService::logSecurityEvent('get_tokens_invalid_format', [
          'ip_address' => $clientIP,
          'session_token' => substr($sessionToken, 0, 8) . '...'
        ], 'WARNING');

        ResponseHelper::error('Formato de token de sesión inválido', null, 400);
        return;
      }

      RateLimitService::recordAttempt('social_get_tokens', $clientIP);

      // Obtener tokens de la sesión temporal
      $authTokens = $this->authService->getTokensFromSession($sessionToken);

      if (!$authTokens) {
        SecurityLoggerService::logSecurityEvent('get_tokens_invalid_session', [
          'ip_address' => $clientIP,
          'session_token' => substr($sessionToken, 0, 8) . '...'
        ], 'WARNING');

        ResponseHelper::error('Token de sesión inválido o expirado', null, 400);
        return;
      }

      // Limpiar sesión temporal después de usarla
      $this->authService->clearTemporarySession($sessionToken);

      Logger::info('Tokens retrieved successfully', [
        'ip_address' => $clientIP,
        'session_token' => substr($sessionToken, 0, 8) . '...'
      ]);

      SecurityLoggerService::logSecurityEvent('get_tokens_success', [
        'ip_address' => $clientIP
      ], 'INFO');

      ResponseHelper::success('Tokens obtenidos exitosamente', [
        'access_token' => $authTokens['access_token'],
        'refresh_token' => $authTokens['refresh_token'],
        'expires_in' => $authTokens['expires_in']
      ]);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('get_tokens_unexpected_error', [
        'ip_address' => $clientIP,
        'error' => $e->getMessage()
      ], 'ERROR');

      Logger::error('Unexpected error retrieving tokens', ['error' => $e->getMessage()]);
      ResponseHelper::error('Error al obtener tokens', $e, 500);
    }
  }
}
