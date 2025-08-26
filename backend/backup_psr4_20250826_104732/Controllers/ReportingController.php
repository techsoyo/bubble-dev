<?php declare(strict_types=1);

namespace Controllers\ReportingController.php\Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class ReportingController
{
  public function index(Request $request)
  {
    // TODO: Reporte general
    return ResponseHelper::success('Reports overview', []);
  }

  public function byCandidate(Request $request, array $params)
  {
    $candidateId = $params['candidateId'] ?? null;
    // TODO: Reporte por candidato
    return ResponseHelper::success("Report for candidate $candidateId", [
      'candidateId' => $candidateId
    ]);
  }

  public function byJob(Request $request, array $params)
  {
    $jobId = $params['jobId'] ?? null;
    // TODO: Reporte por trabajo
    return ResponseHelper::success("Report for job $jobId", [
      'jobId' => $jobId
    ]);
  }
}
