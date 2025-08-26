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
     * Basado en la estructura real de la tabla bt_application_notes
     *
     * @var array<string>
     */
    protected array $fillable = [
        'application_id',
        'note_idx',
        'note',
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

        // BaseModel ya maneja las excepciones de forma segura
        return $this->findAll(
            ['application_id' => $applicationId],
            1,
            self::MAX_LIMIT,
            ['note_idx' => 'ASC']
        );
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
        if (empty($applicationId) || empty(trim($note))) {
            throw new \InvalidArgumentException('Application ID and note content cannot be empty');
        }

        // Validar que la aplicación existe antes de crear la nota
        if (!$this->applicationExists($applicationId)) {
            throw new \RuntimeException('Application not found');
        }

        // Obtener el próximo índice secuencial
        $nextIdx = $this->getNextNoteIndex($applicationId);

        // Crear la nota con el índice calculado - BaseModel maneja excepciones
        return $this->store([
            'application_id' => $applicationId,
            'note_idx' => $nextIdx,
            'note' => trim($note)
        ]);
    }

    /**
     * Obtiene el próximo índice secuencial para una aplicación
     *
     * @param string $applicationId ID de la aplicación
     * @return int Próximo note_idx disponible
     */
    private function getNextNoteIndex(string $applicationId): int
    {
        $sql = "SELECT COALESCE(MAX(note_idx), 0) + 1 as next_idx 
                FROM `{$this->table}` 
                WHERE application_id = :application_id";

        $result = $this->query($sql, [':application_id' => $applicationId]);

        return (int)($result[0]['next_idx'] ?? 1);
    }

    /**
     * Valida que una aplicación existe en el sistema
     *
     * @param string $applicationId ID de la aplicación
     * @return bool True si la aplicación existe
     */
    private function applicationExists(string $applicationId): bool
    {
        $sql = "SELECT 1 FROM vw_applications_extended 
                WHERE application_id = :application_id LIMIT 1";

        $result = $this->query($sql, [':application_id' => $applicationId]);

        return !empty($result);
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
}
