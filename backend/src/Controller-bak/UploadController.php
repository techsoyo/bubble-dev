<?php declare(strict_types=1);
namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class UploadController extends BaseController
{
  public function upload(Request $request, array $params = [])
  {
    try {
      $file = $request->file('file');
      if (!$file) {
        return ResponseHelper::fail("Archivo no proporcionado (campo 'file')", 400);
      }

      // TODO: mover a storage/uploads
      // move_uploaded_file($file['tmp_name'], BACKEND_ROOT . '/storage/uploads/' . basename($file['name']));

      return ResponseHelper::success('Archivo subido', [
        'name' => $file['name'] ?? null,
        'size' => $file['size'] ?? null,
        'type' => $file['type'] ?? null
      ], 200);
    } catch (\Throwable $e) {
      return ResponseHelper::error('Error subiendo archivo', $e);
    }
  }
}
