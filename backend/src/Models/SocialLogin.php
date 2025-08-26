<?php declare(strict_types=1);
namespace Models;

use Utils\Logger;
use PDOException;

/**
 * Modelo SocialLogin para gestiÃƒÆ’Ã‚Â³n de autenticaciones OAuth
 * 
 * Maneja las vinculaciones de proveedores sociales (Google, LinkedIn, Facebook, GitHub)
 * con los usuarios del sistema, proporcionando funcionalidades de OAuth seguras
 * con encriptaciÃƒÆ’Ã‚Â³n de tokens y cache para datos de perfil.
 *
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class SocialLogin extends BaseModel
{
    /**
     * Tabla asociada al modelo
     */
    protected string $table = 'social_logins';
    /*
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â§ CORRECCIÃƒÆ’Ã¢â‚¬Å“N AUTOMÃƒÆ’Ã‚ÂTICA APLICADA
     * Modelo: SocialLogin
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ÃƒÂ¢Ã…Â¾Ã¢â‚¬Â¢ Campos aÃƒÆ’Ã‚Â±adidos: ['candidate_id', 'provider_user_id']
     * ÃƒÂ¢Ã‚ÂÃ…â€™ Campos removidos: ['user_id', 'provider_id', 'access_token', 'refresh_token', 'expires_at', 'profile_data']
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  Total campos fillable: 3
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'candidate_id',
        'provider',
        'provider_user_id',
    ];

    /**
     * Campos que deben ser ocultados en arrays/JSON
     */
    protected array $hidden = [
        'access_token',
        'refresh_token',
        'provider_secret'
    ];

    /**
     * Proveedores OAuth soportados
     */
    protected const SUPPORTED_PROVIDERS = [
        'google',
        'linkedin',
        'facebook',
        'github'
    ];

    /**
     * Tiempo de cache para datos de perfil (en segundos)
     */
    protected const PROFILE_CACHE_TTL = 3600; // 1 hora

    /**
     * Cache para datos de perfil
     */
    protected array $profileCache = [];

    /**
     * Clave de encriptaciÃƒÆ’Ã‚Â³n para tokens (deberÃƒÆ’Ã‚Â­a venir de configuraciÃƒÆ’Ã‚Â³n)
     */
    private function getEncryptionKey(): string
    {
        // En producciÃƒÆ’Ã‚Â³n, esto deberÃƒÆ’Ã‚Â­a venir de una variable de entorno
        return $_ENV['OAUTH_ENCRYPTION_KEY'] ?? 'default-encryption-key-change-in-production';
    }

    /**
     * MÃƒÆ’Ã¢â‚¬Â°TODOS PRINCIPALES OAUTH
     */

    /**
     * Buscar autenticaciÃƒÆ’Ã‚Â³n social por proveedor y ID externo
     *
     * @param string $provider Proveedor (google, linkedin, facebook, github)
     * @param string $providerId ID del usuario en el proveedor
     * @return array|null Datos de la autenticaciÃƒÆ’Ã‚Â³n social o null si no existe
     * @throws \InvalidArgumentException Si el proveedor no es vÃƒÆ’Ã‚Â¡lido
     */
    public function findByProvider(string $provider, string $providerId): ?array
    {
        if (!$this->isValidProvider($provider)) {
            throw new \InvalidArgumentException("Proveedor no soportado: $provider");
        }

        if (empty($providerId)) {
            throw new \InvalidArgumentException('Provider ID no puede estar vacÃƒÆ’Ã‚Â­o');
        }

        try {
            $result = $this->findOneBy('provider', $provider);

            if ($result && $result['provider_id'] === $providerId) {
                // Desencriptar tokens antes de devolver
                $result = $this->decryptTokens($result);
                return $result;
            }

            return null;
        } catch (\Exception $e) {
            Logger::error('Error buscando por proveedor', [
                'provider' => $provider,
                'provider_id' => $providerId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al buscar autenticaciÃƒÆ’Ã‚Â³n social: ' . $e->getMessage());
        }
    }

    /**
     * Crear nueva vinculaciÃƒÆ’Ã‚Â³n OAuth
     *
     * @param array $data Datos de la vinculaciÃƒÆ’Ã‚Â³n OAuth
     * @return mixed ID de la nueva vinculaciÃƒÆ’Ã‚Â³n
     * @throws \InvalidArgumentException Si los datos son invÃƒÆ’Ã‚Â¡lidos
     */
    public function createSocialLogin(array $data)
    {
        // Validar datos requeridos
        $required = ['user_id', 'provider', 'provider_id', 'access_token'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo requerido faltante: $field");
            }
        }

        if (!$this->isValidProvider($data['provider'])) {
            throw new \InvalidArgumentException("Proveedor no soportado: {$data['provider']}");
        }

        // Verificar si ya existe vinculaciÃƒÆ’Ã‚Â³n
        $existing = $this->findByProvider($data['provider'], $data['provider_id']);
        if ($existing) {
            throw new \RuntimeException('Ya existe vinculaciÃƒÆ’Ã‚Â³n para este proveedor y usuario');
        }

        try {
            // Encriptar tokens antes de guardar
            $data = $this->encryptTokens($data);

            // Convertir profile_data a JSON si es array
            if (isset($data['profile_data']) && is_array($data['profile_data'])) {
                $data['profile_data'] = json_encode($data['profile_data']);
            }

            // Filtrar solo los campos permitidos por $fillable
            $filtered = [];
            foreach ($this->fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }

            return $this->store($filtered);
        } catch (\Exception $e) {
            Logger::error('Error creando vinculaciÃƒÆ’Ã‚Â³n social', [
                'provider' => $data['provider'],
                'user_id' => $data['user_id'],
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al crear vinculaciÃƒÆ’Ã‚Â³n social: ' . $e->getMessage());
        }
    }

    /**
     * Renovar tokens de acceso
     *
     * @param int $socialLoginId ID de la vinculaciÃƒÆ’Ã‚Â³n social
     * @param string $newAccessToken Nuevo token de acceso
     * @param string|null $newRefreshToken Nuevo token de refresco (opcional)
     * @param string|null $expiresAt Fecha de expiraciÃƒÆ’Ã‚Â³n (opcional)
     * @return bool True si se actualizÃƒÆ’Ã‚Â³ correctamente
     */
    public function refreshToken(int $socialLoginId, string $newAccessToken, ?string $newRefreshToken = null, ?string $expiresAt = null): bool
    {
        if (empty($newAccessToken)) {
            throw new \InvalidArgumentException('Access token no puede estar vacÃƒÆ’Ã‚Â­o');
        }

        try {
            $updateData = [
                'access_token' => $this->encryptValue($newAccessToken)
            ];

            if ($newRefreshToken) {
                $updateData['refresh_token'] = $this->encryptValue($newRefreshToken);
            }

            if ($expiresAt) {
                $updateData['expires_at'] = $expiresAt;
            }

            $updateData['updated_at'] = date('Y-m-d H:i:s');

            $result = $this->update($socialLoginId, $updateData);

            // Limpiar cache de perfil al actualizar tokens
            $this->clearProfileCache($socialLoginId);

            Logger::info('Tokens renovados exitosamente', [
                'social_login_id' => $socialLoginId,
                'has_refresh_token' => !empty($newRefreshToken)
            ]);

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error renovando tokens', [
                'social_login_id' => $socialLoginId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al renovar tokens: ' . $e->getMessage());
        }
    }

    /**
     * Desvincular proveedor OAuth
     *
     * @param int $userId ID del usuario
     * @param string $provider Proveedor a desvincular
     * @return bool True si se desvinculÃƒÆ’Ã‚Â³ correctamente
     */
    public function unlinkProvider(int $userId, string $provider): bool
    {
        if (!$this->isValidProvider($provider)) {
            throw new \InvalidArgumentException("Proveedor no soportado: $provider");
        }

        try {
            $socialLogin = $this->findBy('user_id', $userId);
            $target = null;

            foreach ($socialLogin as $login) {
                if ($login['provider'] === $provider) {
                    $target = $login;
                    break;
                }
            }

            if (!$target) {
                throw new \RuntimeException('No se encontrÃƒÆ’Ã‚Â³ vinculaciÃƒÆ’Ã‚Â³n para este proveedor');
            }

            // Limpiar cache antes de eliminar
            $this->clearProfileCache($target['id']);

            $result = $this->delete($target['id']);

            Logger::info('Proveedor desvinculado exitosamente', [
                'user_id' => $userId,
                'provider' => $provider,
                'social_login_id' => $target['id']
            ]);

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error desvinculando proveedor', [
                'user_id' => $userId,
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al desvincular proveedor: ' . $e->getMessage());
        }
    }

    /**
     * Obtener proveedores vinculados para un usuario
     *
     * @param int $userId ID del usuario
     * @return array Lista de proveedores vinculados con metadatos
     */
    public function getLinkedProviders(int $userId): array
    {
        try {
            $socialLogins = $this->findBy('user_id', $userId);
            $linkedProviders = [];

            foreach ($socialLogins as $login) {
                $profileData = null;

                // Decodificar profile_data si existe
                if (!empty($login['profile_data'])) {
                    $profileData = json_decode($login['profile_data'], true);
                }

                $linkedProviders[] = [
                    'id' => $login['id'],
                    'provider' => $login['provider'],
                    'provider_id' => $login['provider_id'],
                    'linked_at' => $login['created_at'],
                    'last_updated' => $login['updated_at'],
                    'expires_at' => $login['expires_at'] ?? null,
                    'profile_data' => $profileData
                ];
            }

            return $linkedProviders;
        } catch (\Exception $e) {
            Logger::error('Error obteniendo proveedores vinculados', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al obtener proveedores vinculados: ' . $e->getMessage());
        }
    }

    /**
     * MÃƒÆ’Ã¢â‚¬Â°TODOS DE FUNCIONALIDAD EXISTENTE (heredados)
     */

    /**
     * Obtiene las autenticaciones sociales de un candidato
     * (mantiene compatibilidad con cÃƒÆ’Ã‚Â³digo existente)
     *
     * @param string $candidateId ID del candidato
     * @return array Lista de autenticaciones
     */
    public function findByCandidateId(string $candidateId): array
    {
        return $this->findBy('user_id', $candidateId);
    }

    /**
     * MÃƒÆ’Ã¢â‚¬Â°TODOS DE CACHE
     */

    /**
     * Obtener datos de perfil con cache
     *
     * @param int $socialLoginId ID de la vinculaciÃƒÆ’Ã‚Â³n social
     * @return array|null Datos del perfil en cache
     */
    public function getCachedProfileData(int $socialLoginId): ?array
    {
        if (isset($this->profileCache[$socialLoginId])) {
            $cached = $this->profileCache[$socialLoginId];

            // Verificar si el cache ha expirado
            if ($cached['expires_at'] > time()) {
                return $cached['data'];
            } else {
                unset($this->profileCache[$socialLoginId]);
            }
        }

        return null;
    }

    /**
     * Guardar datos de perfil en cache
     *
     * @param int $socialLoginId ID de la vinculaciÃƒÆ’Ã‚Â³n social
     * @param array $profileData Datos del perfil
     */
    public function cacheProfileData(int $socialLoginId, array $profileData): void
    {
        $this->profileCache[$socialLoginId] = [
            'data' => $profileData,
            'expires_at' => time() + self::PROFILE_CACHE_TTL
        ];
    }

    /**
     * Limpiar cache de perfil
     *
     * @param int $socialLoginId ID de la vinculaciÃƒÆ’Ã‚Â³n social
     */
    public function clearProfileCache(int $socialLoginId): void
    {
        unset($this->profileCache[$socialLoginId]);
    }

    /**
     * MÃƒÆ’Ã¢â‚¬Â°TODOS DE VALIDACIÃƒÆ’Ã¢â‚¬Å“N Y UTILIDADES
     */

    /**
     * Validar si un proveedor es soportado
     *
     * @param string $provider Proveedor a validar
     * @return bool True si es vÃƒÆ’Ã‚Â¡lido
     */
    protected function isValidProvider(string $provider): bool
    {
        return in_array(strtolower($provider), self::SUPPORTED_PROVIDERS, true);
    }

    /**
     * MÃƒÆ’Ã¢â‚¬Â°TODOS DE ENCRIPTACIÃƒÆ’Ã¢â‚¬Å“N
     */

    /**
     * Encriptar tokens en los datos
     *
     * @param array $data Datos que contienen tokens
     * @return array Datos con tokens encriptados
     */
    protected function encryptTokens(array $data): array
    {
        $tokensToEncrypt = ['access_token', 'refresh_token', 'provider_secret'];

        foreach ($tokensToEncrypt as $tokenField) {
            if (!empty($data[$tokenField])) {
                $data[$tokenField] = $this->encryptValue($data[$tokenField]);
            }
        }

        return $data;
    }

    /**
     * Desencriptar tokens en los datos
     *
     * @param array $data Datos que contienen tokens encriptados
     * @return array Datos con tokens desencriptados
     */
    protected function decryptTokens(array $data): array
    {
        $tokensToDecrypt = ['access_token', 'refresh_token', 'provider_secret'];

        foreach ($tokensToDecrypt as $tokenField) {
            if (!empty($data[$tokenField])) {
                $data[$tokenField] = $this->decryptValue($data[$tokenField]);
            }
        }

        return $data;
    }

    /**
     * Encriptar un valor
     *
     * @param string $value Valor a encriptar
     * @return string Valor encriptado
     */
    protected function encryptValue(string $value): string
    {
        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Desencriptar un valor
     *
     * @param string $encryptedValue Valor encriptado
     * @return string Valor desencriptado
     */
    protected function decryptValue(string $encryptedValue): string
    {
        $key = $this->getEncryptionKey();
        $data = base64_decode($encryptedValue);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * MÃƒÆ’Ã¢â‚¬Â°TODOS ADICIONALES DE UTILIDAD
     */

    /**
     * Verificar si un token ha expirado
     *
     * @param int $socialLoginId ID de la vinculaciÃƒÆ’Ã‚Â³n social
     * @return bool True si el token ha expirado
     */
    public function isTokenExpired(int $socialLoginId): bool
    {
        try {
            $socialLogin = $this->findById($socialLoginId);

            if (!$socialLogin || empty($socialLogin['expires_at'])) {
                return false; // Si no hay fecha de expiraciÃƒÆ’Ã‚Â³n, asumimos que no expira
            }

            return strtotime($socialLogin['expires_at']) <= time();
        } catch (\Exception $e) {
            Logger::error('Error verificando expiraciÃƒÆ’Ã‚Â³n de token', [
                'social_login_id' => $socialLoginId,
                'error' => $e->getMessage()
            ]);
            return true; // Por seguridad, asumimos que ha expirado si hay error
        }
    }

    /**
     * Obtener estadÃƒÆ’Ã‚Â­sticas de proveedores OAuth
     *
     * @return array EstadÃƒÆ’Ã‚Â­sticas de uso por proveedor
     */
    public function getProviderStats(): array
    {
        try {
            $query = "
                SELECT 
                    provider,
                    COUNT(*) as total_links,
                    COUNT(DISTINCT user_id) as unique_users,
                    AVG(CASE WHEN expires_at IS NOT NULL AND expires_at > NOW() THEN 1 ELSE 0 END) as active_rate
                FROM `{$this->table}`
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY provider
                ORDER BY total_links DESC
            ";

            return $this->query($query);
        } catch (\Exception $e) {
            Logger::error('Error obteniendo estadÃƒÆ’Ã‚Â­sticas de proveedores', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Actualizar datos de perfil de un proveedor
     *
     * @param int $socialLoginId ID de la vinculaciÃƒÆ’Ã‚Â³n social
     * @param array $profileData Nuevos datos del perfil
     * @return bool True si se actualizÃƒÆ’Ã‚Â³ correctamente
     */
    public function updateProfileData(int $socialLoginId, array $profileData): bool
    {
        try {
            $updateData = [
                'profile_data' => json_encode($profileData),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $result = $this->update($socialLoginId, $updateData);

            // Actualizar cache
            $this->cacheProfileData($socialLoginId, $profileData);

            Logger::info('Datos de perfil actualizados', [
                'social_login_id' => $socialLoginId
            ]);

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error actualizando datos de perfil', [
                'social_login_id' => $socialLoginId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al actualizar datos de perfil: ' . $e->getMessage());
        }
    }

    /**
     * Limpiar vinculaciones expiradas
     *
     * @return int NÃƒÆ’Ã‚Âºmero de vinculaciones limpiadas
     */
    public function cleanExpiredLinks(): int
    {
        try {
            $query = "
                DELETE FROM `{$this->table}`
                WHERE expires_at IS NOT NULL 
                AND expires_at < NOW() 
                AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();

            $deletedCount = $stmt->rowCount();

            Logger::info('Vinculaciones expiradas limpiadas', [
                'deleted_count' => $deletedCount
            ]);

            return $deletedCount;
        } catch (\Exception $e) {
            Logger::error('Error limpiando vinculaciones expiradas', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al limpiar vinculaciones expiradas: ' . $e->getMessage());
        }
    }

    /**
     * ==========================================
     * MÃƒÆ’Ã¢â‚¬Â°TODOS CRUD ESTÃƒÆ’Ã‚ÂNDAR (BaseModel Template)
     * ==========================================
     */

    /**
     * Validar datos de SocialLogin
     *
     * @param array $data Datos a validar
     * @param bool $isUpdate Si es una actualizaciÃƒÆ’Ã‚Â³n (algunos campos opcionales)
     * @return array Array con 'valid' (bool) y 'errors' (array)
     */
    public static function validateSocialLoginData(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // Validar candidate_id (requerido en creaciÃƒÆ’Ã‚Â³n)
        if (!$isUpdate && empty($data['candidate_id'])) {
            $errors[] = 'candidate_id es requerido';
        } elseif (!empty($data['candidate_id']) && !is_numeric($data['candidate_id'])) {
            $errors[] = 'candidate_id debe ser un nÃƒÆ’Ã‚Âºmero vÃƒÆ’Ã‚Â¡lido';
        }

        // Validar provider (requerido en creaciÃƒÆ’Ã‚Â³n)
        if (!$isUpdate && empty($data['provider'])) {
            $errors[] = 'provider es requerido';
        } elseif (!empty($data['provider'])) {
            if (!is_string($data['provider']) || strlen($data['provider']) > 50) {
                $errors[] = 'provider debe ser un string vÃƒÆ’Ã‚Â¡lido (mÃƒÆ’Ã‚Â¡x. 50 caracteres)';
            } elseif (!in_array($data['provider'], self::SUPPORTED_PROVIDERS)) {
                $errors[] = 'provider debe ser uno de: ' . implode(', ', self::SUPPORTED_PROVIDERS);
            }
        }

        // Validar provider_user_id (requerido en creaciÃƒÆ’Ã‚Â³n)
        if (!$isUpdate && empty($data['provider_user_id'])) {
            $errors[] = 'provider_user_id es requerido';
        } elseif (!empty($data['provider_user_id']) && (!is_string($data['provider_user_id']) || strlen($data['provider_user_id']) > 255)) {
            $errors[] = 'provider_user_id debe ser un string vÃƒÆ’Ã‚Â¡lido (mÃƒÆ’Ã‚Â¡x. 255 caracteres)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Crear nueva vinculaciÃƒÆ’Ã‚Â³n social (mÃƒÆ’Ã‚Â©todo estÃƒÆ’Ã‚Â¡ndar)
     *
     * @param array $data Datos de la vinculaciÃƒÆ’Ã‚Â³n social
     * @return mixed ID del registro creado o false en caso de error
     */
    public static function createSocialLoginStandard(array $data)
    {
        $instance = new self();

        // Validar datos
        $validation = self::validateSocialLoginData($data);
        if (!$validation['valid']) {
            $instance->logError('Datos invÃƒÆ’Ã‚Â¡lidos para crear vinculaciÃƒÆ’Ã‚Â³n social', [
                'errors' => $validation['errors'],
                'data' => $data
            ]);
            return false;
        }

        try {
            // Filtrar solo los campos permitidos por $fillable
            $filtered = [];
            foreach ($instance->fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }
            $result = $instance->store($filtered);

            if ($result) {
                $instance->logDebug('VinculaciÃƒÆ’Ã‚Â³n social creada exitosamente', [
                    'id' => $result,
                    'candidate_id' => $filtered['candidate_id'] ?? null,
                    'provider' => $filtered['provider'] ?? null
                ]);

                // Invalidar cache relacionado
                $instance->invalidateModelCache();
            }

            return $result;
        } catch (\Exception $e) {
            $instance->logError('Error al crear vinculaciÃƒÆ’Ã‚Â³n social', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Obtener vinculaciÃƒÆ’Ã‚Â³n social por ID (mÃƒÆ’Ã‚Â©todo estÃƒÆ’Ã‚Â¡ndar)
     *
     * @param int $id ID de la vinculaciÃƒÆ’Ã‚Â³n social
     * @return array|null Datos de la vinculaciÃƒÆ’Ã‚Â³n social o null si no existe
     */
    public static function getSocialLoginStandard(int $id): ?array
    {
        $instance = new self();

        try {
            $result = $instance->findById($id);

            if ($result) {
                $instance->logDebug('VinculaciÃƒÆ’Ã‚Â³n social obtenida', ['id' => $id]);
            }

            return $result;
        } catch (\Exception $e) {
            $instance->logError('Error al obtener vinculaciÃƒÆ’Ã‚Â³n social', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar vinculaciÃƒÆ’Ã‚Â³n social (mÃƒÆ’Ã‚Â©todo estÃƒÆ’Ã‚Â¡ndar)
     *
     * @param int $id ID de la vinculaciÃƒÆ’Ã‚Â³n social
     * @param array $data Datos a actualizar
     * @return bool ÃƒÆ’Ã¢â‚¬Â°xito de la operaciÃƒÆ’Ã‚Â³n
     */
    public static function updateSocialLoginStandard(int $id, array $data): bool
    {
        $instance = new self();

        // Validar datos para actualizaciÃƒÆ’Ã‚Â³n
        $validation = self::validateSocialLoginData($data, true);
        if (!$validation['valid']) {
            $instance->logError('Datos invÃƒÆ’Ã‚Â¡lidos para actualizar vinculaciÃƒÆ’Ã‚Â³n social', [
                'id' => $id,
                'errors' => $validation['errors'],
                'data' => $data
            ]);
            return false;
        }

        try {
            // Filtrar solo los campos permitidos por $fillable
            $filtered = [];
            foreach ($instance->fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }
            $result = $instance->update($id, $filtered);

            if ($result) {
                $instance->logDebug('VinculaciÃƒÆ’Ã‚Â³n social actualizada exitosamente', [
                    'id' => $id,
                    'data' => $filtered
                ]);

                // Invalidar cache relacionado
                $instance->invalidateModelCache();
            }

            return $result;
        } catch (\Exception $e) {
            $instance->logError('Error al actualizar vinculaciÃƒÆ’Ã‚Â³n social', [
                'id' => $id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Eliminar vinculaciÃƒÆ’Ã‚Â³n social (mÃƒÆ’Ã‚Â©todo estÃƒÆ’Ã‚Â¡ndar)
     *
     * @param int $id ID de la vinculaciÃƒÆ’Ã‚Â³n social
     * @return bool ÃƒÆ’Ã¢â‚¬Â°xito de la operaciÃƒÆ’Ã‚Â³n
     */
    public static function deleteSocialLoginStandard(int $id): bool
    {
        $instance = new self();

        try {
            $result = $instance->delete($id);

            if ($result) {
                $instance->logDebug('VinculaciÃƒÆ’Ã‚Â³n social eliminada exitosamente', ['id' => $id]);

                // Invalidar cache relacionado
                $instance->invalidateModelCache();
            }

            return $result;
        } catch (\Exception $e) {
            $instance->logError('Error al eliminar vinculaciÃƒÆ’Ã‚Â³n social', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar vinculaciones sociales (mÃƒÆ’Ã‚Â©todo estÃƒÆ’Ã‚Â¡ndar)
     *
     * @param array $criteria Criterios de bÃƒÆ’Ã‚Âºsqueda
     * @param int $limit LÃƒÆ’Ã‚Â­mite de resultados (0 = sin lÃƒÆ’Ã‚Â­mite)
     * @param int $offset Desplazamiento para paginaciÃƒÆ’Ã‚Â³n
     * @return array Lista de vinculaciones sociales
     */
    public static function searchSocialLoginsStandard(array $criteria = [], int $limit = 0, int $offset = 0): array
    {
        $instance = new self();

        try {
            $result = $instance->findAll($criteria, 1, $limit);

            $instance->logDebug('BÃƒÆ’Ã‚Âºsqueda de vinculaciones sociales realizada', [
                'criteria' => $criteria,
                'limit' => $limit,
                'offset' => $offset,
                'results_count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $instance->logError('Error en bÃƒÆ’Ã‚Âºsqueda de vinculaciones sociales', [
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar vinculaciones sociales (mÃƒÆ’Ã‚Â©todo estÃƒÆ’Ã‚Â¡ndar)
     *
     * @param array $criteria Criterios de conteo
     * @return int NÃƒÆ’Ã‚Âºmero de vinculaciones sociales que coinciden con los criterios
     */
    public static function countSocialLoginsStandard(array $criteria = []): int
    {
        $instance = new self();

        try {
            $result = $instance->countAll($criteria);

            $instance->logDebug('Conteo de vinculaciones sociales realizado', [
                'criteria' => $criteria,
                'count' => $result
            ]);

            return $result;
        } catch (\Exception $e) {
            $instance->logError('Error en conteo de vinculaciones sociales', [
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Invalidar cache del modelo
     *
     * @return void
     */
    protected function invalidateModelCache(): void
    {
        // Limpiar cache especÃƒÆ’Ã‚Â­fico del modelo usando mÃƒÆ’Ã‚Â©todo de BaseModel
        $this->invalidateCache();
    }
}
