<?php declare(strict_types=1);

namespace Controllers\StatisticsController.php\Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class StatisticsController
{
  public function index(Request $request)
  {
    // TODO: EstadÃ­sticas globales
    return ResponseHelper::success('General statistics', [
      'candidates' => 0,
      'jobs' => 0,
      'applications' => 0
    ]);
  }

  public function byDepartment(Request $request)
  {
    $departmentId = $request->input('department_id');
    // TODO: EstadÃ­sticas por departamento
    return ResponseHelper::success("Statistics for department $departmentId", [
      'departmentId' => $departmentId
    ]);
  }

  public function byRecruiter(Request $request)
  {
    $recruiterId = $request->input('recruiter_id');
    // TODO: EstadÃ­sticas por recruiter
    return ResponseHelper::success("Statistics for recruiter $recruiterId", [
      'recruiterId' => $recruiterId
    ]);
  }
}
