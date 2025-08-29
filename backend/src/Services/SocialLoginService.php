<?php

declare(strict_types=1);

namespace Services;

use Domain\ValueObjects\OAuthToken;
use Domain\Exceptions\OAuthException;

class SocialLoginService
{
  private array $config;

  public function __construct()
  {
    $this->config = [
      'google' => [
        'client_id' => getenv('GOOGLE_CLIENT_ID'),
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => getenv('GOOGLE_REDIRECT_URI'),
        'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'userinfo_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
        'scope' => 'openid email profile'
      ],
      'linkedin' => [
        'client_id' => getenv('LINKEDIN_CLIENT_ID'),
        'client_secret' => getenv('LINKEDIN_CLIENT_SECRET'),
        'redirect_uri' => getenv('LINKEDIN_REDIRECT_URI'),
        'authorization_url' => 'https://www.linkedin.com/oauth/v2/authorization',
        'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
        'userinfo_url' => 'https://api.linkedin.com/v2/userinfo',
        'scope' => 'r_liteprofile r_emailaddress'
      ]
    ];
  }

  /**
   * Obtiene proveedores disponibles
   */
  public function getAvailableProviders(): array
  {
    return array_keys($this->config);
  }

  /**
   * Almacena el estado para prevenir CSRF
   */
  public function storeState(string $state): void
  {
    // Almacenar en sesión o cache temporal
    $_SESSION['oauth_state'] = $state;
  }

  /**
   * Verifica el estado para prevenir CSRF
   */
  public function verifyState(string $state): bool
  {
    return isset($_SESSION['oauth_state']) && $_SESSION['oauth_state'] === $state;
  }

  /**
   * Limpia el estado usado
   */
  public function clearState(string $state): void
  {
    unset($_SESSION['oauth_state']);
  }

  /**
   * Genera URL de autorización para el proveedor
   */
  public function getAuthorizationUrl(string $provider, string $state): string
  {
    if (!isset($this->config[$provider])) {
      throw new OAuthException("Unsupported provider: $provider");
    }

    $config = $this->config[$provider];

    $params = [
      'client_id' => $config['client_id'],
      'redirect_uri' => $config['redirect_uri'],
      'response_type' => 'code',
      'scope' => $config['scope'],
      'state' => $state,
      'access_type' => 'offline', // Para refresh token
      'prompt' => 'consent' // Forzar consentimiento
    ];

    return $config['authorization_url'] . '?' . http_build_query($params);
  }

  /**
   * Intercambia código de autorización por tokens
   */
  public function exchangeCodeForTokens(string $provider, string $code): OAuthToken
  {
    if (!isset($this->config[$provider])) {
      throw new OAuthException("Unsupported provider: $provider");
    }

    $config = $this->config[$provider];

    $postData = [
      'client_id' => $config['client_id'],
      'client_secret' => $config['client_secret'],
      'code' => $code,
      'grant_type' => 'authorization_code',
      'redirect_uri' => $config['redirect_uri']
    ];

    $response = $this->makeHttpRequest($config['token_url'], [
      'method' => 'POST',
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Accept' => 'application/json'
      ],
      'body' => http_build_query($postData)
    ]);

    if (!$response || isset($response['error'])) {
      throw new OAuthException('Failed to exchange code for tokens: ' . ($response['error_description'] ?? 'Unknown error'));
    }

    return new OAuthToken(
      $response['access_token'],
      $response['refresh_token'] ?? null,
      $response['expires_in'] ?? 3600,
      $response['token_type'] ?? 'Bearer'
    );
  }

  /**
   * Obtiene información del usuario usando el token de acceso
   */
  public function getUserInfo(string $provider, OAuthToken $token): array
  {
    if (!isset($this->config[$provider])) {
      throw new OAuthException("Unsupported provider: $provider");
    }

    $config = $this->config[$provider];

    $response = $this->makeHttpRequest($config['userinfo_url'], [
      'method' => 'GET',
      'headers' => [
        'Authorization' => $token->getTokenType() . ' ' . $token->getAccessToken(),
        'Accept' => 'application/json'
      ]
    ]);

    if (!$response) {
      throw new OAuthException('Failed to get user info from provider');
    }

    // Normalizar la respuesta según el proveedor
    return $this->normalizeUserInfo($provider, $response);
  }

  /**
   * Normaliza la información del usuario de diferentes proveedores
   */
  private function normalizeUserInfo(string $provider, array $data): array
  {
    switch ($provider) {
      case 'google':
        return [
          'provider_id' => $data['id'],
          'provider' => 'google',
          'email' => $data['email'] ?? null,
          'email_verified' => $data['verified_email'] ?? false,
          'name' => $data['name'] ?? null,
          'first_name' => $data['given_name'] ?? null,
          'last_name' => $data['family_name'] ?? null,
          'picture' => $data['picture'] ?? null,
          'locale' => $data['locale'] ?? null
        ];

      case 'linkedin':
        return [
          'provider_id' => $data['sub'],
          'provider' => 'linkedin',
          'email' => $data['email'] ?? null,
          'email_verified' => true, // LinkedIn siempre verifica emails
          'name' => $data['name'] ?? null,
          'first_name' => $data['given_name'] ?? null,
          'last_name' => $data['family_name'] ?? null,
          'picture' => $data['picture'] ?? null,
          'locale' => $data['locale'] ?? null
        ];

      default:
        throw new OAuthException("Cannot normalize user info for provider: $provider");
    }
  }

  /**
   * Realiza una petición HTTP
   */
  private function makeHttpRequest(string $url, array $options = []): ?array
  {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if (isset($options['method'])) {
      if ($options['method'] === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
      } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $options['method']);
      }
    }

    if (isset($options['headers'])) {
      $headers = [];
      foreach ($options['headers'] as $key => $value) {
        $headers[] = "$key: $value";
      }
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    if (isset($options['body'])) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
      error_log("HTTP Request Error: $error");
      return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
      error_log("HTTP Request failed with code: $httpCode");
      return null;
    }

    return json_decode($response, true);
  }
}
