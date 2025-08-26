<?php declare(strict_types=1);

namespace Controllers\UtilityController.php\Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class UtilityController
{
  public function requestInfo(Request $request)
  {
    // InformaciÃ³n bÃ¡sica de la request (debug)
    return ResponseHelper::success('Request info', [
      'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
      'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
      'headers' => getallheaders(),
      'query' => $_GET,
      'body' => $request->all()
    ]);
  }

  public function ping()
  {
    return ResponseHelper::success('pong', [
      'time' => date('c')
    ]);
  }

  public function version()
  {
    return ResponseHelper::success('System version', [
      'version' => '1.0.0'
    ]);
  }
}
