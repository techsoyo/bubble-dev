<?php

declare(strict_types=1);

namespace Utils;

/**
 * JWT Middleware - Protección de endpoints con JWT
 * 
 * @package Utils
 * @author Bubble Talents Development Team
 * @version 1.0.0
 */

require_once __DIR__ . '/JWTHelper.php';

use Security\Cookies;

class JWTMiddleware
{
  /**
   * Verificar autenticación JWT en request actual
   */
  public static function requireAuth(): ?array
  {
    $token = null;

    // En producción: solo cookies por seguridad
    if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
      $token = Cookies::getJwt();

      if (!$token) {
        self::sendUnauthorized('Token de autorización requerido');
        return null;
      }
    } else {
      // En desarrollo/staging: mantener fallback para compatibilidad
      // 1. Primero, intentar obtener token desde cookie (método preferido)
      $token = Cookies::getJwt();

      if (!$token) {
        // 2. Fallback: verificar header Authorization
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if ($authHeader) {
          // Extraer token del header "Bearer TOKEN"
          if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
          }
        }
      }

      if (!$token) {
        self::sendUnauthorized('Token de autorización requerido');
        return null;
      }
    }

    // Validar token
    $payload = JWTHelper::validateToken($token);

    if (!$payload) {
      self::sendUnauthorized('Token inví¡lido o expirado');
      return null;
    }

    // Verificar que el usuario existe y estí¡ activo
    if (!self::verifyUserExists($payload['user_id'])) {
      self::sendUnauthorized('Usuario no encontrado o inactivo');
      return null;
    }

    return $payload;
  }

  /**
   * Verificar que el usuario existe y estí¡ activo
   */
  private static function verifyUserExists(string $userId): bool
  {
    try {
      $db = getDbConnection();

      // Primero intentar en la tabla de candidatos
      $stmt = $db->prepare('SELECT status FROM bt_candidates WHERE id = ?');
      $stmt->execute([$userId]);
      $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($candidate) {
        return $candidate['status'] === 'active';
      }

      // Si no se encuentra como candidato, verificar en staff
      $stmt = $db->prepare('SELECT active FROM bt_staff_profiles WHERE id = ?');
      $stmt->execute([$userId]);
      $staff = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($staff) {
        return (bool) $staff['active'];
      }

      return false;
    } catch (Exception $e) {
      error_log("User verification error: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Enviar respuesta de no autorizado
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
   * Verificar rol especí­fico
   */
  public static function requireRole(array $allowedRoles): ?array
  {
    $payload = self::requireAuth();

    if (!$payload) {
      return null;
    }

    $userRole = $payload['role'] ?? 'candidate';

    if (!in_array($userRole, $allowedRoles)) {
      http_response_code(403);
      header('Content-Type: application/json');
      echo json_encode([
        'success' => false,
        'message' => 'Permisos insuficientes',
        'error_code' => 'FORBIDDEN'
      ]);
      exit;
    }

    return $payload;
  }
}
