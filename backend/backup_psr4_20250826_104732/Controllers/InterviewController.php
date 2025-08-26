<?php declare(strict_types=1);

namespace Controllers\InterviewController.php\Controllers;

use Models\Interview;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

class InterviewController extends BaseController
{
  private Interview $model;

  public function __construct()
  {
    parent::__construct();
    $this->model = new Interview();
  }

  public function index(Request $request, array $params = [])
  {
    try {
      $filters = $_GET ?? [];
      $page = max(1, (int)($filters['page'] ?? 1));
      $limit = (int)($filters['limit'] ?? 20);

      // Usar mÃ©todo de vista del modelo para obtener informaciÃ³n completa
      $interviews = $this->model->getInterviewSchedule($filters, $page, $limit);
      $total = $this->model->countAll($filters);

      return ResponseHelper::success('Lista de entrevistas', [
        'data' => $interviews,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
      ], 200);
    } catch (\Throwable $e) {
      Logger::error('Error listing interviews', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al listar entrevistas', $e, 500);
    }
  }

  public function show(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $interview = $this->model->findById((int)$id);
      if (!$interview) {
        return ResponseHelper::fail('Entrevista no encontrada', 404);
      }

      return ResponseHelper::success('Entrevista encontrada', $interview, 200);
    } catch (\Throwable $e) {
      Logger::error('Error retrieving interview', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al obtener entrevista', $e, 500);
    }
  }

  public function store(Request $request, array $params = [])
  {
    try {
      $data = $request->getBody();

      // Validar campos requeridos
      if (empty($data['application_id'])) {
        return ResponseHelper::fail('application_id es requerido', 400);
      }
      if (empty($data['scheduled_datetime'])) {
        return ResponseHelper::fail('scheduled_datetime es requerido', 400);
      }

      $id = $this->model->store($data);

      if ($id === false) {
        return ResponseHelper::fail('Unable to create interview', 400);
      }

      Logger::info('Interview created from controller', ['id' => $id]);
      return ResponseHelper::success('Entrevista creada', ['id' => $id], 201);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed creating interview', ['error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error creating interview', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al crear entrevista', $e, 500);
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

      $ok = $this->model->update((int)$id, $data);

      if (!$ok) {
        return ResponseHelper::fail('Update failed', 400);
      }

      Logger::info('Interview updated from controller', ['id' => $id]);
      return ResponseHelper::success("Entrevista $id actualizada", ['success' => true], 200);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed updating interview', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error updating interview', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al actualizar entrevista', $e, 500);
    }
  }

  public function delete(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $ok = $this->model->delete((int)$id);
      if (!$ok) {
        return ResponseHelper::fail('Delete failed', 400);
      }

      Logger::info('Interview deleted from controller', ['id' => $id]);
      return ResponseHelper::success("Entrevista $id eliminada", [], 204);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error deleting interview', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al eliminar entrevista', $e, 500);
    }
  }
}
