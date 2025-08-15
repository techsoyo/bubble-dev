<?php

namespace Models;

class CandidateCertification extends BaseModel
{
    protected string $table = 'candidate_certifications';
    protected string $primaryKey = 'id';

    public function findByCandidate(int $candidateId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE candidate_id = ? ORDER BY obtained_at DESC";
        return $this->query($sql, [$candidateId]);
    }
}
