<?php

namespace Middleware;

use Utils\JWT;
use Security\SecurityLogger;

/**
 * Middleware de autorización para endpoints administrativos
 * 
 * Verifica que el usuario esté autenticado y tenga los permisos necesarios
 * para acceder a funcionalidades administrativas.
 */
class AuthorizationMiddleware
{
  /**
   * Verificar autenticación y autorización
   *
   * @param array $requiredRoles Roles requeridos para acceder
   * @param bool $strict Si es true, requiere roles exactos
   * @return array Datos del usuario autenticado
   * @throws Exception Si la autenticación/autorización falla
   */
  public static function requireAuth(array $requiredRoles = [], bool $strict = false): array
  {
    // Verificar token de autenticación
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
      SecurityLogger::logAuthFailure('Missing or invalid Authorization header');
      self::denyAccess('Authentication required', 401);
    }

    $token = substr($authHeader, 7); // Remover "Bearer "

    if (empty($token)) {
      SecurityLogger::logAuthFailure('Empty token');
      self::denyAccess('Invalid token format', 401);
    }

    try {
      $user = JWT::verify($token);
    } catch (\Exception $e) {
      SecurityLogger::logInvalidJWT($e->getMessage(), $token);
      self::denyAccess('Invalid or expired token', 401);
    }

    if (!$user || !isset($user['user_id'])) {
      SecurityLogger::logInvalidJWT('Invalid token payload', $token);
      self::denyAccess('Invalid token payload', 401);
    }

    // Log autenticación exitosa
    SecurityLogger::logAuthSuccess($user['user_id'], $user['role'] ?? 'unknown');

    // Verificar autorización si se requieren roles específicos
    if (!empty($requiredRoles)) {
      $userRole = $user['role'] ?? 'guest';

      if ($strict) {
        // Modo estricto: el rol debe coincidir exactamente
        if (!in_array($userRole, $requiredRoles, true)) {
          SecurityLogger::logAccessDenied("Role mismatch: required " . implode(',', $requiredRoles) . ", got $userRole", $user['user_id']);
          self::denyAccess('Insufficient privileges', 403);
        }
      } else {
        // Modo jerárquico: admin puede acceder a todo
        $hasAccess = in_array($userRole, $requiredRoles, true) ||
          in_array($userRole, ['admin', 'superadmin'], true);

        if (!$hasAccess) {
          SecurityLogger::logAccessDenied("Insufficient role: required " . implode(',', $requiredRoles) . ", got $userRole", $user['user_id']);
          self::denyAccess('Insufficient privileges', 403);
        }
      }
    }

    // Log del acceso autorizado
    self::logSecurityEvent('AUTHORIZED_ACCESS', [
      'user_id' => $user['user_id'],
      'role' => $user['role'] ?? 'unknown',
      'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown',
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    return $user;
  }

  /**
   * Verificar si el usuario es administrador
   */
  public static function requireAdmin(): array
  {
    return self::requireAuth(['admin', 'superadmin'], true);
  }

  /**
   * Verificar acceso a objeto específico (Object Level Authorization)
   */
  public static function requireObjectAccess(string $resourceType, $resourceId, array $user = null): array
  {
    if ($user === null) {
      $user = self::requireAuth();
    }

    // Admin siempre tiene acceso
    if (in_array($user['role'], ['admin', 'superadmin'], true)) {
      return $user;
    }

    // Verificar si el usuario puede acceder a este recurso específico
    switch ($resourceType) {
      case 'candidate':
        if ($user['user_id'] != $resourceId) {
          self::denyAccess('Access denied to this candidate profile', 403);
        }
        break;

      case 'application':
        // Aquí implementarías lógica para verificar si el usuario
        // puede acceder a esta aplicación específica
        // Por ahora, solo el propio usuario o admin
        break;

      default:
        self::denyAccess('Unknown resource type', 400);
    }

    return $user;
  }

  /**
   * Rate limiting básico por usuario
   */
  public static function checkRateLimit(array $user, int $maxRequests = 100, int $timeWindow = 3600): bool
  {
    $userId = $user['user_id'];
    $cacheFile = BASE_PATH . '/storage/ratelimit/user_' . $userId . '.json';

    // Crear directorio si no existe
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
      mkdir($cacheDir, 0755, true);
    }

    $now = time();
    $requests = [];

    // Leer requests previas
    if (file_exists($cacheFile)) {
      $data = json_decode(file_get_contents($cacheFile), true);
      $requests = $data['requests'] ?? [];
    }

    // Filtrar requests dentro de la ventana de tiempo
    $requests = array_filter($requests, function ($timestamp) use ($now, $timeWindow) {
      return ($now - $timestamp) < $timeWindow;
    });

    // Verificar límite
    if (count($requests) >= $maxRequests) {
      SecurityLogger::logRateLimitViolation($userId, $_SERVER['REQUEST_URI'] ?? 'unknown');
      self::denyAccess('Rate limit exceeded', 429);
    }

    // Añadir request actual
    $requests[] = $now;

    // Guardar requests actualizadas
    file_put_contents($cacheFile, json_encode(['requests' => $requests]));

    return true;
  }

  /**
   * Denegar acceso con logging de seguridad
   */
  private static function denyAccess(string $message, int $httpCode = 403): never
  {
    self::logSecurityEvent('ACCESS_DENIED', [
      'message' => $message,
      'http_code' => $httpCode,
      'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown',
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode([
      'success' => false,
      'error' => $message,
      'code' => $httpCode
    ]);
    exit;
  }

  /**
   * Log de eventos de seguridad
   */
  private static function logSecurityEvent(string $event, array $details): void
  {
    $logData = [
      'timestamp' => date('Y-m-d H:i:s'),
      'event' => $event,
      'details' => $details
    ];

    error_log('SECURITY_EVENT: ' . json_encode($logData));

    // Para eventos críticos, puedes implementar alertas adicionales
    if (in_array($event, ['ACCESS_DENIED', 'RATE_LIMIT_EXCEEDED'])) {
      // Implementar alertas para el equipo de seguridad
    }
  }
}
