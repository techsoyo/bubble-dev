<?php

declare(strict_types=1);

namespace Controllers;

require_once __DIR__ . '/../../api/bootstrap.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Security\Cookies;
use Utils\Request;
use Utils\ResponseHelper;
use Services\RateLimitService;
use Services\SecurityLoggerService;
use Services\ValidationService;
use PDO;

/**
 * AuthController
 *
 * Nota de arquitectura:
 * - El bootstrap principal se carga desde el front controller/router.
 * - NO hacer require de backend/public/api/bootstrap.php aquí­.
 * - La autenticación por cookie HttpOnly se aplica en endpoints/routers con JWTMiddleware.
 * - Aquí­ solo generamos/limpiamos cookies y exponemos utilidades (user-info, verify, etc.).
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
   * Valida credenciales contra la base de datos usando implementación real
   * Devuelve array con: id, email, role, subrole (opcional), status ('active'|'disabled')
   */
  private function validateCredentials(string $email, string $password, string $targetRole = 'user'): ?array
  {
    try {
      // Obtener conexión a la base de datos
      $db = \Utils\Database::getInstance()->getConnection();

      // Determinar tabla según rol objetivo
      $table = match ($targetRole) {
        'candidate' => 'bt_candidates',
        'staff' => 'bt_staff_profiles',
        default => 'bt_users'
      };

      // Preparar consulta segura
      $stmt = $db->prepare("
        SELECT id, email, password_hash, role, status, first_name, last_name, name
        FROM {$table}
        WHERE email = ? AND status = 'active'
        LIMIT 1
      ");

      $stmt->execute([$email]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        SecurityLoggerService::logLoginAttempt($email, false, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return null;
      }

      // Verificar contraseña usando password_verify
      if (!password_verify($password, $user['password_hash'])) {
        SecurityLoggerService::logLoginAttempt($email, false, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return null;
      }

      // Log de login exitoso
      SecurityLoggerService::logLoginAttempt($email, true, $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      // Retornar datos del usuario
      return [
        'id' => (string)$user['id'],
        'email' => $user['email'],
        'role' => $user['role'] ?? $targetRole,
        'subrole' => null, // Para mantener compatibilidad
        'status' => 'active',
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? '',
        'name' => $user['name'] ?? ''
      ];
    } catch (\Exception $e) {
      SecurityLoggerService::logSecurityEvent('credential_validation_error', [
        'email' => $email,
        'error' => $e->getMessage(),
        'target_role' => $targetRole
      ], 'ERROR');

      return null;
    }
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
   * Login genérico mejorado con rate limiting y validación robusta
   */
  public function login(Request $request, array $params = []): void
  {
    try {
      $data = $request->getBody();
      $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

      // RATE LIMITING - Verificar antes de procesar
      if (!RateLimitService::canPerform('login', $clientIP)) {
        $retryAfter = RateLimitService::getRetryAfter('login', $clientIP);
        SecurityLoggerService::logRateLimitViolation('login', $clientIP, 5, 5);

        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $retryAfter);
        echo json_encode([
          'ok' => false,
          'error' => 'too_many_attempts',
          'message' => 'Demasiados intentos de login. Intente nuevamente más tarde.',
          'retry_after' => $retryAfter
        ]);
        exit;
      }

      // VALIDACIÓN DE ENTRADA
      $emailValidation = ValidationService::validateEmail($data['email'] ?? '', true);
      if (!$emailValidation['valid']) {
        RateLimitService::recordAttempt('login', $clientIP);
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'invalid_email', 'message' => $emailValidation['error']]);
        exit;
      }

      $passwordValidation = ValidationService::validatePassword($data['password'] ?? '', true);
      if (!$passwordValidation['valid']) {
        RateLimitService::recordAttempt('login', $clientIP);
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'invalid_password', 'message' => $passwordValidation['error']]);
        exit;
      }

      $email = $emailValidation['sanitized'];
      $password = $passwordValidation['sanitized'];

      // VALIDAR CREDENCIALES
      $user = $this->validateCredentials($email, $password, 'user');
      if (!$user) {
        RateLimitService::recordAttempt('login', $clientIP);
        $this->invalidCredentials();
      }

      // VERIFICAR STATUS DEL USUARIO
      if (($user['status'] ?? 'active') !== 'active') {
        SecurityLoggerService::logSecurityEvent('inactive_user_login_attempt', [
          'user_id' => $user['id'],
          'email' => $email,
          'status' => $user['status']
        ], 'WARNING');

        $this->forbidden();
      }

      // GENERAR JWT
      $jwt = $this->generateJwt([
        'sub'   => (string)$user['id'],
        'email' => $user['email'],
        'role'  => $user['role'],
        'sr'    => $user['subrole'] ?? null,
      ]);

      // ESTABLECER COOKIES
      Cookies::setJwt($jwt);
      $this->emitCsrfCookie();

      // LOG DE LOGIN EXITOSO
      SecurityLoggerService::logSecurityEvent('login_success', [
        'user_id' => $user['id'],
        'email' => $email,
        'role' => $user['role'],
        'ip_address' => $clientIP
      ], 'INFO');

      $this->successfulLoginResponse($user, $jwt);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('login_error', [
        'error' => $e->getMessage(),
        'email' => $email ?? 'unknown',
        'ip_address' => $clientIP
      ], 'ERROR');

      // GENERAR JWT
      $jwt = $this->generateJwt([
        'sub'   => (string)$user['id'],
        'email' => $user['email'],
        'role'  => $user['role'],
        'sr'    => $user['subrole'] ?? null,
      ]);

      // ESTABLECER COOKIES
      Cookies::setJwt($jwt);
      $this->emitCsrfCookie();

      // LOG DE LOGIN EXITOSO
      SecurityLoggerService::logSecurityEvent('login_success', [
        'user_id' => $user['id'],
        'email' => $email,
        'role' => $user['role'],
        'ip_address' => $clientIP
      ], 'INFO');

      $this->successfulLoginResponse($user, $jwt);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('login_error', [
        'error' => $e->getMessage(),
        'email' => $email ?? 'unknown',
        'ip_address' => $clientIP
      ], 'ERROR');

      $this->internalError();
    }
  }
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
   * Verifica si la sesión (cookie JWT) es ví¡lida
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
   * Validación bí¡sica de CSRF (double-submit) ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â preferible usar CsrfMiddleware en endpoints de escritura
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
   * Cambio de contraseña seguro con validación completa
   */
  public function changePassword(Request $request, array $params = []): void
  {
    try {
      $data = $request->getBody();
      $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

      // RATE LIMITING para cambios de contraseña
      if (!RateLimitService::canPerform('password_reset', $clientIP)) {
        $retryAfter = RateLimitService::getRetryAfter('password_reset', $clientIP);
        SecurityLoggerService::logRateLimitViolation('password_reset', $clientIP, 3, 3);

        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $retryAfter);
        echo json_encode([
          'ok' => false,
          'error' => 'too_many_attempts',
          'message' => 'Demasiados intentos de cambio de contraseña. Intente nuevamente más tarde.',
          'retry_after' => $retryAfter
        ]);
        exit;
      }

      // VALIDACIÓN DE CAMPOS REQUERIDOS
      $requiredFields = ['current_password', 'new_password', 'confirm_password'];
      foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
          SecurityLoggerService::logValidationError($field, 'Campo requerido faltante');
          http_response_code(400);
          header('Content-Type: application/json');
          echo json_encode(['ok' => false, 'error' => 'missing_field', 'message' => "Campo requerido: {$field}"]);
          exit;
        }
      }

      // VALIDACIÓN DE CONTRASEÑA ACTUAL
      $currentPasswordValidation = ValidationService::validatePassword($data['current_password'], true);
      if (!$currentPasswordValidation['valid']) {
        RateLimitService::recordAttempt('password_reset', $clientIP);
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'invalid_current_password', 'message' => $currentPasswordValidation['error']]);
        exit;
      }

      // VALIDACIÓN DE NUEVA CONTRASEÑA
      $newPasswordValidation = ValidationService::validatePassword($data['new_password'], true);
      if (!$newPasswordValidation['valid']) {
        RateLimitService::recordAttempt('password_reset', $clientIP);
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'invalid_new_password', 'message' => $newPasswordValidation['error']]);
        exit;
      }

      // VERIFICAR QUE LAS CONTRASEÑAS COINCIDAN
      if ($data['new_password'] !== $data['confirm_password']) {
        RateLimitService::recordAttempt('password_reset', $clientIP);
        SecurityLoggerService::logValidationError('password_confirmation', 'Las contraseñas no coinciden');
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'password_mismatch', 'message' => 'Las contraseñas no coinciden']);
        exit;
      }

      // VERIFICAR QUE NO SEA LA MISMA CONTRASEÑA
      if (password_verify($data['new_password'], password_hash($data['current_password'], PASSWORD_DEFAULT))) {
        RateLimitService::recordAttempt('password_reset', $clientIP);
        SecurityLoggerService::logValidationError('password_same', 'La nueva contraseña no puede ser igual a la actual');
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'same_password', 'message' => 'La nueva contraseña no puede ser igual a la actual']);
        exit;
      }

      $currentPassword = $currentPasswordValidation['sanitized'];
      $newPassword = $newPasswordValidation['sanitized'];

      // OBTENER USUARIO AUTENTICADO (debería venir del middleware JWT)
      $userId = $params['user_id'] ?? null; // Esto debería venir del middleware
      if (!$userId) {
        SecurityLoggerService::logSecurityEvent('password_change_no_user', [
          'ip_address' => $clientIP
        ], 'WARNING');

        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'unauthorized', 'message' => 'Usuario no autenticado']);
        exit;
      }

      // VERIFICAR CONTRASEÑA ACTUAL EN LA BASE DE DATOS
      $db = \Utils\Database::getInstance()->getConnection();
      $stmt = $db->prepare("SELECT password_hash FROM bt_users WHERE id = ? AND status = 'active'");
      $stmt->execute([$userId]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        RateLimitService::recordAttempt('password_reset', $clientIP);
        SecurityLoggerService::logSecurityEvent('password_change_user_not_found', [
          'user_id' => $userId,
          'ip_address' => $clientIP
        ], 'WARNING');

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'user_not_found', 'message' => 'Usuario no encontrado']);
        exit;
      }

      // VERIFICAR CONTRASEÑA ACTUAL
      if (!password_verify($currentPassword, $user['password_hash'])) {
        RateLimitService::recordAttempt('password_reset', $clientIP);
        SecurityLoggerService::logSecurityEvent('password_change_wrong_current', [
          'user_id' => $userId,
          'ip_address' => $clientIP
        ], 'WARNING');

        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'wrong_current_password', 'message' => 'Contraseña actual incorrecta']);
        exit;
      }

      // GENERAR NUEVO HASH DE CONTRASEÑA
      $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

      // ACTUALIZAR CONTRASEÑA EN LA BASE DE DATOS
      $updateStmt = $db->prepare("UPDATE bt_users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
      $result = $updateStmt->execute([$newPasswordHash, $userId]);

      if (!$result) {
        SecurityLoggerService::logSecurityEvent('password_change_db_error', [
          'user_id' => $userId,
          'ip_address' => $clientIP
        ], 'ERROR');

        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'db_error', 'message' => 'Error al actualizar la contraseña']);
        exit;
      }

      // LOG DE CAMBIO EXITOSO
      SecurityLoggerService::logPasswordChange($userId, true, $clientIP);

      // INVALIDAR TOKENS ANTERIORES (opcional pero recomendado)
      // Aquí podrías implementar invalidación de JWT si tienes un sistema de blacklist

      header('Content-Type: application/json');
      echo json_encode([
        'ok' => true,
        'message' => 'Contraseña cambiada exitosamente',
        'data' => [
          'changed_at' => date('Y-m-d H:i:s')
        ]
      ]);
      exit;
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('password_change_error', [
        'error' => $e->getMessage(),
        'user_id' => $userId ?? 'unknown',
        'ip_address' => $clientIP
      ], 'ERROR');

      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['ok' => false, 'error' => 'internal_error', 'message' => 'Error interno del servidor']);
      exit;
    }
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
