<?php

namespace Controllers;

class JobCategoryController extends BaseController
{
  public function index($params = [])
  {
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      $this->error('Método no permitido', null, 405);
      return;
    }

    $this->success('Job categories retrieved successfully', [
      'categories' => [
        ['id' => 1, 'name' => 'Technology', 'description' => 'IT and Software jobs'],
        ['id' => 2, 'name' => 'Marketing', 'description' => 'Marketing and Sales jobs']
      ],
      'total' => 2,
      'endpoint' => 'GET /api/job-categories',
      'message' => 'Endpoint working - ready for implementation'
    ]);
  }

  public function store($params = [])
  {
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->error('Método no permitido', null, 405);
      return;
    }

    $requestData = $this->getRequestData();

    $this->success('Job category created successfully', [
      'category' => [
        'id' => rand(1, 1000),
        'name' => $requestData['name'] ?? 'Test Category',
        'description' => $requestData['description'] ?? 'Test description',
        'created_at' => date('c')
      ],
      'endpoint' => 'POST /api/job-categories',
      'message' => 'Endpoint working - ready for implementation'
    ]);
  }

  public function show($params = [])
  {
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      $this->error('Método no permitido', null, 405);
      return;
    }

    $id = $params['id'] ?? 'unknown';

    $this->success('Job category retrieved successfully', [
      'category' => [
        'id' => $id,
        'name' => 'Category ' . $id,
        'description' => 'Description for category ' . $id,
        'jobs_count' => rand(1, 50)
      ],
      'endpoint' => "GET /api/job-categories/$id",
      'message' => 'Endpoint working - ready for implementation'
    ]);
  }

  public function update($params = [])
  {
    // Validar método HTTP
    if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'])) {
      $this->error('Método no permitido', null, 405);
      return;
    }

    $id = $params['id'] ?? 'unknown';
    $requestData = $this->getRequestData();

    $this->success('Job category updated successfully', [
      'category' => [
        'id' => $id,
        'updated_fields' => array_keys($requestData),
        'updated_at' => date('c')
      ],
      'endpoint' => "PUT /api/job-categories/$id",
      'message' => 'Endpoint working - ready for implementation'
    ]);
  }

  public function delete($params = [])
  {
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
      $this->error('Método no permitido', null, 405);
      return;
    }

    $id = $params['id'] ?? 'unknown';

    $this->success('Job category deleted successfully', [
      'deleted_id' => $id,
      'endpoint' => "DELETE /api/job-categories/$id",
      'message' => 'Endpoint working - ready for implementation'
    ]);
  }

  private function getRequestData()
  {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
      $input = file_get_contents('php://input');
      return json_decode($input, true) ?? [];
    }

    return array_merge($_GET, $_POST);
  }
}
