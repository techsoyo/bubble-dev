<?php

declare(strict_types=1);

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class RouteController
{
  public function assign(Request $request)
  {
    $candidateId = $request->input('candidate_id');
    $departmentId = $request->input('department_id');

    // TODO: Lógica de asignación
    return ResponseHelper::success('Candidate assigned', [
      'candidateId' => $candidateId,
      'departmentId' => $departmentId
    ]);
  }

  public function unassign(Request $request)
  {
    $candidateId = $request->input('candidate_id');
    // TODO: Lógica para desasignar
    return ResponseHelper::success('Candidate unassigned', [
      'candidateId' => $candidateId
    ]);
  }
}
