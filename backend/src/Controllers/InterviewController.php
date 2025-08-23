<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class InterviewController
{
  public function index(Request $request)
  {
    // TODO: Listar entrevistas
    return ResponseHelper::success('Interview list', []);
  }

  public function show(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    // TODO: Mostrar entrevista específica
    return ResponseHelper::success("Interview $id", [
      'id' => $id
    ]);
  }

  public function store(Request $request)
  {
    $data = $request->all();
    // TODO: Crear nueva entrevista
    return ResponseHelper::success('Interview created', $data);
  }

  public function update(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    $data = $request->all();
    // TODO: Actualizar entrevista
    return ResponseHelper::success("Interview $id updated", $data);
  }

  public function delete(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    // TODO: Eliminar entrevista
    return ResponseHelper::success("Interview $id deleted");
  }
}
