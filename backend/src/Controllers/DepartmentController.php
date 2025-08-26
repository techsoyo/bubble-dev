<?php declare(strict_types=1);
namespace Controllers;

use Models\Department;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

class DepartmentController
{
  private Department $model;

  public function __construct()
  {
    $this->model = new Department();
  }

  public function index(Request $request)
  {
    try {
      $filters = $_GET ?? [];
      $page    = max(1, (int)($filters['page'] ?? 1));
      $limit   = (int)($filters['limit'] ?? 20);
      $orderBy = [];

      $rows  = $this->model->searchDepartments($filters, $page, $limit, $orderBy);
      $total = $this->model->countDepartments($filters);

      return ResponseHelper::success('Department list', [
        'data' => $rows,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
      ], 200);
    } catch (\Throwable $e) {
      Logger::error('Error listing departments', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error listing departments', $e, 500);
    }
  }

  public function show(Request $request, array $params)
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail("ID no proporcionado", 400);
      }

      $row = $this->model->getDepartment($id);
      if (!$row) {
        return ResponseHelper::fail("Department not found", 404);
      }

      return ResponseHelper::success("Department found", $row, 200);
    } catch (\Throwable $e) {
      Logger::error('Error retrieving department', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error retrieving department', $e, 500);
    }
  }

  public function store(Request $request)
  {
    try {
      $data = $request->getBody();

      $id = $this->model->createDepartment($data);

      if ($id === false) {
        return ResponseHelper::fail('Unable to create department', 400);
      }

      Logger::info('Department created from controller', ['id' => $id]);
      return ResponseHelper::success('Department created', ['id' => $id], 201);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed creating department', ['error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error creating department', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error creating department', $e, 500);
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

      $ok = $this->model->updateDepartment($id, $data);

      if (!$ok) {
        return ResponseHelper::fail('Update failed', 400);
      }

      Logger::info('Department updated from controller', ['id' => $id]);
      return ResponseHelper::success("Department $id updated", ['success' => true], 200);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed updating department', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error updating department', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error updating department', $e, 500);
    }
  }

  public function delete(Request $request, array $params)
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail("ID no proporcionado", 400);
      }

      $ok = $this->model->deleteDepartment($id);
      if (!$ok) {
        return ResponseHelper::fail('Delete failed', 400);
      }

      Logger::info('Department deleted from controller', ['id' => $id]);
      return ResponseHelper::success("Department $id deleted", null, 204);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error deleting department', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error deleting department', $e, 500);
    }
  }
}
