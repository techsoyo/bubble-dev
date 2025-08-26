<?php

declare(strict_types=1);


namespace Controllers;

require_once __DIR__ . '/../../api/bootstrap.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Security\Cookies;
use Utils\Request;
use Utils\ResponseHelper;

/**
 * AuthController
 *
 * Nota de arquitectura:
 * - El bootstrap principal se carga desde el front controller/router.
 * - NO hacer require de backend/public/api/bootstrap.php aquí.
 * - La autenticación por cookie HttpOnly se aplica en endpoints/routers con JWTMiddleware.
 * - Aquí solo generamos/limpiamos cookies y exponemos utilidades (user-info, verify, etc.).
 */
class AuthController
{
  public function __construct()
  {
    // En producción no aceptamos Authorization header: solo cookie HttpOnly
    if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
      http_response_code(401);
      header('Content-Type: application/json');
      echo json_encode(['error' => 'Unauthorized (cookie required)']);
      exit;
    }
  }

  /**
   * ----- Helpers privados -----
   */

  private function generateJwt(array $claims): string
  {
    $now = time();
    $exp = $now + (int)($_ENV['JWT_TTL'] ?? 86400); // 24h por defecto

    $payload = array_merge([
      'iat' => $now,
      'nbf' => $now,
      'exp' => $exp,
      'iss' => $_ENV['JWT_ISS'] ?? 'bubble',
      'aud' => $_ENV['JWT_AUD'] ?? 'bubble-app',
    ], $claims);

    $secret = $_ENV['JWT_SECRET'] ?? '';
    if ($secret === '') {
      throw new \RuntimeException('JWT_SECRET not configured');
    }

    return JWT::encode($payload, $secret, 'HS256');
  }

  private function decodeJwtFromCookie(): ?array
  {
    $cookie = $_COOKIE['access_token'] ?? null;
    if (!$cookie) return null;

    $secret = $_ENV['JWT_SECRET'] ?? '';
    if ($secret === '') return null;

    try {
      $decoded = JWT::decode($cookie, new Key($secret, 'HS256'));
      // Convertir stdClass a array sencillo
      return json_decode(json_encode($decoded), true);
    } catch (\Throwable $e) {
      return null;
    }
  }

  private function emitCsrfCookie(): void
  {
    $csrf = bin2hex(random_bytes(32));
    setcookie('XSRF-TOKEN', $csrf, Cookies::options(['httponly' => false]));
  }

  private function unsetAuthCookies(): void
  {
    Cookies::unsetJwt();
    setcookie('XSRF-TOKEN', '', Cookies::options(['expires' => time() - 3600, 'httponly' => false]));
  }

  /**
   * Valida credenciales contra la base de datos.
   * Debe reemplazarse por tu DAO/Servicio real.
   * Devuelve array con: id, email, role, subrole (opcional), status ('active'|'disabled')
   */
  private function validateCredentials(string $email, string $password, string $targetRole = 'user'): ?array
  {
    // Aquí iría la implementación real para validar credenciales
    $user = null; // Resultado de consulta a base de datos
    
    // Si no se encuentra el usuario o la contraseña no coincide
    if ($user === null) {
      return null;
    }

    if (!empty($user['status']) && $user['status'] !== 'active') {
      return null;
    }
    
    return [
      'id'       => (string)$user['id'],
      'email'    => $user['email'],
      'role'     => $user['role'] ?? $targetRole,
      'subrole'  => $user['subrole'] ?? null, // admin|recruiter para staff
      'status'   => 'active',
    ];
    
    // Sin implementación real -> null (no usar mocks)
    // return null; // Código inalcanzable
  }

  private function successfulLoginResponse(array $user, string $jwt): void
  {
    $hideToken = filter_var($_ENV['HIDE_TOKEN_IN_RESPONSE'] ?? 'true', FILTER_VALIDATE_BOOL);

    header('Content-Type: application/json');
    echo json_encode([
      'ok'   => true,
      'user' => [
        'id'    => (string)$user['id'],
        'email' => $user['email'],
        'role'  => $user['role'],
        'sr'    => $user['subrole'] ?? null,
      ],
      ...($hideToken ? [] : ['token' => $jwt]),
    ]);
    exit;
  }

  private function invalidCredentials(): void
  {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'invalid_credentials']);
    exit;
  }

  private function forbidden(): void
  {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'forbidden']);
    exit;
  }

  private function internalError(): void
  {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'internal_server_error']);
    exit;
  }

  /**
   * ----- Acciones públicas -----
   */

  /**
   * Login genérico (si lo usas en tu router). Recomendado usar los específicos.
   */
  public function login(Request $request, array $params = []): void
  {
    try {
      $data = $request->getBody();
      $email = trim($data['email'] ?? '');
      $password = (string)($data['password'] ?? '');

      $user = $this->validateCredentials($email, $password, 'user');
      if (!$user) $this->invalidCredentials();

      if (($user['status'] ?? 'active') !== 'active') {
        $this->forbidden();
      }

      $jwt = $this->generateJwt([
        'sub'   => (string)$user['id'],
        'email' => $user['email'],
        'role'  => $user['role'] ?? 'user',
        'sr'    => $user['subrole'] ?? null,
      ]);

      Cookies::setJwt($jwt);
      $this->emitCsrfCookie();

      $this->successfulLoginResponse($user, $jwt);
    } catch (\Throwable $e) {
      $this->internalError();
    }
  }

  /**
   * Login de candidato
   */
  public function candidateLogin(Request $request, array $params = []): void
  {
    try {
      $data = $request->getBody();
      $email = trim($data['email'] ?? '');
      $password = (string)($data['password'] ?? '');

      $user = $this->validateCredentials($email, $password, 'candidate');
      if (!$user) $this->invalidCredentials();

      if (($user['status'] ?? 'active') !== 'active') {
        $this->forbidden();
      }

      // Forzar role candidate
      $user['role'] = 'candidate';

      $jwt = $this->generateJwt([
        'sub'   => (string)$user['id'],
        'email' => $user['email'],
        'role'  => 'candidate',
      ]);

      Cookies::setJwt($jwt);
      $this->emitCsrfCookie();

      $this->successfulLoginResponse($user, $jwt);
    } catch (\Throwable $e) {
      $this->internalError();
    }
  }

  /**
   * Login de staff (admin/recruiter)
   */
  public function staffLogin(Request $request, array $params = []): void
  {
    try {
      $data = $request->getBody();
      $email = trim($data['email'] ?? '');
      $password = (string)($data['password'] ?? '');

      $user = $this->validateCredentials($email, $password, 'staff');
      if (!$user) $this->invalidCredentials();

      if (($user['status'] ?? 'active') !== 'active') {
        $this->forbidden();
      }

      // Forzar role staff y subrol (admin|recruiter)
      $user['role']    = 'staff';
      $user['subrole'] = $user['subrole'] ?? 'recruiter';

      $jwt = $this->generateJwt([
        'sub'   => (string)$user['id'],
        'email' => $user['email'],
        'role'  => 'staff',
        'sr'    => $user['subrole'],
      ]);

      Cookies::setJwt($jwt);
      $this->emitCsrfCookie();

      $this->successfulLoginResponse($user, $jwt);
    } catch (\Throwable $e) {
      $this->internalError();
    }
  }

  /**
   * Logout: limpia cookies JWT y CSRF
   */
  public function logout(Request $request, array $params = []): void
  {
    try {
      $this->unsetAuthCookies();
      header('Content-Type: application/json');
      echo json_encode(['ok' => true]);
      exit;
    } catch (\Throwable $e) {
      $this->internalError();
    }
  }

  /**
   * Información del usuario autenticado (claims del JWT)
   */
  public function userInfo(Request $request, array $params = []): void
  {
    try {
      $claims = $this->decodeJwtFromCookie();
      if (!$claims) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'jwt_required']);
        exit;
      }

      header('Content-Type: application/json');
      echo json_encode([
        'ok'     => true,
        'user'   => [
          'id'    => (string)($claims['sub'] ?? ''),
          'email' => $claims['email'] ?? null,
          'role'  => $claims['role'] ?? null,
          'sr'    => $claims['sr']   ?? null,
        ],
        'claims' => $claims,
      ]);
      exit;
    } catch (\Throwable $e) {
      $this->internalError();
    }
  }

  /**
   * Verifica si la sesión (cookie JWT) es válida
   */
  public function verifySession(Request $request, array $params = []): void
  {
    try {
      $claims = $this->decodeJwtFromCookie();
      if (!$claims) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'session_invalid']);
        exit;
      }
      header('Content-Type: application/json');
      echo json_encode(['ok' => true]);
      exit;
    } catch (\Throwable $e) {
      $this->internalError();
    }
  }

  /**
   * Devuelve un nuevo token CSRF (y cookie)
   */
  public function csrfToken(Request $request, array $params = []): void
  {
    try {
      $this->emitCsrfCookie();
      $cookie = $_COOKIE['XSRF-TOKEN'] ?? null;
      header('Content-Type: application/json');
      echo json_encode(['ok' => true, 'csrf_token' => $cookie]);
      exit;
    } catch (\Throwable $e) {
      $this->internalError();
    }
  }

  /**
   * Validación básica de CSRF (double-submit) — preferible usar CsrfMiddleware en endpoints de escritura
   */
  public function validateCsrf(Request $request, array $params = []): void
  {
    try {
      $data  = $request->getBody();
      $token = $data['csrf_token'] ?? '';
      $cookie = $_COOKIE['XSRF-TOKEN'] ?? '';
      $valid = ($token !== '' && hash_equals((string)$cookie, (string)$token));

      header('Content-Type: application/json');
      echo json_encode(['ok' => true, 'valid' => $valid]);
      exit;
    } catch (\Throwable $e) {
      $this->internalError();
    }
  }

  /**
   * Ping simple (diagnóstico)
   */
  public function ping(Request $request, array $params = []): void
  {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'status' => 'auth_controller_online']);
    exit;
  }

  /**
   * Cambio de contraseña — requiere JWT (debe validarse en middleware)
   * Implementación real pendiente (DAO).
   */
  public function changePassword(Request $request, array $params = []): void
  {
    http_response_code(501);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'not_implemented']);
    exit;
  }

  /**
   * Interceptor de autenticación (debug)
   */
  public function intercept(Request $request, array $params = []): void
  {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'intercepted' => true]);
    exit;
  }
}
