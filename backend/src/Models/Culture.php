<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para los valores de cultura corporativa
 *
 * Gestiona los aspectos culturales de la organización, incluyendo
 * valores, características culturales, evaluación de ajuste cultural
 * y matching entre candidatos y cultura organizacional.
 *
 * @package Models
 * @version 2.0.0
 * @since 2025-08-23
 */
class Culture extends BaseModel
{
    /**
     * Nombre de la tabla asociada al modelo
     */
    protected string $table = 'cultures';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: Culture
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ninguno
     * ❌ Campos removidos: ['traits', 'department_id', 'status']
     * 📊 Total campos fillable: 3
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'name',
        'description',
        'values',
    ];

    /**
     * Campos que deben ocultarse en arrays/JSON
     */
    protected array $hidden = [
        'internal_notes'
    ];

    /**
     * Cache para datos culturales
     */
    private array $cultureCache = [];

    /**
     * TTL por defecto para cache (en segundos)
     */
    private const CACHE_TTL = 3600; // 1 hora

    /**
     * Obtener culturas organizacionales por departamento
     *
     * @param int $departmentId ID del departamento
     * @param bool $activeOnly Solo culturas activas
     * @return array Array de culturas del departamento
     *
     * @throws \InvalidArgumentException Si department_id es inválido
     * @throws \RuntimeException Si falla la operación de base de datos
     */
    public function getCulturesByDepartment(int $departmentId, bool $activeOnly = true): array
    {
        if ($departmentId <= 0) {
            throw new \InvalidArgumentException('Department ID must be greater than 0');
        }

        $cacheKey = "cultures_dept_{$departmentId}_" . ($activeOnly ? 'active' : 'all');

        // Verificar cache
        if (isset($this->cultureCache[$cacheKey])) {
            Logger::debug('Culture cache hit', ['cache_key' => $cacheKey]);
            return $this->cultureCache[$cacheKey];
        }

        try {
            $filters = ['department_id' => $departmentId];
            if ($activeOnly) {
                $filters['status'] = 'active';
            }

            $cultures = $this->findBy('department_id', $departmentId);

            if ($activeOnly) {
                $cultures = array_filter($cultures, fn($culture) => $culture['status'] === 'active');
            }

            // Procesar valores y traits como arrays si están en JSON
            $cultures = array_map([$this, 'processCultureData'], $cultures);

            // Guardar en cache
            $this->cultureCache[$cacheKey] = $cultures;

            Logger::info('Cultures retrieved by department', [
                'department_id' => $departmentId,
                'count' => count($cultures),
                'active_only' => $activeOnly
            ]);

            return $cultures;
        } catch (\Exception $e) {
            Logger::error('Error getting cultures by department', [
                'department_id' => $departmentId,
                'active_only' => $activeOnly,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get cultures by department: ' . $e->getMessage());
        }
    }

    /**
     * Realizar matching cultural entre candidato y cultura organizacional
     *
     * @param int $candidateId ID del candidato
     * @param int $cultureId ID de la cultura organizacional
     * @return array Resultado del matching con score y detalles
     *
     * @throws \InvalidArgumentException Si los IDs son inválidos
     * @throws \RuntimeException Si falla la operación
     */
    public function matchCandidateWithCulture(int $candidateId, int $cultureId): array
    {
        if ($candidateId <= 0 || $cultureId <= 0) {
            throw new \InvalidArgumentException('Candidate ID and Culture ID must be greater than 0');
        }

        try {
            // Obtener datos del candidato (requiere acceso a modelo Candidate)
            $candidateModel = new \Models\Candidate();
            $candidate = $candidateModel->findById($candidateId);

            if (!$candidate) {
                throw new \RuntimeException("Candidate not found: {$candidateId}");
            }

            // Obtener datos de la cultura
            $culture = $this->findById($cultureId);

            if (!$culture) {
                throw new \RuntimeException("Culture not found: {$cultureId}");
            }

            // Procesar datos culturales
            $culture = $this->processCultureData($culture);

            // Extraer soft skills del candidato
            $candidateSkills = [];
            if (isset($candidate['soft_skills'])) {
                $candidateSkills = is_string($candidate['soft_skills'])
                    ? json_decode($candidate['soft_skills'], true) ?? []
                    : $candidate['soft_skills'] ?? [];
            }

            // Realizar matching
            $matchResult = $this->calculateCultureMatch($candidateSkills, $culture);

            Logger::info('Culture matching completed', [
                'candidate_id' => $candidateId,
                'culture_id' => $cultureId,
                'match_score' => $matchResult['score']
            ]);

            return $matchResult;
        } catch (\Exception $e) {
            Logger::error('Error in culture matching', [
                'candidate_id' => $candidateId,
                'culture_id' => $cultureId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to match candidate with culture: ' . $e->getMessage());
        }
    }

    /**
     * Obtener características culturales de una cultura específica
     *
     * @param int $cultureId ID de la cultura
     * @return array Características culturales organizadas
     *
     * @throws \InvalidArgumentException Si culture_id es inválido
     * @throws \RuntimeException Si falla la operación
     */
    public function getCultureTraits(int $cultureId): array
    {
        if ($cultureId <= 0) {
            throw new \InvalidArgumentException('Culture ID must be greater than 0');
        }

        $cacheKey = "culture_traits_{$cultureId}";

        // Verificar cache
        if (isset($this->cultureCache[$cacheKey])) {
            return $this->cultureCache[$cacheKey];
        }

        try {
            $culture = $this->findById($cultureId);

            if (!$culture) {
                throw new \RuntimeException("Culture not found: {$cultureId}");
            }

            $culture = $this->processCultureData($culture);

            $traits = [
                'id' => $culture['id'],
                'name' => $culture['name'],
                'description' => $culture['description'],
                'values' => $culture['values'] ?? [],
                'traits' => $culture['traits'] ?? [],
                'department_id' => $culture['department_id'],
                'status' => $culture['status']
            ];

            // Guardar en cache
            $this->cultureCache[$cacheKey] = $traits;

            return $traits;
        } catch (\Exception $e) {
            Logger::error('Error getting culture traits', [
                'culture_id' => $cultureId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get culture traits: ' . $e->getMessage());
        }
    }

    /**
     * Evaluar el ajuste cultural de un candidato
     *
     * @param int $candidateId ID del candidato
     * @param array $departmentIds Array de IDs de departamentos a evaluar
     * @return array Evaluación de ajuste cultural por departamento
     *
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si falla la evaluación
     */
    public function assessCultureFit(int $candidateId, array $departmentIds = []): array
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be greater than 0');
        }

        try {
            $results = [];

            // Si no se especifican departamentos, obtener todos los activos
            if (empty($departmentIds)) {
                $departmentIds = $this->getActiveDepartmentIds();
            }

            foreach ($departmentIds as $deptId) {
                if (!is_numeric($deptId) || $deptId <= 0) {
                    continue;
                }

                $deptId = (int) $deptId;

                // Obtener culturas del departamento
                $cultures = $this->getCulturesByDepartment($deptId, true);

                $departmentResults = [];
                $totalScore = 0;
                $cultureCount = 0;

                foreach ($cultures as $culture) {
                    $matchResult = $this->matchCandidateWithCulture($candidateId, $culture['id']);
                    $departmentResults[] = [
                        'culture_id' => $culture['id'],
                        'culture_name' => $culture['name'],
                        'match_score' => $matchResult['score'],
                        'matched_traits' => $matchResult['matched_traits'] ?? [],
                        'missing_traits' => $matchResult['missing_traits'] ?? []
                    ];

                    $totalScore += $matchResult['score'];
                    $cultureCount++;
                }

                $avgScore = $cultureCount > 0 ? round($totalScore / $cultureCount, 2) : 0;

                $results[$deptId] = [
                    'department_id' => $deptId,
                    'average_fit_score' => $avgScore,
                    'culture_assessments' => $departmentResults,
                    'recommendation' => $this->getCultureFitRecommendation($avgScore)
                ];
            }

            Logger::info('Culture fit assessment completed', [
                'candidate_id' => $candidateId,
                'departments_assessed' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            Logger::error('Error assessing culture fit', [
                'candidate_id' => $candidateId,
                'department_ids' => $departmentIds,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to assess culture fit: ' . $e->getMessage());
        }
    }

    /**
     * Procesar datos culturales (convertir JSON a arrays si es necesario)
     *
     * @param array $culture Datos de cultura raw
     * @return array Datos de cultura procesados
     */
    private function processCultureData(array $culture): array
    {
        // Procesar 'values' field
        if (isset($culture['values']) && is_string($culture['values'])) {
            $decodedValues = json_decode($culture['values'], true);
            $culture['values'] = is_array($decodedValues) ? $decodedValues : [];
        }

        // Procesar 'traits' field
        if (isset($culture['traits']) && is_string($culture['traits'])) {
            $decodedTraits = json_decode($culture['traits'], true);
            $culture['traits'] = is_array($decodedTraits) ? $decodedTraits : [];
        }

        return $culture;
    }

    /**
     * Calcular el matching cultural entre candidato y cultura
     *
     * @param array $candidateSkills Skills del candidato
     * @param array $culture Datos de la cultura
     * @return array Resultado del matching
     */
    private function calculateCultureMatch(array $candidateSkills, array $culture): array
    {
        $cultureTraits = $culture['traits'] ?? [];
        $cultureValues = $culture['values'] ?? [];

        // Combinar traits y values para comparación
        $culturalRequirements = array_merge($cultureTraits, $cultureValues);

        if (empty($culturalRequirements) || empty($candidateSkills)) {
            return [
                'score' => 0,
                'matched_traits' => [],
                'missing_traits' => $culturalRequirements,
                'total_traits' => count($culturalRequirements)
            ];
        }

        $matchedTraits = [];
        $missingTraits = [];

        foreach ($culturalRequirements as $trait) {
            $traitName = is_array($trait) ? ($trait['name'] ?? $trait['value'] ?? '') : $trait;

            if (empty($traitName)) continue;

            $found = false;
            foreach ($candidateSkills as $skill) {
                $skillName = is_array($skill) ? ($skill['name'] ?? $skill['skill'] ?? '') : $skill;

                if (
                    stripos($skillName, $traitName) !== false ||
                    stripos($traitName, $skillName) !== false ||
                    $this->isSemanticMatch($skillName, $traitName)
                ) {
                    $matchedTraits[] = $traitName;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $missingTraits[] = $traitName;
            }
        }

        $totalTraits = count($culturalRequirements);
        $matchedCount = count($matchedTraits);
        $score = $totalTraits > 0 ? round(($matchedCount / $totalTraits) * 100, 2) : 0;

        return [
            'score' => $score,
            'matched_traits' => $matchedTraits,
            'missing_traits' => $missingTraits,
            'total_traits' => $totalTraits,
            'matched_count' => $matchedCount
        ];
    }

    /**
     * Verificar si dos términos tienen coincidencia semántica básica
     *
     * @param string $skill1 Primer término
     * @param string $skill2 Segundo término
     * @return bool True si hay coincidencia semántica
     */
    private function isSemanticMatch(string $skill1, string $skill2): bool
    {
        $synonyms = [
            'liderazgo' => ['leadership', 'líder', 'dirección'],
            'teamwork' => ['trabajo en equipo', 'colaboración', 'team work'],
            'innovation' => ['innovación', 'creatividad', 'creative'],
            'adaptability' => ['adaptabilidad', 'flexibilidad', 'flexible'],
            'communication' => ['comunicación', 'comunicar'],
            'responsibility' => ['responsabilidad', 'responsable']
        ];

        $skill1Lower = strtolower($skill1);
        $skill2Lower = strtolower($skill2);

        foreach ($synonyms as $base => $variants) {
            $allTerms = array_merge([$base], $variants);

            if ((in_array($skill1Lower, $allTerms) && in_array($skill2Lower, $allTerms))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener IDs de departamentos activos
     *
     * @return array Array de IDs de departamentos
     */
    private function getActiveDepartmentIds(): array
    {
        try {
            $query = "SELECT DISTINCT department_id FROM `{$this->table}` WHERE status = 'active' AND department_id IS NOT NULL";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            $results = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            return array_map('intval', $results);
        } catch (\Exception $e) {
            Logger::error('Error getting active department IDs', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Obtener recomendación basada en score de fit cultural
     *
     * @param float $score Score de 0-100
     * @return array Recomendación con nivel y descripción
     */
    private function getCultureFitRecommendation(float $score): array
    {
        if ($score >= 80) {
            return [
                'level' => 'excellent',
                'description' => 'Excelente ajuste cultural. Candidato altamente compatible.',
                'action' => 'recommend'
            ];
        } elseif ($score >= 60) {
            return [
                'level' => 'good',
                'description' => 'Buen ajuste cultural. Candidato compatible con desarrollo.',
                'action' => 'consider'
            ];
        } elseif ($score >= 40) {
            return [
                'level' => 'moderate',
                'description' => 'Ajuste cultural moderado. Requiere evaluación adicional.',
                'action' => 'evaluate'
            ];
        } else {
            return [
                'level' => 'poor',
                'description' => 'Ajuste cultural bajo. Posible incompatibilidad.',
                'action' => 'reconsider'
            ];
        }
    }

    /**
     * Invalidar cache de culturas
     *
     * @param int|null $cultureId ID específico de cultura o null para todo el cache
     * @return int Número de entradas de cache eliminadas
     */
    public function invalidateCultureCache(?int $cultureId = null): int
    {
        $deletedCount = 0;

        try {
            if ($cultureId !== null) {
                // Invalidar cache específico de una cultura
                $patterns = [
                    "culture_traits_{$cultureId}",
                    "cultures_dept_*",
                    "culture_match_{$cultureId}_*"
                ];

                foreach ($patterns as $pattern) {
                    foreach ($this->cultureCache as $key => $value) {
                        if (fnmatch($pattern, $key)) {
                            unset($this->cultureCache[$key]);
                            $deletedCount++;
                        }
                    }
                }
            } else {
                // Limpiar todo el cache
                $deletedCount = count($this->cultureCache);
                $this->cultureCache = [];
            }

            Logger::debug('Culture cache invalidated', [
                'culture_id' => $cultureId,
                'deleted_count' => $deletedCount
            ]);

            return $deletedCount;
        } catch (\Exception $e) {
            Logger::error('Error invalidating culture cache', [
                'culture_id' => $cultureId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Obtener estadísticas de cultura por departamento
     *
     * @param int $departmentId ID del departamento
     * @return array Estadísticas culturales
     */
    public function getCultureStatsByDepartment(int $departmentId): array
    {
        if ($departmentId <= 0) {
            throw new \InvalidArgumentException('Department ID must be greater than 0');
        }

        $cacheKey = "culture_stats_dept_{$departmentId}";

        if (isset($this->cultureCache[$cacheKey])) {
            return $this->cultureCache[$cacheKey];
        }

        try {
            $cultures = $this->getCulturesByDepartment($departmentId, false);

            $stats = [
                'department_id' => $departmentId,
                'total_cultures' => count($cultures),
                'active_cultures' => count(array_filter($cultures, fn($c) => $c['status'] === 'active')),
                'inactive_cultures' => count(array_filter($cultures, fn($c) => $c['status'] !== 'active')),
                'most_common_traits' => $this->extractMostCommonTraits($cultures),
                'culture_coverage' => $this->calculateCultureCoverage($cultures)
            ];

            $this->cultureCache[$cacheKey] = $stats;
            return $stats;
        } catch (\Exception $e) {
            Logger::error('Error getting culture stats', [
                'department_id' => $departmentId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get culture statistics: ' . $e->getMessage());
        }
    }

    /**
     * Extraer las características más comunes de un conjunto de culturas
     */
    private function extractMostCommonTraits(array $cultures): array
    {
        $traitCount = [];

        foreach ($cultures as $culture) {
            $traits = array_merge(
                $culture['traits'] ?? [],
                $culture['values'] ?? []
            );

            foreach ($traits as $trait) {
                $traitName = is_array($trait) ? ($trait['name'] ?? $trait['value'] ?? '') : $trait;
                if (!empty($traitName)) {
                    $traitCount[$traitName] = ($traitCount[$traitName] ?? 0) + 1;
                }
            }
        }

        arsort($traitCount);
        return array_slice($traitCount, 0, 10, true); // Top 10
    }

    /**
     * Calcular cobertura cultural (porcentaje de aspectos culturales definidos)
     */
    private function calculateCultureCoverage(array $cultures): float
    {
        if (empty($cultures)) {
            return 0.0;
        }

        $totalCoverage = 0;
        foreach ($cultures as $culture) {
            $coverage = 0;
            $maxCoverage = 4; // name, description, values, traits

            if (!empty($culture['name'])) $coverage++;
            if (!empty($culture['description'])) $coverage++;
            if (!empty($culture['values'])) $coverage++;
            if (!empty($culture['traits'])) $coverage++;

            $totalCoverage += ($coverage / $maxCoverage) * 100;
        }

        return round($totalCoverage / count($cultures), 2);
    }

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

            $this->logDebug('Culture created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            $this->logError('Error creating culture', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ], $e);
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
            $this->logError('Error retrieving culture', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ], $e);
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
                $this->logDebug('Culture updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error updating culture', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ], $e);
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
                $this->logDebug('Culture deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error deleting culture', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ], $e);
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
            $this->logError('Error searching cultures', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ], $e);
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
            $this->logError('Error counting cultures', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ], $e);
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
        // Validar nombre requerido
        if (isset($data['name']) && empty(trim($data['name']))) {
            throw new \InvalidArgumentException('Culture name is required and cannot be empty');
        }

        // Validar descripción si se proporciona
        if (isset($data['description']) && !is_string($data['description'])) {
            throw new \InvalidArgumentException('Description must be a string');
        }

        // Validar valores si se proporcionan
        if (isset($data['values'])) {
            if (!is_string($data['values']) && !is_array($data['values'])) {
                throw new \InvalidArgumentException('Values must be a string or array');
            }

            // Si es array, convertir a JSON
            if (is_array($data['values'])) {
                $data['values'] = json_encode($data['values']);
            }
        }

        // Validar que el nombre no esté duplicado (si es creación o actualización con nombre diferente)
        if (isset($data['name'])) {
            $existing = $this->findBy('name', $data['name']);
            if (!empty($existing)) {
                // Si es actualización, verificar que no sea el mismo registro
                if ($id === null || $existing[0]['id'] != $id) {
                    throw new \InvalidArgumentException('Culture name already exists');
                }
            }
        }
    }
}
