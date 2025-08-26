<?php declare(strict_types=1);

namespace Models\CandidateLanguage.php\Models;

use Utils\Logger;

/**
 * Modelo CandidateLanguage
 *
 * Gestiona los idiomas y niveles de proficiencia de los candidatos,
 * incluyendo validaciones segÃºn el Marco ComÃºn Europeo de Referencia.
 *
 * @package Models
 * @version 2.0.0
 * @since 2025-08-25
 */
class CandidateLanguage extends BaseModel
{
    protected string $table = 'candidate_languages';
    protected string $primaryKey = 'id';

    /**
     * Campos que pueden ser asignados masivamente
     * Basados en la estructura real de la tabla bt_candidate_languages
     *
     * @var array<string>
     */
    protected array $fillable = [
        'candidate_id',
        'language',
        'proficiency_level'
    ];

    protected array $hidden = [];

    /**
     * Constantes para niveles de proficiencia segÃºn marco europeo
     */
    public const PROFICIENCY_LEVELS = [
        'A1' => 'Principiante',
        'A2' => 'BÃ¡sico',
        'B1' => 'Intermedio',
        'B2' => 'Intermedio-Alto',
        'C1' => 'Avanzado',
        'C2' => 'Nativo/BilingÃ¼e'
    ];

    public const LEVEL_A1 = 'A1';
    public const LEVEL_A2 = 'A2';
    public const LEVEL_B1 = 'B1';
    public const LEVEL_B2 = 'B2';
    public const LEVEL_C1 = 'C1';
    public const LEVEL_C2 = 'C2';

    /**
     * Obtener todos los idiomas de un candidato
     */
    public function getCandidateLanguages(int $candidateId): array
    {
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
                ORDER BY level_order DESC, cl.language ASC";

        return $this->query($sql, [':candidate_id' => $candidateId]);
    }

    /**
     * Obtener idiomas filtrados por nivel de proficiencia
     */
    public function getLanguagesByProficiency(string $proficiencyLevel, ?int $candidateId = null): array
    {
        if (!$this->isValidProficiencyLevel($proficiencyLevel)) {
            throw new \InvalidArgumentException("Invalid proficiency level: $proficiencyLevel");
        }

        $sql = "SELECT * FROM {$this->table} 
                WHERE proficiency_level = :proficiency_level";
        $params = [':proficiency_level' => $proficiencyLevel];

        if ($candidateId !== null) {
            $sql .= " AND candidate_id = :candidate_id";
            $params[':candidate_id'] = $candidateId;
        }

        $sql .= " ORDER BY language ASC";

        return $this->query($sql, $params);
    }

    /**
     * Actualizar el nivel de proficiencia de un idioma
     */
    public function updateProficiency(int $languageId, string $newProficiencyLevel): bool
    {
        if (!$this->isValidProficiencyLevel($newProficiencyLevel)) {
            throw new \InvalidArgumentException("Invalid proficiency level: $newProficiencyLevel");
        }

        // Verificar que el registro existe
        $existing = $this->findById($languageId);
        if (!$existing) {
            throw new \RuntimeException("Language record with ID $languageId not found");
        }

        return $this->update($languageId, [
            'proficiency_level' => $newProficiencyLevel,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Buscar idiomas por candidato (mÃ©todo compatible con versiÃ³n anterior)
     */
    public function findByCandidate(int $candidateId): array
    {
        return $this->getCandidateLanguages($candidateId);
    }

    /**
     * Crear o actualizar idioma de un candidato
     */
    public function upsertLanguage(int $candidateId, string $language, string $proficiencyLevel): mixed
    {
        if (!$this->isValidProficiencyLevel($proficiencyLevel)) {
            throw new \InvalidArgumentException("Invalid proficiency level: $proficiencyLevel");
        }

        // Buscar registro existente
        $existing = $this->findAll([
            'candidate_id' => $candidateId,
            'language' => trim($language)
        ]);

        if (!empty($existing)) {
            // Actualizar existente
            return $this->update($existing[0]['id'], [
                'proficiency_level' => $proficiencyLevel,
                'updated_at' => date('Y-m-d H:i:s')
            ]) ? $existing[0]['id'] : false;
        }

        // Crear nuevo registro
        return $this->store([
            'candidate_id' => $candidateId,
            'language' => trim($language),
            'proficiency_level' => $proficiencyLevel,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Validar si un nivel de proficiencia es vÃ¡lido
     */
    private function isValidProficiencyLevel(string $level): bool
    {
        return array_key_exists($level, self::PROFICIENCY_LEVELS);
    }

    /**
     * Obtener todos los niveles de proficiencia disponibles
     */
    public static function getProficiencyLevels(): array
    {
        return self::PROFICIENCY_LEVELS;
    }

    /**
     * Obtener descripciÃ³n de un nivel de proficiencia
     */
    public static function getProficiencyLevelDescription(string $level): ?string
    {
        return self::PROFICIENCY_LEVELS[$level] ?? null;
    }

    /**
     * Obtener estadÃ­sticas de idiomas por candidato
     */
    public function getLanguageStats(int $candidateId): array
    {
        $languages = $this->getCandidateLanguages($candidateId);

        $stats = [
            'total_languages' => count($languages),
            'proficiency_breakdown' => [],
            'highest_level' => null,
            'languages_list' => []
        ];

        foreach (self::PROFICIENCY_LEVELS as $level => $description) {
            $stats['proficiency_breakdown'][$level] = 0;
        }

        foreach ($languages as $language) {
            $level = $language['proficiency_level'];
            if (isset($stats['proficiency_breakdown'][$level])) {
                $stats['proficiency_breakdown'][$level]++;
            }

            $stats['languages_list'][] = [
                'language' => $language['language'],
                'level' => $level
            ];

            // Determinar nivel mÃ¡s alto
            if (
                $stats['highest_level'] === null ||
                array_search($level, array_keys(self::PROFICIENCY_LEVELS)) >
                array_search($stats['highest_level'], array_keys(self::PROFICIENCY_LEVELS))
            ) {
                $stats['highest_level'] = $level;
            }
        }

        return $stats;
    }

    /**
     * Buscar candidatos por idioma y nivel
     */
    public function findCandidatesByLanguageAndLevel(string $language, string $proficiencyLevel): array
    {
        if (!$this->isValidProficiencyLevel($proficiencyLevel)) {
            throw new \InvalidArgumentException("Invalid proficiency level: $proficiencyLevel");
        }

        $sql = "SELECT cl.candidate_id, cl.language, cl.proficiency_level,
                       c.name, c.email, c.location
                FROM {$this->table} cl
                INNER JOIN bt_candidates c ON c.id = cl.candidate_id
                WHERE cl.language = :language 
                AND cl.proficiency_level = :proficiency_level
                ORDER BY c.name ASC";

        return $this->query($sql, [
            ':language' => $language,
            ':proficiency_level' => $proficiencyLevel
        ]);
    }

    /**
     * Obtener los idiomas mÃ¡s comunes en la base de datos
     */
    public function getMostCommonLanguages(int $limit = 20): array
    {
        $sql = "SELECT language, 
                       COUNT(*) as candidate_count,
                       COUNT(DISTINCT candidate_id) as unique_candidates
                FROM {$this->table} 
                GROUP BY language 
                ORDER BY candidate_count DESC 
                LIMIT :limit";

        return $this->query($sql, [':limit' => $limit]);
    }

    /**
     * Obtener distribuciÃ³n de niveles para un idioma especÃ­fico
     */
    public function getLanguageProficiencyDistribution(string $language): array
    {
        $sql = "SELECT proficiency_level, 
                       COUNT(*) as count,
                       ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
                FROM {$this->table} 
                WHERE language = :language
                GROUP BY proficiency_level 
                ORDER BY 
                    CASE proficiency_level
                        WHEN 'C2' THEN 6
                        WHEN 'C1' THEN 5
                        WHEN 'B2' THEN 4
                        WHEN 'B1' THEN 3
                        WHEN 'A2' THEN 2
                        WHEN 'A1' THEN 1
                        ELSE 0
                    END DESC";

        return $this->query($sql, [':language' => $language]);
    }
}
