<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para notas asociadas a solicitudes de empleo.
 *
 * Cada nota está ligada a una solicitud (application_id) y tiene
 * un índice incremental (note_idx) que permite múltiples notas por
 * aplicación. Extiende BaseModel para aprovechar las operaciones
 * genéricas de CRUD y los filtros seguros.
 *
 * Este modelo integra con vw_applications_extended para obtener
 * información contextual completa de las aplicaciones.
 *
 * @package Models
 * @author Bubble of Talents Development Team  
 * @version 2.0.0
 * @since 2025-08-23
 */
class ApplicationNote extends BaseModel
{
    /**
     * Nombre de la tabla sin prefijo
     *
     * @var string
     */
    protected string $table = 'application_notes';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: ApplicationNote
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ['author_id', 'is_internal']
     * ❌ Campos removidos: ['note_idx']
     * 📊 Total campos fillable: 4
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    /**
     * Clave primaria compuesta (application_id, note_idx)
     * Usamos application_id como clave lógica principal
     *
     * @var string
     */
    protected string $primaryKey = 'application_id';

    /**
     * Campos que pueden ser asignados masivamente
     *
     * @var array<string>
     */
    protected array $fillable = [
        'application_id',
        'author_id',
        'note',
        'is_internal',
        'note_idx',
        'resume',
        'cover_letter',
        'insights',
    ];

    /**
     * Campos ocultos en arrays/JSON
     * Las notas son internas, no hay campos específicos que ocultar
     *
     * @var array<string>
     */
    protected array $hidden = [];

    /**
     * MÉTODOS DE GESTIÓN DE NOTAS
     */

    /**
     * Obtiene todas las notas de una aplicación ordenadas por índice
     *
     * @param string $applicationId ID de la aplicación
     * @return array Lista de notas ordenadas por note_idx
     * @throws \InvalidArgumentException Si el ID está vacío
     * @throws \RuntimeException Si la consulta falla
     */
    public function findByApplicationId(string $applicationId): array
    {
        if (empty($applicationId)) {
            throw new \InvalidArgumentException('Application ID cannot be empty');
        }

        try {
            return $this->findAll(
                ['application_id' => $applicationId],
                1,
                self::MAX_LIMIT,
                ['note_idx' => 'ASC']
            );
        } catch (\Exception $e) {
            $this->logError('Error retrieving application notes', [
                'application_id' => $applicationId
            ], $e);
            throw new \RuntimeException('Failed to retrieve application notes: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene las notas con detalles completos de la aplicación usando vista extendida
     *
     * @param string $applicationId ID de la aplicación
     * @return array Notas con información extendida de aplicación, candidato y trabajo
     * @throws \InvalidArgumentException Si el ID está vacío
     * @throws \RuntimeException Si la consulta falla
     */
    public function getApplicationNotesWithDetails(string $applicationId): array
    {
        if (empty($applicationId)) {
            throw new \InvalidArgumentException('Application ID cannot be empty');
        }

        $sql = "SELECT n.*, 
               a.job_id, a.job_title, a.job_company, a.job_location,
               a.candidate_id, a.candidate_name, a.candidate_email,
               a.status as application_status, a.score, a.source,
               a.resume, a.cover_letter, a.insights,
               a.created_at as application_created_at,
               a.updated_at as application_updated_at
        FROM `{$this->table}` n
        INNER JOIN vw_applications_extended a ON a.application_id = n.application_id
        WHERE n.application_id = :application_id
        ORDER BY n.note_idx ASC";

        try {
            return $this->query($sql, [':application_id' => $applicationId]);
        } catch (\Exception $e) {
            $this->logError('Error getting application notes with details', [
                'application_id' => $applicationId
            ], $e);
            throw new \RuntimeException('Failed to get application notes with details: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene la línea de tiempo completa de notas para análisis
     *
     * @param string $applicationId ID de la aplicación
     * @return array Timeline de notas con información temporal
     * @throws \InvalidArgumentException Si el ID está vacío
     * @throws \RuntimeException Si la consulta falla
     */
    public function getNotesTimeline(string $applicationId): array
    {
        if (empty($applicationId)) {
            throw new \InvalidArgumentException('Application ID cannot be empty');
        }

        $sql = "SELECT n.note_idx,
                       n.note,
                       n.created_at as note_created_at,
                       n.updated_at as note_updated_at,
                       a.candidate_name,
                       a.job_title,
                       a.application_status,
                       CHAR_LENGTH(n.note) as note_length,
                       CASE 
                           WHEN n.created_at = n.updated_at THEN 'created'
                           ELSE 'updated'
                       END as action_type
                FROM `{$this->table}` n
                INNER JOIN vw_applications_extended a ON a.application_id = n.application_id  
                WHERE n.application_id = :application_id
                ORDER BY n.note_idx ASC, n.created_at ASC";

        try {
            return $this->query($sql, [':application_id' => $applicationId]);
        } catch (\Exception $e) {
            $this->logError('Error getting notes timeline', [
                'application_id' => $applicationId
            ], $e);
            throw new \RuntimeException('Failed to get notes timeline: ' . $e->getMessage());
        }
    }

    /**
     * Añade una nueva nota secuencial a una aplicación
     * 
     * Calcula automáticamente el próximo note_idx y crea la nota
     *
     * @param string $applicationId ID de la aplicación
     * @param string $note Contenido de la nota
     * @return mixed ID del registro creado
     * @throws \InvalidArgumentException Si los parámetros están vacíos
     * @throws \RuntimeException Si la operación falla
     */
    public function addSequentialNote(string $applicationId, string $note)
    {
        if (empty($applicationId)) {
            throw new \InvalidArgumentException('Application ID cannot be empty');
        }

        if (empty(trim($note))) {
            throw new \InvalidArgumentException('Note content cannot be empty');
        }

        try {
            // Obtener el próximo índice secuencial
            $nextIdx = $this->getNextNoteIndex($applicationId);

            // Validar que la aplicación existe antes de crear la nota
            if (!$this->applicationExists($applicationId)) {
                throw new \RuntimeException('Application not found');
            }

            // Crear la nota con el índice calculado
            $noteData = [
                'application_id' => $applicationId,
                'note_idx' => $nextIdx,
                'note' => trim($note)
            ];

            $result = $this->store($noteData);

            Logger::info('Sequential note added successfully', [
                'application_id' => $applicationId,
                'note_idx' => $nextIdx,
                'note_length' => strlen(trim($note))
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error adding sequential note', [
                'application_id' => $applicationId,
                'note_preview' => substr($note, 0, 50) . '...'
            ], $e);
            throw new \RuntimeException('Failed to add sequential note: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene el próximo índice secuencial para una aplicación
     *
     * @param string $applicationId ID de la aplicación
     * @return int Próximo note_idx disponible
     * @throws \RuntimeException Si la consulta falla
     */
    private function getNextNoteIndex(string $applicationId): int
    {
        try {
            $sql = "SELECT COALESCE(MAX(note_idx), 0) + 1 as next_idx 
                    FROM `{$this->table}` 
                    WHERE application_id = :application_id";

            $result = $this->query($sql, [':application_id' => $applicationId]);

            return (int)($result[0]['next_idx'] ?? 1);
        } catch (\Exception $e) {
            $this->logError('Error calculating next note index', [
                'application_id' => $applicationId
            ], $e);
            throw new \RuntimeException('Failed to calculate next note index: ' . $e->getMessage());
        }
    }

    /**
     * Valida que una aplicación existe en el sistema
     *
     * @param string $applicationId ID de la aplicación
     * @return bool True si la aplicación existe
     * @throws \RuntimeException Si la consulta falla
     */
    private function applicationExists(string $applicationId): bool
    {
        try {
            $sql = "SELECT 1 FROM vw_applications_extended 
                    WHERE application_id = :application_id LIMIT 1";

            $result = $this->query($sql, [':application_id' => $applicationId]);

            return !empty($result);
        } catch (\Exception $e) {
            $this->logError('Error checking application existence', [
                'application_id' => $applicationId
            ], $e);
            throw new \RuntimeException('Failed to validate application existence: ' . $e->getMessage());
        }
    }

    /**
     * MÉTODOS DE VALIDACIÓN Y UTILIDADES
     */

    /**
     * Valida que el note_idx sea secuencial para una aplicación
     *
     * @param string $applicationId ID de la aplicación
     * @param int $noteIdx Índice a validar
     * @return bool True si el índice es válido secuencialmente
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si la validación falla
     */
    public function validateSequentialIndex(string $applicationId, int $noteIdx): bool
    {
        if (empty($applicationId)) {
            throw new \InvalidArgumentException('Application ID cannot be empty');
        }

        if ($noteIdx < 1) {
            throw new \InvalidArgumentException('Note index must be 1 or greater');
        }

        try {
            $sql = "SELECT COUNT(*) as count 
                    FROM `{$this->table}` 
                    WHERE application_id = :application_id 
                    AND note_idx < :note_idx";

            $result = $this->query($sql, [
                ':application_id' => $applicationId,
                ':note_idx' => $noteIdx
            ]);

            $existingCount = (int)($result[0]['count'] ?? 0);

            // El índice es válido si hay exactamente (noteIdx - 1) notas anteriores
            return ($existingCount === ($noteIdx - 1));
        } catch (\Exception $e) {
            $this->logError('Error validating sequential index', [
                'application_id' => $applicationId,
                'note_idx' => $noteIdx
            ], $e);
            throw new \RuntimeException('Failed to validate sequential index: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene estadísticas de notas por aplicación
     *
     * @param string $applicationId ID de la aplicación  
     * @return array Estadísticas de las notas
     * @throws \InvalidArgumentException Si el ID está vacío
     * @throws \RuntimeException Si la consulta falla
     */
    public function getApplicationNotesStats(string $applicationId): array
    {
        if (empty($applicationId)) {
            throw new \InvalidArgumentException('Application ID cannot be empty');
        }

        $sql = "SELECT 
                    COUNT(*) as total_notes,
                    MAX(note_idx) as max_index,
                    MIN(CHAR_LENGTH(note)) as min_note_length,
                    MAX(CHAR_LENGTH(note)) as max_note_length,
                    AVG(CHAR_LENGTH(note)) as avg_note_length,
                    MIN(created_at) as first_note_date,
                    MAX(created_at) as last_note_date
                FROM `{$this->table}` 
                WHERE application_id = :application_id";

        try {
            $result = $this->query($sql, [':application_id' => $applicationId]);

            $stats = $result[0] ?? [];

            // Convertir valores numéricos y formatear fechas
            if (!empty($stats)) {
                $stats['total_notes'] = (int)$stats['total_notes'];
                $stats['max_index'] = (int)($stats['max_index'] ?? 0);
                $stats['min_note_length'] = (int)($stats['min_note_length'] ?? 0);
                $stats['max_note_length'] = (int)($stats['max_note_length'] ?? 0);
                $stats['avg_note_length'] = round((float)($stats['avg_note_length'] ?? 0), 2);
            }

            return $stats;
        } catch (\Exception $e) {
            $this->logError('Error getting application notes stats', [
                'application_id' => $applicationId
            ], $e);
            throw new \RuntimeException('Failed to get application notes statistics: ' . $e->getMessage());
        }
    }

    /**
     * Busca notas por contenido de texto
     *
     * @param string $searchTerm Término de búsqueda
     * @param string|null $applicationId ID específico de aplicación (opcional)
     * @param int $limit Límite de resultados
     * @return array Notas que coinciden con la búsqueda
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si la consulta falla
     */
    public function searchNotesByContent(string $searchTerm, ?string $applicationId = null, int $limit = 50): array
    {
        if (empty(trim($searchTerm))) {
            throw new \InvalidArgumentException('Search term cannot be empty');
        }

        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Limit must be between 1 and 500');
        }

        $sql = "SELECT n.*, 
                       a.candidate_name, a.job_title, a.status as application_status,
                       a.resume, a.cover_letter, a.insights,
                       MATCH(n.note) AGAINST(:search_term IN BOOLEAN MODE) as relevance_score
                FROM `{$this->table}` n
                INNER JOIN vw_applications_extended a ON a.application_id = n.application_id
                WHERE MATCH(n.note) AGAINST(:search_term IN BOOLEAN MODE)";

        $params = [':search_term' => $searchTerm];

        if ($applicationId !== null && !empty($applicationId)) {
            $sql .= " AND n.application_id = :application_id";
            $params[':application_id'] = $applicationId;
        }

        $sql .= " ORDER BY relevance_score DESC, n.created_at DESC LIMIT :limit";
        $params[':limit'] = $limit;

        try {
            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error searching notes by content', [
                'search_term' => $searchTerm,
                'application_id' => $applicationId,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to search notes by content: ' . $e->getMessage());
        }
    }

    /**
     * MÉTODOS LEGACY - Mantenidos para compatibilidad
     */

    /**
     * Método legacy mantenido para compatibilidad
     * 
     * @deprecated Usar findByApplicationId() en su lugar
     */
    public function findAll(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        // Si se filtra por application_id, usar el método optimizado
        if (isset($filters['application_id']) && count($filters) === 1) {
            return $this->findByApplicationId($filters['application_id']);
        }

        // Fallback al método padre para otros casos
        return parent::findAll($filters, $page, $limit, $orderBy);
    }

    // ==========================================
    // MÉTODOS CRUD ENCAPSULADOS ESTÁNDAR
    // ==========================================

    /**
     * Crear nuevo application_note con validaciones
     * @param array $data Datos del nuevo application_note
     * @return mixed ID del nuevo application_note o false en caso de error
     */
    public function createApplicationNote(array $data): mixed
    {
        try {
            $this->validateApplicationNoteData($data);
            $id = $this->store($data);
            $this->invalidateApplicationNoteCache();

            Logger::info('ApplicationNote created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating application_note', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener application_note por ID
     * @param mixed $id ID del application_note
     * @return array|null Datos del application_note o null si no existe
     */
    public function getApplicationNote($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving application_note', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar application_note con validaciones
     * @param mixed $id ID del application_note a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualización fue exitosa
     */
    public function updateApplicationNote($id, array $data): bool
    {
        try {
            $this->validateApplicationNoteData($data, $id);
            $result = $this->update($id, $data);

            if ($result) {
                $this->invalidateApplicationNoteCache();
                Logger::info('ApplicationNote updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating application_note', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar application_note con validaciones
     * @param mixed $id ID del application_note a eliminar
     * @return bool True si la eliminación fue exitosa
     */
    public function deleteApplicationNote($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateApplicationNoteCache();
                Logger::info('ApplicationNote deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting application_note', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar application_notes con filtros
     * @param array $filters Filtros de búsqueda
     * @param int $page Página actual
     * @param int $limit Registros por página
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de application_notes
     */
    public function searchApplicationNotes(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching application_notes', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de application_notes con filtros
     * @param array $filters Filtros de búsqueda
     * @return int Número total de application_notes
     */
    public function countApplicationNotes(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting application_notes', [
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
     * Validar datos específicos de application_notes
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualización (opcional)
     * @throws \InvalidArgumentException Si los datos no son válidos
     */
    private function validateApplicationNoteData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones específicas del modelo
    }

    /**
     * Invalidar cache específico de application_notes
     */
    public function invalidateApplicationNoteCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['application_notes', 'application_note_core', 'application_note_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating application_note cache', [], $e);
            return 0;
        }
    }
}
