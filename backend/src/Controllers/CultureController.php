<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class CultureController
{
  public function index(Request $request)
  {
    // TODO: Listar elementos de cultura
    return ResponseHelper::success('Culture list', []);
  }

  public function store(Request $request)
  {
    // TODO: Crear nuevo recurso cultural
    $data = $request->all();
    return ResponseHelper::success('Culture created', $data);
  }

  public function show(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    // TODO: Mostrar un recurso específico
    return ResponseHelper::success("Culture item $id", [
      'id' => $id
    ]);
  }

  public function update(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    $data = $request->all();
    // TODO: Actualizar recurso cultural
    return ResponseHelper::success("Culture item $id updated", $data);
  }

  public function delete(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    // TODO: Eliminar recurso cultural
    return ResponseHelper::success("Culture item $id deleted");
  }
}
