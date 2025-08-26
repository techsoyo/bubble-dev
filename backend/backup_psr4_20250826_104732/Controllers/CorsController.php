<?php declare(strict_types=1);

namespace Controllers\CorsController.php\Controllers;

class CorsController extends BaseController
{
  public function preflight(\Utils\Request $request, array $params = [])
  {
    // Los headers CORS ya los aplica el router; devolvemos 204.
    http_response_code(204);
    return true;
  }
}
