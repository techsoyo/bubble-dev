<?php declare(strict_types=1);

namespace Models\CandidateReference.php\Models;

use Utils\Logger;

/**
 * Modelo para referencias de candidatos
 *
 * Gestiona las referencias laborales y profesionales proporcionadas por los candidatos,
 * incluyendo informaciÃ³n de contacto de antiguos empleadores, supervisores y colegas.
 *
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class CandidateReference extends BaseModel
{
    /**
     * Tabla asociada al modelo
     */
    protected string $table = 'candidate_references';
    /*
     * ðŸ”§ CORRECCIÃ“N AUTOMÃTICA APLICADA
     * Modelo: CandidateReference
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * âž• Campos aÃ±adidos: ['reference_name', 'reference_email', 'reference_phone']
     * âŒ Campos removidos: ['name', 'position', 'email', 'phone']
     * ðŸ“Š Total campos fillable: 6
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    /**
     * Clave primaria de la tabla
     */
    protected string $primaryKey = 'id';

    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'candidate_id',
        'reference_name',
        'reference_email',
        'reference_phone',
        'company',
        'relationship',
    ];

    /**
     * Campos que deben ocultarse en arrays/JSON (informaciÃ³n sensible de contacto)
     */
    protected array $hidden = [
        'phone',
        'email'
    ];

    /**
     * Encontrar todas las referencias de un candidato ordenadas por fecha de creaciÃ³n
     *
     * @param int $candidateId ID del candidato
     * @return array Lista de referencias del candidato
     * @throws \InvalidArgumentException Si el candidateId es invÃ¡lido
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function findByCandidate(int $candidateId): array
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be a positive integer');
        }

        try {
            $sql = "SELECT * FROM `{$this->table}` WHERE `candidate_id` = ? ORDER BY `created_at` DESC";
            $results = $this->query($sql, [$candidateId]);

            $this->logDebug('References retrieved for candidate', [
                'candidate_id' => $candidateId,
                'count' => count($results)
            ]);

            return $this->hideFields($results);
        } catch (\Exception $e) {
            $this->logError('Error retrieving candidate references', [
                'candidate_id' => $candidateId
            ], $e);
            throw new \RuntimeException('Failed to retrieve candidate references: ' . $e->getMessage());
        }
    }

    /**
     * Crear una nueva referencia para un candidato
     *
     * @param int $candidateId ID del candidato
     * @param array $referenceData Datos de la referencia
     * @return mixed ID de la referencia creada
     * @throws \InvalidArgumentException Si los datos son invÃ¡lidos
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function createReference(int $candidateId, array $referenceData)
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be a positive integer');
        }

        if (empty($referenceData['name'])) {
            throw new \InvalidArgumentException('Reference name is required');
        }

        if (empty($referenceData['position'])) {
            throw new \InvalidArgumentException('Reference position is required');
        }

        try {
            $data = array_merge($referenceData, [
                'candidate_id' => $candidateId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $referenceId = $this->store($data);

            Logger::info('Reference created successfully', [
                'reference_id' => $referenceId,
                'candidate_id' => $candidateId,
                'reference_name' => $referenceData['name']
            ]);

            return $referenceId;
        } catch (\Exception $e) {
            $this->logError('Error creating reference', [
                'candidate_id' => $candidateId,
                'reference_data' => $referenceData
            ], $e);
            throw new \RuntimeException('Failed to create reference: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar una referencia existente
     *
     * @param mixed $referenceId ID de la referencia
     * @param array $referenceData Datos actualizados
     * @return bool True si la actualizaciÃ³n fue exitosa
     * @throws \InvalidArgumentException Si los datos son invÃ¡lidos
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function updateReference($referenceId, array $referenceData): bool
    {
        if (empty($referenceId)) {
            throw new \InvalidArgumentException('Reference ID cannot be empty');
        }

        if (empty($referenceData)) {
            throw new \InvalidArgumentException('Reference data cannot be empty');
        }

        try {
            $referenceData['updated_at'] = date('Y-m-d H:i:s');
            $result = $this->update($referenceId, $referenceData);

            Logger::info('Reference updated successfully', [
                'reference_id' => $referenceId
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error updating reference', [
                'reference_id' => $referenceId,
                'reference_data' => $referenceData
            ], $e);
            throw new \RuntimeException('Failed to update reference: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar una referencia
     *
     * @param mixed $referenceId ID de la referencia
     * @return bool True si la eliminaciÃ³n fue exitosa
     * @throws \InvalidArgumentException Si el ID es invÃ¡lido
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function deleteReference($referenceId): bool
    {
        if (empty($referenceId)) {
            throw new \InvalidArgumentException('Reference ID cannot be empty');
        }

        try {
            $result = $this->delete($referenceId);

            Logger::info('Reference deleted successfully', [
                'reference_id' => $referenceId
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error deleting reference', [
                'reference_id' => $referenceId
            ], $e);
            throw new \RuntimeException('Failed to delete reference: ' . $e->getMessage());
        }
    }

    /**
     * Contar el nÃºmero total de referencias de un candidato
     *
     * @param int $candidateId ID del candidato
     * @return int NÃºmero de referencias
     * @throws \InvalidArgumentException Si el candidateId es invÃ¡lido
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function countByCandidateId(int $candidateId): int
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be a positive integer');
        }

        try {
            return $this->countAll(['candidate_id' => $candidateId]);
        } catch (\Exception $e) {
            $this->logError('Error counting references', [
                'candidate_id' => $candidateId
            ], $e);
            throw new \RuntimeException('Failed to count references: ' . $e->getMessage());
        }
    }

    /**
     * Validar si un candidato puede agregar mÃ¡s referencias
     *
     * @param int $candidateId ID del candidato
     * @param int $maxReferences NÃºmero mÃ¡ximo de referencias permitidas (por defecto 5)
     * @return bool True si puede agregar mÃ¡s referencias
     */
    public function canAddMoreReferences(int $candidateId, int $maxReferences = 5): bool
    {
        try {
            $currentCount = $this->countByCandidateId($candidateId);
            return $currentCount < $maxReferences;
        } catch (\Exception $e) {
            Logger::warning('Error checking reference limits', [
                'candidate_id' => $candidateId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener referencias con informaciÃ³n de contacto (solo para usuarios autorizados)
     *
     * @param int $candidateId ID del candidato
     * @param bool $includeContactInfo Si incluir informaciÃ³n de contacto sensible
     * @return array Lista de referencias
     * @throws \InvalidArgumentException Si el candidateId es invÃ¡lido
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function findByCandidateWithContactInfo(int $candidateId, bool $includeContactInfo = false): array
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be a positive integer');
        }

        try {
            $sql = "SELECT * FROM `{$this->table}` WHERE `candidate_id` = ? ORDER BY `created_at` DESC";
            $results = $this->query($sql, [$candidateId]);

            // Si no se incluye informaciÃ³n de contacto, usar el mÃ©todo estÃ¡ndar con campos ocultos
            if (!$includeContactInfo) {
                return $this->hideFields($results);
            }

            Logger::info('References retrieved with contact info', [
                'candidate_id' => $candidateId,
                'count' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logError('Error retrieving candidate references with contact info', [
                'candidate_id' => $candidateId,
                'include_contact' => $includeContactInfo
            ], $e);
            throw new \RuntimeException('Failed to retrieve candidate references: ' . $e->getMessage());
        }
    }
    // ==========================================
    // MÃ‰TODOS CRUD ENCAPSULADOS ESTÃNDAR
    // ==========================================

    /**
     * Crear nuevo candidate_reference con validaciones
     * @param array $data Datos del nuevo candidate_reference
     * @return mixed ID del nuevo candidate_reference o false en caso de error
     */
    public function createCandidateReference(array $data): mixed
    {
        try {
            $this->validateCandidateReferenceData($data);
            $id = $this->store($data);
            $this->invalidateCandidateReferenceCache();

            Logger::info('CandidateReference created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating candidate_reference', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener candidate_reference por ID
     * @param mixed $id ID del candidate_reference
     * @return array|null Datos del candidate_reference o null si no existe
     */
    public function getCandidateReference($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving candidate_reference', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar candidate_reference con validaciones
     * @param mixed $id ID del candidate_reference a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualizaciÃ³n fue exitosa
     */
    public function updateCandidateReference($id, array $data): bool
    {
        try {
            $this->validateCandidateReferenceData($data, $id);
            $result = $this->update($id, $data);

            if ($result) {
                $this->invalidateCandidateReferenceCache();
                Logger::info('CandidateReference updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating candidate_reference', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar candidate_reference con validaciones
     * @param mixed $id ID del candidate_reference a eliminar
     * @return bool True si la eliminaciÃ³n fue exitosa
     */
    public function deleteCandidateReference($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateCandidateReferenceCache();
                Logger::info('CandidateReference deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting candidate_reference', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar candidate_references con filtros
     * @param array $filters Filtros de bÃºsqueda
     * @param int $page PÃ¡gina actual
     * @param int $limit Registros por pÃ¡gina
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de candidate_references
     */
    public function searchCandidateReferences(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching candidate_references', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de candidate_references con filtros
     * @param array $filters Filtros de bÃºsqueda
     * @return int NÃºmero total de candidate_references
     */
    public function countCandidateReferences(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting candidate_references', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    // ==========================================
    // MÃ‰TODOS DE VALIDACIÃ“N ESPECÃFICOS
    // ==========================================

    /**
     * Validar datos especÃ­ficos de candidate_references
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualizaciÃ³n (opcional)
     * @throws \InvalidArgumentException Si los datos no son vÃ¡lidos
     */
    private function validateCandidateReferenceData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones especÃ­ficas del modelo
    }

    /**
     * Invalidar cache especÃ­fico de candidate_references
     */
    public function invalidateCandidateReferenceCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['candidate_references', 'candidate_reference_core', 'candidate_reference_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating candidate_reference cache', [], $e);
            return 0;
        }
    }
}
