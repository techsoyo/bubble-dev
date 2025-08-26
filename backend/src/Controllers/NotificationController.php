<?php declare(strict_types=1);
namespace Controllers;

use Models\Notification;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

class NotificationController extends BaseController
{
  private Notification $model;

  public function __construct()
  {
    parent::__construct();
    $this->model = new Notification();
  }

  public function index(Request $request, array $params = [])
  {
    try {
      $filters = $_GET ?? [];
      $page = max(1, (int)($filters['page'] ?? 1));
      $limit = (int)($filters['limit'] ?? 20);
      $orderBy = $this->sanitizeOrder($filters['orderBy'] ?? []);
      $offset = ($page - 1) * $limit;

      // Usar mÃƒÆ’Ã‚Â©todo existente del modelo
      $rows = Notification::searchNotificationsStandard($filters, $limit, $offset);
      $total = Notification::countNotificationsStandard($filters);

      return ResponseHelper::success('Lista de notificaciones', [
        'data' => $rows,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
      ], 200);
    } catch (\Throwable $e) {
      Logger::error('Error listing notifications', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al listar notificaciones', $e, 500);
    }
  }

  public function show(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      // Usar mÃƒÆ’Ã‚Â©todo existente del modelo
      $row = Notification::getNotificationStandard((int)$id);
      if (!$row) {
        return ResponseHelper::fail('NotificaciÃƒÆ’Ã‚Â³n no encontrada', 404);
      }

      return ResponseHelper::success('NotificaciÃƒÆ’Ã‚Â³n encontrada', $row, 200);
    } catch (\Throwable $e) {
      Logger::error('Error retrieving notification', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al obtener notificaciÃƒÆ’Ã‚Â³n', $e, 500);
    }
  }

  public function store(Request $request, array $params = [])
  {
    try {
      $data = $request->getBody();

      $id = $this->model->createNotification($data);

      if ($id === false) {
        return ResponseHelper::fail('Unable to create notification', 400);
      }

      Logger::info('Notification created from controller', ['id' => $id]);
      return ResponseHelper::success('NotificaciÃƒÆ’Ã‚Â³n creada', ['id' => $id], 201);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed creating notification', ['error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error creating notification', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al crear notificaciÃƒÆ’Ã‚Â³n', $e, 500);
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

      // Usar mÃƒÆ’Ã‚Â©todo existente del modelo
      $ok = Notification::updateNotificationStandard((int)$id, $data);

      if (!$ok) {
        return ResponseHelper::fail('Update failed', 400);
      }

      Logger::info('Notification updated from controller', ['id' => $id]);
      return ResponseHelper::success("NotificaciÃƒÆ’Ã‚Â³n $id actualizada", ['success' => true], 200);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed updating notification', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error updating notification', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al actualizar notificaciÃƒÆ’Ã‚Â³n', $e, 500);
    }
  }

  public function markAsRead(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      // TODO: Implementar markAsRead en el modelo
      $ok = false;

      if (!$ok) {
        return ResponseHelper::fail('Mark as read failed', 400);
      }

      Logger::info('Notification marked as read', ['id' => $id]);
      return ResponseHelper::success("NotificaciÃƒÆ’Ã‚Â³n $id marcada como leÃƒÆ’Ã‚Â­da", ['success' => true], 200);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error marking notification as read', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al marcar notificaciÃƒÆ’Ã‚Â³n como leÃƒÆ’Ã‚Â­da', $e, 500);
    }
  }

  public function delete(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      // TODO: Implementar deleteNotification en el modelo
      $ok = false;
      if (!$ok) {
        return ResponseHelper::fail('Delete failed', 400);
      }

      Logger::info('Notification deleted from controller', ['id' => $id]);
      return ResponseHelper::success("NotificaciÃƒÆ’Ã‚Â³n $id eliminada", [], 204);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error deleting notification', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al eliminar notificaciÃƒÆ’Ã‚Â³n', $e, 500);
    }
  }

  /**
   * Sanitize orderBy parameters
   */
  private function sanitizeOrder(array $orderBy): array
  {
    $allowedFields = ['id', 'created_at', 'updated_at', 'type', 'status'];
    $sanitized = [];

    foreach ($orderBy as $field => $direction) {
      if (in_array($field, $allowedFields) && in_array(strtoupper($direction), ['ASC', 'DESC'])) {
        $sanitized[$field] = strtoupper($direction);
      }
    }

    return $sanitized;
  }
}
