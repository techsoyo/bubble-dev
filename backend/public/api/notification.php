<?php



require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../src/Services/NotificationService.php';

use Services\NotificationService;
use Utils\Request;
use Utils\ResponseHelper as Res;
use Utils\Validator as Val;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        Res::error('MÃƒÆ’Ã‚Â©todo no permitido', 405);
    }
    $input = Request::json();
    ['ok' => $ok, 'errors' => $errs] = Val::validate($input, [
      'type'    => 'required|string:2,10|in:email,sms,push',
      'to'      => 'required|string:3,255',
      'subject' => 'string:0,255',
      'body'    => 'string:0,2000',
      'message' => 'string:0,2000'
    ]);
    if (!$ok) {
        Res::error('ValidaciÃƒÆ’Ã‚Â³n fallida', 422, ['errors' => $errs]);
    }
    $service = new NotificationService();
    $type = $input['type'];
    $to = $input['to'];
    $subject = $input['subject'] ?? '';
    $body = $input['body'] ?? '';
    $message = $input['message'] ?? '';
    $result = false;
    if ($type === 'email') {
        $result = $service->sendEmail($to, $subject, $body);
    } elseif ($type === 'sms') {
        $result = $service->sendSMS($to, $message);
    } elseif ($type === 'push') {
        $result = $service->sendPush($to, $message);
    }
    if ($result) {
        Res::success('NotificaciÃƒÆ’Ã‚Â³n enviada', null, 201);
    } else {
        Res::error('No se pudo enviar la notificaciÃƒÆ’Ã‚Â³n', 500);
    }
} catch (Throwable $e) {
    Res::error('Error', 500);
}


