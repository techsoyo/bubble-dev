<?php

require_once __DIR__ . '/bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÂ¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÂ¡ticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../src/Services/NotificationService.php';

use Services\NotificationService;
use Utils\Request;
use Utils\ResponseHelper as Res;
use Utils\Validator as Val;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        Res::error('MÃƒÂ©todo no permitido', 405);
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
        Res::error('ValidaciÃƒÂ³n fallida', 422, ['errors' => $errs]);
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
        Res::success('NotificaciÃƒÂ³n enviada', null, 201);
    } else {
        Res::error('No se pudo enviar la notificaciÃƒÂ³n', 500);
    }
} catch (Throwable $e) {
    Res::error('Error', 500);
}

