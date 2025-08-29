<?php

declare(strict_types=1);

namespace Utils;

/**
 * Clase segura para manejo de tokens JWT
 *
 * Implementa mejores prí¡cticas de seguridad para la generación y validación
 * de tokens JWT, incluyendo manejo seguro de secretos y validaciones adicionales.
 *
 * @version 2.0.0
 * @author Bubble of Talents Security Team
 */
class JWT
{
    /**
     * Algoritmos permitidos para firmar tokens
     */
    private static $allowedAlgorithms = ['HS256', 'HS384', 'HS512'];

    /**
     * Tiempo de vida por defecto del token (24 horas)
     */
    // Reducir el tiempo de expiración por defecto a 3600 segundos (1 hora).
    // Tiempos de expiración  más cortos limitan la ventana de explotación en caso de
    // compromiso de un token.  Los tokens de larga duración incrementan el riesgo si se
    // filtran, por lo que se recomienda implementar refresh tokens para mantener la
    // experiencia de usuario sin comprometer la seguridad.
    private static $defaultExpiry = 3600;

    /**
     * Tiempo de tolerancia para validación de tiempo (5 minutos)
     */
    private static $timeTolerance = 300;

    /**
     * Genera un token JWT seguro
     *
     * @param array $payload Datos a incluir en el token
     * @param int|null $expiry Tiempo de expiración en segundos
     * @param string $algorithm Algoritmo de firma
     * @return string Token JWT
     * @throws Exception Si hay errores en la configuración
     */
    public static function generate($payload, $expiry = null, $algorithm = 'HS256')
    {
        // Validar algoritmo
        if (!in_array($algorithm, self::$allowedAlgorithms)) {
            throw new \Exception("Algoritmo no permitido: $algorithm");
        }

        // Obtener clave secreta de manera segura
        $secret = self::getSecret();

        // Validar payload
        if (!is_array($payload)) {
            throw new \Exception('El payload debe ser un array');
        }

        // Prevenir campos reservados en el payload
        $reservedClaims = ['iss', 'aud', 'exp', 'nbf', 'iat', 'jti'];
        foreach ($reservedClaims as $claim) {
            if (isset($payload[$claim])) {
                throw new \Exception("Campo reservado '$claim' no debe incluirse en el payload");
            }
        }

        // Si no se especifica un tiempo de expiración, usar el valor por defecto
        if ($expiry === null) {
            $expiry = config('JWT_EXPIRY', self::$defaultExpiry);
        }

        // Validar tiempo de expiración
        if ($expiry <= 0 || $expiry > 86400 * 30) { // Mí¡ximo 30 dí­as
            throw new \Exception('Tiempo de expiración inví¡lido');
        }

        $currentTime = time();

        // Crear header con información adicional de seguridad
        $header = [
            'alg' => $algorithm,
            'typ' => 'JWT',
            'kid' => self::getKeyId() // Key ID para rotación de claves
        ];

        // Aí±adir claims estí¡ndar al payload
        $payload['iss'] = config('JWT_ISSUER', 'bubble-talents-api'); // Issuer
        $payload['aud'] = config('JWT_AUDIENCE', 'bubble-talents-app'); // Audience
        $payload['iat'] = $currentTime; // Issued At
        $payload['nbf'] = $currentTime; // Not Before
        $payload['exp'] = $currentTime + $expiry; // Expiration Time
        $payload['jti'] = self::generateJti(); // JWT ID único

        // Codificar header y payload
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        // Crear firma usando el algoritmo especificado
        $signature = self::createSignature("$headerEncoded.$payloadEncoded", $secret, $algorithm);
        $signatureEncoded = self::base64UrlEncode($signature);

        // Crear token
        $token = "$headerEncoded.$payloadEncoded.$signatureEncoded";

        // Log para auditoría (sin información sensible)
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('JWT token generado', [
                'algorithm' => $algorithm,
                'user_id' => (int)($payload['user_id'] ?? 0)  // ← Forzar a INT
            ]);
        }

        return $token;
    }

    /**
     * Verifica y decodifica un token JWT de manera segura
     *
     * @param string $token Token JWT a verificar
     * @param array $options Opciones de validación
     * @return array|false Payload decodificado o false si es inví¡lido
     */
    public static function verify($token, $options = [])
    {
        try {
            // Validaciones bí¡sicas
            if (empty($token) || !is_string($token)) {
                self::logSecurityEvent('jwt_verify_failed', ['reason' => 'token_empty_or_invalid']);
                return false;
            }

            // Dividir el token en sus partes
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_token_structure']);
                return false;
            }

            list($headerEncoded, $payloadEncoded, $signatureProvided) = $parts;

            // Decodificar y validar header
            $header = json_decode(self::base64UrlDecode($headerEncoded), true);
            if (!$header || !self::validateHeader($header)) {
                self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_header']);
                return false;
            }

            // Obtener clave secreta
            $secret = self::getSecret();

            // Verificar firma
            $signature = self::createSignature("$headerEncoded.$payloadEncoded", $secret, $header['alg']);
            $signatureCalculated = self::base64UrlEncode($signature);

            if (!hash_equals($signatureProvided, $signatureCalculated)) {
                self::logSecurityEvent('jwt_verify_failed', ['reason' => 'signature_mismatch']);
                return false;
            }

            // Decodificar payload
            $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

            if (!$payload) {
                self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_payload']);
                return false;
            }

            // Validar claims temporales
            if (!self::validateTimeClaims($payload, $options)) {
                return false;
            }

            // Validar claims estí¡ndar
            if (!self::validateStandardClaims($payload, $options)) {
                return false;
            }

            // Validar que el token no esté en blacklist (si se implementa)
            if (isset($payload['jti']) && self::isTokenBlacklisted($payload['jti'])) {
                self::logSecurityEvent('jwt_verify_failed', ['reason' => 'token_blacklisted', 'jti' => $payload['jti']]);
                return false;
            }

            // Log de éxito (sin información sensible)
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::info('JWT token verificado exitosamente', [
                    'user_id' => $payload['user_id'] ?? 'unknown',
                    'jti' => $payload['jti'] ?? 'unknown'
                ]);
            }

            return $payload;
        } catch (\Exception $e) {
            self::logSecurityEvent('jwt_verify_error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Invalida un token aí±adiéndolo a una blacklist
     *
     * @param string $token Token a invalidar
     * @return bool True si se invalidó correctamente
     */
    public static function invalidate($token)
    {
        $payload = self::verify($token);
        if (!$payload || !isset($payload['jti'])) {
            return false;
        }

        return self::addToBlacklist($payload['jti'], $payload['exp']);
    }

    /**
     * Obtiene la clave secreta de manera segura
     *
     * @return string Clave secreta
     * @throws Exception Si la clave no estí¡ configurada
     */
    private static function getSecret()
    {
        $secret = config('JWT_SECRET');

        if (empty($secret)) {
            throw new \Exception('JWT_SECRET no estí¡ configurado');
        }

        // Validar longitud mí­nima de la clave
        if (strlen($secret) < 32) {
            throw new \Exception('JWT_SECRET debe tener al menos 32 caracteres');
        }

        return $secret;
    }

    /**
     * Obtiene el ID de la clave para rotación
     *
     * @return string Key ID
     */
    private static function getKeyId()
    {
        return config('JWT_KEY_ID', 'default');
    }

    /**
     * Genera un JWT ID único
     *
     * @return string JWT ID
     */
    private static function generateJti()
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Crea la firma usando el algoritmo especificado
     *
     * @param string $data Datos a firmar
     * @param string $secret Clave secreta
     * @param string $algorithm Algoritmo
     * @return string Firma
     */
    private static function createSignature($data, $secret, $algorithm)
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
     * Valida el header del JWT
     *
     * @param array $header Header decodificado
     * @return bool True si es ví¡lido
     */
    private static function validateHeader($header)
    {
        if (!isset($header['alg']) || !in_array($header['alg'], self::$allowedAlgorithms)) {
            return false;
        }

        if (!isset($header['typ']) || $header['typ'] !== 'JWT') {
            return false;
        }

        return true;
    }

    /**
     * Valida los claims temporales del JWT
     *
     * @param array $payload Payload decodificado
     * @param array $options Opciones de validación
     * @return bool True si es ví¡lido
     */
    private static function validateTimeClaims($payload, $options)
    {
        $currentTime = time();
        $tolerance = $options['time_tolerance'] ?? self::$timeTolerance;

        // Verificar expiración
        if (isset($payload['exp'])) {
            if ($currentTime > ($payload['exp'] + $tolerance)) {
                self::logSecurityEvent('jwt_verify_failed', ['reason' => 'token_expired']);
                return false;
            }
        }

        // Verificar "not before"
        if (isset($payload['nbf'])) {
            if ($currentTime < ($payload['nbf'] - $tolerance)) {
                self::logSecurityEvent('jwt_verify_failed', ['reason' => 'token_not_yet_valid']);
                return false;
            }
        }

        // Verificar "issued at" (no puede ser futuro)
        if (isset($payload['iat'])) {
            if ($payload['iat'] > ($currentTime + $tolerance)) {
                self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_issued_at']);
                return false;
            }
        }

        return true;
    }

    /**
     * Valida los claims estí¡ndar del JWT
     *
     * @param array $payload Payload decodificado
     * @param array $options Opciones de validación
     * @return bool True si es ví¡lido
     */
    private static function validateStandardClaims($payload, $options)
    {
        // Validar issuer si se especifica
        if (isset($options['issuer'])) {
            if (!isset($payload['iss']) || $payload['iss'] !== $options['issuer']) {
                self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_issuer']);
                return false;
            }
        }

        // Validar audience si se especifica
        if (isset($options['audience'])) {
            if (!isset($payload['aud']) || $payload['aud'] !== $options['audience']) {
                self::logSecurityEvent('jwt_verify_failed', ['reason' => 'invalid_audience']);
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica si un token estí¡ en la blacklist
     *
     * @param string $jti JWT ID
     * @return bool True si estí¡ en blacklist
     */
    private static function isTokenBlacklisted($jti)
    {
        // Implementación simple con archivos (en producción usar Redis/DB)
        $blacklistFile = __DIR__ . '/../../cache/jwt_blacklist.json';

        if (!file_exists($blacklistFile)) {
            return false;
        }

        $blacklist = json_decode(file_get_contents($blacklistFile), true);

        if (!$blacklist) {
            return false;
        }

        // Limpiar tokens expirados
        $currentTime = time();
        $blacklist = array_filter($blacklist, function ($item) use ($currentTime) {
            return $item['exp'] > $currentTime;
        });

        // Guardar blacklist limpia
        file_put_contents($blacklistFile, json_encode($blacklist), LOCK_EX);

        return isset($blacklist[$jti]);
    }

    /**
     * Aí±ade un token a la blacklist
     *
     * @param string $jti JWT ID
     * @param int $exp Tiempo de expiración
     * @return bool True si se aí±adió correctamente
     */
    private static function addToBlacklist($jti, $exp)
    {
        $blacklistFile = __DIR__ . '/../../cache/jwt_blacklist.json';
        $cacheDir = dirname($blacklistFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }

        $blacklist = [];
        if (file_exists($blacklistFile)) {
            $blacklist = json_decode(file_get_contents($blacklistFile), true) ?: [];
        }

        $blacklist[$jti] = ['exp' => $exp, 'added' => time()];

        return file_put_contents($blacklistFile, json_encode($blacklist), LOCK_EX) !== false;
    }

    /**
     * Registra eventos de seguridad
     *
     * @param string $event Tipo de evento
     * @param array $details Detalles del evento
     */
    private static function logSecurityEvent($event, $details = [])
    {
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::security($event, $details);
        }
    }

    /**
     * Codifica datos en Base64 URL-safe
     *
     * @param string $data Datos a codificar
     * @return string Datos codificados
     */
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodifica datos en Base64 URL-safe
     *
     * @param string $data Datos a decodificar
     * @return string Datos decodificados
     */
    private static function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function requireAuth(array $options = []): array
    {
        // 1) Leer el header Authorization: Bearer <token>
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            throw new \RuntimeException('Authorization Bearer token requerido');
        }
        $token = trim($m[1]);

        // 2) Verificar el token usando tu verify() robusto
        //    Puedes fijar issuer/audience por defecto desde tu config
        $defaults = [
            'issuer'   => config('JWT_ISSUER', 'bubble-talents-api'),
            'audience' => config('JWT_AUDIENCE', 'bubble-talents-app'),
            'time_tolerance' => 300,
        ];
        $opts = array_replace($defaults, $options);

        $payload = self::verify($token, $opts);
        if ($payload === false) {
            throw new \RuntimeException('Token inví¡lido o expirado');
        }

        return $payload; // ej. ['user_id'=>'...', 'role'=>'recruiter', ...]
    }
}
