<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class NewsController
{
  public function index(Request $request)
  {
    // TODO: Listar noticias
    return ResponseHelper::success('News list', []);
  }

  public function show(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    // TODO: Mostrar noticia específica
    return ResponseHelper::success("News $id", [
      'id' => $id
    ]);
  }

  public function store(Request $request)
  {
    $data = $request->all();
    // TODO: Crear nueva noticia
    return ResponseHelper::success('News created', $data);
  }

  public function update(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    $data = $request->all();
    // TODO: Actualizar noticia
    return ResponseHelper::success("News $id updated", $data);
  }

  public function delete(Request $request, array $params)
  {
    $id = $params['id'] ?? null;
    // TODO: Eliminar noticia
    return ResponseHelper::success("News $id deleted");
  }
}
