<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para las notificaciones enviadas a los candidatos.
 * 
 * Gestiona las notificaciones del sistema con soporte para diferentes tipos,
 * estados, cache optimizado y funcionalidades específicas de notificaciones.
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
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: Notification
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ['title', 'is_read', 'data']
     * ❌ Campos removidos: ['status', 'sent_at', 'email_to', 'subject', 'error_message']
     * 📊 Total campos fillable: 6
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
     * Tipos de notificación válidos según análisis de BD
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
     * Estados válidos de notificación
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
     * Obtener notificaciones no leídas de un candidato con cache optimizado
     * 
     * @param string $candidateId ID del candidato
     * @param int $cacheTtl Tiempo de vida del cache en segundos
     * @return array Lista de notificaciones no leídas
     */
    public function getUnreadNotifications(string $candidateId, int $cacheTtl = self::CACHE_TTL): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        $cacheKey = $this->generateCacheKey('unread_notifications', ['candidate_id' => $candidateId]);

        try {
            // Intentar obtener desde cache si está habilitado
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
     * Ejecutar query de notificaciones no leídas
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
     * Marcar notificación como leída (enviada)
     * 
     * @param string $notificationId ID de la notificación
     * @return bool True si se marcó correctamente
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
     * @param string $type Tipo de notificación
     * @param array $filters Filtros adicionales opcionales
     * @param int $limit Límite de resultados
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
        $cacheKey = $this->generateCacheKey('notifications_by_type', array_merge($filters, ['limit' => $limit]));

        try {
            // Intentar obtener desde cache si está habilitado
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
     * @param int $daysOld Días de antigüedad para considerar como "antiguas" (por defecto 30)
     * @param array $statusesToClean Estados a limpiar (por defecto solo 'sent')
     * @return int Número de notificaciones eliminadas
     */
    public function cleanupOldNotifications(int $daysOld = 30, array $statusesToClean = ['sent']): int
    {
        if ($daysOld < 1) {
            throw new \InvalidArgumentException('Days old must be greater than 0');
        }

        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
            
            // Preparar condiciones para múltiples estados
            $statusPlaceholders = implode(',', array_fill(0, count($statusesToClean), '?'));
            
            $query = "DELETE FROM `{$this->table}` 
                     WHERE `sent_at` < ? 
                     AND `status` IN ($statusPlaceholders)";
            
            $params = array_merge([$cutoffDate], $statusesToClean);
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $deletedCount = $stmt->rowCount();

            if ($deletedCount > 0) {
                // Invalidar cache después de limpieza
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
     * Validar tipo de notificación
     * 
     * @param string $type
     * @return bool
     */
    private function isValidNotificationType(string $type): bool
    {
        return in_array($type, self::VALID_TYPES, true);
    }

    /**
     * Validar estado de notificación
     * 
     * @param string $status
     * @return bool
     */
    private function isValidNotificationStatus(string $status): bool
    {
        return in_array($status, self::VALID_STATUSES, true);
    }

    /**
     * Crear nueva notificación con validación
     * 
     * @param array $data Datos de la notificación
     * @return mixed ID de la nueva notificación o false
     */
    public function createNotification(array $data)
    {
        // Validar tipo si está presente
        if (isset($data['type']) && !$this->isValidNotificationType($data['type'])) {
            throw new \InvalidArgumentException("Invalid notification type: {$data['type']}");
        }

        // Validar estado si está presente
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

        try {
            $notificationId = $this->store($data);
            
            if ($notificationId) {
                // Invalidar cache después de crear
                $this->invalidateNotificationCache();
            }
            
            return $notificationId;
        } catch (\Exception $e) {
            $this->logError('Error creating notification', ['data' => $data], $e);
            throw $e;
        }
    }

    /**
     * MÉTODOS HEREDADOS DEL MODELO ORIGINAL - Mantener compatibilidad
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
     * Obtener estadísticas de notificaciones por candidato
     * 
     * @param string $candidateId
     * @return array Estadísticas (total, pending, sent, failed)
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
     * Invalidar cache específico de notificaciones
     * 
     * @param string|null $notificationId ID específico de notificación (opcional)
     * @return int Número de entradas de cache eliminadas
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
     * @return int Número de entradas de cache eliminadas
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
     * Generar clave de cache para notificaciones
     * 
     * @param string $operation Tipo de operación
     * @param array $params Parámetros para la clave
     * @return string Clave de cache
     */
    private function generateCacheKey(string $operation, array $params = []): string
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

    /**
     * Log de errores específico para notificaciones
     * 
     * @param string $message
     * @param array $context
     * @param \Exception|null $exception
     */
    private function logError(string $message, array $context = [], ?\Exception $exception = null): void
    {
        Logger::error($message, array_merge([
            'model' => static::class,
            'table' => $this->table
        ], $context, $exception ? ['exception' => $exception->getMessage()] : []));
    }

    /**
     * Log de debug específico para notificaciones
     * 
     * @param string $message
     * @param array $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        Logger::debug($message, array_merge([
            'model' => static::class,
            'table' => $this->table
        ], $context));
    }
}