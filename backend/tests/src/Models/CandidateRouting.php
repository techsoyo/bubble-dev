<?php

namespace Models;

class CandidateRouting extends BaseModel
{
    protected string $table = 'candidate_routing';
    protected string $primaryKey = 'id';

    public function findLatestByCandidate(int $candidateId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE candidate_id = ? ORDER BY assigned_at DESC LIMIT 1";
        $rows = $this->query($sql, [$candidateId]);
        return $rows[0] ?? null;
    }
}
