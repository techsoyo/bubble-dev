<?php

namespace Controllers;

use Models\Recruiter;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

class RecruiterController
{
  private Recruiter $model;

  public function __construct()
  {
    $this->model = new Recruiter();
  }

  public function index(Request $request)
  {
    try {
      $filters = $_GET ?? [];
      $page    = max(1, (int)($filters['page'] ?? 1));
      $limit   = (int)($filters['limit'] ?? 20);
      $orderBy = [];

      $rows  = $this->model->searchRecruiters($filters, $page, $limit, $orderBy);
      $total = $this->model->countRecruiters($filters);

      return ResponseHelper::success('Recruiter list', [
        'data' => $rows,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
      ], 200);
    } catch (\Throwable $e) {
      Logger::error('Error listing recruiters', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error listing recruiters', $e, 500);
    }
  }

  public function show(Request $request, array $params)
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail("ID no proporcionado", 400);
      }

      $row = $this->model->getRecruiter($id);
      if (!$row) {
        return ResponseHelper::fail("Recruiter not found", 404);
      }

      return ResponseHelper::success("Recruiter found", $row, 200);
    } catch (\Throwable $e) {
      Logger::error('Error retrieving recruiter', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error retrieving recruiter', $e, 500);
    }
  }

  public function store(Request $request)
  {
    try {
      $data = $request->getBody();

      $id = $this->model->createRecruiter($data);

      if ($id === false) {
        return ResponseHelper::fail('Unable to create recruiter', 400);
      }

      Logger::info('Recruiter created from controller', ['id' => $id]);
      return ResponseHelper::success('Recruiter created', ['id' => $id], 201);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed creating recruiter', ['error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error creating recruiter', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error creating recruiter', $e, 500);
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

      $ok = $this->model->updateRecruiter($id, $data);

      if (!$ok) {
        return ResponseHelper::fail('Update failed', 400);
      }

      Logger::info('Recruiter updated from controller', ['id' => $id]);
      return ResponseHelper::success("Recruiter $id updated", ['success' => true], 200);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed updating recruiter', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error updating recruiter', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error updating recruiter', $e, 500);
    }
  }

  public function delete(Request $request, array $params)
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail("ID no proporcionado", 400);
      }

      $ok = $this->model->deleteRecruiter($id);
      if (!$ok) {
        return ResponseHelper::fail('Delete failed', 400);
      }

      Logger::info('Recruiter deleted from controller', ['id' => $id]);
      return ResponseHelper::success("Recruiter $id deleted", null, 204);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error deleting recruiter', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error deleting recruiter', $e, 500);
    }
  }
}
