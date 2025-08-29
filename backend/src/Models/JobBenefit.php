<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para los beneficios asociados a las ofertas de trabajo.
 * 
 * Este modelo maneja los beneficios que ofrecen las empresas en sus ofertas de trabajo,
 * incluyendo valores monetarios, tipos de beneficios y requisitos.
 * Optimizado para consultas frecuentes con sistema de cache integrado.
 * 
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class JobBenefit extends BaseModel
{
    /**
     * Tabla asociada al modelo
     */
    protected string $table = 'job_benefits';
    /*
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â§ CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: JobBenefit
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ÃƒÂ¢Ã…Â¾Ã¢â‚¬Â¢ Campos aí±adidos: ninguno
     * ÃƒÂ¢Ã‚ÂÃ…â€™ Campos removidos: ['description', 'value', 'currency', 'is_required']
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  Total campos fillable: 2
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'job_id',
        'benefit_type',
    ];

    /**
     * Campos que deben ocultarse en las respuestas JSON/arrays
     * Incluye información sensible monetaria e interna
     */
    protected array $hidden = [
        'value',        // Información monetaria sensible
        'internal_cost' // Costos internos de la empresa
    ];

    /**
     * Tipos de beneficios ví¡lidos
     */
    private const VALID_BENEFIT_TYPES = [
        'salary',
        'bonus',
        'health_insurance',
        'dental_insurance',
        'vision_insurance',
        'life_insurance',
        'retirement_plan',
        'vacation_days',
        'sick_days',
        'flexible_hours',
        'remote_work',
        'gym_membership',
        'training_budget',
        'lunch_allowance',
        'transport_allowance',
        'phone_allowance',
        'internet_allowance',
        'stock_options',
        'profit_sharing',
        'other'
    ];

    /**
     * Monedas ví¡lidas
     */
    private const VALID_CURRENCIES = [
        'USD',
        'EUR',
        'GBP',
        'CAD',
        'AUD',
        'JPY',
        'CHF',
        'MXN',
        'BRL',
        'ARS',
        'CLP',
        'PEN',
        'COP',
        'UYU'
    ];

    /**
     * Obtener todos los beneficios asociados a un trabajo especí­fico
     * 
     * @param string|int $jobId ID del trabajo
     * @param bool $includeOptional Si incluir beneficios opcionales
     * @param int $cacheTtl Tiempo de vida del cache en segundos (0 = sin cache)
     * @return array Lista de beneficios del trabajo
     * 
     * @throws \InvalidArgumentException Si el jobId es inví¡lido
     * @throws \RuntimeException Si ocurre un error en la consulta
     */
    public function getBenefitsByJob($jobId, bool $includeOptional = true, int $cacheTtl = 300): array
    {
        if (empty($jobId)) {
            throw new \InvalidArgumentException('Job ID cannot be empty');
        }

        $cacheKey = $this->generateCacheKey('job_benefits', [
            'job_id' => $jobId,
            'include_optional' => $includeOptional
        ]);

        try {
            // Intentar obtener desde cache si estí¡ habilitado
            if ($cacheTtl > 0 && isset($this->cache[$cacheKey])) {
                $this->logDebug('Benefits retrieved from cache', ['job_id' => $jobId]);
                return $this->cache[$cacheKey];
            }

            $filters = ['job_id' => $jobId];

            if (!$includeOptional) {
                $filters['is_required'] = 1;
            }

            $results = $this->findAll($filters, 1, self::MAX_LIMIT, ['benefit_type' => 'ASC']);

            // Guardar en cache si estí¡ habilitado
            if ($cacheTtl > 0) {
                $this->cache[$cacheKey] = $results;
            }

            $this->logDebug('Benefits retrieved successfully', [
                'job_id' => $jobId,
                'count' => count($results),
                'include_optional' => $includeOptional
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logError('Error getting benefits by job', [
                'job_id' => $jobId,
                'include_optional' => $includeOptional
            ], $e);
            throw new \RuntimeException('Failed to get job benefits: ' . $e->getMessage());
        }
    }

    /**
     * Obtener beneficios filtrados por tipo especí­fico
     * 
     * @param string $benefitType Tipo de beneficio a filtrar
     * @param array $additionalFilters Filtros adicionales opcionales
     * @param int $limit Lí­mite de resultados
     * @param int $cacheTtl Tiempo de vida del cache en segundos
     * @return array Lista de beneficios del tipo especificado
     * 
     * @throws \InvalidArgumentException Si el tipo de beneficio es inví¡lido
     * @throws \RuntimeException Si ocurre un error en la consulta
     */
    public function getBenefitsByType(string $benefitType, array $additionalFilters = [], int $limit = 100, int $cacheTtl = 300): array
    {
        if (empty($benefitType)) {
            throw new \InvalidArgumentException('Benefit type cannot be empty');
        }

        if (!$this->isValidBenefitType($benefitType)) {
            throw new \InvalidArgumentException("Invalid benefit type: $benefitType");
        }

        $filters = array_merge(['benefit_type' => $benefitType], $additionalFilters);

        $cacheKey = $this->generateCacheKey('benefits_by_type', $filters);

        try {
            // Intentar obtener desde cache si estí¡ habilitado
            if ($cacheTtl > 0 && isset($this->cache[$cacheKey])) {
                $this->logDebug('Benefits by type retrieved from cache', ['type' => $benefitType]);
                return $this->cache[$cacheKey];
            }

            $results = $this->findAll($filters, 1, min($limit, self::MAX_LIMIT), ['job_id' => 'ASC']);

            // Guardar en cache si estí¡ habilitado
            if ($cacheTtl > 0) {
                $this->cache[$cacheKey] = $results;
            }

            $this->logDebug('Benefits by type retrieved successfully', [
                'type' => $benefitType,
                'count' => count($results),
                'additional_filters' => $additionalFilters
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logError('Error getting benefits by type', [
                'type' => $benefitType,
                'additional_filters' => $additionalFilters
            ], $e);
            throw new \RuntimeException('Failed to get benefits by type: ' . $e->getMessage());
        }
    }

    /**
     * Obtener solo los beneficios obligatorios/requeridos
     * 
     * @param string|int|null $jobId ID especí­fico del trabajo (opcional)
     * @param int $limit Lí­mite de resultados
     * @param int $cacheTtl Tiempo de vida del cache en segundos
     * @return array Lista de beneficios obligatorios
     * 
     * @throws \RuntimeException Si ocurre un error en la consulta
     */
    public function getRequiredBenefits($jobId = null, int $limit = 100, int $cacheTtl = 300): array
    {
        $filters = ['is_required' => 1];

        if ($jobId !== null) {
            if (empty($jobId)) {
                throw new \InvalidArgumentException('Job ID cannot be empty when specified');
            }
            $filters['job_id'] = $jobId;
        }

        $cacheKey = $this->generateCacheKey('required_benefits', $filters);

        try {
            // Intentar obtener desde cache si estí¡ habilitado
            if ($cacheTtl > 0 && isset($this->cache[$cacheKey])) {
                $this->logDebug('Required benefits retrieved from cache', ['job_id' => $jobId]);
                return $this->cache[$cacheKey];
            }

            $results = $this->findAll($filters, 1, min($limit, self::MAX_LIMIT), [
                'benefit_type' => 'ASC',
                'value' => 'DESC'
            ]);

            // Guardar en cache si estí¡ habilitado
            if ($cacheTtl > 0) {
                $this->cache[$cacheKey] = $results;
            }

            $this->logDebug('Required benefits retrieved successfully', [
                'job_id' => $jobId,
                'count' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logError('Error getting required benefits', ['job_id' => $jobId], $e);
            throw new \RuntimeException('Failed to get required benefits: ' . $e->getMessage());
        }
    }

    /**
     * Calcular el valor total monetario de los beneficios de un trabajo
     * 
     * @param string|int $jobId ID del trabajo
     * @param string $targetCurrency Moneda objetivo para la conversión (opcional)
     * @param bool $requiredOnly Si calcular solo beneficios obligatorios
     * @param int $cacheTtl Tiempo de vida del cache en segundos
     * @return array Información del valor total con desglose por moneda
     * 
     * @throws \InvalidArgumentException Si los parí¡metros son inví¡lidos
     * @throws \RuntimeException Si ocurre un error en la consulta
     */
    public function calculateBenefitsValue($jobId, string $targetCurrency = 'USD', bool $requiredOnly = false, int $cacheTtl = 300): array
    {
        if (empty($jobId)) {
            throw new \InvalidArgumentException('Job ID cannot be empty');
        }

        if (!empty($targetCurrency) && !$this->isValidCurrency($targetCurrency)) {
            throw new \InvalidArgumentException("Invalid target currency: $targetCurrency");
        }

        $cacheKey = $this->generateCacheKey('benefits_value', [
            'job_id' => $jobId,
            'target_currency' => $targetCurrency,
            'required_only' => $requiredOnly
        ]);

        try {
            // Intentar obtener desde cache si estí¡ habilitado
            if ($cacheTtl > 0 && isset($this->cache[$cacheKey])) {
                $this->logDebug('Benefits value retrieved from cache', ['job_id' => $jobId]);
                return $this->cache[$cacheKey];
            }

            $filters = ['job_id' => $jobId];
            if ($requiredOnly) {
                $filters['is_required'] = 1;
            }

            // Obtener beneficios con valores monetarios
            $benefits = $this->findAll($filters, 1, self::MAX_LIMIT);

            $totalValue = 0;
            $breakdown = [];
            $currencyBreakdown = [];

            foreach ($benefits as $benefit) {
                $value = $benefit['value'] ?? 0;
                $currency = $benefit['currency'] ?? $targetCurrency;
                $benefitType = $benefit['benefit_type'] ?? 'unknown';

                if ($value > 0) {
                    // Agregar al breakdown por tipo
                    if (!isset($breakdown[$benefitType])) {
                        $breakdown[$benefitType] = 0;
                    }
                    $breakdown[$benefitType] += $value;

                    // Agregar al breakdown por moneda
                    if (!isset($currencyBreakdown[$currency])) {
                        $currencyBreakdown[$currency] = 0;
                    }
                    $currencyBreakdown[$currency] += $value;

                    // Sumar al total (en la prí¡ctica aquí­ se harí­a conversión de moneda)
                    $totalValue += $value;
                }
            }

            $result = [
                'total_value' => $totalValue,
                'target_currency' => $targetCurrency,
                'breakdown_by_type' => $breakdown,
                'breakdown_by_currency' => $currencyBreakdown,
                'benefits_count' => count($benefits),
                'monetary_benefits_count' => count(array_filter($benefits, fn($b) => ($b['value'] ?? 0) > 0))
            ];

            // Guardar en cache si estí¡ habilitado
            if ($cacheTtl > 0) {
                $this->cache[$cacheKey] = $result;
            }

            $this->logDebug('Benefits value calculated successfully', [
                'job_id' => $jobId,
                'total_value' => $totalValue,
                'benefits_count' => count($benefits)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error calculating benefits value', [
                'job_id' => $jobId,
                'target_currency' => $targetCurrency,
                'required_only' => $requiredOnly
            ], $e);
            throw new \RuntimeException('Failed to calculate benefits value: ' . $e->getMessage());
        }
    }

    /**
     * Validar si un tipo de beneficio es ví¡lido
     * 
     * @param string $benefitType Tipo de beneficio a validar
     * @return bool True si es ví¡lido, false en caso contrario
     */
    private function isValidBenefitType(string $benefitType): bool
    {
        return in_array($benefitType, self::VALID_BENEFIT_TYPES, true);
    }

    /**
     * Validar si una moneda es ví¡lida
     * 
     * @param string $currency Código de moneda a validar
     * @return bool True si es ví¡lida, false en caso contrario
     */
    private function isValidCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), self::VALID_CURRENCIES, true);
    }

    /**
     * Crear un nuevo beneficio con validaciones
     * 
     * @param array $data Datos del beneficio
     * @return mixed ID del beneficio creado
     * 
     * @throws \InvalidArgumentException Si los datos no son ví¡lidos
     * @throws \RuntimeException Si ocurre un error en la creación
     */
    public function createBenefit(array $data)
    {
        $this->validateBenefitData($data);

        // Limpiar cache relacionado
        $this->clearBenefitCache($data['job_id'] ?? null);

        try {
            $id = $this->store($data);

            $this->logDebug('Benefit created successfully', [
                'id' => $id,
                'job_id' => $data['job_id'] ?? null,
                'benefit_type' => $data['benefit_type'] ?? null
            ]);

            return $id;
        } catch (\Exception $e) {
            $this->logError('Error creating benefit', ['data' => $data], $e);
            throw new \RuntimeException('Failed to create benefit: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar un beneficio existente con validaciones
     * 
     * @param mixed $id ID del beneficio
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     * 
     * @throws \InvalidArgumentException Si los datos no son ví¡lidos
     * @throws \RuntimeException Si ocurre un error en la actualización
     */
    public function updateBenefit($id, array $data): bool
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('Benefit ID cannot be empty');
        }

        $this->validateBenefitData($data, true);

        // Obtener el beneficio actual para limpiar cache
        $currentBenefit = $this->findById($id);
        if ($currentBenefit) {
            $this->clearBenefitCache($currentBenefit['job_id'] ?? null);
        }

        // Limpiar cache para el nuevo job_id si cambió
        if (isset($data['job_id']) && $currentBenefit && $data['job_id'] !== $currentBenefit['job_id']) {
            $this->clearBenefitCache($data['job_id']);
        }

        try {
            $result = $this->update($id, $data);

            $this->logDebug('Benefit updated successfully', [
                'id' => $id,
                'job_id' => $data['job_id'] ?? $currentBenefit['job_id'] ?? null
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error updating benefit', [
                'id' => $id,
                'data' => $data
            ], $e);
            throw new \RuntimeException('Failed to update benefit: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar un beneficio
     * 
     * @param mixed $id ID del beneficio
     * @return bool True si se eliminó correctamente
     * 
     * @throws \InvalidArgumentException Si el ID es inví¡lido
     * @throws \RuntimeException Si ocurre un error en la eliminación
     */
    public function deleteBenefit($id): bool
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('Benefit ID cannot be empty');
        }

        // Obtener el beneficio para limpiar cache
        $benefit = $this->findById($id);
        if ($benefit) {
            $this->clearBenefitCache($benefit['job_id'] ?? null);
        }

        try {
            $result = $this->delete($id);

            $this->logDebug('Benefit deleted successfully', [
                'id' => $id,
                'job_id' => $benefit['job_id'] ?? null
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error deleting benefit', ['id' => $id], $e);
            throw new \RuntimeException('Failed to delete benefit: ' . $e->getMessage());
        }
    }

    /**
     * Validar datos de beneficio
     * 
     * @param array $data Datos a validar
     * @param bool $isUpdate Si es una actualización (permite campos opcionales)
     * @return void
     * 
     * @throws \InvalidArgumentException Si algún dato es inví¡lido
     */
    private function validateBenefitData(array $data, bool $isUpdate = false): void
    {
        // Validar campos requeridos para creación
        if (!$isUpdate) {
            $requiredFields = ['job_id', 'benefit_type', 'description'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new \InvalidArgumentException("Field '$field' is required");
                }
            }
        }

        // Validar tipo de beneficio si estí¡ presente
        if (isset($data['benefit_type']) && !$this->isValidBenefitType($data['benefit_type'])) {
            throw new \InvalidArgumentException("Invalid benefit type: {$data['benefit_type']}");
        }

        // Validar moneda si estí¡ presente
        if (isset($data['currency']) && !empty($data['currency']) && !$this->isValidCurrency($data['currency'])) {
            throw new \InvalidArgumentException("Invalid currency: {$data['currency']}");
        }

        // Validar valor monetario si estí¡ presente
        if (isset($data['value']) && $data['value'] !== null) {
            if (!is_numeric($data['value']) || $data['value'] < 0) {
                throw new \InvalidArgumentException("Benefit value must be a non-negative number");
            }
        }

        // Validar is_required si estí¡ presente
        if (isset($data['is_required']) && !in_array($data['is_required'], [0, 1, true, false], true)) {
            throw new \InvalidArgumentException("Field 'is_required' must be boolean or 0/1");
        }
    }

    /**
     * Limpiar cache relacionado con beneficios de un trabajo
     * 
     * @param mixed $jobId ID del trabajo (opcional)
     * @return void
     */
    private function clearBenefitCache($jobId = null): void
    {
        if (empty($this->cache)) {
            return;
        }

        $keysToRemove = [];
        foreach ($this->cache as $key => $value) {
            if (
                strpos($key, 'job_benefits') !== false ||
                strpos($key, 'benefits_by_type') !== false ||
                strpos($key, 'required_benefits') !== false ||
                strpos($key, 'benefits_value') !== false
            ) {

                if ($jobId === null || strpos($key, "job_id:$jobId") !== false) {
                    $keysToRemove[] = $key;
                }
            }
        }

        foreach ($keysToRemove as $key) {
            unset($this->cache[$key]);
        }

        $this->logDebug('Benefit cache cleared', [
            'job_id' => $jobId,
            'keys_removed' => count($keysToRemove)
        ]);
    }

    /**
     * MANTENER FUNCIONALIDAD EXISTENTE DEL MODELO ORIGINAL
     */

    /**
     * Devuelve todos los beneficios asociados a un puesto de trabajo.
     * (Método original mantenido por compatibilidad)
     *
     * @param string $jobId ID del puesto
     * @return array Lista de beneficios
     */
    public function findByJobId(string $jobId): array
    {
        return $this->findAll(['job_id' => $jobId], 1, self::MAX_LIMIT);
    }

    /**
     * Obtener tipos de beneficios disponibles
     * 
     * @return array Lista de tipos ví¡lidos de beneficios
     */
    public static function getValidBenefitTypes(): array
    {
        return self::VALID_BENEFIT_TYPES;
    }

    /**
     * Obtener monedas soportadas
     * 
     * @return array Lista de códigos de moneda ví¡lidos
     */
    public static function getValidCurrencies(): array
    {
        return self::VALID_CURRENCIES;
    }

    // ==========================================
    // MÉTODOS CRUD ENCAPSULADOS ESTÁNDAR
    // ==========================================

    /**
     * Crear nuevo job_benefit con validaciones
     * @param array $data Datos del nuevo job_benefit
     * @return mixed ID del nuevo job_benefit o false en caso de error
     */
    public function createJobBenefit(array $data): mixed
    {
        try {
            $this->validateJobBenefitData($data);
            // Filtrar solo los campos permitidos
            $filtered = array_intersect_key($data, array_flip($this->fillable));
            $id = $this->store($filtered);
            $this->invalidateJobBenefitCache();
            $this->logDebug('JobBenefit created successfully', [
                'model' => static::class,
                'id' => $id
            ]);
            return $id;
        } catch (\Exception $e) {
            $this->logError('Error creating job_benefit', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ], $e);
            return false;
        }
    }

    /**
     * Obtener job_benefit por ID
     * @param mixed $id ID del job_benefit
     * @return array|null Datos del job_benefit o null si no existe
     */
    public function getJobBenefit($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            $this->logError('Error retrieving job_benefit', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ], $e);
            return null;
        }
    }

    /**
     * Actualizar job_benefit con validaciones
     * @param mixed $id ID del job_benefit a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualización fue exitosa
     */
    public function updateJobBenefit($id, array $data): bool
    {
        try {
            $this->validateJobBenefitData($data, $id);
            // Filtrar solo los campos permitidos
            $filtered = array_intersect_key($data, array_flip($this->fillable));
            $result = $this->update($id, $filtered);
            if ($result) {
                $this->invalidateJobBenefitCache();
                $this->logDebug('JobBenefit updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($filtered)
                ]);
            }
            return $result;
        } catch (\Exception $e) {
            $this->logError('Error updating job_benefit', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ], $e);
            return false;
        }
    }

    /**
     * Eliminar job_benefit con validaciones
     * @param mixed $id ID del job_benefit a eliminar
     * @return bool True si la eliminación fue exitosa
     */
    public function deleteJobBenefit($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateJobBenefitCache();
                $this->logDebug('JobBenefit deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error deleting job_benefit', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ], $e);
            return false;
        }
    }

    /**
     * Buscar job_benefits con filtros
     * @param array $filters Filtros de búsqueda
     * @param int $page Pí¡gina actual
     * @param int $limit Registros por pí¡gina
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de job_benefits
     */
    public function searchJobBenefits(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            $this->logError('Error searching job_benefits', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ], $e);
            return [];
        }
    }

    /**
     * Contar total de job_benefits con filtros
     * @param array $filters Filtros de búsqueda
     * @return int Número total de job_benefits
     */
    public function countJobBenefits(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            $this->logError('Error counting job_benefits', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ], $e);
            return 0;
        }
    }

    // ==========================================
    // MÉTODOS DE VALIDACIÓN ESPECíFICOS
    // ==========================================

    /**
     * Validar datos especí­ficos de job_benefits
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualización (opcional)
     * @throws \InvalidArgumentException Si los datos no son ví¡lidos
     */
    private function validateJobBenefitData(array $data, $id = null): void
    {
        // Validar job_id requerido
        if (isset($data['job_id'])) {
            if (empty($data['job_id'])) {
                throw new \InvalidArgumentException('Job ID is required and cannot be empty');
            }

            // Validar que el job existe
            if (!$this->validateJobExists($data['job_id'])) {
                throw new \InvalidArgumentException('Job ID does not exist');
            }
        }

        // Validar benefit_type requerido
        if (isset($data['benefit_type']) && empty(trim($data['benefit_type']))) {
            throw new \InvalidArgumentException('Benefit type is required and cannot be empty');
        }

        // Validar que benefit_type esté en la lista de tipos ví¡lidos si se proporciona
        if (isset($data['benefit_type']) && !in_array($data['benefit_type'], self::VALID_BENEFIT_TYPES)) {
            throw new \InvalidArgumentException('Invalid benefit type. Valid types: ' . implode(', ', self::VALID_BENEFIT_TYPES));
        }

        // Validar duplicados: mismo job_id + benefit_type
        if (isset($data['job_id']) && isset($data['benefit_type'])) {
            $existing = $this->findBy('job_id', $data['job_id']);
            if (!empty($existing)) {
                foreach ($existing as $benefit) {
                    if ($benefit['benefit_type'] === $data['benefit_type']) {
                        // Si es actualización, verificar que no sea el mismo registro
                        if ($id === null || $benefit['id'] != $id) {
                            throw new \InvalidArgumentException('This benefit type already exists for this job');
                        }
                    }
                }
            }
        }
    }

    /**
     * Validar que existe un job con el ID proporcionado
     * @param mixed $jobId ID del job a validar
     * @return bool True si existe el job
     */
    private function validateJobExists($jobId): bool
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM jobs WHERE id = :job_id";
            $result = $this->query($sql, [':job_id' => $jobId]);
            return ($result[0]['count'] ?? 0) > 0;
        } catch (\Exception $e) {
            $this->logError('Error validating job exists', ['job_id' => $jobId], $e);
            return false;
        }
    }

    /**
     * Invalidar cache especí­fico de job_benefits
     */
    public function invalidateJobBenefitCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['job_benefits', 'job_benefit_core', 'job_benefit_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating job_benefit cache', [], $e);
            return 0;
        }
    }
}
