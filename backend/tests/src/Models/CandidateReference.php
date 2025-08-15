<?php

namespace Models;

class CandidateReference extends BaseModel
{
    protected string $table = 'candidate_references';
    protected string $primaryKey = 'id';

    public function findByCandidate(int $candidateId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE candidate_id = ? ORDER BY created_at DESC";
        return $this->query($sql, [$candidateId]);
    }
}
