<?php

namespace Models;

class CandidateLanguage extends BaseModel
{
    protected string $table = 'candidate_languages';
    protected string $primaryKey = 'id';

    public function findByCandidate(int $candidateId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE candidate_id = ? ORDER BY level DESC, language ASC";
        return $this->query($sql, [$candidateId]);
    }
}
