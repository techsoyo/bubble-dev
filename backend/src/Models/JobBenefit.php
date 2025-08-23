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
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: JobBenefit
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ninguno
     * ❌ Campos removidos: ['description', 'value', 'currency', 'is_required']
     * 📊 Total campos fillable: 2
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
     * Tipos de beneficios válidos
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
     * Monedas válidas
     */
    private const VALID_CURRENCIES = [
        'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF',
        'MXN', 'BRL', 'ARS', 'CLP', 'PEN', 'COP', 'UYU'
    ];

    /**
     * Obtener todos los beneficios asociados a un trabajo específico
     * 
     * @param string|int $jobId ID del trabajo
     * @param bool $includeOptional Si incluir beneficios opcionales
     * @param int $cacheTtl Tiempo de vida del cache en segundos (0 = sin cache)
     * @return array Lista de beneficios del trabajo
     * 
     * @throws \InvalidArgumentException Si el jobId es inválido
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
            // Intentar obtener desde cache si está habilitado
            if ($cacheTtl > 0 && isset($this->cache[$cacheKey])) {
                $this->logDebug('Benefits retrieved from cache', ['job_id' => $jobId]);
                return $this->cache[$cacheKey];
            }

            $filters = ['job_id' => $jobId];
            
            if (!$includeOptional) {
                $filters['is_required'] = 1;
            }

            $results = $this->findAll($filters, 1, self::MAX_LIMIT, ['benefit_type' => 'ASC']);

            // Guardar en cache si está habilitado
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
     * Obtener beneficios filtrados por tipo específico
     * 
     * @param string $benefitType Tipo de beneficio a filtrar
     * @param array $additionalFilters Filtros adicionales opcionales
     * @param int $limit Límite de resultados
     * @param int $cacheTtl Tiempo de vida del cache en segundos
     * @return array Lista de beneficios del tipo especificado
     * 
     * @throws \InvalidArgumentException Si el tipo de beneficio es inválido
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
            // Intentar obtener desde cache si está habilitado
            if ($cacheTtl > 0 && isset($this->cache[$cacheKey])) {
                $this->logDebug('Benefits by type retrieved from cache', ['type' => $benefitType]);
                return $this->cache[$cacheKey];
            }

            $results = $this->findAll($filters, 1, min($limit, self::MAX_LIMIT), ['job_id' => 'ASC']);

            // Guardar en cache si está habilitado
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
     * @param string|int|null $jobId ID específico del trabajo (opcional)
     * @param int $limit Límite de resultados
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
            // Intentar obtener desde cache si está habilitado
            if ($cacheTtl > 0 && isset($this->cache[$cacheKey])) {
                $this->logDebug('Required benefits retrieved from cache', ['job_id' => $jobId]);
                return $this->cache[$cacheKey];
            }

            $results = $this->findAll($filters, 1, min($limit, self::MAX_LIMIT), [
                'benefit_type' => 'ASC',
                'value' => 'DESC'
            ]);

            // Guardar en cache si está habilitado
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
     * @throws \InvalidArgumentException Si los parámetros son inválidos
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
            // Intentar obtener desde cache si está habilitado
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

                    // Sumar al total (en la práctica aquí se haría conversión de moneda)
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

            // Guardar en cache si está habilitado
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
     * Validar si un tipo de beneficio es válido
     * 
     * @param string $benefitType Tipo de beneficio a validar
     * @return bool True si es válido, false en caso contrario
     */
    private function isValidBenefitType(string $benefitType): bool
    {
        return in_array($benefitType, self::VALID_BENEFIT_TYPES, true);
    }

    /**
     * Validar si una moneda es válida
     * 
     * @param string $currency Código de moneda a validar
     * @return bool True si es válida, false en caso contrario
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
     * @throws \InvalidArgumentException Si los datos no son válidos
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
     * @throws \InvalidArgumentException Si los datos no son válidos
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
     * @throws \InvalidArgumentException Si el ID es inválido
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
     * @throws \InvalidArgumentException Si algún dato es inválido
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

        // Validar tipo de beneficio si está presente
        if (isset($data['benefit_type']) && !$this->isValidBenefitType($data['benefit_type'])) {
            throw new \InvalidArgumentException("Invalid benefit type: {$data['benefit_type']}");
        }

        // Validar moneda si está presente
        if (isset($data['currency']) && !empty($data['currency']) && !$this->isValidCurrency($data['currency'])) {
            throw new \InvalidArgumentException("Invalid currency: {$data['currency']}");
        }

        // Validar valor monetario si está presente
        if (isset($data['value']) && $data['value'] !== null) {
            if (!is_numeric($data['value']) || $data['value'] < 0) {
                throw new \InvalidArgumentException("Benefit value must be a non-negative number");
            }
        }

        // Validar is_required si está presente
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
            if (strpos($key, 'job_benefits') !== false || 
                strpos($key, 'benefits_by_type') !== false ||
                strpos($key, 'required_benefits') !== false ||
                strpos($key, 'benefits_value') !== false) {
                
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
     * Generar clave de cache única
     * 
     * @param string $prefix Prefijo de la clave
     * @param array $data Datos para incluir en la clave
     * @return string Clave de cache
     */
    private function generateCacheKey(string $prefix, array $data): string
    {
        ksort($data);
        $dataString = '';
        foreach ($data as $key => $value) {
            $dataString .= "$key:" . (is_array($value) ? json_encode($value) : $value) . "|";
        }
        return $prefix . '_' . md5($dataString);
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
     * @return array Lista de tipos válidos de beneficios
     */
    public static function getValidBenefitTypes(): array
    {
        return self::VALID_BENEFIT_TYPES;
    }

    /**
     * Obtener monedas soportadas
     * 
     * @return array Lista de códigos de moneda válidos
     */
    public static function getValidCurrencies(): array
    {
        return self::VALID_CURRENCIES;
    }

    /**
     * Logging helpers
     */
    private function logDebug(string $message, array $context = []): void
    {
        Logger::debug($message, array_merge(['model' => static::class], $context));
    }

    private function logError(string $message, array $context = [], ?\Exception $exception = null): void
    {
        $contextData = array_merge(['model' => static::class], $context);
        if ($exception) {
            $contextData['exception'] = $exception->getMessage();
        }
        Logger::error($message, $contextData);
    }
}