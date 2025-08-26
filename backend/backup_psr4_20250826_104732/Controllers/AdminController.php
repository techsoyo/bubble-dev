<?php declare(strict_types=1);

namespace Controllers\AdminController.php\Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class AdminController extends BaseController
{
  /**
   * Dashboard principal de administraciÃ³n
   */
  public function dashboard(Request $request, array $params = [])
  {
    return ResponseHelper::success('Datos del dashboard', [
      'users' => 125,
      'jobs' => 48,
      'candidates' => 300
    ]);
  }

  /**
   * EstadÃ­sticas del sistema
   */
  public function stats(Request $request, array $params = [])
  {
    return ResponseHelper::success('EstadÃ­sticas generales', [
      'applications_today' => 20,
      'new_candidates' => 5,
      'open_jobs' => 12
    ]);
  }

  /**
   * Acciones masivas de administraciÃ³n
   */
  public function bulkActions(Request $request, array $params = [])
  {
    $action = $request->input('action');
    $ids = $request->input('ids', []);

    if (!$action || empty($ids)) {
      return ResponseHelper::error('ParÃ¡metros insuficientes', null);
    }

    // TODO: implementar acciones masivas (ej: borrar usuarios, resetear estados, etc.)
    return ResponseHelper::success("AcciÃ³n $action aplicada a registros", [
      'ids' => $ids
    ]);
  }
}
