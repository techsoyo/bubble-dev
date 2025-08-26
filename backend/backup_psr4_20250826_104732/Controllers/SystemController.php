<?php declare(strict_types=1);

namespace Controllers\SystemController.php\Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class SystemController extends BaseController
{
  public function health(Request $request, array $params = [])
  {
    return ResponseHelper::success('Health OK', [
      'time' => date('c'),
      'php'  => PHP_VERSION
    ]);
  }

  public function status(Request $request, array $params = [])
  {
    // TODO: estado real (db, cache, cola, etc.)
    return ResponseHelper::success('System status', [
      'db'    => 'unknown',
      'cache' => 'unknown'
    ]);
  }

  public function version(Request $request, array $params = [])
  {
    // Si tienes una constante/ENV versiÃ³n, Ãºsala aquÃ­
    $ver = getenv('APP_VERSION') ?: '1.0.0';
    return ResponseHelper::success('System version', ['version' => $ver]);
  }
}
