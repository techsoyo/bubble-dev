<?php

namespace Controllers;

use Models\News;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

class NewsController
{
  private News $model;

  public function __construct()
  {
    $this->model = new News();
  }

  public function index(Request $request)
  {
    try {
      $filters = $_GET ?? [];
      $page = max(1, (int)($filters['page'] ?? 1));
      $limit = (int)($filters['limit'] ?? 20);
      $orderBy = $this->sanitizeOrder($filters['orderBy'] ?? []);

      $rows = $this->model->searchNews($filters['search'] ?? '', $page, $limit, true);
      $total = count($rows); // TODO: Implementar countNews en modelo

      return ResponseHelper::success('Listado de noticias', [
        'data' => $rows,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
      ], 200);
    } catch (\Throwable $e) {
      Logger::error('Error listing news', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al listar noticias', $e, 500);
    }
  }

  public function show(Request $request, array $params)
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      // TODO: Implementar getNews en el modelo
      $row = null;
      if (!$row) {
        return ResponseHelper::fail('Noticia no encontrada', 404);
      }

      return ResponseHelper::success('Noticia encontrada', $row, 200);
    } catch (\Throwable $e) {
      Logger::error('Error retrieving news', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al obtener noticia', $e, 500);
    }
  }

  public function store(Request $request)
  {
    try {
      $data = $request->getBody();

      $id = $this->model->createNews($data);

      if ($id === false) {
        return ResponseHelper::fail('Unable to create news', 400);
      }

      Logger::info('News created from controller', ['id' => $id]);
      return ResponseHelper::success('Noticia creada', ['id' => $id], 201);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed creating news', ['error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error creating news', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al crear noticia', $e, 500);
    }
  }

  public function update(Request $request, array $params)
  {
    try {
      $id = $params['id'] ?? null;
      $data = $request->getBody();

      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $ok = $this->model->updateNews($id, $data);

      if (!$ok) {
        return ResponseHelper::fail('Update failed', 400);
      }

      Logger::info('News updated from controller', ['id' => $id]);
      return ResponseHelper::success("Noticia $id actualizada", ['success' => true], 200);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed updating news', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error updating news', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al actualizar noticia', $e, 500);
    }
  }

  public function delete(Request $request, array $params)
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      // TODO: Implementar deleteNews en el modelo
      $ok = false;
      if (!$ok) {
        return ResponseHelper::fail('Delete failed', 400);
      }

      Logger::info('News deleted from controller', ['id' => $id]);
      return ResponseHelper::success("Noticia $id eliminada", [], 204);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error deleting news', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al eliminar noticia', $e, 500);
    }
  }

  /**
   * Sanitize orderBy parameters
   */
  private function sanitizeOrder(array $orderBy): array
  {
    $allowedFields = ['id', 'title', 'published_at', 'created_at', 'updated_at'];
    $sanitized = [];

    foreach ($orderBy as $field => $direction) {
      if (in_array($field, $allowedFields) && in_array(strtoupper($direction), ['ASC', 'DESC'])) {
        $sanitized[$field] = strtoupper($direction);
      }
    }

    return $sanitized;
  }
}
