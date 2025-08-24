<?php

declare(strict_types=1);

/**
 * JWT Middleware - Protección de endpoints con JWT
 * 
 * @package Utils
 * @author Bubble Talents Development Team
 * @version 1.0.0
 */

require_once __DIR__ . '/JWTHelper.php';

class JWTMiddleware
{
  /**
   * Verificar autenticación JWT en request actual
   */
  public static function requireAuth(): ?array
  {
    // Verificar header Authorization
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!$authHeader) {
      self::sendUnauthorized('Token de autorización requerido');
      return null;
    }

    // Extraer token del header "Bearer TOKEN"
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
      self::sendUnauthorized('Formato de token inválido');
      return null;
    }

    $token = $matches[1];

    // Validar token
    $payload = JWTHelper::validateToken($token);

    if (!$payload) {
      self::sendUnauthorized('Token inválido o expirado');
      return null;
    }

    // Verificar que el usuario existe y está activo
    if (!self::verifyUserExists($payload['user_id'])) {
      self::sendUnauthorized('Usuario no encontrado o inactivo');
      return null;
    }

    return $payload;
  }

  /**
   * Verificar que el usuario existe y está activo
   */
  private static function verifyUserExists(string $userId): bool
  {
    try {
      $db = getDbConnection();
      $stmt = $db->prepare('SELECT status FROM bt_candidates WHERE id = ?');
      $stmt->execute([$userId]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      return $user && $user['status'] === 'active';
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
   * Verificar rol específico
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
