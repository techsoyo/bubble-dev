<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class FileController extends BaseController
{
  public function serve(Request $request, array $params = [])
  {
    $path = $params['path'] ?? null;
    if (!$path) return ResponseHelper::fail('Ruta requerida', 400);

    // TODO: validar y servir archivo
    return ResponseHelper::success('Archivo solicitado', ['path' => $path]);
  }

  public function delete(Request $request, array $params = [])
  {
    $path = $params['path'] ?? null;
    if (!$path) return ResponseHelper::fail('Ruta requerida para borrar', 400);

    // TODO: borrar archivo
    return ResponseHelper::success('Archivo eliminado', ['path' => $path]);
  }
}
