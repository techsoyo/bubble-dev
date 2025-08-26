<?php declare(strict_types=1);
namespace Models;

use Utils\Logger;

/**
 * Modelo para las notificaciones enviadas a los candidatos.
 * 
 * Gestiona las notificaciones del sistema con soporte para diferentes tipos,
 * estados, cache optimizado y funcionalidades especÃƒÆ’Ã‚Â­ficas de notificaciones.
 * 
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class Notification extends BaseModel
{
    protected string $table = 'notifications';
    /*
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â§ CORRECCIÃƒÆ’Ã¢â‚¬Å“N AUTOMÃƒÆ’Ã‚ÂTICA APLICADA
     * Modelo: Notification
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ÃƒÂ¢Ã…Â¾Ã¢â‚¬Â¢ Campos aÃƒÆ’Ã‚Â±adidos: ['title', 'is_read', 'data']
     * ÃƒÂ¢Ã‚ÂÃ…â€™ Campos removidos: ['status', 'sent_at', 'email_to', 'subject', 'error_message']
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  Total campos fillable: 6
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */
    // Corresponde a bt_notifications en BD

    protected array $fillable = [
        'candidate_id',
        'type',
        'title',
        'message',
        'is_read',
        'data',
    ];

    protected array $hidden = [
        'error_message',
        'internal_data'
    ];

    /**
     * Tipos de notificaciÃƒÆ’Ã‚Â³n vÃƒÆ’Ã‚Â¡lidos segÃƒÆ’Ã‚Âºn anÃƒÆ’Ã‚Â¡lisis de BD
     */
    const VALID_TYPES = [
        'general',
        'assignment_notice',
        'status_change',
        'internal_routing',
        'interview_scheduled',
        'application_received',
        'document_request'
    ];

    /**
     * Estados vÃƒÆ’Ã‚Â¡lidos de notificaciÃƒÆ’Ã‚Â³n
     */
    const VALID_STATUSES = [
        'pending',
        'sent',
        'failed'
    ];

    /**
     * Cache TTL para notificaciones (5 minutos)
     */
    const CACHE_TTL = 300;

    /**
     * Obtener notificaciones no leÃƒÆ’Ã‚Â­das de un candidato con cache optimizado
     * 
     * @param string $candidateId ID del candidato
     * @param int $cacheTtl Tiempo de vida del cache en segundos
     * @return array Lista de notificaciones no leÃƒÆ’Ã‚Â­das
     */
    public function getUnreadNotifications(string $candidateId, int $cacheTtl = self::CACHE_TTL): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        $cacheKey = $this->buildNotificationCacheKey('unread_notifications', ['candidate_id' => $candidateId]);

        try {
            // Intentar obtener desde cache si estÃƒÆ’Ã‚Â¡ habilitado
            if ($cacheTtl > 0 && class_exists('\Utils\Cache')) {
                return \Utils\Cache::get($cacheKey, $cacheTtl, function () use ($candidateId) {
                    return $this->executeUnreadNotificationsQuery($candidateId);
                });
            }

            // Fallback sin cache
            return $this->executeUnreadNotificationsQuery($candidateId);
        } catch (\Exception $e) {
            $this->logError('Error getting unread notifications', [
                'candidate_id' => $candidateId
            ], $e);

            // Fallback directo a la query
            return $this->executeUnreadNotificationsQuery($candidateId);
        }
    }

    /**
     * Ejecutar query de notificaciones no leÃƒÆ’Ã‚Â­das
     * 
     * @param string $candidateId
     * @return array
     */
    private function executeUnreadNotificationsQuery(string $candidateId): array
    {
        $filters = [
            'candidate_id' => $candidateId,
            'status' => 'pending'
        ];

        $orderBy = ['created_at' => 'DESC'];

        return $this->findAll($filters, 1, 50, $orderBy);
    }

    /**
     * Marcar notificaciÃƒÆ’Ã‚Â³n como leÃƒÆ’Ã‚Â­da (enviada)
     * 
     * @param string $notificationId ID de la notificaciÃƒÆ’Ã‚Â³n
     * @return bool True si se marcÃƒÆ’Ã‚Â³ correctamente
     */
    public function markAsRead(string $notificationId): bool
    {
        if (empty($notificationId)) {
            throw new \InvalidArgumentException('Notification ID cannot be empty');
        }

        try {
            $data = [
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s')
            ];

            $result = $this->update($notificationId, $data);

            if ($result) {
                // Invalidar cache relacionado
                $this->invalidateNotificationCache($notificationId);

                $this->logDebug('Notification marked as read', [
                    'notification_id' => $notificationId
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error marking notification as read', [
                'notification_id' => $notificationId
            ], $e);
            return false;
        }
    }

    /**
     * Obtener notificaciones por tipo con cache
     * 
     * @param string $type Tipo de notificaciÃƒÆ’Ã‚Â³n
     * @param array $filters Filtros adicionales opcionales
     * @param int $limit LÃƒÆ’Ã‚Â­mite de resultados
     * @param int $cacheTtl Tiempo de vida del cache
     * @return array Lista de notificaciones del tipo especificado
     */
    public function getNotificationsByType(
        string $type,
        array $filters = [],
        int $limit = 50,
        int $cacheTtl = self::CACHE_TTL
    ): array {
        if (!$this->isValidNotificationType($type)) {
            throw new \InvalidArgumentException("Invalid notification type: $type");
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        $filters['type'] = $type;
        $cacheKey = $this->buildNotificationCacheKey('notifications_by_type', array_merge($filters, ['limit' => $limit]));

        try {
            // Intentar obtener desde cache si estÃƒÆ’Ã‚Â¡ habilitado
            if ($cacheTtl > 0 && class_exists('\Utils\Cache')) {
                return \Utils\Cache::get($cacheKey, $cacheTtl, function () use ($filters, $limit) {
                    return $this->executeNotificationsByTypeQuery($filters, $limit);
                });
            }

            // Fallback sin cache
            return $this->executeNotificationsByTypeQuery($filters, $limit);
        } catch (\Exception $e) {
            $this->logError('Error getting notifications by type', [
                'type' => $type,
                'filters' => $filters
            ], $e);

            // Fallback directo a la query
            return $this->executeNotificationsByTypeQuery($filters, $limit);
        }
    }

    /**
     * Ejecutar query de notificaciones por tipo
     * 
     * @param array $filters
     * @param int $limit
     * @return array
     */
    private function executeNotificationsByTypeQuery(array $filters, int $limit): array
    {
        $orderBy = ['created_at' => 'DESC'];
        return $this->findAll($filters, 1, $limit, $orderBy);
    }

    /**
     * Limpiar notificaciones antiguas y procesadas
     * 
     * @param int $daysOld DÃƒÆ’Ã‚Â­as de antigÃƒÆ’Ã‚Â¼edad para considerar como "antiguas" (por defecto 30)
     * @param array $statusesToClean Estados a limpiar (por defecto solo 'sent')
     * @return int NÃƒÆ’Ã‚Âºmero de notificaciones eliminadas
     */
    public function cleanupOldNotifications(int $daysOld = 30, array $statusesToClean = ['sent']): int
    {
        if ($daysOld < 1) {
            throw new \InvalidArgumentException('Days old must be greater than 0');
        }

        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

            // Preparar condiciones para mÃƒÆ’Ã‚Âºltiples estados
            $statusPlaceholders = implode(',', array_fill(0, count($statusesToClean), '?'));

            $query = "DELETE FROM `{$this->table}` 
                     WHERE `sent_at` < ? 
                     AND `status` IN ($statusPlaceholders)";

            $params = array_merge([$cutoffDate], $statusesToClean);

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $deletedCount = $stmt->rowCount();

            if ($deletedCount > 0) {
                // Invalidar cache despuÃƒÆ’Ã‚Â©s de limpieza
                $this->invalidateAllNotificationCache();
            }

            $this->logDebug('Old notifications cleanup completed', [
                'days_old' => $daysOld,
                'statuses_cleaned' => $statusesToClean,
                'deleted_count' => $deletedCount
            ]);

            return $deletedCount;
        } catch (\Exception $e) {
            $this->logError('Error cleaning up old notifications', [
                'days_old' => $daysOld,
                'statuses' => $statusesToClean
            ], $e);
            return 0;
        }
    }

    /**
     * Validar tipo de notificaciÃƒÆ’Ã‚Â³n
     * 
     * @param string $type
     * @return bool
     */
    private function isValidNotificationType(string $type): bool
    {
        return in_array($type, self::VALID_TYPES, true);
    }

    /**
     * Validar estado de notificaciÃƒÆ’Ã‚Â³n
     * 
     * @param string $status
     * @return bool
     */
    private function isValidNotificationStatus(string $status): bool
    {
        return in_array($status, self::VALID_STATUSES, true);
    }

    /**
     * Crear nueva notificaciÃƒÆ’Ã‚Â³n con validaciÃƒÆ’Ã‚Â³n
     * 
     * @param array $data Datos de la notificaciÃƒÆ’Ã‚Â³n
     * @return mixed ID de la nueva notificaciÃƒÆ’Ã‚Â³n o false
     */
    public function createNotification(array $data)
    {
        // Validar tipo si estÃƒÆ’Ã‚Â¡ presente
        if (isset($data['type']) && !$this->isValidNotificationType($data['type'])) {
            throw new \InvalidArgumentException("Invalid notification type: {$data['type']}");
        }

        // Validar estado si estÃƒÆ’Ã‚Â¡ presente
        if (isset($data['status']) && !$this->isValidNotificationStatus($data['status'])) {
            throw new \InvalidArgumentException("Invalid notification status: {$data['status']}");
        }

        // Asegurar valores por defecto
        if (!isset($data['type'])) {
            $data['type'] = 'general';
        }

        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }

        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        // Filtrar solo los campos permitidos por $fillable
        $filtered = [];
        foreach ($this->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        try {
            $notificationId = $this->store($filtered);

            if ($notificationId) {
                // Invalidar cache despuÃƒÆ’Ã‚Â©s de crear
                $this->invalidateNotificationCache();
            }

            return $notificationId;
        } catch (\Exception $e) {
            $this->logError('Error creating notification', ['data' => $data], $e);
            throw $e;
        }
    }

    /**
     * MÃƒÆ’Ã¢â‚¬Â°TODOS HEREDADOS DEL MODELO ORIGINAL - Mantener compatibilidad
     */

    /**
     * Devuelve todas las notificaciones de un candidato.
     *
     * @param string $candidateId ID del candidato
     * @return array Lista de notificaciones
     */
    public function findByCandidateId(string $candidateId): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        return $this->findAll(['candidate_id' => $candidateId], 1, self::MAX_LIMIT);
    }

    /**
     * Obtener estadÃƒÆ’Ã‚Â­sticas de notificaciones por candidato
     * 
     * @param string $candidateId
     * @return array EstadÃƒÆ’Ã‚Â­sticas (total, pending, sent, failed)
     */
    public function getNotificationStats(string $candidateId): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        try {
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                     FROM `{$this->table}` 
                     WHERE `candidate_id` = :candidate_id";

            $result = $this->query($query, [':candidate_id' => $candidateId]);

            return [
                'total' => (int)($result[0]['total'] ?? 0),
                'pending' => (int)($result[0]['pending'] ?? 0),
                'sent' => (int)($result[0]['sent'] ?? 0),
                'failed' => (int)($result[0]['failed'] ?? 0)
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting notification stats', [
                'candidate_id' => $candidateId
            ], $e);
            return ['total' => 0, 'pending' => 0, 'sent' => 0, 'failed' => 0];
        }
    }

    /**
     * Invalidar cache especÃƒÆ’Ã‚Â­fico de notificaciones
     * 
     * @param string|null $notificationId ID especÃƒÆ’Ã‚Â­fico de notificaciÃƒÆ’Ã‚Â³n (opcional)
     * @return int NÃƒÆ’Ã‚Âºmero de entradas de cache eliminadas
     */
    public function invalidateNotificationCache(?string $notificationId = null): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                $tags = ['notifications', 'unread_notifications', 'notifications_by_type'];

                if ($notificationId) {
                    $tags[] = "notification_{$notificationId}";
                }

                return \Utils\Cache::deleteByTags($tags);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating notification cache', [
                'notification_id' => $notificationId
            ], $e);
            return 0;
        }
    }

    /**
     * Invalidar todo el cache de notificaciones
     * 
     * @return int NÃƒÆ’Ã‚Âºmero de entradas de cache eliminadas
     */
    private function invalidateAllNotificationCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['notifications']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating all notification cache', [], $e);
            return 0;
        }
    }

    /**
     * Generar clave de cache para notificaciones (usa mÃƒÆ’Ã‚Â©todo de BaseModel)
     * 
     * @param string $operation Tipo de operaciÃƒÆ’Ã‚Â³n
     * @param array $params ParÃƒÆ’Ã‚Â¡metros para la clave
     * @return string Clave de cache
     */
    private function buildNotificationCacheKey(string $operation, array $params = []): string
    {
        $keyParts = ['notifications', $operation];

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $value = md5(serialize($value));
            }
            $keyParts[] = "{$key}:{$value}";
        }

        return implode(':', $keyParts);
    }

    // =====================================================
    // CRUD METHODS ESTÃƒÆ’Ã‚ÂNDAR - BaseModel Template v2.0.0
    // =====================================================

    /**
     * Crear nueva notificaciÃƒÆ’Ã‚Â³n con validaciones CRUD estÃƒÆ’Ã‚Â¡ndar
     *
     * @param array $data Datos de la notificaciÃƒÆ’Ã‚Â³n
     * @return int|false ID de la nueva notificaciÃƒÆ’Ã‚Â³n o false en caso de error
     * @throws InvalidArgumentException Si los datos no son vÃƒÆ’Ã‚Â¡lidos
     */
    public static function createNotificationStandard(array $data)
    {
        self::validateNotificationData($data);

        $notification = new self();
        // Filtrar solo los campos permitidos por $fillable
        $filtered = [];
        foreach ($notification->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }
        $id = $notification->store($filtered);

        if (!$id) {
            throw new \Exception('Error al crear la notificaciÃƒÆ’Ã‚Â³n');
        }

        self::invalidateNotificationCacheStandard();

        return $id;
    }

    /**
     * Obtener notificaciÃƒÆ’Ã‚Â³n por ID
     *
     * @param int $id ID de la notificaciÃƒÆ’Ã‚Â³n
     * @return array|null
     */
    public static function getNotificationStandard(int $id): ?array
    {
        try {
            $instance = new self();
            return $instance->findById($id);
        } catch (\Exception $e) {
            self::logError('Error al obtener notificaciÃƒÆ’Ã‚Â³n', ['id' => $id], $e);
            return null;
        }
    }

    /**
     * Actualizar notificaciÃƒÆ’Ã‚Â³n con validaciones
     *
     * @param int $id ID de la notificaciÃƒÆ’Ã‚Â³n
     * @param array $data Nuevos datos
     * @return bool
     * @throws InvalidArgumentException Si los datos no son vÃƒÆ’Ã‚Â¡lidos
     */
    public static function updateNotificationStandard(int $id, array $data): bool
    {
        self::validateNotificationData($data, true);

        $notification = new self();
        $existingNotification = $notification->findById($id);
        if (!$existingNotification) {
            throw new \InvalidArgumentException("NotificaciÃƒÆ’Ã‚Â³n con ID {$id} no encontrada");
        }

        // Filtrar solo los campos permitidos por $fillable
        $filtered = [];
        foreach ($notification->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }
        $success = $notification->update($id, $filtered);

        if ($success) {
            self::invalidateNotificationCacheStandard();
        }

        return $success;
    }

    /**
     * Eliminar notificaciÃƒÆ’Ã‚Â³n
     *
     * @param int $id ID de la notificaciÃƒÆ’Ã‚Â³n
     * @return bool
     */
    public static function deleteNotificationStandard(int $id): bool
    {
        try {
            $notification = new self();
            $existingNotification = $notification->findById($id);
            if (!$existingNotification) {
                return false;
            }

            $success = $notification->delete($id);

            if ($success) {
                self::invalidateNotificationCacheStandard();
            }

            return $success;
        } catch (\Exception $e) {
            self::logError('Error al eliminar notificaciÃƒÆ’Ã‚Â³n', ['id' => $id], $e);
            return false;
        }
    }

    /**
     * Buscar notificaciones
     *
     * @param array $criteria Criterios de bÃƒÆ’Ã‚Âºsqueda
     * @param int $limit LÃƒÆ’Ã‚Â­mite de resultados
     * @param int $offset Offset para paginaciÃƒÆ’Ã‚Â³n
     * @return array
     */
    public static function searchNotificationsStandard(array $criteria = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $query = "SELECT * FROM notifications WHERE 1=1";
            $params = [];

            // Filtro por candidate_id
            if (!empty($criteria['candidate_id'])) {
                $query .= " AND candidate_id = :candidate_id";
                $params['candidate_id'] = $criteria['candidate_id'];
            }

            // Filtro por tipo
            if (!empty($criteria['type'])) {
                $query .= " AND type = :type";
                $params['type'] = $criteria['type'];
            }

            // Filtro por estado de lectura
            if (isset($criteria['is_read'])) {
                $query .= " AND is_read = :is_read";
                $params['is_read'] = (bool)$criteria['is_read'];
            }

            // Filtro por tÃƒÆ’Ã‚Â­tulo
            if (!empty($criteria['title'])) {
                $query .= " AND title LIKE :title";
                $params['title'] = '%' . $criteria['title'] . '%';
            }

            $query .= " ORDER BY created_at DESC";
            $query .= " LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;

            $notification = new self();
            return $notification->query($query, $params);
        } catch (\Exception $e) {
            self::logError('Error en bÃƒÆ’Ã‚Âºsqueda de notificaciones', $criteria, $e);
            return [];
        }
    }

    /**
     * Contar notificaciones
     *
     * @param array $criteria Criterios de bÃƒÆ’Ã‚Âºsqueda
     * @return int
     */
    public static function countNotificationsStandard(array $criteria = []): int
    {
        try {
            $query = "SELECT COUNT(*) as total FROM notifications WHERE 1=1";
            $params = [];

            // Aplicar los mismos filtros que en searchNotifications
            if (!empty($criteria['candidate_id'])) {
                $query .= " AND candidate_id = :candidate_id";
                $params['candidate_id'] = $criteria['candidate_id'];
            }

            if (!empty($criteria['type'])) {
                $query .= " AND type = :type";
                $params['type'] = $criteria['type'];
            }

            if (isset($criteria['is_read'])) {
                $query .= " AND is_read = :is_read";
                $params['is_read'] = (bool)$criteria['is_read'];
            }

            if (!empty($criteria['title'])) {
                $query .= " AND title LIKE :title";
                $params['title'] = '%' . $criteria['title'] . '%';
            }

            $notification = new self();
            $result = $notification->query($query, $params);
            return $result[0]['total'] ?? 0;
        } catch (\Exception $e) {
            self::logError('Error al contar notificaciones', $criteria, $e);
            return 0;
        }
    }

    /**
     * Validar datos de notificaciÃƒÆ’Ã‚Â³n
     *
     * @param array $data Datos a validar
     * @param bool $isUpdate Si es una actualizaciÃƒÆ’Ã‚Â³n (permite campos opcionales)
     * @throws InvalidArgumentException Si los datos no son vÃƒÆ’Ã‚Â¡lidos
     */
    private static function validateNotificationData(array $data, bool $isUpdate = false): void
    {
        // candidate_id es requerido en creaciÃƒÆ’Ã‚Â³n
        if (!$isUpdate && empty($data['candidate_id'])) {
            throw new \InvalidArgumentException('El candidate_id es requerido');
        }

        if (isset($data['candidate_id']) && (!is_numeric($data['candidate_id']) || $data['candidate_id'] <= 0)) {
            throw new \InvalidArgumentException('El candidate_id debe ser un nÃƒÆ’Ã‚Âºmero entero positivo');
        }

        // type es requerido en creaciÃƒÆ’Ã‚Â³n
        if (!$isUpdate && empty($data['type'])) {
            throw new \InvalidArgumentException('El tipo es requerido');
        }

        if (isset($data['type'])) {
            if (!in_array($data['type'], self::VALID_TYPES)) {
                throw new \InvalidArgumentException('Tipo de notificaciÃƒÆ’Ã‚Â³n no vÃƒÆ’Ã‚Â¡lido: ' . $data['type']);
            }
        }

        // title es requerido en creaciÃƒÆ’Ã‚Â³n
        if (!$isUpdate && empty($data['title'])) {
            throw new \InvalidArgumentException('El tÃƒÆ’Ã‚Â­tulo es requerido');
        }

        if (isset($data['title'])) {
            if (!is_string($data['title']) || strlen(trim($data['title'])) < 1) {
                throw new \InvalidArgumentException('El tÃƒÆ’Ã‚Â­tulo no puede estar vacÃƒÆ’Ã‚Â­o');
            }
            if (strlen($data['title']) > 255) {
                throw new \InvalidArgumentException('El tÃƒÆ’Ã‚Â­tulo no puede exceder los 255 caracteres');
            }
        }

        // message es requerido en creaciÃƒÆ’Ã‚Â³n
        if (!$isUpdate && empty($data['message'])) {
            throw new \InvalidArgumentException('El mensaje es requerido');
        }

        if (isset($data['message'])) {
            if (!is_string($data['message']) || strlen(trim($data['message'])) < 1) {
                throw new \InvalidArgumentException('El mensaje no puede estar vacÃƒÆ’Ã‚Â­o');
            }
        }

        // Validar is_read
        if (isset($data['is_read']) && !is_bool($data['is_read'])) {
            throw new \InvalidArgumentException('is_read debe ser un booleano');
        }

        // Validar data (debe ser un array vÃƒÆ’Ã‚Â¡lido o JSON vÃƒÆ’Ã‚Â¡lido)
        if (isset($data['data'])) {
            if (is_string($data['data'])) {
                json_decode($data['data']);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('El campo data debe contener JSON vÃƒÆ’Ã‚Â¡lido');
                }
            } elseif (!is_array($data['data']) && !is_null($data['data'])) {
                throw new \InvalidArgumentException('El campo data debe ser un array o JSON vÃƒÆ’Ã‚Â¡lido');
            }
        }
    }

    /**
     * Invalidar cachÃƒÆ’Ã‚Â© relacionado con notificaciones (versiÃƒÆ’Ã‚Â³n estÃƒÆ’Ã‚Â¡tica)
     */
    private static function invalidateNotificationCacheStandard(): void
    {
        try {
            // Crear instancia temporal para acceder a mÃƒÆ’Ã‚Â©todos de instancia
            $tempInstance = new self();
            $tempInstance->invalidateAllNotificationCache();

            self::logDebug('CachÃƒÆ’Ã‚Â© de notificaciones invalidado');
        } catch (\Exception $e) {
            self::logError('Error al invalidar cachÃƒÆ’Ã‚Â© de notificaciones', [], $e);
        }
    }
}
