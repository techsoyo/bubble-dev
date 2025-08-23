<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo CandidateCertification
 * 
 * Gestiona las certificaciones profesionales de los candidatos,
 * incluyendo validación de fechas de expiración y alertas de vencimiento.
 * 
 * @package Models
 * @author Bubble Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class CandidateCertification extends BaseModel
{
    protected string $table = 'candidate_certifications';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: CandidateCertification
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ['issuer']
     * ❌ Campos removidos: ['issuing_organization', 'credential_id', 'verification_url']
     * 📊 Total campos fillable: 5
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */

    protected string $primaryKey = 'id';

    /**
     * Campos que pueden ser asignados masivamente
     *
     * @var array<string>
     */
    protected array $fillable = [
        'candidate_id',
        'certification_name',
        'issuer',
        'issue_date',
        'expiry_date',
    ];

    /**
     * Campos que deben ocultarse en las representaciones de array/JSON
     *
     * @var array<string>
     */
    protected array $hidden = [
        'credential_id' // Información sensible
    ];

    /**
     * Conversiones de tipos para campos específicos
     *
     * @var array<string, string>
     */
    protected array $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date'
    ];

    /**
     * MÉTODOS ESPECÍFICOS DE CERTIFICACIONES
     */

    /**
     * Obtiene todas las certificaciones de un candidato
     *
     * @param int $candidateId ID del candidato
     * @return array<array<string, mixed>> Lista de certificaciones del candidato
     * @throws \InvalidArgumentException Si el ID del candidato no es válido
     * @throws \RuntimeException Si falla la consulta a la base de datos
     */
    public function getCandidateCertifications(int $candidateId): array
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('El ID del candidato debe ser mayor a 0');
        }

        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE candidate_id = :candidate_id 
                    ORDER BY expiry_date DESC, issue_date DESC";

            $result = $this->query($sql, [':candidate_id' => $candidateId]);

            $this->logDebug('Certificaciones del candidato obtenidas exitosamente', [
                'candidate_id' => $candidateId,
                'count' => count($result)
            ]);

            return $this->hideFields($result);
        } catch (\Exception $e) {
            $this->logError('Error obteniendo certificaciones del candidato', [
                'candidate_id' => $candidateId
            ], $e);
            throw new \RuntimeException('Error al obtener las certificaciones: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene las certificaciones activas (no expiradas) de un candidato
     *
     * @param int $candidateId ID del candidato
     * @return array<array<string, mixed>> Lista de certificaciones activas
     * @throws \InvalidArgumentException Si el ID del candidato no es válido
     * @throws \RuntimeException Si falla la consulta a la base de datos
     */
    public function getActiveCertifications(int $candidateId): array
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('El ID del candidato debe ser mayor a 0');
        }

        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE candidate_id = :candidate_id 
                    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                    ORDER BY expiry_date DESC, issue_date DESC";

            $result = $this->query($sql, [':candidate_id' => $candidateId]);

            $this->logDebug('Certificaciones activas obtenidas exitosamente', [
                'candidate_id' => $candidateId,
                'count' => count($result)
            ]);

            return $this->hideFields($result);
        } catch (\Exception $e) {
            $this->logError('Error obteniendo certificaciones activas', [
                'candidate_id' => $candidateId
            ], $e);
            throw new \RuntimeException('Error al obtener las certificaciones activas: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene las certificaciones que expiran próximamente (dentro de X días)
     *
     * @param int $candidateId ID del candidato (opcional, si no se proporciona obtiene de todos)
     * @param int $daysAhead Días hacia adelante para considerar como "próximo a expirar" (default: 30)
     * @return array<array<string, mixed>> Lista de certificaciones próximas a expirar
     * @throws \InvalidArgumentException Si los parámetros no son válidos
     * @throws \RuntimeException Si falla la consulta a la base de datos
     */
    public function getExpiringCertifications(?int $candidateId = null, int $daysAhead = 30): array
    {
        if ($candidateId !== null && $candidateId <= 0) {
            throw new \InvalidArgumentException('El ID del candidato debe ser mayor a 0');
        }

        if ($daysAhead <= 0) {
            throw new \InvalidArgumentException('Los días hacia adelante deben ser mayor a 0');
        }

        try {
            $sql = "SELECT c.*, 
                           DATEDIFF(c.expiry_date, CURDATE()) as days_to_expire,
                           candidates.first_name,
                           candidates.last_name,
                           candidates.email
                    FROM {$this->table} c
                    LEFT JOIN bt_candidates candidates ON c.candidate_id = candidates.id
                    WHERE c.expiry_date IS NOT NULL 
                    AND c.expiry_date >= CURDATE()
                    AND c.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days_ahead DAY)";

            $params = [':days_ahead' => $daysAhead];

            if ($candidateId !== null) {
                $sql .= " AND c.candidate_id = :candidate_id";
                $params[':candidate_id'] = $candidateId;
            }

            $sql .= " ORDER BY c.expiry_date ASC";

            $result = $this->query($sql, $params);

            $this->logDebug('Certificaciones próximas a expirar obtenidas', [
                'candidate_id' => $candidateId,
                'days_ahead' => $daysAhead,
                'count' => count($result)
            ]);

            return $this->hideFields($result);
        } catch (\Exception $e) {
            $this->logError('Error obteniendo certificaciones próximas a expirar', [
                'candidate_id' => $candidateId,
                'days_ahead' => $daysAhead
            ], $e);
            throw new \RuntimeException('Error al obtener las certificaciones próximas a expirar: ' . $e->getMessage());
        }
    }

    /**
     * Verifica la validez de una certificación específica
     *
     * @param int $certificationId ID de la certificación
     * @return array<string, mixed> Estado de verificación de la certificación
     * @throws \InvalidArgumentException Si el ID de certificación no es válido
     * @throws \RuntimeException Si falla la consulta a la base de datos
     */
    public function verifyCertification(int $certificationId): array
    {
        if ($certificationId <= 0) {
            throw new \InvalidArgumentException('El ID de la certificación debe ser mayor a 0');
        }

        try {
            $sql = "SELECT *, 
                           CASE 
                               WHEN expiry_date IS NULL THEN 'no_expiry'
                               WHEN expiry_date >= CURDATE() THEN 'valid'
                               ELSE 'expired'
                           END as status,
                           CASE 
                               WHEN expiry_date IS NOT NULL AND expiry_date >= CURDATE() 
                               THEN DATEDIFF(expiry_date, CURDATE())
                               ELSE NULL
                           END as days_remaining
                    FROM {$this->table} 
                    WHERE id = :certification_id";

            $result = $this->query($sql, [':certification_id' => $certificationId]);

            if (empty($result)) {
                return [
                    'exists' => false,
                    'status' => 'not_found',
                    'message' => 'Certificación no encontrada'
                ];
            }

            $certification = $result[0];

            $verificationData = [
                'exists' => true,
                'status' => $certification['status'],
                'certification_name' => $certification['certification_name'],
                'issuing_organization' => $certification['issuing_organization'],
                'issue_date' => $certification['issue_date'],
                'expiry_date' => $certification['expiry_date'],
                'verification_url' => $certification['verification_url'],
                'days_remaining' => $certification['days_remaining']
            ];

            // Agregar mensaje descriptivo basado en el estado
            switch ($certification['status']) {
                case 'valid':
                    $days = $certification['days_remaining'];
                    if ($days <= 30) {
                        $verificationData['message'] = "Certificación válida pero expira en {$days} días";
                        $verificationData['alert_level'] = 'warning';
                    } elseif ($days <= 90) {
                        $verificationData['message'] = "Certificación válida, expira en {$days} días";
                        $verificationData['alert_level'] = 'info';
                    } else {
                        $verificationData['message'] = 'Certificación válida';
                        $verificationData['alert_level'] = 'success';
                    }
                    break;
                case 'expired':
                    $verificationData['message'] = 'Certificación expirada';
                    $verificationData['alert_level'] = 'danger';
                    break;
                case 'no_expiry':
                    $verificationData['message'] = 'Certificación válida sin fecha de expiración';
                    $verificationData['alert_level'] = 'success';
                    break;
            }

            $this->logDebug('Certificación verificada exitosamente', [
                'certification_id' => $certificationId,
                'status' => $certification['status']
            ]);

            return $verificationData;
        } catch (\Exception $e) {
            $this->logError('Error verificando certificación', [
                'certification_id' => $certificationId
            ], $e);
            throw new \RuntimeException('Error al verificar la certificación: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene alertas de vencimiento para notificaciones
     *
     * @param int $daysAhead Días hacia adelante para generar alertas (default: 30)
     * @return array<array<string, mixed>> Lista de alertas de vencimiento
     * @throws \RuntimeException Si falla la consulta a la base de datos
     */
    public function getExpirationAlerts(int $daysAhead = 30): array
    {
        try {
            $expiringCertifications = $this->getExpiringCertifications(null, $daysAhead);

            $alerts = [];
            foreach ($expiringCertifications as $cert) {
                $daysRemaining = $cert['days_to_expire'];

                // Determinar el nivel de alerta
                $alertLevel = 'info';
                if ($daysRemaining <= 7) {
                    $alertLevel = 'danger';
                } elseif ($daysRemaining <= 15) {
                    $alertLevel = 'warning';
                }

                $alerts[] = [
                    'certification_id' => $cert['id'],
                    'candidate_id' => $cert['candidate_id'],
                    'candidate_name' => trim($cert['first_name'] . ' ' . $cert['last_name']),
                    'candidate_email' => $cert['email'],
                    'certification_name' => $cert['certification_name'],
                    'issuing_organization' => $cert['issuing_organization'],
                    'expiry_date' => $cert['expiry_date'],
                    'days_remaining' => $daysRemaining,
                    'alert_level' => $alertLevel,
                    'message' => "La certificación '{$cert['certification_name']}' expira en {$daysRemaining} días"
                ];
            }

            $this->logDebug('Alertas de vencimiento generadas', [
                'days_ahead' => $daysAhead,
                'alerts_count' => count($alerts)
            ]);

            return $alerts;
        } catch (\Exception $e) {
            $this->logError('Error generando alertas de vencimiento', [
                'days_ahead' => $daysAhead
            ], $e);
            throw new \RuntimeException('Error al generar alertas de vencimiento: ' . $e->getMessage());
        }
    }

    /**
     * Valida que las fechas de una certificación sean consistentes
     *
     * @param array<string, mixed> $data Datos de la certificación
     * @return array<string> Lista de errores de validación
     */
    public function validateCertificationDates(array $data): array
    {
        $errors = [];

        if (isset($data['issue_date']) && isset($data['expiry_date'])) {
            $issueDate = is_string($data['issue_date']) ?
                new \DateTime($data['issue_date']) : $data['issue_date'];
            $expiryDate = is_string($data['expiry_date']) ?
                new \DateTime($data['expiry_date']) : $data['expiry_date'];

            if ($expiryDate <= $issueDate) {
                $errors[] = 'La fecha de expiración debe ser posterior a la fecha de emisión';
            }

            // Validar que la fecha de emisión no sea futura
            $today = new \DateTime();
            if ($issueDate > $today) {
                $errors[] = 'La fecha de emisión no puede ser futura';
            }
        }

        return $errors;
    }

    /**
     * MÉTODOS HEREDADOS ADAPTADOS
     */

    /**
     * Busca certificaciones por candidato (método heredado adaptado)
     *
     * @param int $candidateId ID del candidato
     * @return array<array<string, mixed>> Lista de certificaciones del candidato
     * @throws \RuntimeException Si falla la consulta a la base de datos
     */
    public function findByCandidate(int $candidateId): array
    {
        return $this->getCandidateCertifications($candidateId);
    }

    /**
     * Crea una nueva certificación con validaciones
     *
     * @param array<string, mixed> $data Datos de la certificación
     * @return mixed ID de la certificación creada
     * @throws \InvalidArgumentException Si los datos no son válidos
     * @throws \RuntimeException Si falla la creación
     */
    public function store(array $data)
    {
        // Validar fechas antes de crear
        $validationErrors = $this->validateCertificationDates($data);
        if (!empty($validationErrors)) {
            throw new \InvalidArgumentException('Errores de validación: ' . implode(', ', $validationErrors));
        }

        return parent::store($data);
    }

    /**
     * Actualiza una certificación con validaciones
     *
     * @param mixed $id ID de la certificación
     * @param array<string, mixed> $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     * @throws \InvalidArgumentException Si los datos no son válidos
     * @throws \RuntimeException Si falla la actualización
     */
    public function update($id, array $data): bool
    {
        // Validar fechas antes de actualizar
        $validationErrors = $this->validateCertificationDates($data);
        if (!empty($validationErrors)) {
            throw new \InvalidArgumentException('Errores de validación: ' . implode(', ', $validationErrors));
        }

        return parent::update($id, $data);
    }

    /**
     * MÉTODOS DE UTILIDAD PRIVADOS
     */

    // ==========================================
    // MÉTODOS CRUD ENCAPSULADOS ESTÁNDAR
    // ==========================================

    /**
     * Crear nuevo candidate_certification con validaciones
     * @param array $data Datos del nuevo candidate_certification
     * @return mixed ID del nuevo candidate_certification o false en caso de error
     */
    public function createCandidateCertification(array $data): mixed
    {
        try {
            $this->validateCandidateCertificationData($data);
            $id = $this->store($data);
            $this->invalidateCandidateCertificationCache();

            Logger::info('CandidateCertification created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating candidate_certification', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener candidate_certification por ID
     * @param mixed $id ID del candidate_certification
     * @return array|null Datos del candidate_certification o null si no existe
     */
    public function getCandidateCertification($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving candidate_certification', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar candidate_certification con validaciones
     * @param mixed $id ID del candidate_certification a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualización fue exitosa
     */
    public function updateCandidateCertification($id, array $data): bool
    {
        try {
            $this->validateCandidateCertificationData($data, $id);
            $result = $this->update($id, $data);

            if ($result) {
                $this->invalidateCandidateCertificationCache();
                Logger::info('CandidateCertification updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating candidate_certification', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar candidate_certification con validaciones
     * @param mixed $id ID del candidate_certification a eliminar
     * @return bool True si la eliminación fue exitosa
     */
    public function deleteCandidateCertification($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateCandidateCertificationCache();
                Logger::info('CandidateCertification deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting candidate_certification', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar candidate_certifications con filtros
     * @param array $filters Filtros de búsqueda
     * @param int $page Página actual
     * @param int $limit Registros por página
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de candidate_certifications
     */
    public function searchCandidateCertifications(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching candidate_certifications', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de candidate_certifications con filtros
     * @param array $filters Filtros de búsqueda
     * @return int Número total de candidate_certifications
     */
    public function countCandidateCertifications(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting candidate_certifications', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    // ==========================================
    // MÉTODOS DE VALIDACIÓN ESPECÍFICOS
    // ==========================================

    /**
     * Validar datos específicos de candidate_certifications
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualización (opcional)
     * @throws \InvalidArgumentException Si los datos no son válidos
     */
    private function validateCandidateCertificationData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones específicas del modelo
    }

    /**
     * Invalidar cache específico de candidate_certifications
     */
    public function invalidateCandidateCertificationCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['candidate_certifications', 'candidate_certification_core', 'candidate_certification_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating candidate_certification cache', [], $e);
            return 0;
        }
    }
}
