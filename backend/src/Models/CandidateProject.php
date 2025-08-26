<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;
use PDO;
use InvalidArgumentException;
/**
 * Modelo para proyectos de candidatos
 *
 * @package Models
 * @version 1.0.0
 * @since 2025-08-26
 */
class CandidateProject extends BaseModel
{
  protected string $table = 'candidate_projects';
  protected string $primaryKey = 'id';
  protected array $fillable = [
    'candidate_id',
    'project_name',
    'description',
    'technologies',
  ];

  protected array $hidden = [];

  /**
   * Reemplaza todos los proyectos de un candidato por los nuevos
   * @param int $candidateId
   * @param array $rows
   * @return int Total insertado
   */
  public function replaceMany(int $candidateId, array $rows): int
  {
    if ($candidateId < 1) {
      throw new \InvalidArgumentException('candidateId inválido');
    }
    if (!is_array($rows)) {
      throw new \InvalidArgumentException('Formato rows inválido');
    }
    $this->delete(['candidate_id' => $candidateId]);
    $inserted = 0;
    foreach ($rows as $i => $row) {
      $clean = $this->validateRow($candidateId, $row, $i);
      $this->store($clean);
      $inserted++;
    }
    return $inserted;
  }

  private function validateRow(int $candidateId, array $row, int $i): array
  {
    $title   = trim((string)($row['title'] ?? ''));
    $desc    = isset($row['description']) ? trim((string)$row['description']) : null;
    $url     = isset($row['url']) ? trim((string)$row['url']) : null;
    $repo    = isset($row['repo_url']) ? trim((string)$row['repo_url']) : null;
    $start   = isset($row['start_date']) ? trim((string)$row['start_date']) : null;
    $end     = isset($row['end_date']) ? trim((string)$row['end_date']) : null;
    $current = isset($row['current']) ? filter_var($row['current'], FILTER_VALIDATE_BOOLEAN) : false;
    $stack   = isset($row['tech_stack']) ? trim((string)$row['tech_stack']) : null;

    if ($title === '') {
      throw new \InvalidArgumentException("Proyecto #$i: campo obligatorio 'title' faltante");
    }
    if ($url !== null && !filter_var($url, FILTER_VALIDATE_URL)) {
      throw new \InvalidArgumentException("Proyecto #$i: url inválida");
    }
    if ($repo !== null && !filter_var($repo, FILTER_VALIDATE_URL)) {
      throw new \InvalidArgumentException("Proyecto #$i: repo_url inválida");
    }
    if ($start !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
      throw new \InvalidArgumentException("Proyecto #$i: start_date inválida");
    }
    if ($end !== null) {
      if ($current) { $end = null; }
      elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        throw new \InvalidArgumentException("Proyecto #$i: end_date inválida");
      } elseif ($start !== null && $start > $end) {
        throw new \InvalidArgumentException("Proyecto #$i: start_date > end_date");
      }
    }
    $title = mb_substr($title, 0, 120);
    if ($desc !== null) { $desc = mb_substr($desc, 0, 2000); }
    if ($stack !== null) { $stack = mb_substr($stack, 0, 500); }

    return $this->onlyFillable([
      'candidate_id' => $candidateId,
      'title'        => $title,
      'description'  => $desc,
      'url'          => $url,
      'repo_url'     => $repo,
      'start_date'   => $start,
      'end_date'     => $current ? null : $end,
      'current'      => $current ? 1 : 0,
      'tech_stack'   => $stack,
    ]);
  }
}
