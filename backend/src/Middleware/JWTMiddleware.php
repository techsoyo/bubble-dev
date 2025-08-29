<?php

declare(strict_types=1);

namespace Middleware;

/**
 * JWT Middleware Unificado - Manejo completo de autenticación JWT seguro
 * 
 * @package Middleware
 * @author Bubble Talents Development Team
 * @version 2.1.0
 */

use Security\Cookies;

class JWTMiddleware
{
  private static $allowedAlgorithms = ['HS256', 'HS384', 'HS512'];
  private static $defaultExpiry = 3600; // 1 hora
  private static $timeTolerance = 300; // 5 minutos
  private static $maxExpiry = 2592000; // 30 días

  /**
   * Codificación Base64 URL-safe
   */
  private static function base64UrlEncode(string $data): string
  {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  /**
   * Decodificación Base64 URL-safe con padding automático
   */
  private static function base64UrlDecode(string $data): string
  {
    $padLen = 4 - (strlen($data) % 4);
    if ($padLen !== 4) {
      $data .= str_repeat('=', $padLen);
    }
    return base64_decode(strtr($data, '-_', '+/'));
  }

  /**
   * Obtener clave secreta desde configuración con validaciones
   */
  private static function getSecret(): string
  {
    $secret = config('JWT_SECRET');
    if (empty($secret)) {
      throw new \Exception('JWT_SECRET no está configurado');
    }
    if (strlen($secret) < 32) {
      throw new \Exception('JWT_SECRET debe tener al menos 32 caracteres');
    }
    return $secret;
  }

  /**
   * Crear firma de JWT con soporte de varios algoritmos
   */
  private static function createSignature(string $data, string $secret, string $algorithm): string
  {
    switch ($algorithm) {
      case 'HS256':
        return hash_hmac('sha256', $data, $secret, true);
      case 'HS384':
        return hash_hmac('sha384', $data, $secret, true);
      case 'HS512':
        return hash_hmac('sha512', $data, $secret, true);
      default:
        throw new \Exception("Algoritmo no soportado: $algorithm");
    }
  }

  /**
   * Generar un token JWT seguro con validación de parámetros
   */
  public static function generateToken(array $payload, ?int $expiry = null, string $algorithm = 'HS256'): string
  {
    if (!in_array($algorithm, self::$allowedAlgorithms)) {
      throw new \Exception("Algoritmo no permitido: $algorithm");
    }

    if ($expiry !== null) {
      if (!is_int($expiry) || $expiry <= 0 || $expiry > self::$maxExpiry) {
        throw new \InvalidArgumentException(
          "Tiempo de expiración inválido. Debe ser un entero entre 1 y " . self::$maxExpiry
        );
      }
    } else {
      $expiry = config('JWT_EXPIRY', self::$defaultExpiry);
    }

    $currentTime = time();
    // Genera un JTI seguro
    try {
      $jti = bin2hex(random_bytes(16));
    } catch (\Exception $e) {
      throw new \RuntimeException('Error al generar identificador único del token: ' . $e->getMessage());
    }

    $header = [
      'alg' => $algorithm,
      'typ' => 'JWT',
      'kid' => config('JWT_KEY_ID', 'default')
    ];

    $fullPayload = array_merge($payload, [
      'iss' => config('JWT_ISSUER', 'bubble-talents-api'),
      'aud' => config('JWT_AUDIENCE', 'bubble-talents-app'),
      'iat' => $currentTime,
      'nbf' => $currentTime,
      'exp' => $currentTime + $expiry,
      'jti' => $jti
    ]);

    $headerEncoded = self::base64UrlEncode(json_encode($header));
    $payloadEncoded = self::base64UrlEncode(json_encode($fullPayload));
    $signature = self::createSignature("$headerEncoded.$payloadEncoded", self::getSecret(), $algorithm);
    $signatureEncoded = self::base64UrlEncode($signature);

    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::info('JWT token generado', [
        'algorithm' => $algorithm,
        'user_id' => $payload['user_id'] ?? 'unknown',
        'expiry' => $expiry
      ]);
    }

    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
  }

  /**
   * Validar y decodificar un token JWT de manera segura
   */
  public static function validateToken(string $token): ?array
  {
    try {
      $parts = explode('.', $token);
      if (count($parts) !== 3) {
        self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_structure']);
        return null;
      }

      list($headerEncoded, $payloadEncoded, $signatureProvided) = $parts;
      $header = json_decode(self::base64UrlDecode($headerEncoded), true);

      if (!$header || !self::validateHeader($header)) {
        self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_header']);
        return null;
      }

      $secret = self::getSecret();
      $signature = self::createSignature("$headerEncoded.$payloadEncoded", $secret, $header['alg']);
      $signatureCalculated = self::base64UrlEncode($signature);

      if (!hash_equals($signatureProvided, $signatureCalculated)) {
        self::logSecurityEvent('jwt_verify_failed', ['reason' => 'signature_mismatch']);
        return null;
      }

      $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
      if (!$payload) {
        self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_payload']);
        return null;
      }

      if (!self::validateTimeClaims($payload)) {
        return null;
      }

      if (isset($payload['jti']) && self::isTokenBlacklisted($payload['jti'])) {
        self::logSecurityEvent('jwt_verify_failed', ['reason' => 'token_blacklisted']);
        return null;
      }

      return $payload;
    } catch (\Exception $e) {
      self::logSecurityEvent('jwt_verify_error', ['error' => $e->getMessage()]);
      return null;
    }
  }

  /**
   * Middleware para requerir autenticación (acepta opciones)
   */
  public static function requireAuth(array $options = []): ?array
  {
    $token = null;

    if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
      $token = Cookies::getJwt();
      if (!$token) {
        self::sendUnauthorized('Token de autorización requerido');
        return null;
      }
    } else {
      $token = Cookies::getJwt();
      if (!$token) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
          $token = trim($matches[1]);
        } else if (function_exists('getallheaders')) {
          $headers = getallheaders();
          $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
          if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
          }
        }
      }
      if (!$token) {
        self::sendUnauthorized('Token de autorización requerido');
        return null;
      }
    }

    $payload = self::validateToken($token);
    if (!$payload) {
      self::sendUnauthorized('Token inválido o expirado');
      return null;
    }

    if (!self::validateStandardClaims($payload, $options)) {
      self::sendUnauthorized('Token no cumple con los requisitos de validación');
      return null;
    }

    if (!self::verifyUserExists((int)$payload['user_id'])) {
      self::sendUnauthorized('Usuario no encontrado o inactivo');
      return null;
    }

    return $payload;
  }

  /**
   * Validar cabeceras estándar del JWT
   */
  private static function validateHeader(array $header): bool
  {
    return isset($header['alg']) &&
      in_array($header['alg'], self::$allowedAlgorithms) &&
      isset($header['typ']) &&
      $header['typ'] === 'JWT';
  }

  /**
   * Validar claims basados en tiempo
   */
  private static function validateTimeClaims(array $payload): bool
  {
    $currentTime = time();

    if (isset($payload['exp']) && $currentTime > ($payload['exp'] + self::$timeTolerance)) {
      self::logSecurityEvent('jwt_verify_failed', ['reason' => 'token_expired']);
      return false;
    }

    if (isset($payload['nbf']) && $currentTime < ($payload['nbf'] - self::$timeTolerance)) {
      self::logSecurityEvent('jwt_verify_failed', ['reason' => 'token_not_yet_valid']);
      return false;
    }

    if (isset($payload['iat']) && $payload['iat'] > ($currentTime + self::$timeTolerance)) {
      self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_issued_at']);
      return false;
    }

    return true;
  }

  /**
   * Validar claims estándar (issuer, audience)
   */
  private static function validateStandardClaims(array $payload, array $options): bool
  {
    if (isset($options['issuer'])) {
      if (!isset($payload['iss']) || $payload['iss'] !== $options['issuer']) {
        self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_issuer']);
        return false;
      }
    }

    if (isset($options['audience'])) {
      if (!isset($payload['aud']) || $payload['aud'] !== $options['audience']) {
        self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_audience']);
        return false;
      }
    }

    return true;
  }

  /**
   * Verificar que el usuario existe y está activo
   */
  private static function verifyUserExists(int $userId): bool
  {
    try {
      $db = getDbConnection();

      $stmt = $db->prepare('SELECT status FROM bt_candidates WHERE id = ?');
      $stmt->execute([$userId]);
      $candidate = $stmt->fetch(\PDO::FETCH_ASSOC);

      if ($candidate) {
        return $candidate['status'] === 'active';
      }

      $stmt = $db->prepare('SELECT active FROM bt_staff_profiles WHERE id = ?');
      $stmt->execute([$userId]);
      $staff = $stmt->fetch(\PDO::FETCH_ASSOC);

      return $staff ? (bool)$staff['active'] : false;
    } catch (\Exception $e) {
      error_log("User verification error: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Revisar si el token está en blacklist
   */
  private static function isTokenBlacklisted(string $jti): bool
  {
    $blacklistFile = __DIR__ . '/../../cache/jwt_blacklist.json';
    if (!file_exists($blacklistFile)) {
      return false;
    }

    $blacklist = json_decode(file_get_contents($blacklistFile), true);
    if (!$blacklist) {
      return false;
    }

    $currentTime = time();
    $blacklist = array_filter($blacklist, function ($item) use ($currentTime) {
      return $item['exp'] > $currentTime;
    });

    file_put_contents($blacklistFile, json_encode($blacklist), LOCK_EX);

    return isset($blacklist[$jti]);
  }

  /**
   * Agregar token a blacklist
   */
  private static function addToBlacklist(string $jti, int $exp): bool
  {
    $blacklistFile = __DIR__ . '/../../cache/jwt_blacklist.json';
    $cacheDir = dirname($blacklistFile);

    if (!is_dir($cacheDir)) {
      mkdir($cacheDir, 0750, true);
    }

    $blacklist = [];
    if (file_exists($blacklistFile)) {
      $blacklist = json_decode(file_get_contents($blacklistFile), true);
    }

    $blacklist[$jti] = ['exp' => $exp, 'added' => time()];

    return file_put_contents($blacklistFile, json_encode($blacklist), LOCK_EX) !== false;
  }

  /**
   * Enviar respuesta 401 no autorizada
   */
  private static function sendUnauthorized(string $message): void
  {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
      'success' => false,
      'message' => $message,
      'error_code' => 'UNAUTHORIZED'
    ]);
    exit;
  }

  /**
   * Registrar eventos de seguridad
   */
  private static function logSecurityEvent(string $event, array $details = []): void
  {
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::security($event, $details);
    }
  }

  /**
   * Compatibilidad con JWTHelper::getUserFromToken
   */
  public static function getUserFromToken(string $token): ?array
  {
    return self::validateToken($token);
  }

  /**
   * Compatibilidad con JWT::verify (devuelve false cuando token inválido)
   */
  public static function verify(string $token, array $options = [])
  {
    $payload = self::validateToken($token);
    if ($payload === null) {
      return false;
    }
    if (!self::validateStandardClaims($payload, $options)) {
      return false;
    }
    return $payload;
  }

  /**
   * Método alternativo para compatibilidad, llama a generateToken
   */
  public static function generate(array $payload, ?int $expiry = null, string $algorithm = 'HS256'): string
  {
    return self::generateToken($payload, $expiry, $algorithm);
  }

  /**
   * Método para pruebas rápidas (smoke test)
   */
  public static function smokeTest(): array
  {
    $results = [];
    try {
      $testPayload = ['user_id' => 999, 'email' => 'test@example.com', 'role' => 'candidate'];
      $token = self::generateToken($testPayload, 3600);
      $results['generate_token'] = !empty($token) ? 'PASS' : 'FAIL';

      $validatedPayload = self::validateToken($token);
      $results['validate_token'] = ($validatedPayload && $validatedPayload['user_id'] === 999) ? 'PASS' : 'FAIL';

      $verifyResult = self::verify($token);
      $results['verify_compatibility'] = ($verifyResult && $verifyResult['user_id'] === 999) ? 'PASS' : 'FAIL';

      $testData = 'test';
      $encoded = self::base64UrlEncode($testData);
      $decoded = self::base64UrlDecode($encoded);
      $results['base64_padding'] = ($decoded === $testData) ? 'PASS' : 'FAIL';

      $testSignature256 = self::createSignature('test.data', 'secret', 'HS256');
      $testSignature384 = self::createSignature('test.data', 'secret', 'HS384');
      $testSignature512 = self::createSignature('test.data', 'secret', 'HS512');
      $results['multiple_algorithms'] = (
        !empty($testSignature256) &&
        !empty($testSignature384) &&
        !empty($testSignature512)
      ) ? 'PASS' : 'FAIL';

      $results['overall_status'] = 'SUCCESS';
    } catch (\Exception $e) {
      $results['overall_status'] = 'ERROR';
      $results['error'] = $e->getMessage();
    }
    return $results;
  }
}
