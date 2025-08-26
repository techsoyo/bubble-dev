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
 * @since 2025-08-25
 */
class CandidateCertification extends BaseModel
{
    protected string $table = 'candidate_certifications';
    protected string $primaryKey = 'id';

    /**
     * Campos que pueden ser asignados masivamente
     * Basados en la estructura real de la tabla bt_candidate_certifications
     *
     * @var array<string>
     */
    protected array $fillable = [
        'candidate_id',
        'certification_name',
        'issuer',
        'issue_date',
        'expiry_date'
    ];

    /**
     * Campos que deben ocultarse en las representaciones de array/JSON
     *
     * @var array<string>
     */
    protected array $hidden = [];

    /**
     * MÉTODOS ESPECÍFICOS DE CERTIFICACIONES
     */

    /**
     * Obtiene todas las certificaciones de un candidato
     */
    public function getCandidateCertifications(int $candidateId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE candidate_id = :candidate_id 
                ORDER BY expiry_date DESC, issue_date DESC";

        return $this->query($sql, [':candidate_id' => $candidateId]);
    }

    /**
     * Obtiene las certificaciones activas (no expiradas) de un candidato
     */
    public function getActiveCertifications(int $candidateId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE candidate_id = :candidate_id 
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                ORDER BY expiry_date DESC, issue_date DESC";

        return $this->query($sql, [':candidate_id' => $candidateId]);
    }

    /**
     * Obtiene las certificaciones que expiran próximamente
     */
    public function getExpiringCertifications(?int $candidateId = null, int $daysAhead = 30): array
    {
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

        return $this->query($sql, $params);
    }

    /**
     * Verifica la validez de una certificación específica
     */
    public function verifyCertification(int $certificationId): array
    {
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
            'issuer' => $certification['issuer'],
            'issue_date' => $certification['issue_date'],
            'expiry_date' => $certification['expiry_date'],
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

        return $verificationData;
    }

    /**
     * Obtiene alertas de vencimiento para notificaciones
     */
    public function getExpirationAlerts(int $daysAhead = 30): array
    {
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
                'issuer' => $cert['issuer'],
                'expiry_date' => $cert['expiry_date'],
                'days_remaining' => $daysRemaining,
                'alert_level' => $alertLevel,
                'message' => "La certificación '{$cert['certification_name']}' expira en {$daysRemaining} días"
            ];
        }

        return $alerts;
    }

    /**
     * Valida que las fechas de una certificación sean consistentes
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
     */
    public function findByCandidate(int $candidateId): array
    {
        return $this->getCandidateCertifications($candidateId);
    }

    /**
     * Crea una nueva certificación con validaciones
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
}
