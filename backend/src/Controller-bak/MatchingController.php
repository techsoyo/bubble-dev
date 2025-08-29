<?php

declare(strict_types=1);

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class MatchingController
{
  public function calculate(Request $request)
  {
    $candidateId = $request->input('candidate_id');
    $jobId = $request->input('job_id');

    // TODO: Implementar lógica de matching IA
    return ResponseHelper::success('Matching calculated', [
      'candidateId' => $candidateId,
      'jobId' => $jobId,
      'score' => 0.0
    ]);
  }

  public function history(Request $request)
  {
    // TODO: Retornar histórico de matchings
    return ResponseHelper::success('Matching history', []);
  }
}
