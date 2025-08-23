<?php

namespace Controllers;

use Models\Culture;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

class CultureController
{
  private Culture $model;

  public function __construct()
  {
    $this->model = new Culture();
  }

  public function index(Request $request)
  {
    try {
      $filters = $_GET ?? [];
      $page    = max(1, (int)($filters['page'] ?? 1));
      $limit   = (int)($filters['limit'] ?? 20);
      $orderBy = [];

      $rows  = $this->model->searchCultures($filters, $page, $limit, $orderBy);
      $total = $this->model->countCultures($filters);

      return ResponseHelper::success('Culture list', [
        'data' => $rows,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
      ], 200);
    } catch (\Throwable $e) {
      Logger::error('Error listing cultures', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error listing cultures', $e, 500);
    }
  }

  public function store(Request $request)
  {
    try {
      $data = $request->getBody();

      $id = $this->model->createCulture($data);

      if ($id === false) {
        return ResponseHelper::fail('Unable to create culture', 400);
      }

      Logger::info('Culture created from controller', ['id' => $id]);
      return ResponseHelper::success('Culture created', ['id' => $id], 201);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed creating culture', ['error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error creating culture', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error creating culture', $e, 500);
    }
  }

  public function show(Request $request, array $params)
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail("ID no proporcionado", 400);
      }

      $row = $this->model->getCulture($id);
      if (!$row) {
        return ResponseHelper::fail("Culture not found", 404);
      }

      return ResponseHelper::success("Culture found", $row, 200);
    } catch (\Throwable $e) {
      Logger::error('Error retrieving culture', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error retrieving culture', $e, 500);
    }
  }

  public function update(Request $request, array $params)
  {
    try {
      $id = $params['id'] ?? null;
      $data = $request->getBody();

      if (!$id) {
        return ResponseHelper::fail("ID no proporcionado", 400);
      }

      $ok = $this->model->updateCulture($id, $data);

      if (!$ok) {
        return ResponseHelper::fail('Update failed', 400);
      }

      Logger::info('Culture updated from controller', ['id' => $id]);
      return ResponseHelper::success("Culture item $id updated", ['success' => true], 200);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed updating culture', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error updating culture', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error updating culture', $e, 500);
    }
  }

  public function delete(Request $request, array $params)
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail("ID no proporcionado", 400);
      }

      $ok = $this->model->deleteCulture($id);
      if (!$ok) {
        return ResponseHelper::fail('Delete failed', 400);
      }

      Logger::info('Culture deleted from controller', ['id' => $id]);
      return ResponseHelper::success("Culture item $id deleted", null, 204);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error deleting culture', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error deleting culture', $e, 500);
    }
  }
}
