<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class SkillController
{
  public function index(Request $request)
  {
    // TODO: Listar todas las habilidades disponibles
    return ResponseHelper::success('Skill list', []);
  }

  public function extract(Request $request)
  {
    $text = $request->input('text');
    // TODO: Extraer habilidades desde un texto o CV
    return ResponseHelper::success('Skills extracted', [
      'skills' => [],
      'source' => $text
    ]);
  }

  public function store(\Utils\Request $r, array $p = [])
  {
    return \Utils\ResponseHelper::success('Skill creada', [], 201);
  }
  public function show(\Utils\Request $r, array $p = [])
  {
    $id = $p['id'] ?? null;
    if (!$id) return \Utils\ResponseHelper::fail('ID requerido', 400);
    return \Utils\ResponseHelper::success("Skill $id", []);
  }
  public function update(\Utils\Request $r, array $p = [])
  {
    $id = $p['id'] ?? null;
    if (!$id) return \Utils\ResponseHelper::fail('ID', 400);
    return \Utils\ResponseHelper::success("Skill $id actualizada", []);
  }
  public function delete(\Utils\Request $r, array $p = [])
  {
    $id = $p['id'] ?? null;
    if (!$id) return \Utils\ResponseHelper::fail('ID', 400);
    return \Utils\ResponseHelper::success("Skill $id eliminada");
  }
}
