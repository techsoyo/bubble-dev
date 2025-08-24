<?php

declare(strict_types=1);

/**
 * JWT Helper - Manejo seguro de JSON Web Tokens
 * 
 * @package Utils
 * @author Bubble Talents Development Team
 * @version 1.0.0
 */

class JWTHelper
{
  private static function base64UrlEncode($data): string
  {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  private static function base64UrlDecode($data): string
  {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
  }

  public static function generateToken(array $payload): string
  {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);

    $base64Header = self::base64UrlEncode($header);
    $base64Payload = self::base64UrlEncode($payload);

    $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $_ENV['JWT_SECRET'], true);
    $base64Signature = self::base64UrlEncode($signature);

    return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
  }

  public static function validateToken(string $token): ?array
  {
    $parts = explode('.', $token);

    if (count($parts) !== 3) {
      return null;
    }

    list($header, $payload, $signature) = $parts;

    $validSignature = hash_hmac('sha256', $header . '.' . $payload, $_ENV['JWT_SECRET'], true);

    if (!hash_equals(self::base64UrlDecode($signature), $validSignature)) {
      return null;
    }

    $payloadData = json_decode(self::base64UrlDecode($payload), true);

    if (!$payloadData || !isset($payloadData['exp']) || $payloadData['exp'] < time()) {
      return null;
    }

    return $payloadData;
  }

  public static function getUserFromToken(string $token): ?array
  {
    $payload = self::validateToken($token);

    if (!$payload || !isset($payload['user_id'])) {
      return null;
    }

    return $payload;
  }
}
