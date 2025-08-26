<?php declare(strict_types=1);

namespace Controllers\UserController.php\Controllers;

use Models\User;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

class UserController extends BaseController
{
  private User $model;

  public function __construct()
  {
    parent::__construct();
    $this->model = new User();
  }

  public function index(Request $request, array $params = [])
  {
    try {
      $filters = $_GET ?? [];
      $page = max(1, (int)($filters['page'] ?? 1));
      $limit = (int)($filters['limit'] ?? 20);
      $orderBy = $this->sanitizeOrder($filters['orderBy'] ?? []);

      $rows = User::searchUsers($filters, $limit, ($page - 1) * $limit);
      $total = count($rows); // TODO: Implementar countUsers en modelo

      return ResponseHelper::success('Lista de usuarios', [
        'data' => $rows,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
      ], 200);
    } catch (\Throwable $e) {
      Logger::error('Error listing users', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al listar usuarios', $e, 500);
    }
  }

  public function show(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $row = User::getUser((int)$id);
      if (!$row) {
        return ResponseHelper::fail('Usuario no encontrado', 404);
      }

      return ResponseHelper::success("Detalle usuario $id", $row, 200);
    } catch (\Throwable $e) {
      Logger::error('Error retrieving user', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al obtener usuario', $e, 500);
    }
  }

  public function store(Request $request, array $params = [])
  {
    try {
      $data = $request->getBody();

      $id = User::createUser($data);

      if ($id === false) {
        return ResponseHelper::fail('Unable to create user', 400);
      }

      Logger::info('User created from controller', ['id' => $id]);
      return ResponseHelper::success('Usuario creado', ['id' => $id], 201);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed creating user', ['error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error creating user', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al crear usuario', $e, 500);
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

      $ok = $this->model->update($id, $data);

      if (!$ok) {
        return ResponseHelper::fail('Update failed', 400);
      }

      Logger::info('User updated from controller', ['id' => $id]);
      return ResponseHelper::success("Usuario $id actualizado", ['success' => true], 200);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed updating user', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error updating user', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al actualizar usuario', $e, 500);
    }
  }

  public function delete(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $ok = $this->model->delete($id);
      if (!$ok) {
        return ResponseHelper::fail('Delete failed', 400);
      }

      Logger::info('User deleted from controller', ['id' => $id]);
      return ResponseHelper::success("Usuario $id eliminado", [], 204);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error deleting user', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al eliminar usuario', $e, 500);
    }
  }

  /**
   * Sanitize orderBy parameters
   */
  private function sanitizeOrder(array $orderBy): array
  {
    // Basic whitelist for common fields
    $allowedFields = ['id', 'email', 'name', 'created_at', 'updated_at'];
    $sanitized = [];

    foreach ($orderBy as $field => $direction) {
      if (in_array($field, $allowedFields) && in_array(strtoupper($direction), ['ASC', 'DESC'])) {
        $sanitized[$field] = strtoupper($direction);
      }
    }

    return $sanitized;
  }
}
