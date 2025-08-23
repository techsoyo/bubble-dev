<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para referencias de candidatos
 *
 * Gestiona las referencias laborales y profesionales proporcionadas por los candidatos,
 * incluyendo información de contacto de antiguos empleadores, supervisores y colegas.
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
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: CandidateReference
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ['reference_name', 'reference_email', 'reference_phone']
     * ❌ Campos removidos: ['name', 'position', 'email', 'phone']
     * 📊 Total campos fillable: 6
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
     * Campos que deben ocultarse en arrays/JSON (información sensible de contacto)
     */
    protected array $hidden = [
        'phone',
        'email'
    ];

    /**
     * Encontrar todas las referencias de un candidato ordenadas por fecha de creación
     *
     * @param int $candidateId ID del candidato
     * @return array Lista de referencias del candidato
     * @throws \InvalidArgumentException Si el candidateId es inválido
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
     * @throws \InvalidArgumentException Si los datos son inválidos
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
     * @return bool True si la actualización fue exitosa
     * @throws \InvalidArgumentException Si los datos son inválidos
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
     * @return bool True si la eliminación fue exitosa
     * @throws \InvalidArgumentException Si el ID es inválido
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
     * Contar el número total de referencias de un candidato
     *
     * @param int $candidateId ID del candidato
     * @return int Número de referencias
     * @throws \InvalidArgumentException Si el candidateId es inválido
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
     * Validar si un candidato puede agregar más referencias
     *
     * @param int $candidateId ID del candidato
     * @param int $maxReferences Número máximo de referencias permitidas (por defecto 5)
     * @return bool True si puede agregar más referencias
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
     * Obtener referencias con información de contacto (solo para usuarios autorizados)
     *
     * @param int $candidateId ID del candidato
     * @param bool $includeContactInfo Si incluir información de contacto sensible
     * @return array Lista de referencias
     * @throws \InvalidArgumentException Si el candidateId es inválido
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

            // Si no se incluye información de contacto, usar el método estándar con campos ocultos
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
}
