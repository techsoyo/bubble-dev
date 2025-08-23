<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class DepartmentController
{
  public function index(Request $request)
  {
    // TODO: Listar departamentos
    return ResponseHelper::success('Department list', []);
  }

  public function show(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    // TODO: Mostrar un departamento
    return ResponseHelper::success("Department $id", [
      'id' => $id
    ]);
  }

  public function store(Request $request)
  {
    $data = $request->all();
    // TODO: Crear nuevo departamento
    return ResponseHelper::success('Department created', $data);
  }

  public function update(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    $data = $request->all();
    // TODO: Actualizar un departamento
    return ResponseHelper::success("Department $id updated", $data);
  }

  public function delete(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    // TODO: Eliminar un departamento
    return ResponseHelper::success("Department $id deleted");
  }
}
