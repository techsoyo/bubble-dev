<?php declare(strict_types=1);

namespace Models\CandidateEducation.php\Models;

use Utils\Logger;

/**
 * Modelo CandidateEducation - GestiÃ³n de educaciÃ³n de candidatos
 *
 * Gestiona informaciÃ³n educativa de candidatos con validaciones de fechas,
 * cache de historiales educativos y mÃ©todos especializados para consultas
 * educativas complejas.
 *
 * @package Models
 * @version 2.0.0
 */
class CandidateEducation extends BaseModel
{
    protected string $table = 'candidate_education';

    /**
     * Campos asignables masivamente - Solo campos reales de la tabla
     */
    protected array $fillable = [
        'candidate_id',
        'degree',
        'field_of_study',
        'institution',
        'start_date',
        'end_date',
        'gpa'
    ];

    protected string $primaryKey = 'id';

    protected array $hidden = [
        'gpa' // InformaciÃ³n acadÃ©mica sensible
    ];

    /**
     * Cache para historiales educativos
     */
    private array $educationHistoryCache = [];

    /**
     * Buscar educaciÃ³n por candidato
     */
    public function findByCandidate(int $candidateId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE candidate_id = ? ORDER BY start_date DESC, end_date DESC";
        return $this->query($sql, [$candidateId]);
    }

    /**
     * Obtener historial educativo completo de un candidato con cache
     *
     * @param string|int $candidateId ID del candidato
     * @param bool $useCache Usar cache (default: true)
     * @param int $cacheTtl TTL del cache en segundos (default: 300)
     * @return array Historial educativo ordenado por fechas
     *
     * @throws \InvalidArgumentException Si el ID del candidato estÃ¡ vacÃ­o
     * @throws \RuntimeException Si falla la consulta
     */
    public function getCandidateEducationHistory($candidateId, bool $useCache = true, int $cacheTtl = 300): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        $cacheKey = "education_history_{$candidateId}";

        // Intentar obtener desde cache interno primero
        if ($useCache && isset($this->educationHistoryCache[$cacheKey])) {
            return $this->educationHistoryCache[$cacheKey];
        }

        $sql = "SELECT * FROM {$this->table} 
                WHERE candidate_id = ? 
                ORDER BY 
                    CASE WHEN end_date IS NULL THEN 0 ELSE 1 END,
                    COALESCE(end_date, start_date) DESC,
                    start_date DESC";

        $results = $this->query($sql, [$candidateId]);

        // Procesar datos para mejorar la informaciÃ³n
        $processedResults = array_map(function ($education) {
            // Calcular duraciÃ³n si hay fechas
            if ($education['start_date'] && $education['end_date']) {
                $start = new \DateTime($education['start_date']);
                $end = new \DateTime($education['end_date']);
                $education['duration_years'] = $start->diff($end)->y;
            }

            // Marcar educaciÃ³n actual si no tiene fecha de fin
            if (empty($education['end_date']) && !empty($education['start_date'])) {
                $education['is_current'] = true;
            }

            return $education;
        }, $results);

        // Guardar en cache interno
        if ($useCache) {
            $this->educationHistoryCache[$cacheKey] = $processedResults;
        }

        // Intentar cache externo si estÃ¡ disponible
        if ($useCache && $cacheTtl > 0 && class_exists('\Utils\Cache')) {
            try {
                \Utils\Cache::set($cacheKey, $processedResults, $cacheTtl);
            } catch (\Exception $e) {
                Logger::warning('Failed to set education history cache', [
                    'candidate_id' => $candidateId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Logger::debug('Education history retrieved successfully', [
            'candidate_id' => $candidateId,
            'count' => count($processedResults)
        ]);

        return $processedResults;
    }
    /**
     * Obtener educaciÃ³n actual del candidato
     *
     * @param string|int $candidateId ID del candidato
     * @return array|null EducaciÃ³n actual o null si no tiene
     */

    public function getCurrentEducation($candidateId): ?array  
      {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        $sql = "SELECT * FROM {$this->table} 
                WHERE candidate_id = ? 
                AND (end_date IS NULL OR end_date > CURDATE())
                ORDER BY start_date DESC
                LIMIT 1";

        $results = $this->query($sql, [$candidateId]);
        return $results[0] ?? null;
    }

    /**
     * Obtener educaciÃ³n por nivel especÃ­fico
     *
     * @param string|int $candidateId ID del candidato
     * @param string $level Nivel educativo (bachelor, master, phd, etc.)
     * @return array EducaciÃ³n del nivel especificado
     */
    public function getEducationByLevel($candidateId, string $level): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        if (empty($level)) {
            throw new \InvalidArgumentException('Education level cannot be empty');
        }

        $sql = "SELECT * FROM {$this->table} 
                WHERE candidate_id = ? 
                AND degree LIKE ?
                ORDER BY start_date DESC";

        $results = $this->query($sql, [$candidateId, "%{$level}%"]);

        Logger::debug('Education by level retrieved', [
            'candidate_id' => $candidateId,
            'level' => $level,
            'count' => count($results)
        ]);

        return $results;
    }

    /**
     * Validar rango de fechas de educaciÃ³n
     *
     * @param string|null $startDate Fecha de inicio
     * @param string|null $endDate Fecha de fin
     * @param bool $isCurrent Es educaciÃ³n actual
     * @return array Array con 'valid' (bool) y 'errors' (array)
     */
    public function validateDateRange(?string $startDate, ?string $endDate, bool $isCurrent = false): array
    {
        $errors = [];
        $valid = true;

        // Validar formato de fechas
        if ($startDate && !$this->isValidDate($startDate)) {
            $errors[] = 'Invalid start date format';
            $valid = false;
        }

        if ($endDate && !$this->isValidDate($endDate)) {
            $errors[] = 'Invalid end date format';
            $valid = false;
        }

        // Si ambas fechas son vÃ¡lidas, validar lÃ³gica
        if ($startDate && $endDate && $this->isValidDate($startDate) && $this->isValidDate($endDate)) {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);

            // La fecha de fin debe ser posterior a la de inicio
            if ($end <= $start) {
                $errors[] = 'End date must be after start date';
                $valid = false;
            }

            // Validar que no sean fechas futuras irreales
            $today = new \DateTime();
            if ($end > $today->add(new \DateInterval('P10Y'))) {
                $errors[] = 'End date cannot be more than 10 years in the future';
                $valid = false;
            }
        }

        // Si es educaciÃ³n actual, no debe tener fecha de fin
        if ($isCurrent && $endDate) {
            $errors[] = 'Current education cannot have an end date';
            $valid = false;
        }

        // Si no es actual, deberÃ­a tener fecha de fin
        if (!$isCurrent && !$endDate && $startDate) {
            // Solo advertencia, no error crÃ­tico
            Logger::info('Education without end date but not marked as current', [
                'start_date' => $startDate
            ]);
        }

        return [
            'valid' => $valid,
            'errors' => $errors
        ];
    }

    /**
     * Validar formato de fecha
     */
    private function isValidDate(string $date): bool
    {
        $formats = ['Y-m-d', 'Y-m-d H:i:s'];

        foreach ($formats as $format) {
            $d = \DateTime::createFromFormat($format, $date);
            if ($d && $d->format($format) === $date) {
                return true;
            }
        }

        return false;
    }

    /**
     * Limpiar cache de historial educativo
     *
     * @param string|int|null $candidateId ID especÃ­fico o null para limpiar todo
     * @return int NÃºmero de entradas eliminadas del cache
     */
    public function clearEducationHistoryCache($candidateId = null): int
    {
        $cleared = 0;

        if ($candidateId !== null) {
            $cacheKey = "education_history_{$candidateId}";
            if (isset($this->educationHistoryCache[$cacheKey])) {
                unset($this->educationHistoryCache[$cacheKey]);
                $cleared++;
            }

            // Limpiar cache externo si estÃ¡ disponible
            if (class_exists('\Utils\Cache')) {
                try {
                    \Utils\Cache::delete($cacheKey);
                } catch (\Exception $e) {
                    Logger::warning('Failed to clear external education cache', [
                        'candidate_id' => $candidateId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } else {
            // Limpiar todo el cache interno
            $cleared = count($this->educationHistoryCache);
            $this->educationHistoryCache = [];

            // Limpiar cache externo por tags si estÃ¡ disponible
            if (class_exists('\Utils\Cache')) {
                try {
                    \Utils\Cache::deleteByTags(['candidate_education', 'education_history']);
                } catch (\Exception $e) {
                    Logger::warning('Failed to clear external education cache by tags', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        Logger::debug('Education cache cleared', [
            'candidate_id' => $candidateId,
            'cleared_count' => $cleared
        ]);

        return $cleared;
    }

    /**
     * Obtener estadÃ­sticas educativas por instituciÃ³n
     */
    public function getEducationStatsByInstitution(): array
    {
        $sql = "SELECT 
                    institution,
                    COUNT(*) as total_records,
                    COUNT(DISTINCT candidate_id) as unique_candidates,
                    AVG(CASE WHEN gpa IS NOT NULL THEN gpa END) as avg_gpa
                FROM {$this->table} 
                WHERE institution IS NOT NULL AND institution != ''
                GROUP BY institution 
                ORDER BY total_records DESC
                LIMIT 50";

        return $this->query($sql, []);
    }

    /**
     * Obtener candidatos con educaciÃ³n en progreso
     */
    public function getCandidatesWithCurrentEducation(): array
    {
        $sql = "SELECT DISTINCT candidate_id, institution, degree, field_of_study
                FROM {$this->table} 
                WHERE end_date IS NULL AND start_date IS NOT NULL
                ORDER BY start_date DESC";

        return $this->query($sql, []);
    }

    /**
     * Validar datos especÃ­ficos de candidate_educations
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualizaciÃ³n (opcional)
     * @throws \InvalidArgumentException Si los datos no son vÃ¡lidos
     */
    private function validateCandidateEducationData(array $data, $id = null): void
    {
        // Validaciones especÃ­ficas del modelo se mantienen aquÃ­
        // si son diferentes de las ya implementadas en BaseModel
    }

    /**
     * Invalidar cache especÃ­fico de candidate_educations
     */
    public function invalidateCandidateEducationCache(): int
    {
        if (class_exists('\Utils\Cache')) {
            return \Utils\Cache::deleteByTags(['candidate_educations', 'candidate_education_core', 'candidate_education_list']);
        }
        return 0;
    }
}
