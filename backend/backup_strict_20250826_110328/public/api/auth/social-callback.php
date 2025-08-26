<?php declare(strict_types=1);

// @public

/**
 * Endpoint para manejar callbacks de autenticaciÃƒÆ’Ã‚Â³n social (Google y LinkedIn)
 *
 * Este archivo procesa los cÃƒÆ’Ã‚Â³digos de autorizaciÃƒÆ’Ã‚Â³n OAuth2 y crea o actualiza
 * cuentas de usuario basadas en la informaciÃƒÆ’Ã‚Â³n del proveedor social.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/bootstrap.php';

try {
  // Solo permitir GET y POST para callbacks OAuth
  if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode(['error' => 'MÃƒÆ’Ã‚Â©todo no permitido']);
    exit;
  }

  // Obtener parÃƒÆ’Ã‚Â¡metros del callback
  $code = $_GET['code'] ?? null;
  $state = $_GET['state'] ?? null;
  $error = $_GET['error'] ?? null;

  // Verificar si hay errores en el callback
  if ($error) {
    http_response_code(400);
    echo json_encode([
      'error' => 'Error de autenticaciÃƒÆ’Ã‚Â³n',
      'message' => 'El usuario cancelÃƒÆ’Ã‚Â³ la autenticaciÃƒÆ’Ã‚Â³n o hubo un error: ' . $error
    ]);
    exit;
  }

  // Verificar que se recibiÃƒÆ’Ã‚Â³ el cÃƒÆ’Ã‚Â³digo de autorizaciÃƒÆ’Ã‚Â³n
  if (!$code) {
    http_response_code(400);
    echo json_encode([
      'error' => 'CÃƒÆ’Ã‚Â³digo de autorizaciÃƒÆ’Ã‚Â³n faltante',
      'message' => 'No se recibiÃƒÆ’Ã‚Â³ el cÃƒÆ’Ã‚Â³digo de autorizaciÃƒÆ’Ã‚Â³n del proveedor'
    ]);
    exit;
  }

  // Determinar el proveedor desde el state
  $provider = 'google'; // Default
  if ($state) {
    if (strpos($state, 'linkedin') === 0) {
      $provider = 'linkedin';
    } elseif (strpos($state, 'google') === 0) {
      $provider = 'google';
    }
  }

  // Procesar segÃƒÆ’Ã‚Âºn el proveedor
  switch ($provider) {
    case 'google':
      $userInfo = handleGoogleCallback($code);
      break;
    case 'linkedin':
      $userInfo = handleLinkedInCallback($code);
      break;
    default:
      throw new Exception('Proveedor de autenticaciÃƒÆ’Ã‚Â³n no soportado');
  }

  // Crear o actualizar usuario en la base de datos
  $user = createOrUpdateSocialUser($userInfo, $provider);

  // Establecer sesiÃƒÆ’Ã‚Â³n
  session_start();
  $_SESSION['user_id'] = $user['id'];
  $_SESSION['user_email'] = $user['email'];
  $_SESSION['user_role'] = 'candidate';
  $_SESSION['login_method'] = 'social_' . $provider;

  // Verificar si hay un job ID en el state para redirecciÃƒÆ’Ã‚Â³n
  $jobId = null;
  if ($state && strpos($state, '&job=') !== false) {
    $parts = explode('&job=', $state);
    $jobId = $parts[1] ?? null;
  }

  // Redirigir al usuario
  $redirectUrl = $jobId
    ? '/jobs/apply?job=' . urlencode($jobId)
    : '/dashboard/cddashboard';

  // Respuesta exitosa
  echo json_encode([
    'success' => true,
    'user' => [
      'id' => $user['id'],
      'email' => $user['email'],
      'name' => $user['name'],
      'provider' => $provider
    ],
    'redirect_url' => $redirectUrl,
    'message' => 'AutenticaciÃƒÆ’Ã‚Â³n exitosa'
  ]);
} catch (Exception $e) {
  error_log('Error en social callback: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'error' => 'Error interno del servidor',
    'message' => 'No se pudo completar la autenticaciÃƒÆ’Ã‚Â³n. IntÃƒÆ’Ã‚Â©ntalo nuevamente.'
  ]);
}

/**
 * Maneja el callback de Google OAuth
 */
function handleGoogleCallback($code)
{
  // ConfiguraciÃƒÆ’Ã‚Â³n de Google OAuth
  $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? 'YOUR_GOOGLE_CLIENT_ID';
  $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'YOUR_GOOGLE_CLIENT_SECRET';
  $redirectUri = $_ENV['APP_URL'] . '/auth/callback';

  // Intercambiar cÃƒÆ’Ã‚Â³digo por token de acceso
  $tokenData = exchangeCodeForToken('google', $code, $clientId, $clientSecret, $redirectUri);

  // Obtener informaciÃƒÆ’Ã‚Â³n del usuario
  $userInfo = getUserInfoFromGoogle($tokenData['access_token']);

  return [
    'email' => $userInfo['email'],
    'name' => $userInfo['name'],
    'first_name' => $userInfo['given_name'] ?? '',
    'last_name' => $userInfo['family_name'] ?? '',
    'avatar' => $userInfo['picture'] ?? null,
    'provider_id' => $userInfo['id']
  ];
}

/**
 * Maneja el callback de LinkedIn OAuth
 */
function handleLinkedInCallback($code)
{
  // ConfiguraciÃƒÆ’Ã‚Â³n de LinkedIn OAuth
  $clientId = $_ENV['LINKEDIN_CLIENT_ID'] ?? 'YOUR_LINKEDIN_CLIENT_ID';
  $clientSecret = $_ENV['LINKEDIN_CLIENT_SECRET'] ?? 'YOUR_LINKEDIN_CLIENT_SECRET';
  $redirectUri = $_ENV['APP_URL'] . '/auth/callback';

  // Intercambiar cÃƒÆ’Ã‚Â³digo por token de acceso
  $tokenData = exchangeCodeForToken('linkedin', $code, $clientId, $clientSecret, $redirectUri);

  // Obtener informaciÃƒÆ’Ã‚Â³n del usuario
  $userInfo = getUserInfoFromLinkedIn($tokenData['access_token']);

  return [
    'email' => $userInfo['email'],
    'name' => $userInfo['localizedFirstName'] . ' ' . $userInfo['localizedLastName'],
    'first_name' => $userInfo['localizedFirstName'] ?? '',
    'last_name' => $userInfo['localizedLastName'] ?? '',
    'avatar' => $userInfo['profilePicture']['displayImage'] ?? null,
    'provider_id' => $userInfo['id']
  ];
}

/**
 * Intercambia el cÃƒÆ’Ã‚Â³digo de autorizaciÃƒÆ’Ã‚Â³n por un token de acceso
 */
function exchangeCodeForToken($provider, $code, $clientId, $clientSecret, $redirectUri)
{
  $tokenUrls = [
    'google' => 'https://oauth2.googleapis.com/token',
    'linkedin' => 'https://www.linkedin.com/oauth/v2/accessToken'
  ];

  $postData = [
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
  ];

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $tokenUrls[$provider]);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/x-www-form-urlencoded'
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200) {
    throw new Exception("Error al obtener token de acceso: HTTP $httpCode");
  }

  $tokenData = json_decode($response, true);
  if (!$tokenData || !isset($tokenData['access_token'])) {
    throw new Exception('Token de acceso no vÃƒÆ’Ã‚Â¡lido');
  }

  return $tokenData;
}

/**
 * Obtiene informaciÃƒÆ’Ã‚Â³n del usuario desde Google
 */
function getUserInfoFromGoogle($accessToken)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Accept: application/json'
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200) {
    throw new Exception("Error al obtener informaciÃƒÆ’Ã‚Â³n del usuario de Google: HTTP $httpCode");
  }

  return json_decode($response, true);
}

/**
 * Obtiene informaciÃƒÆ’Ã‚Â³n del usuario desde LinkedIn
 */
function getUserInfoFromLinkedIn($accessToken)
{
  // Obtener perfil bÃƒÆ’Ã‚Â¡sico
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.linkedin.com/v2/me');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Accept: application/json'
  ]);

  $profileResponse = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200) {
    throw new Exception("Error al obtener perfil de LinkedIn: HTTP $httpCode");
  }

  $profile = json_decode($profileResponse, true);

  // Obtener email
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Accept: application/json'
  ]);

  $emailResponse = curl_exec($ch);
  curl_close($ch);

  $emailData = json_decode($emailResponse, true);
  $email = $emailData['elements'][0]['handle~']['emailAddress'] ?? null;

  $profile['email'] = $email;
  return $profile;
}

/**
 * Crea o actualiza un usuario desde autenticaciÃƒÆ’Ã‚Â³n social
 */
function createOrUpdateSocialUser($userInfo, $provider)
{
  global $pdo;

  try {
    // Buscar usuario existente por email
    $stmt = $pdo->prepare('
            SELECT id, email, nombre, apellido, avatar, provider_id, provider_type
            FROM bt_candidates 
            WHERE email = ? OR (provider_id = ? AND provider_type = ?)
        ');
    $stmt->execute([$userInfo['email'], $userInfo['provider_id'], $provider]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
      // Actualizar usuario existente
      $stmt = $pdo->prepare('
                UPDATE bt_candidates 
                SET provider_id = ?, provider_type = ?, avatar = ?, updated_at = NOW()
                WHERE id = ?
            ');
      $stmt->execute([
        $userInfo['provider_id'],
        $provider,
        $userInfo['avatar'],
        $existingUser['id']
      ]);

      return [
        'id' => $existingUser['id'],
        'email' => $existingUser['email'],
        'name' => $existingUser['nombre'] . ' ' . $existingUser['apellido']
      ];
    } else {
      // Crear nuevo usuario
      $stmt = $pdo->prepare('
                INSERT INTO bt_candidates (
                    email, nombre, apellido, avatar, provider_id, provider_type, 
                    password_hash, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ');

      $stmt->execute([
        $userInfo['email'],
        $userInfo['first_name'],
        $userInfo['last_name'],
        $userInfo['avatar'],
        $userInfo['provider_id'],
        $provider,
        password_hash(uniqid(), PASSWORD_DEFAULT) // Password temporal para OAuth users
      ]);

      $userId = $pdo->lastInsertId();

      return [
        'id' => $userId,
        'email' => $userInfo['email'],
        'name' => $userInfo['name']
      ];
    }
  } catch (PDOException $e) {
    error_log('Error en base de datos: ' . $e->getMessage());
    throw new Exception('Error al procesar usuario en base de datos');
  }
}
