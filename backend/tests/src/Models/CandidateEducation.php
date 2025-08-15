<?php

namespace Models;

class CandidateEducation extends BaseModel
{
    protected string $table = 'candidate_education';
    protected string $primaryKey = 'id';

    public function findByCandidate(int $candidateId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE candidate_id = ? ORDER BY start_date DESC, end_date DESC";
        return $this->query($sql, [$candidateId]);
    }
}
