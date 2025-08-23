<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class NotificationController extends BaseController
{
  public function index(Request $request, array $params = [])
  {
    return ResponseHelper::success('Notification list', ['data' => []]);
  }

  public function store(Request $request, array $params = [])
  {
    $data = $request->all();
    return ResponseHelper::success('Notification created', ['data' => $data], 201);
  }

  public function markAsRead(Request $request, array $params = [])
  {
    $id = $params['id'] ?? null;
    if (!$id) return ResponseHelper::fail('ID requerido', 400);
    // TODO: marcar como leída
    return ResponseHelper::success("Notificación $id marcada como leída");
  }

  public function delete(Request $request, array $params = [])
  {
    $id = $params['id'] ?? null;
    if (!$id) return ResponseHelper::fail('ID requerido', 400);
    // TODO: eliminar
    return ResponseHelper::success("Notificación $id eliminada");
  }
}
