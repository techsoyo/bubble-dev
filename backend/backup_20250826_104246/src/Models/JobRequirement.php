<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para los requisitos de las ofertas de trabajo.
 * 
 * Proporciona funcionalidad para gestionar los requisitos específicos
 * de cada puesto de trabajo, incluyendo clasificación por tipo,
 * prioridad y obligatoriedad.
 * 
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0 
 * @since 2025-08-23
 */
class JobRequirement extends BaseModel
{
    protected string $table = 'job_requirements';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: JobRequirement
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ['requirement']
     * ❌ Campos removidos: ['requirement_type', 'description', 'is_mandatory', 'priority']
     * 📊 Total campos fillable: 2
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */

    protected string $primaryKey = 'id';

    /**
     * Campos que se pueden asignar de forma masiva
     *
     * @var array<string>
     */
    protected array $fillable = [
        'job_id',
        'requirement',
    ];

    /**
     * Campos que deben ser ocultados en arrays/JSON
     *
     * @var array<string>
     */
    protected array $hidden = [
        'internal_notes'
    ];

    /**
     * Tipos de requisitos válidos
     */
    private const VALID_REQUIREMENT_TYPES = [
        'education',
        'experience',
        'skill',
        'certification',
        'language',
        'availability',
        'location',
        'other'
    ];

    /**
     * Niveles de prioridad válidos
     */
    private const VALID_PRIORITIES = [1, 2, 3, 4, 5];

    /**
     * TTL para cache en segundos (5 minutos)
     */
    private const CACHE_TTL = 300;

    /**
     * Obtiene todos los requisitos de un trabajo específico
     *
     * @param int|string $jobId ID del trabajo
     * @param bool $useCache Si usar cache para la consulta
     * @return array Lista de requisitos
     * @throws \InvalidArgumentException Si el ID del trabajo es inválido
     * @throws \RuntimeException Si falla la consulta
     */
    public function getRequirementsByJob($jobId, bool $useCache = true): array
    {
        if (empty($jobId)) {
            throw new \InvalidArgumentException('Job ID cannot be empty');
        }

        $cacheKey = "job_requirements_job_{$jobId}";

        try {
            if ($useCache && isset($this->cache[$cacheKey])) {
                $this->logDebug('Cache hit for job requirements', ['job_id' => $jobId]);
                return $this->cache[$cacheKey];
            }

            $result = $this->findAll(['job_id' => $jobId], 1, self::MAX_LIMIT, ['priority' => 'ASC']);

            if ($useCache) {
                $this->cache[$cacheKey] = $result;
            }

            $this->logDebug('Job requirements retrieved successfully', [
                'job_id' => $jobId,
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error getting job requirements', ['job_id' => $jobId], $e);
            throw new \RuntimeException('Failed to get job requirements: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene solo los requisitos obligatorios de un trabajo
     *
     * @param int|string $jobId ID del trabajo
     * @param bool $useCache Si usar cache para la consulta
     * @return array Lista de requisitos obligatorios
     * @throws \InvalidArgumentException Si el ID del trabajo es inválido
     * @throws \RuntimeException Si falla la consulta
     */
    public function getMandatoryRequirements($jobId, bool $useCache = true): array
    {
        if (empty($jobId)) {
            throw new \InvalidArgumentException('Job ID cannot be empty');
        }

        $cacheKey = "mandatory_requirements_job_{$jobId}";

        try {
            if ($useCache && isset($this->cache[$cacheKey])) {
                $this->logDebug('Cache hit for mandatory requirements', ['job_id' => $jobId]);
                return $this->cache[$cacheKey];
            }

            $filters = [
                'job_id' => $jobId,
                'is_mandatory' => 1
            ];

            $result = $this->findAll($filters, 1, self::MAX_LIMIT, ['priority' => 'ASC']);

            if ($useCache) {
                $this->cache[$cacheKey] = $result;
            }

            $this->logDebug('Mandatory requirements retrieved successfully', [
                'job_id' => $jobId,
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error getting mandatory requirements', ['job_id' => $jobId], $e);
            throw new \RuntimeException('Failed to get mandatory requirements: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene requisitos de un trabajo ordenados por prioridad
     *
     * @param int|string $jobId ID del trabajo
     * @param string $order Dirección del ordenamiento ('ASC' o 'DESC')
     * @param bool $useCache Si usar cache para la consulta
     * @return array Lista de requisitos ordenados por prioridad
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si falla la consulta
     */
    public function getRequirementsByPriority($jobId, string $order = 'ASC', bool $useCache = true): array
    {
        if (empty($jobId)) {
            throw new \InvalidArgumentException('Job ID cannot be empty');
        }

        if (!in_array(strtoupper($order), ['ASC', 'DESC'])) {
            throw new \InvalidArgumentException('Order must be ASC or DESC');
        }

        $order = strtoupper($order);
        $cacheKey = "requirements_priority_job_{$jobId}_{$order}";

        try {
            if ($useCache && isset($this->cache[$cacheKey])) {
                $this->logDebug('Cache hit for requirements by priority', [
                    'job_id' => $jobId,
                    'order' => $order
                ]);
                return $this->cache[$cacheKey];
            }

            $result = $this->findAll(['job_id' => $jobId], 1, self::MAX_LIMIT, ['priority' => $order]);

            if ($useCache) {
                $this->cache[$cacheKey] = $result;
            }

            $this->logDebug('Requirements by priority retrieved successfully', [
                'job_id' => $jobId,
                'order' => $order,
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error getting requirements by priority', [
                'job_id' => $jobId,
                'order' => $order
            ], $e);
            throw new \RuntimeException('Failed to get requirements by priority: ' . $e->getMessage());
        }
    }

    /**
     * Verifica si un candidato cumple con los requisitos de un trabajo
     *
     * Esta función realiza una verificación básica basada en datos estructurados.
     * Para verificaciones más complejas, debería integrarse con la lógica de matching
     * del sistema.
     *
     * @param int|string $jobId ID del trabajo
     * @param int|string $candidateId ID del candidato  
     * @param bool $mandatoryOnly Si verificar solo requisitos obligatorios
     * @param bool $useCache Si usar cache para la consulta
     * @return array Resultado de la verificación con detalles
     * @throws \InvalidArgumentException Si los IDs son inválidos
     * @throws \RuntimeException Si falla la consulta
     */
    public function checkCandidateRequirements($jobId, $candidateId, bool $mandatoryOnly = false, bool $useCache = true): array
    {
        if (empty($jobId) || empty($candidateId)) {
            throw new \InvalidArgumentException('Job ID and Candidate ID cannot be empty');
        }

        $cacheKey = "candidate_check_{$candidateId}_job_{$jobId}_mandatory_{$mandatoryOnly}";

        try {
            if ($useCache && isset($this->cache[$cacheKey])) {
                $this->logDebug('Cache hit for candidate requirements check', [
                    'job_id' => $jobId,
                    'candidate_id' => $candidateId,
                    'mandatory_only' => $mandatoryOnly
                ]);
                return $this->cache[$cacheKey];
            }

            // Obtener requisitos del trabajo
            $requirements = $mandatoryOnly
                ? $this->getMandatoryRequirements($jobId, $useCache)
                : $this->getRequirementsByJob($jobId, $useCache);

            // Obtener datos del candidato (simplificado - en un sistema real se haría join complejo)
            $candidateModel = new \Models\Candidate();
            $candidate = $candidateModel->findById($candidateId);

            if (!$candidate) {
                throw new \RuntimeException("Candidate with ID {$candidateId} not found");
            }

            $totalRequirements = count($requirements);
            $metRequirements = 0;
            $requirementDetails = [];

            // Verificar cada requisito (lógica básica - expandir según necesidades)
            foreach ($requirements as $requirement) {
                $met = $this->checkSingleRequirement($requirement, $candidate);
                $metRequirements += $met ? 1 : 0;

                $requirementDetails[] = [
                    'id' => $requirement['id'],
                    'requirement_type' => $requirement['requirement_type'],
                    'description' => $requirement['description'],
                    'is_mandatory' => (bool)$requirement['is_mandatory'],
                    'priority' => (int)$requirement['priority'],
                    'met' => $met
                ];
            }

            $score = $totalRequirements > 0 ? round(($metRequirements / $totalRequirements) * 100, 2) : 0;

            $result = [
                'job_id' => $jobId,
                'candidate_id' => $candidateId,
                'total_requirements' => $totalRequirements,
                'met_requirements' => $metRequirements,
                'score_percentage' => $score,
                'all_mandatory_met' => $this->allMandatoryRequirementsMet($requirementDetails),
                'requirements_detail' => $requirementDetails,
                'checked_at' => date('Y-m-d H:i:s')
            ];

            if ($useCache) {
                $this->cache[$cacheKey] = $result;
            }

            $this->logDebug('Candidate requirements check completed', [
                'job_id' => $jobId,
                'candidate_id' => $candidateId,
                'score' => $score,
                'met_requirements' => $metRequirements,
                'total_requirements' => $totalRequirements
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error checking candidate requirements', [
                'job_id' => $jobId,
                'candidate_id' => $candidateId,
                'mandatory_only' => $mandatoryOnly
            ], $e);
            throw new \RuntimeException('Failed to check candidate requirements: ' . $e->getMessage());
        }
    }

    /**
     * Valida un tipo de requisito
     *
     * @param string $type Tipo de requisito a validar
     * @return bool True si el tipo es válido
     */
    public function validateRequirementType(string $type): bool
    {
        return in_array($type, self::VALID_REQUIREMENT_TYPES);
    }

    /**
     * Valida un nivel de prioridad
     *
     * @param int $priority Prioridad a validar
     * @return bool True si la prioridad es válida
     */
    public function validatePriority(int $priority): bool
    {
        return in_array($priority, self::VALID_PRIORITIES);
    }

    /**
     * Sobrescribir el método store para añadir validaciones
     *
     * @param array $data Datos del requisito
     * @return mixed ID del nuevo requisito o false
     * @throws \InvalidArgumentException Si los datos no son válidos
     */
    public function store(array $data)
    {
        // Validar tipo de requisito
        if (isset($data['requirement_type']) && !$this->validateRequirementType($data['requirement_type'])) {
            throw new \InvalidArgumentException(
                'Invalid requirement type. Valid types: ' . implode(', ', self::VALID_REQUIREMENT_TYPES)
            );
        }

        // Validar prioridad
        if (isset($data['priority']) && !$this->validatePriority((int)$data['priority'])) {
            throw new \InvalidArgumentException(
                'Invalid priority. Valid priorities: ' . implode(', ', self::VALID_PRIORITIES)
            );
        }

        // Validar campos requeridos
        if (empty($data['job_id']) || empty($data['description'])) {
            throw new \InvalidArgumentException('job_id and description are required fields');
        }

        // Limpiar cache relacionada
        $this->clearJobRequirementsCache($data['job_id']);

        return parent::store($data);
    }

    /**
     * Sobrescribir el método update para añadir validaciones
     *
     * @param mixed $id ID del requisito
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     * @throws \InvalidArgumentException Si los datos no son válidos
     */
    public function update($id, array $data): bool
    {
        // Validar tipo de requisito si se proporciona
        if (isset($data['requirement_type']) && !$this->validateRequirementType($data['requirement_type'])) {
            throw new \InvalidArgumentException(
                'Invalid requirement type. Valid types: ' . implode(', ', self::VALID_REQUIREMENT_TYPES)
            );
        }

        // Validar prioridad si se proporciona
        if (isset($data['priority']) && !$this->validatePriority((int)$data['priority'])) {
            throw new \InvalidArgumentException(
                'Invalid priority. Valid priorities: ' . implode(', ', self::VALID_PRIORITIES)
            );
        }

        // Obtener el requisito actual para limpiar cache
        $current = $this->findById($id);
        if ($current) {
            $this->clearJobRequirementsCache($current['job_id']);
        }

        return parent::update($id, $data);
    }

    /**
     * Sobrescribir el método delete para limpiar cache
     *
     * @param mixed $id ID del requisito a eliminar
     * @return bool True si se eliminó correctamente
     */
    public function delete($id): bool
    {
        // Obtener el requisito antes de eliminarlo para limpiar cache
        $requirement = $this->findById($id);
        if ($requirement) {
            $this->clearJobRequirementsCache($requirement['job_id']);
        }

        return parent::delete($id);
    }

    /**
     * Limpia el cache relacionado con los requisitos de un trabajo
     *
     * @param int|string $jobId ID del trabajo
     * @return void
     */
    private function clearJobRequirementsCache($jobId): void
    {
        $cacheKeys = [
            "job_requirements_job_{$jobId}",
            "mandatory_requirements_job_{$jobId}",
            "requirements_priority_job_{$jobId}_ASC",
            "requirements_priority_job_{$jobId}_DESC"
        ];

        foreach ($cacheKeys as $key) {
            unset($this->cache[$key]);
        }

        $this->logDebug('Job requirements cache cleared', ['job_id' => $jobId]);
    }

    /**
     * Método auxiliar para verificar un requisito individual
     * 
     * Esta es una implementación básica. En un sistema real, 
     * esto debería ser más sofisticado e integrado con el 
     * sistema de matching de candidatos.
     *
     * @param array $requirement Datos del requisito
     * @param array $candidate Datos del candidato
     * @return bool True si el candidato cumple el requisito
     */
    private function checkSingleRequirement(array $requirement, array $candidate): bool
    {
        $type = $requirement['requirement_type'] ?? '';
        $description = strtolower($requirement['description'] ?? '');

        switch ($type) {
            case 'education':
                // Verificación básica de educación (expandir según necesidades)
                return !empty($candidate['education_summary'] ?? '');

            case 'experience':
                // Verificación básica de experiencia
                return !empty($candidate['experience_summary'] ?? '');

            case 'skill':
                // Verificación básica de habilidades
                $skills = $candidate['soft_skills'] ?? [];
                $hardSkills = $candidate['hard_skills'] ?? [];

                if (is_string($skills)) {
                    $skills = json_decode($skills, true) ?? [];
                }
                if (is_string($hardSkills)) {
                    $hardSkills = json_decode($hardSkills, true) ?? [];
                }

                $allSkills = array_merge($skills, $hardSkills);
                $allSkillsText = strtolower(implode(' ', $allSkills));

                return strpos($allSkillsText, $description) !== false;

            case 'language':
                // Verificación básica de idiomas
                $languages = $candidate['languages_summary'] ?? '';
                return strpos(strtolower($languages), $description) !== false;

            case 'certification':
                // Verificación básica de certificaciones  
                $certifications = $candidate['certifications_summary'] ?? '';
                return strpos(strtolower($certifications), $description) !== false;

            case 'location':
                // Verificación básica de ubicación
                $location = strtolower($candidate['location'] ?? '');
                return strpos($location, $description) !== false;

            default:
                // Para tipos no reconocidos, retornar false por defecto
                return false;
        }
    }

    /**
     * Verifica si todos los requisitos obligatorios han sido cumplidos
     *
     * @param array $requirementDetails Detalles de la verificación de requisitos
     * @return bool True si todos los requisitos obligatorios están cumplidos
     */
    private function allMandatoryRequirementsMet(array $requirementDetails): bool
    {
        foreach ($requirementDetails as $detail) {
            if ($detail['is_mandatory'] && !$detail['met']) {
                return false;
            }
        }
        return true;
    }

    // Mantener métodos del modelo original para compatibilidad hacia atrás

    /**
     * Obtiene los requisitos de un puesto de trabajo concreto.
     * 
     * @deprecated Usar getRequirementsByJob() en su lugar
     * @param string $jobId ID del puesto
     * @return array Lista de requisitos
     */
    public function findByJobId(string $jobId): array
    {
        Logger::warning('Using deprecated method findByJobId. Use getRequirementsByJob instead.', [
            'job_id' => $jobId,
            'caller' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? []
        ]);

        try {
            return $this->getRequirementsByJob($jobId);
        } catch (\Exception $e) {
            Logger::error('Error in deprecated findByJobId method', ['job_id' => $jobId], $e);
            return [];
        }
    }

    /**
     * Obtiene tipos de requisitos válidos
     *
     * @return array Lista de tipos de requisitos válidos
     */
    public static function getValidRequirementTypes(): array
    {
        return self::VALID_REQUIREMENT_TYPES;
    }

    /**
     * Obtiene prioridades válidas
     *
     * @return array Lista de prioridades válidas
     */
    public static function getValidPriorities(): array
    {
        return self::VALID_PRIORITIES;
    }

    // =====================================================
    // CRUD METHODS ESTÁNDAR - BaseModel Template v2.0.0
    // =====================================================

    /**
     * Crear un nuevo requisito de trabajo
     *
     * @param array $data Datos del requisito
     * @return int|false ID del nuevo requisito o false en caso de error
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function createJobRequirement(array $data)
    {
        self::validateJobRequirementData($data);

        $jobRequirement = new self();
        // Filtrar solo los campos permitidos por $fillable
        $filteredData = [];
        foreach ($jobRequirement->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filteredData[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        $id = $jobRequirement->store($filteredData);

        if (!$id) {
            throw new \Exception('Error al crear el requisito de trabajo');
        }

        self::invalidateJobRequirementCache();

        return $id;
    }

    /**
     * Obtener un requisito de trabajo por ID
     *
     * @param int $id ID del requisito
     * @return array|null
     */
    public static function getJobRequirement(int $id): ?array
    {
        try {
            $instance = new self();
            return $instance->findById($id);
        } catch (\Exception $e) {
            self::logError('Error al obtener requisito de trabajo', ['id' => $id], $e);
            return null;
        }
    }

    /**
     * Actualizar un requisito de trabajo
     *
     * @param int $id ID del requisito
     * @param array $data Nuevos datos
     * @return bool
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function updateJobRequirement(int $id, array $data): bool
    {
        self::validateJobRequirementData($data, true);

        $jobRequirement = new self();
        $existingJob = $jobRequirement->findById($id);
        if (!$existingJob) {
            throw new \InvalidArgumentException("Requisito de trabajo con ID {$id} no encontrado");
        }

        // Filtrar solo los campos permitidos por $fillable
        $filteredData = [];
        foreach ($jobRequirement->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filteredData[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        $success = $jobRequirement->update($id, $filteredData);

        if ($success) {
            self::invalidateJobRequirementCache();
        }

        return $success;
    }

    /**
     * Eliminar un requisito de trabajo
     *
     * @param int $id ID del requisito
     * @return bool
     */
    public static function deleteJobRequirement(int $id): bool
    {
        try {
            $jobRequirement = new self();
            $existingJob = $jobRequirement->findById($id);
            if (!$existingJob) {
                return false;
            }

            $success = $jobRequirement->delete($id);

            if ($success) {
                self::invalidateJobRequirementCache();
            }

            return $success;
        } catch (\Exception $e) {
            self::logError('Error al eliminar requisito de trabajo', ['id' => $id], $e);
            return false;
        }
    }

    /**
     * Buscar requisitos de trabajo
     *
     * @param array $criteria Criterios de búsqueda
     * @param int $limit Límite de resultados
     * @param int $offset Offset para paginación
     * @return array
     */
    public static function searchJobRequirements(array $criteria = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $query = "SELECT * FROM job_requirements WHERE 1=1";
            $params = [];

            // Filtro por job_id
            if (!empty($criteria['job_id'])) {
                $query .= " AND job_id = :job_id";
                $params['job_id'] = $criteria['job_id'];
            }

            // Filtro por requisito (texto)
            if (!empty($criteria['requirement'])) {
                $query .= " AND requirement LIKE :requirement";
                $params['requirement'] = '%' . $criteria['requirement'] . '%';
            }

            // Filtro por tipo
            if (!empty($criteria['requirement_type'])) {
                $query .= " AND requirement_type = :requirement_type";
                $params['requirement_type'] = $criteria['requirement_type'];
            }

            // Filtro por prioridad
            if (!empty($criteria['priority'])) {
                $query .= " AND priority = :priority";
                $params['priority'] = $criteria['priority'];
            }

            // Filtro por estado activo
            if (isset($criteria['is_active'])) {
                $query .= " AND is_active = :is_active";
                $params['is_active'] = (bool)$criteria['is_active'];
            }

            $query .= " ORDER BY priority DESC, created_at DESC";
            $query .= " LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;

            return self::query($query, $params);
        } catch (\Exception $e) {
            self::logError('Error en búsqueda de requisitos de trabajo', $criteria, $e);
            return [];
        }
    }

    /**
     * Contar requisitos de trabajo
     *
     * @param array $criteria Criterios de búsqueda
     * @return int
     */
    public static function countJobRequirements(array $criteria = []): int
    {
        try {
            $query = "SELECT COUNT(*) as total FROM job_requirements WHERE 1=1";
            $params = [];

            // Aplicar los mismos filtros que en searchJobRequirements
            if (!empty($criteria['job_id'])) {
                $query .= " AND job_id = :job_id";
                $params['job_id'] = $criteria['job_id'];
            }

            if (!empty($criteria['requirement'])) {
                $query .= " AND requirement LIKE :requirement";
                $params['requirement'] = '%' . $criteria['requirement'] . '%';
            }

            if (!empty($criteria['requirement_type'])) {
                $query .= " AND requirement_type = :requirement_type";
                $params['requirement_type'] = $criteria['requirement_type'];
            }

            if (!empty($criteria['priority'])) {
                $query .= " AND priority = :priority";
                $params['priority'] = $criteria['priority'];
            }

            if (isset($criteria['is_active'])) {
                $query .= " AND is_active = :is_active";
                $params['is_active'] = (bool)$criteria['is_active'];
            }

            $result = self::query($query, $params);
            return $result[0]['total'] ?? 0;
        } catch (\Exception $e) {
            self::logError('Error al contar requisitos de trabajo', $criteria, $e);
            return 0;
        }
    }

    /**
     * Validar datos de requisito de trabajo
     *
     * @param array $data Datos a validar
     * @param bool $isUpdate Si es una actualización (permite campos opcionales)
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    private static function validateJobRequirementData(array $data, bool $isUpdate = false): void
    {
        // job_id es requerido en creación
        if (!$isUpdate && empty($data['job_id'])) {
            throw new \InvalidArgumentException('El job_id es requerido');
        }

        if (isset($data['job_id']) && (!is_numeric($data['job_id']) || $data['job_id'] <= 0)) {
            throw new \InvalidArgumentException('El job_id debe ser un número entero positivo');
        }

        // requirement es requerido en creación
        if (!$isUpdate && empty($data['requirement'])) {
            throw new \InvalidArgumentException('El requisito es requerido');
        }

        if (isset($data['requirement'])) {
            if (!is_string($data['requirement']) || strlen(trim($data['requirement'])) < 3) {
                throw new \InvalidArgumentException('El requisito debe tener al menos 3 caracteres');
            }
            if (strlen($data['requirement']) > 1000) {
                throw new \InvalidArgumentException('El requisito no puede exceder los 1000 caracteres');
            }
        }

        // Validar tipo de requisito
        if (isset($data['requirement_type'])) {
            if (!in_array($data['requirement_type'], self::VALID_REQUIREMENT_TYPES)) {
                throw new \InvalidArgumentException('Tipo de requisito no válido: ' . $data['requirement_type']);
            }
        }

        // Validar prioridad
        if (isset($data['priority'])) {
            if (!in_array($data['priority'], self::VALID_PRIORITIES)) {
                throw new \InvalidArgumentException('Prioridad no válida: ' . $data['priority']);
            }
        }

        // Validar is_active
        if (isset($data['is_active']) && !is_bool($data['is_active'])) {
            throw new \InvalidArgumentException('is_active debe ser un booleano');
        }
    }

    /**
     * Invalidar caché relacionado con requisitos de trabajo
     */
    private static function invalidateJobRequirementCache(): void
    {
        try {
            // Como los métodos CRUD son estáticos, necesitamos crear una instancia temporal
            $tempInstance = new self();

            // Limpiar todo el caché de la instancia
            $tempInstance->cache = [];

            self::logDebug('Caché de requisitos de trabajo invalidado');
        } catch (\Exception $e) {
            self::logError('Error al invalidar caché de requisitos de trabajo', [], $e);
        }
    }
}
