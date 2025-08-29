<?php

declare(strict_types=1);

namespace Controllers;

use Models\JobCategory;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

class JobCategoryController extends BaseController
{
  private JobCategory $model;

  public function __construct()
  {
    parent::__construct();
    $this->model = new JobCategory();
  }

  public function index(Request $request, array $params = [])
  {
    try {
      $filters = $_GET ?? [];
      $page = max(1, (int)($filters['page'] ?? 1));
      $limit = (int)($filters['limit'] ?? 20);
      $offset = ($page - 1) * $limit;

      // Si se solicitan solo categorí­as principales
      if (isset($filters['main_only']) && $filters['main_only'] == '1') {
        $categories = $this->model->getMainCategories();
        $total = count($categories);
      } else {
        $categories = $this->model->searchJobCategories($filters, $limit, $offset);
        $total = $this->model->countJobCategories($filters);
      }

      return ResponseHelper::success('Lista de categorí­as de trabajo', [
        'data' => $categories,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
      ], 200);
    } catch (\Throwable $e) {
      Logger::error('Error listing job categories', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al listar categorí­as de trabajo', $e, 500);
    }
  }

  public function show(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $category = $this->model->getJobCategoryWithCount((int)$id);
      if (!$category) {
        return ResponseHelper::fail('Categorí­a de trabajo no encontrada', 404);
      }

      return ResponseHelper::success('Categorí­a de trabajo encontrada', $category, 200);
    } catch (\Throwable $e) {
      Logger::error('Error retrieving job category', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al obtener categorí­a de trabajo', $e, 500);
    }
  }

  public function store(Request $request, array $params = [])
  {
    try {
      $data = $request->getBody();

      $id = $this->model->createJobCategory($data);

      if ($id === false) {
        return ResponseHelper::fail('Unable to create job category', 400);
      }

      Logger::info('Job category created from controller', ['id' => $id]);
      return ResponseHelper::success('Categorí­a de trabajo creada', ['id' => $id], 201);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed creating job category', ['error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error creating job category', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al crear categorí­a de trabajo', $e, 500);
    }
  }

  public function update(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      $data = $request->getBody();

      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $ok = $this->model->updateJobCategory((int)$id, $data);

      if (!$ok) {
        return ResponseHelper::fail('Update failed', 400);
      }

      Logger::info('Job category updated from controller', ['id' => $id]);
      return ResponseHelper::success("Categorí­a de trabajo $id actualizada", ['success' => true], 200);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed updating job category', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error updating job category', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al actualizar categorí­a de trabajo', $e, 500);
    }
  }

  public function delete(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $ok = $this->model->deleteJobCategory((int)$id);
      if (!$ok) {
        return ResponseHelper::fail('Delete failed', 400);
      }

      Logger::info('Job category deleted from controller', ['id' => $id]);
      return ResponseHelper::success("Categorí­a de trabajo $id eliminada", [], 204);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed deleting job category', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error deleting job category', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al eliminar categorí­a de trabajo', $e, 500);
    }
  }
}
