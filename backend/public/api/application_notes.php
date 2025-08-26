<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Security\CsrfMiddleware;
use Models\ApplicationNote;
use Utils\Request;
use Utils\ResponseHelper as Res;
use Utils\Validator as Val;

// JWTMiddleware usa alias global del bootstrap
JWTMiddleware::requireAuth();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
  CsrfMiddleware::protect();
}

// En producción NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
  if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
  }
}

$noteModel = new ApplicationNote();
try {
  switch ($method) {
    case 'GET': {
        $applicationId = $_GET['application_id'] ?? $_GET['applicationId'] ?? null;
        ['ok' => $ok, 'errors' => $errs] = Val::validate(['application_id' => $applicationId], [
          'application_id' => 'required|string:1,36'
        ]);
        if (!$ok) {
          Res::fail('Validación fallida', 422, ['errors' => $errs]);
        }
        // Ownership: aquí podrías validar que el usuario tiene acceso a la aplicación
        $notes = $noteModel->findByApplicationId($applicationId);
        Res::success('OK', ['items' => $notes]);
        break;
      }

    case 'POST': {
        $input = Request::json();
        ['ok' => $ok, 'errors' => $errs] = Val::validate($input, [
          'application_id' => 'required|string:1,36',
          'note'           => 'required|string:1,1000'
        ]);
        if (!$ok) {
          Res::fail('Validación fallida', 422, ['errors' => $errs]);
        }
        // Ownership: aquí podrías validar que el usuario tiene acceso a la aplicación
        $existingNotes = $noteModel->findByApplicationId($input['application_id']);
        $nextIdx = 0;
        foreach ($existingNotes as $n) {
          if ((int)$n['note_idx'] >= $nextIdx) {
            $nextIdx = (int)$n['note_idx'] + 1;
          }
        }

        $data = [
          'application_id' => $input['application_id'],
          'note_idx' => $nextIdx,
          'note' => $input['note']
        ];
        $noteModel->store($data);
        Res::success('Nota agregada', [], 201);
        break;
      }
    default:
      Res::fail('Método no permitido', 405);
  }
} catch (\Throwable $e) {
  Res::error('Error interno del servidor', $e, 500);
}
