<?php declare(strict_types=1);

namespace Controllers\SocialLoginController.php\Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class SocialLoginController
{
  public function index(Request $request)
  {
    // TODO: Listar proveedores disponibles (Google, LinkedIn, etc.)
    return ResponseHelper::success('Available social logins', [
      'providers' => ['google', 'linkedin']
    ]);
  }

  public function redirect(Request $request, array $params)
  {
    $provider = $params['provider'] ?? null;
    // TODO: Generar URL de redirecciÃ³n
    return ResponseHelper::success("Redirecting to $provider", [
      'url' => "https://$provider.com/oauth"
    ]);
  }

  public function callback(Request $request, array $params)
  {
    $provider = $params['provider'] ?? null;
    $data = $request->all();
    // TODO: Procesar callback del proveedor
    return ResponseHelper::success("Callback from $provider", $data);
  }
}
