<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo Interview - Gestión de entrevistas programadas
 *
 * Modelo para gestionar las entrevistas del sistema de reclutamiento, 
 * incluyendo programación, seguimiento y métricas de carga.
 * Utiliza la vista vw_interviews_schedule para optimizar consultas complejas.
 *
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class Interview extends BaseModel
{
    protected string $table = 'interviews';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: Interview
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ['scheduled_datetime']
     * ❌ Campos removidos: ['scheduled_at', 'meeting_link', 'interview_type', 'interviewer_notes', 'candidate_feedback', 'technical_score', 'soft_skills_score', 'overall_rating', 'recommendation', 'follow_up_required', 'salary_discussion', 'internal_feedback']
     * 📊 Total campos fillable: 10
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */
    // Tabla bt_interviews en BD

    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'application_id',
        'interviewer_id',
        'scheduled_datetime',
        'duration_minutes',
        'type',
        'location',
        'status',
        'notes',
        'score',
        'feedback',
    ];

    /**
     * Campos que deben ocultarse en serialización
     */
    protected array $hidden = [
        'internal_feedback',
        'salary_discussion',
        'interviewer_notes'
    ];

    /**
     * MÉTODOS DE VISTAS - Funcionalidad específica de entrevistas
     */

    /**
     * Obtener la programación completa de entrevistas con información extendida
     */
    public function getInterviewSchedule(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        $sql = "SELECT * FROM vw_interviews_schedule";
        $params = [];

        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    $whereConditions[] = "`$field` = :filter_$field";
                    $params[":filter_$field"] = $value;
                }
            }
            if (!empty($whereConditions)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
            }
        }

        // Añadir ordenamiento por fecha de entrevista
        $sql .= ' ORDER BY scheduled_at ASC';

        // Añadir paginación
        $offset = ($page - 1) * $limit;
        $sql .= ' LIMIT :limit OFFSET :offset';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        try {
            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error getting interview schedule', [
                'filters' => $filters,
                'page' => $page,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to get interview schedule: ' . $e->getMessage());
        }
    }

    /**
     * Obtener entrevistas en un rango de fechas
     */
    public function getInterviewsByDateRange(string $startDate, string $endDate, array $filters = []): array
    {
        if (empty($startDate) || empty($endDate)) {
            throw new \InvalidArgumentException('Start date and end date are required');
        }

        // Validar formato de fechas
        if (!strtotime($startDate) || !strtotime($endDate)) {
            throw new \InvalidArgumentException('Invalid date format');
        }

        $sql = "SELECT * FROM vw_interviews_schedule 
                WHERE DATE(scheduled_at) BETWEEN :start_date AND :end_date";

        $params = [
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ];

        // Añadir filtros adicionales
        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    $whereConditions[] = "`$field` = :filter_$field";
                    $params[":filter_$field"] = $value;
                }
            }
            if (!empty($whereConditions)) {
                $sql .= ' AND ' . implode(' AND ', $whereConditions);
            }
        }

        $sql .= ' ORDER BY scheduled_at ASC';

        try {
            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error getting interviews by date range', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters' => $filters
            ], $e);
            throw new \RuntimeException('Failed to get interviews by date range: ' . $e->getMessage());
        }
    }

    /**
     * Obtener carga de trabajo del entrevistador
     */
    public function getInterviewerLoad(string $interviewerId = null, int $days = 30): array
    {
        $sql = "SELECT 
                    interviewer_id,
                    COUNT(*) as total_interviews,
                    COUNT(CASE WHEN interview_status = 'scheduled' THEN 1 END) as scheduled_interviews,
                    COUNT(CASE WHEN interview_status = 'completed' THEN 1 END) as completed_interviews,
                    COUNT(CASE WHEN interview_status = 'cancelled' THEN 1 END) as cancelled_interviews,
                    AVG(duration_minutes) as avg_duration,
                    DATE(scheduled_at) as interview_date
                FROM vw_interviews_schedule 
                WHERE DATE(scheduled_at) >= DATE_SUB(CURDATE(), INTERVAL :days DAY)";

        $params = [':days' => $days];

        if (!empty($interviewerId)) {
            $sql .= ' AND interviewer_id = :interviewer_id';
            $params[':interviewer_id'] = $interviewerId;
        }

        $sql .= ' GROUP BY interviewer_id, DATE(scheduled_at)
                  ORDER BY interview_date DESC, total_interviews DESC';

        try {
            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error getting interviewer load', [
                'interviewer_id' => $interviewerId,
                'days' => $days
            ], $e);
            throw new \RuntimeException('Failed to get interviewer load: ' . $e->getMessage());
        }
    }

    /**
     * Obtener próximas entrevistas con cache
     */
    public function getUpcomingInterviews(int $days = 7, array $filters = [], int $cacheTtl = 300): array
    {
        $cacheKey = $this->generateCacheKey('upcoming_interviews', array_merge($filters, ['days' => $days]));

        try {
            // Intentar obtener desde cache si está habilitado
            if ($cacheTtl > 0 && class_exists('\Utils\Cache')) {
                return \Utils\Cache::get($cacheKey, $cacheTtl, function () use ($days, $filters) {
                    return $this->executeUpcomingInterviewsQuery($days, $filters);
                });
            }

            // Fallback sin cache
            return $this->executeUpcomingInterviewsQuery($days, $filters);
        } catch (\Exception $e) {
            $this->logError('Error getting upcoming interviews', [
                'days' => $days,
                'filters' => $filters
            ], $e);
            // Fallback directo a la query
            return $this->executeUpcomingInterviewsQuery($days, $filters);
        }
    }

    /**
     * Método auxiliar para ejecutar la query de próximas entrevistas
     */
    private function executeUpcomingInterviewsQuery(int $days, array $filters): array
    {
        $sql = "SELECT * FROM vw_interviews_schedule 
                WHERE DATE(scheduled_at) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
                AND interview_status IN ('scheduled', 'confirmed')";

        $params = [':days' => $days];

        // Añadir filtros adicionales
        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    $whereConditions[] = "`$field` = :filter_$field";
                    $params[":filter_$field"] = $value;
                }
            }
            if (!empty($whereConditions)) {
                $sql .= ' AND ' . implode(' AND ', $whereConditions);
            }
        }

        $sql .= ' ORDER BY scheduled_at ASC';

        return $this->query($sql, $params);
    }

    /**
     * Obtener estadísticas de entrevistas por estatus
     */
    public function getInterviewStats(array $filters = [], int $cacheTtl = 600): array
    {
        $cacheKey = $this->generateCacheKey('interview_stats', $filters);

        try {
            if ($cacheTtl > 0 && class_exists('\Utils\Cache')) {
                return \Utils\Cache::get($cacheKey, $cacheTtl, function () use ($filters) {
                    return $this->executeInterviewStatsQuery($filters);
                });
            }

            return $this->executeInterviewStatsQuery($filters);
        } catch (\Exception $e) {
            $this->logError('Error getting interview stats', ['filters' => $filters], $e);
            return $this->executeInterviewStatsQuery($filters);
        }
    }

    /**
     * Método auxiliar para ejecutar estadísticas de entrevistas
     */
    private function executeInterviewStatsQuery(array $filters): array
    {
        $sql = "SELECT 
                    interview_status,
                    COUNT(*) as count,
                    AVG(duration_minutes) as avg_duration,
                    interview_type,
                    DATE_FORMAT(scheduled_at, '%Y-%m') as month_year
                FROM vw_interviews_schedule";

        $params = [];

        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    $whereConditions[] = "`$field` = :filter_$field";
                    $params[":filter_$field"] = $value;
                }
            }
            if (!empty($whereConditions)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
            }
        }

        $sql .= ' GROUP BY interview_status, interview_type, month_year
                  ORDER BY month_year DESC, interview_status';

        return $this->query($sql, $params);
    }

    /**
     * MÉTODOS HEREDADOS CON FUNCIONALIDAD ESPECÍFICA
     */

    /**
     * Devuelve todas las entrevistas para una solicitud concreta
     * (Método original mantenido)
     */
    public function findByApplicationId(string $applicationId): array
    {
        if (empty($applicationId)) {
            throw new \InvalidArgumentException('Application ID cannot be empty');
        }

        try {
            return $this->findAll(['application_id' => $applicationId], 1, self::MAX_LIMIT);
        } catch (\Exception $e) {
            $this->logError('Error finding interviews by application ID', [
                'application_id' => $applicationId
            ], $e);
            throw new \RuntimeException('Failed to find interviews by application ID: ' . $e->getMessage());
        }
    }

    /**
     * Buscar entrevistas por entrevistador
     */
    public function findByInterviewerId(string $interviewerId, array $statusFilter = []): array
    {
        if (empty($interviewerId)) {
            throw new \InvalidArgumentException('Interviewer ID cannot be empty');
        }

        $filters = ['interviewer_id' => $interviewerId];

        if (!empty($statusFilter)) {
            // Para filtros complejos usar query directa
            $sql = "SELECT * FROM vw_interviews_schedule 
                    WHERE interviewer_id = :interviewer_id";

            $params = [':interviewer_id' => $interviewerId];

            if (!empty($statusFilter)) {
                $statusPlaceholders = [];
                foreach ($statusFilter as $i => $status) {
                    $key = ":status_$i";
                    $statusPlaceholders[] = $key;
                    $params[$key] = $status;
                }
                $sql .= ' AND interview_status IN (' . implode(',', $statusPlaceholders) . ')';
            }

            $sql .= ' ORDER BY scheduled_at ASC';

            try {
                return $this->query($sql, $params);
            } catch (\Exception $e) {
                $this->logError('Error finding interviews by interviewer ID with status filter', [
                    'interviewer_id' => $interviewerId,
                    'status_filter' => $statusFilter
                ], $e);
                throw new \RuntimeException('Failed to find interviews by interviewer ID: ' . $e->getMessage());
            }
        }

        try {
            return $this->findAll($filters, 1, self::MAX_LIMIT);
        } catch (\Exception $e) {
            $this->logError('Error finding interviews by interviewer ID', [
                'interviewer_id' => $interviewerId
            ], $e);
            throw new \RuntimeException('Failed to find interviews by interviewer ID: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar estatus de entrevista con log de cambios
     */
    public function updateStatus(string $interviewId, string $newStatus, string $notes = null): bool
    {
        if (empty($interviewId) || empty($newStatus)) {
            throw new \InvalidArgumentException('Interview ID and new status are required');
        }

        $validStatuses = ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'rescheduled'];
        if (!in_array($newStatus, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid status provided');
        }

        $data = [
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        try {
            $result = $this->update($interviewId, $data);

            if ($result) {
                // Invalidar cache relacionado
                $this->invalidateInterviewCache();

                $this->logDebug('Interview status updated', [
                    'interview_id' => $interviewId,
                    'new_status' => $newStatus,
                    'notes' => $notes
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error updating interview status', [
                'interview_id' => $interviewId,
                'new_status' => $newStatus,
                'notes' => $notes
            ], $e);
            throw new \RuntimeException('Failed to update interview status: ' . $e->getMessage());
        }
    }

    /**
     * Programar nueva entrevista
     */
    public function scheduleInterview(array $data): mixed
    {
        // Validaciones específicas para entrevistas
        $requiredFields = ['application_id', 'interviewer_id', 'scheduled_at', 'duration_minutes'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field $field is required");
            }
        }

        // Establecer valores por defecto
        $data['status'] = $data['status'] ?? 'scheduled';
        $data['type'] = $data['type'] ?? 'technical';
        $data['created_at'] = date('Y-m-d H:i:s');

        try {
            $interviewId = $this->store($data);

            if ($interviewId) {
                // Invalidar cache relacionado
                $this->invalidateInterviewCache();

                $this->logDebug('New interview scheduled', [
                    'interview_id' => $interviewId,
                    'application_id' => $data['application_id'],
                    'scheduled_at' => $data['scheduled_at']
                ]);
            }

            return $interviewId;
        } catch (\Exception $e) {
            $this->logError('Error scheduling interview', ['data' => $data], $e);
            throw new \RuntimeException('Failed to schedule interview: ' . $e->getMessage());
        }
    }

    /**
     * Invalidar cache específico de entrevistas
     */
    public function invalidateInterviewCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags([
                    'interviews',
                    'upcoming_interviews',
                    'interview_stats',
                    'interview_schedule'
                ]);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating interview cache', [], $e);
            return 0;
        }
    }

    /**
     * MÉTODOS AUXILIARES
     */

    // ==========================================
    // MÉTODOS CRUD ENCAPSULADOS ESTÁNDAR
    // ==========================================

    /**
     * Crear nuevo culture con validaciones
     * @param array $data Datos del nuevo culture
     * @return mixed ID del nuevo culture o false en caso de error
     */
    public function createCulture(array $data): mixed
    {
        try {
            $this->validateCultureData($data);
            $id = $this->store($data);
            $this->invalidateCultureCache();

            Logger::info('Culture created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating culture', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener culture por ID
     * @param mixed $id ID del culture
     * @return array|null Datos del culture o null si no existe
     */
    public function getCulture($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving culture', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar culture con validaciones
     * @param mixed $id ID del culture a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualización fue exitosa
     */
    public function updateCulture($id, array $data): bool
    {
        try {
            $this->validateCultureData($data, $id);
            $result = $this->update($id, $data);

            if ($result) {
                $this->invalidateCultureCache();
                Logger::info('Culture updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating culture', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar culture con validaciones
     * @param mixed $id ID del culture a eliminar
     * @return bool True si la eliminación fue exitosa
     */
    public function deleteCulture($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateCultureCache();
                Logger::info('Culture deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting culture', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar cultures con filtros
     * @param array $filters Filtros de búsqueda
     * @param int $page Página actual
     * @param int $limit Registros por página
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de cultures
     */
    public function searchCultures(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching cultures', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de cultures con filtros
     * @param array $filters Filtros de búsqueda
     * @return int Número total de cultures
     */
    public function countCultures(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting cultures', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    // ==========================================
    // MÉTODOS DE VALIDACIÓN ESPECÍFICOS
    // ==========================================

    /**
     * Validar datos específicos de cultures
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualización (opcional)
     * @throws \InvalidArgumentException Si los datos no son válidos
     */
    private function validateCultureData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones específicas del modelo
    }

    /**
     * Invalidar cache específico de cultures
     */
    public function invalidateCultureCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['cultures', 'culture_core', 'culture_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating culture cache', [], $e);
            return 0;
        }
    }
}
