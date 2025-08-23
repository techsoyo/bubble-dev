<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

class CandidateLanguage extends BaseModel
{
    protected string $table = 'candidate_languages';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: CandidateLanguage
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ninguno
     * ❌ Campos removidos: ['is_native']
     * 📊 Total campos fillable: 3
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */
    
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'candidate_id',
        'language',
        'proficiency_level',
    ];

    protected array $hidden = [];

    /**
     * Constantes para niveles de proficiencia según marco europeo
     */
    public const PROFICIENCY_LEVELS = [
        'A1' => 'Principiante',
        'A2' => 'Básico',
        'B1' => 'Intermedio',
        'B2' => 'Intermedio-Alto',
        'C1' => 'Avanzado',
        'C2' => 'Nativo/Bilingüe'
    ];

    public const LEVEL_A1 = 'A1';
    public const LEVEL_A2 = 'A2';
    public const LEVEL_B1 = 'B1';
    public const LEVEL_B2 = 'B2';
    public const LEVEL_C1 = 'C1';
    public const LEVEL_C2 = 'C2';

    /**
     * Obtener todos los idiomas de un candidato
     *
     * @param int $candidateId ID del candidato
     * @return array Lista de idiomas ordenados por nivel DESC y nombre ASC
     *
     * @throws \InvalidArgumentException Si el candidate_id es inválido
     * @throws \RuntimeException Si falla la consulta
     */
    public function getCandidateLanguages(int $candidateId): array
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be greater than 0');
        }

        try {
            $sql = "SELECT cl.*, 
                           CASE 
                               WHEN cl.proficiency_level = 'C2' THEN 6
                               WHEN cl.proficiency_level = 'C1' THEN 5
                               WHEN cl.proficiency_level = 'B2' THEN 4
                               WHEN cl.proficiency_level = 'B1' THEN 3
                               WHEN cl.proficiency_level = 'A2' THEN 2
                               WHEN cl.proficiency_level = 'A1' THEN 1
                               ELSE 0
                           END as level_order
                    FROM {$this->table} cl
                    WHERE cl.candidate_id = :candidate_id 
                    ORDER BY cl.is_native DESC, level_order DESC, cl.language ASC";

            $results = $this->query($sql, [':candidate_id' => $candidateId]);

            $this->logDebug('Candidate languages retrieved', [
                'candidate_id' => $candidateId,
                'count' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logError('Error getting candidate languages', [
                'candidate_id' => $candidateId
            ], $e);
            throw new \RuntimeException('Failed to get candidate languages: ' . $e->getMessage());
        }
    }

    /**
     * Obtener idiomas filtrados por nivel de proficiencia
     *
     * @param string $proficiencyLevel Nivel de proficiencia (A1, A2, B1, B2, C1, C2)
     * @param int|null $candidateId ID del candidato (opcional)
     * @return array Lista de idiomas con el nivel especificado
     *
     * @throws \InvalidArgumentException Si el nivel de proficiencia es inválido
     * @throws \RuntimeException Si falla la consulta
     */
    public function getLanguagesByProficiency(string $proficiencyLevel, ?int $candidateId = null): array
    {
        if (!$this->isValidProficiencyLevel($proficiencyLevel)) {
            throw new \InvalidArgumentException("Invalid proficiency level: $proficiencyLevel");
        }

        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE proficiency_level = :proficiency_level";
            $params = [':proficiency_level' => $proficiencyLevel];

            if ($candidateId !== null) {
                if ($candidateId <= 0) {
                    throw new \InvalidArgumentException('Candidate ID must be greater than 0');
                }
                $sql .= " AND candidate_id = :candidate_id";
                $params[':candidate_id'] = $candidateId;
            }

            $sql .= " ORDER BY language ASC";

            $results = $this->query($sql, $params);

            $this->logDebug('Languages by proficiency retrieved', [
                'proficiency_level' => $proficiencyLevel,
                'candidate_id' => $candidateId,
                'count' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logError('Error getting languages by proficiency', [
                'proficiency_level' => $proficiencyLevel,
                'candidate_id' => $candidateId
            ], $e);
            throw new \RuntimeException('Failed to get languages by proficiency: ' . $e->getMessage());
        }
    }

    /**
     * Obtener idiomas nativos
     *
     * @param int|null $candidateId ID del candidato (opcional)
     * @return array Lista de idiomas nativos
     *
     * @throws \InvalidArgumentException Si el candidate_id es inválido
     * @throws \RuntimeException Si falla la consulta
     */
    public function getNativeLanguages(?int $candidateId = null): array
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE is_native = 1";
            $params = [];

            if ($candidateId !== null) {
                if ($candidateId <= 0) {
                    throw new \InvalidArgumentException('Candidate ID must be greater than 0');
                }
                $sql .= " AND candidate_id = :candidate_id";
                $params[':candidate_id'] = $candidateId;
            }

            $sql .= " ORDER BY language ASC";

            $results = $this->query($sql, $params);

            $this->logDebug('Native languages retrieved', [
                'candidate_id' => $candidateId,
                'count' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logError('Error getting native languages', [
                'candidate_id' => $candidateId
            ], $e);
            throw new \RuntimeException('Failed to get native languages: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar el nivel de proficiencia de un idioma
     *
     * @param int $languageId ID del registro de idioma
     * @param string $newProficiencyLevel Nuevo nivel de proficiencia
     * @return bool True si la actualización fue exitosa
     *
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si falla la actualización
     */
    public function updateProficiency(int $languageId, string $newProficiencyLevel): bool
    {
        if ($languageId <= 0) {
            throw new \InvalidArgumentException('Language ID must be greater than 0');
        }

        if (!$this->isValidProficiencyLevel($newProficiencyLevel)) {
            throw new \InvalidArgumentException("Invalid proficiency level: $newProficiencyLevel");
        }

        try {
            // Verificar que el registro existe
            $existing = $this->findById($languageId);
            if (!$existing) {
                throw new \RuntimeException("Language record with ID $languageId not found");
            }

            $success = $this->update($languageId, [
                'proficiency_level' => $newProficiencyLevel,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if ($success) {
                $this->logInfo('Language proficiency updated', [
                    'language_id' => $languageId,
                    'old_level' => $existing['proficiency_level'] ?? 'unknown',
                    'new_level' => $newProficiencyLevel
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            $this->logError('Error updating language proficiency', [
                'language_id' => $languageId,
                'new_level' => $newProficiencyLevel
            ], $e);
            throw new \RuntimeException('Failed to update language proficiency: ' . $e->getMessage());
        }
    }

    /**
     * Buscar idiomas por candidato (método compatible con versión anterior)
     *
     * @param int $candidateId ID del candidato
     * @return array Lista de idiomas del candidato
     */
    public function findByCandidate(int $candidateId): array
    {
        return $this->getCandidateLanguages($candidateId);
    }

    /**
     * Crear o actualizar idioma de un candidato
     *
     * @param int $candidateId ID del candidato
     * @param string $language Nombre del idioma
     * @param string $proficiencyLevel Nivel de proficiencia
     * @param bool $isNative Si es idioma nativo
     * @return mixed ID del registro creado/actualizado
     *
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si falla la operación
     */
    public function upsertLanguage(int $candidateId, string $language, string $proficiencyLevel, bool $isNative = false)
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be greater than 0');
        }

        if (empty(trim($language))) {
            throw new \InvalidArgumentException('Language cannot be empty');
        }

        if (!$this->isValidProficiencyLevel($proficiencyLevel)) {
            throw new \InvalidArgumentException("Invalid proficiency level: $proficiencyLevel");
        }

        try {
            // Buscar registro existente
            $existing = $this->findOneBy('candidate_id', $candidateId);
            if ($existing && isset($existing['language']) && $existing['language'] === trim($language)) {
                // Actualizar existente
                return $this->update($existing['id'], [
                    'proficiency_level' => $proficiencyLevel,
                    'is_native' => $isNative ? 1 : 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ]) ? $existing['id'] : false;
            }

            // Crear nuevo registro
            return $this->store([
                'candidate_id' => $candidateId,
                'language' => trim($language),
                'proficiency_level' => $proficiencyLevel,
                'is_native' => $isNative ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logError('Error upserting language', [
                'candidate_id' => $candidateId,
                'language' => $language,
                'proficiency_level' => $proficiencyLevel,
                'is_native' => $isNative
            ], $e);
            throw new \RuntimeException('Failed to upsert language: ' . $e->getMessage());
        }
    }

    /**
     * Validar si un nivel de proficiencia es válido
     *
     * @param string $level Nivel a validar
     * @return bool True si es válido
     */
    private function isValidProficiencyLevel(string $level): bool
    {
        return array_key_exists($level, self::PROFICIENCY_LEVELS);
    }

    /**
     * Obtener todos los niveles de proficiencia disponibles
     *
     * @return array Array con niveles y descripciones
     */
    public static function getProficiencyLevels(): array
    {
        return self::PROFICIENCY_LEVELS;
    }

    /**
     * Obtener descripción de un nivel de proficiencia
     *
     * @param string $level Nivel (A1, A2, B1, B2, C1, C2)
     * @return string|null Descripción del nivel o null si no existe
     */
    public static function getProficiencyLevelDescription(string $level): ?string
    {
        return self::PROFICIENCY_LEVELS[$level] ?? null;
    }

    /**
     * Obtener estadísticas de idiomas por candidato
     *
     * @param int $candidateId ID del candidato
     * @return array Estadísticas de idiomas
     */
    public function getLanguageStats(int $candidateId): array
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be greater than 0');
        }

        try {
            $languages = $this->getCandidateLanguages($candidateId);

            $stats = [
                'total_languages' => count($languages),
                'native_languages' => 0,
                'proficiency_breakdown' => [],
                'highest_level' => null,
                'languages_list' => []
            ];

            foreach (self::PROFICIENCY_LEVELS as $level => $description) {
                $stats['proficiency_breakdown'][$level] = 0;
            }

            foreach ($languages as $language) {
                if ($language['is_native']) {
                    $stats['native_languages']++;
                }

                $level = $language['proficiency_level'];
                if (isset($stats['proficiency_breakdown'][$level])) {
                    $stats['proficiency_breakdown'][$level]++;
                }

                $stats['languages_list'][] = [
                    'language' => $language['language'],
                    'level' => $level,
                    'is_native' => (bool)$language['is_native']
                ];

                // Determinar nivel más alto
                if ($stats['highest_level'] === null || 
                    array_search($level, array_keys(self::PROFICIENCY_LEVELS)) > 
                    array_search($stats['highest_level'], array_keys(self::PROFICIENCY_LEVELS))) {
                    $stats['highest_level'] = $level;
                }
            }

            return $stats;
        } catch (\Exception $e) {
            $this->logError('Error getting language stats', [
                'candidate_id' => $candidateId
            ], $e);
            throw new \RuntimeException('Failed to get language stats: ' . $e->getMessage());
        }
    }

    /**
     * Logging helpers siguiendo el patrón del BaseModel
     */
    private function logDebug(string $message, array $context = []): void
    {
        Logger::debug($message, array_merge([
            'model' => static::class,
            'table' => $this->table
        ], $context));
    }

    private function logInfo(string $message, array $context = []): void
    {
        Logger::info($message, array_merge([
            'model' => static::class,
            'table' => $this->table
        ], $context));
    }

    private function logError(string $message, array $context = [], ?\Exception $exception = null): void
    {
        $logContext = array_merge([
            'model' => static::class,
            'table' => $this->table
        ], $context);

        if ($exception) {
            $logContext['exception'] = $exception->getMessage();
            $logContext['trace'] = $exception->getTraceAsString();
        }

        Logger::error($message, $logContext);
    }
}