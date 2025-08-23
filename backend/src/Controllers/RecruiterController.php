<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class RecruiterController
{
  public function index(Request $request)
  {
    // TODO: Listar recruiters
    return ResponseHelper::success('Recruiter list', []);
  }

  public function show(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    // TODO: Mostrar recruiter específico
    return ResponseHelper::success("Recruiter $id", [
      'id' => $id
    ]);
  }

  public function store(Request $request)
  {
    $data = $request->all();
    // TODO: Crear recruiter
    return ResponseHelper::success('Recruiter created', $data);
  }

  public function update(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    $data = $request->all();
    // TODO: Actualizar recruiter
    return ResponseHelper::success("Recruiter $id updated", $data);
  }

  public function delete(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    // TODO: Eliminar recruiter
    return ResponseHelper::success("Recruiter $id deleted");
  }
}
