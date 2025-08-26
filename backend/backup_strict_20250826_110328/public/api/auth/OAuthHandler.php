<?php declare(strict_types=1);

// @public

/**
 * OAuth Handler - Google y LinkedIn
 * 
 * Maneja el flujo de autenticaciÃƒÂ³n OAuth 2.0 para Google y LinkedIn
 * Preparado para activaciÃƒÂ³n rÃƒÂ¡pida del cliente
 * 
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @date 15 de agosto 2025
 */

require_once dirname(__DIR__) . '/bootstrap.php';

class OAuthHandler
{
  private $config;
  private $db;

  public function __construct()
  {
    $this->loadConfig();
    $this->db = pdo();
  }

  /**
   * Cargar configuraciÃƒÂ³n OAuth desde variables de entorno
   */
  private function loadConfig()
  {
    // Cargar archivo .env.oauth si existe
    $envFile = __DIR__ . '/../.env.oauth';
    if (file_exists($envFile)) {
      $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !strpos($line, '#') === 0) {
          list($key, $value) = explode('=', $line, 2);
          putenv(trim($key) . '=' . trim($value));
        }
      }
    }

    $this->config = [
      'google' => [
        'client_id' => getenv('GOOGLE_CLIENT_ID'),
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => getenv('GOOGLE_REDIRECT_URI'),
        'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'user_info_url' => 'https://www.googleapis.com/oauth2/v2/userinfo'
      ],
      'linkedin' => [
        'client_id' => getenv('LINKEDIN_CLIENT_ID'),
        'client_secret' => getenv('LINKEDIN_CLIENT_SECRET'),
        'redirect_uri' => getenv('LINKEDIN_REDIRECT_URI'),
        'auth_url' => 'https://www.linkedin.com/oauth/v2/authorization',
        'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
        'user_info_url' => 'https://api.linkedin.com/v2/people/~'
      ]
    ];
  }

  /**
   * Verificar si OAuth estÃƒÂ¡ configurado
   */
  public function isConfigured($provider = null)
  {
    if ($provider) {
      return !empty($this->config[$provider]['client_id']) &&
        !empty($this->config[$provider]['client_secret']);
    }

    return $this->isConfigured('google') || $this->isConfigured('linkedin');
  }

  /**
   * Generar URL de autorizaciÃƒÂ³n
   */
  public function getAuthUrl($provider, $state = null)
  {
    if (!$this->isConfigured($provider)) {
      throw new Exception("OAuth no configurado para $provider");
    }

    $config = $this->config[$provider];
    $state = $state ?: bin2hex(random_bytes(16));

    $params = [
      'client_id' => $config['client_id'],
      'redirect_uri' => $config['redirect_uri'],
      'state' => $state,
      'response_type' => 'code'
    ];

    if ($provider === 'google') {
      $params['scope'] = 'email profile';
    } elseif ($provider === 'linkedin') {
      $params['scope'] = 'r_liteprofile r_emailaddress';
    }

    return $config['auth_url'] . '?' . http_build_query($params);
  }

  /**
   * Intercambiar cÃƒÂ³digo por token de acceso
   */
  public function exchangeCodeForToken($provider, $code)
  {
    $config = $this->config[$provider];

    $data = [
      'client_id' => $config['client_id'],
      'client_secret' => $config['client_secret'],
      'code' => $code,
      'grant_type' => 'authorization_code',
      'redirect_uri' => $config['redirect_uri']
    ];

    $response = $this->makeHttpRequest($config['token_url'], $data);
    return json_decode($response, true);
  }

  /**
   * Obtener informaciÃƒÂ³n del usuario
   */
  public function getUserInfo($provider, $accessToken)
  {
    $config = $this->config[$provider];
    $headers = ["Authorization: Bearer $accessToken"];

    if ($provider === 'linkedin') {
      // LinkedIn requiere headers especÃƒÂ­ficos
      $headers[] = 'X-Restli-Protocol-Version: 2.0.0';
    }

    $response = $this->makeHttpRequest($config['user_info_url'], null, $headers);
    return json_decode($response, true);
  }

  /**
   * Realizar peticiÃƒÂ³n HTTP
   */
  private function makeHttpRequest($url, $data = null, $headers = [])
  {
    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTPHEADER => array_merge([
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: Bubble-Talents-OAuth/1.0'
      ], $headers)
    ]);

    if ($data) {
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode !== 200) {
      throw new Exception("HTTP Error $httpCode: $response");
    }

    return $response;
  }

  /**
   * Procesar callback OAuth
   */
  public function handleCallback($provider, $code, $state)
  {
    try {
      // 1. Intercambiar cÃƒÂ³digo por token
      $tokenData = $this->exchangeCodeForToken($provider, $code);

      if (!isset($tokenData['access_token'])) {
        throw new Exception('No se recibiÃƒÂ³ access token');
      }

      // 2. Obtener informaciÃƒÂ³n del usuario
      $userInfo = $this->getUserInfo($provider, $tokenData['access_token']);

      // 3. Normalizar datos del usuario
      $userData = $this->normalizeUserData($provider, $userInfo);

      // 4. Buscar o crear usuario en la base de datos
      $user = $this->findOrCreateUser($userData, $provider);

      return [
        'success' => true,
        'user' => $user,
        'token_data' => $tokenData
      ];
    } catch (Exception $e) {
      return [
        'success' => false,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Normalizar datos del usuario segÃƒÂºn el proveedor
   */
  private function normalizeUserData($provider, $userInfo)
  {
    switch ($provider) {
      case 'google':
        return [
          'email' => $userInfo['email'] ?? null,
          'name' => $userInfo['name'] ?? null,
          'first_name' => $userInfo['given_name'] ?? null,
          'last_name' => $userInfo['family_name'] ?? null,
          'picture' => $userInfo['picture'] ?? null,
          'provider_id' => $userInfo['id'] ?? null
        ];

      case 'linkedin':
        return [
          'email' => $userInfo['emailAddress'] ?? null,
          'name' => $userInfo['localizedFirstName'] . ' ' . $userInfo['localizedLastName'],
          'first_name' => $userInfo['localizedFirstName'] ?? null,
          'last_name' => $userInfo['localizedLastName'] ?? null,
          'picture' => null, // LinkedIn requiere llamada adicional para foto
          'provider_id' => $userInfo['id'] ?? null
        ];

      default:
        return $userInfo;
    }
  }

  /**
   * Buscar o crear usuario en la base de datos
   */
  private function findOrCreateUser($userData, $provider)
  {
    // Buscar usuario existente por email
    $stmt = $this->db->prepare("SELECT * FROM bt_candidates WHERE email = ?");
    $stmt->execute([$userData['email']]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
      // Usuario existe, actualizar datos OAuth
      $this->updateUserOAuthData($existingUser['id'], $provider, $userData);
      return $existingUser;
    } else {
      // Crear nuevo usuario
      return $this->createUserFromOAuth($userData, $provider);
    }
  }

  /**
   * Crear nuevo usuario desde datos OAuth
   */
  private function createUserFromOAuth($userData, $provider)
  {
    $stmt = $this->db->prepare("
            INSERT INTO bt_candidates (
                email, name, oauth_provider, oauth_provider_id, 
                profile_picture, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");

    $stmt->execute([
      $userData['email'],
      $userData['name'],
      $provider,
      $userData['provider_id'],
      $userData['picture']
    ]);

    $userId = $this->db->lastInsertId();

    // Obtener el usuario creado
    $stmt = $this->db->prepare("SELECT * FROM bt_candidates WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
  }

  /**
   * Actualizar datos OAuth de usuario existente
   */
  private function updateUserOAuthData($userId, $provider, $userData)
  {
    $stmt = $this->db->prepare("
            UPDATE bt_candidates 
            SET oauth_provider = ?, oauth_provider_id = ?, 
                profile_picture = ?, updated_at = NOW()
            WHERE id = ?
        ");

    $stmt->execute([
      $provider,
      $userData['provider_id'],
      $userData['picture'],
      $userId
    ]);
  }
}

// Funciones de utilidad para el frontend
function getOAuthStatus()
{
  $oauth = new OAuthHandler();
  return [
    'google_configured' => $oauth->isConfigured('google'),
    'linkedin_configured' => $oauth->isConfigured('linkedin'),
    'any_configured' => $oauth->isConfigured()
  ];
}

function getOAuthAuthUrl($provider, $jobId = null)
{
  $oauth = new OAuthHandler();
  $state = $jobId ? "job_$jobId" : null;
  return $oauth->getAuthUrl($provider, $state);
}
