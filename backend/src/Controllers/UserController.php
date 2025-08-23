<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class UserController extends BaseController
{
  public function index(Request $request, array $params = [])
  {
    // TODO: listar usuarios
    return ResponseHelper::success('Lista de usuarios', ['data' => []]);
  }

  public function show(Request $request, array $params = [])
  {
    $id = $params['id'] ?? null;
    if (!$id) return ResponseHelper::fail('ID requerido', 400);

    // TODO: buscar usuario
    return ResponseHelper::success("Detalle usuario $id", ['id' => $id]);
  }

  public function store(Request $request, array $params = [])
  {
    $data = $request->all();
    // TODO: crear usuario
    return ResponseHelper::success('Usuario creado', ['data' => $data], 201);
  }

  public function update(Request $request, array $params = [])
  {
    $id   = $params['id'] ?? null;
    $data = $request->all();
    if (!$id) return ResponseHelper::fail('ID requerido', 400);

    // TODO: actualizar usuario
    return ResponseHelper::success("Usuario $id actualizado", ['data' => $data]);
  }

  public function delete(Request $request, array $params = [])
  {
    $id = $params['id'] ?? null;
    if (!$id) return ResponseHelper::fail('ID requerido', 400);

    // TODO: eliminar usuario
    return ResponseHelper::success("Usuario $id eliminado");
  }
}
