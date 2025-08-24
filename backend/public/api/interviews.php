<?php

require_once __DIR__ . '/bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÂ¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÂ¡ticamente en bootstrap.php

use Utils\JWT;
use Utils\Request;
use Utils\ResponseHelper as Res;
use Utils\Validator as Val;

if (!function_exists('db')) {
    function db(): PDO
    {
        return $GLOBALS['pdo'];
    }
}
if (!function_exists('T')) {
    function T(string $n): string
    {
        return 'bt_' . $n;
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$authUser = null;
try {
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        $authUser = JWT::requireAuth();
    }
} catch (\Throwable $e) {
    Res::error('No autorizado', 401, ['detail' => $e->getMessage()]);
}

try {
    $pdo = db();
    switch ($method) {
        case 'GET': {
            if (isset($_GET['id'])) {
                $id = $_GET['id'];
                $st = $pdo->prepare('SELECT * FROM ' . T('interviews') . ' WHERE id = ?');
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    Res::error('Entrevista no encontrada', 404);
                }
                Res::success('OK', $row);
            } elseif (isset($_GET['applicationId'])) {
                $appId = $_GET['applicationId'];
                $st = $pdo->prepare('SELECT * FROM ' . T('interviews') . ' WHERE application_id = ? ORDER BY scheduled_at DESC');
                $st->execute([$appId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                Res::success('OK', ['items' => $rows]);
            } else {
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
                $offset = ($page - 1) * $limit;
                $st = $pdo->prepare('SELECT * FROM ' . T('interviews') . ' ORDER BY scheduled_at DESC LIMIT ? OFFSET ?');
                $st->bindValue(1, $limit, PDO::PARAM_INT);
                $st->bindValue(2, $offset, PDO::PARAM_INT);
                $st->execute();
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                Res::success('OK', ['items' => $rows, 'page' => $page, 'limit' => $limit]);
            }
            break;
        }
        case 'POST': {
            $payload = Request::json();
            ['ok' => $ok, 'errors' => $errs] = Val::validate($payload, [
                'application_id' => 'required|string:1,36',
                'scheduled_at' => 'required|date',
                'duration_minutes' => 'required|int:1,1440',
                'recruiter_id' => 'string:0,36',
                'location' => 'string:0,255',
                'type' => 'string:0,50',
                'status' => 'string:0,50',
                'notes' => 'string:0,1000',
                'meeting_link' => 'string:0,255'
            ]);
            if (!$ok) {
                Res::error('ValidaciÃƒÂ³n fallida', 422, ['errors' => $errs]);
            }
            $id = 'int-' . uniqid();
            $st = $pdo->prepare('INSERT INTO ' . T('interviews') . ' (id, application_id, recruiter_id, scheduled_at, duration_minutes, location, type, status, notes, meeting_link, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $st->execute([
                $id,
                $payload['application_id'],
                $payload['recruiter_id'] ?? null,
                $payload['scheduled_at'],
                $payload['duration_minutes'],
                $payload['location'] ?? null,
                $payload['type'] ?? null,
                $payload['status'] ?? 'scheduled',
                $payload['notes'] ?? null,
                $payload['meeting_link'] ?? null
            ]);
            Res::success('Entrevista creada', ['id' => $id], 201);
            break;
        }
        case 'PUT': {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                Res::error('ID requerido', 400);
            }
            $payload = Request::json();
            $fields = ['application_id', 'recruiter_id', 'scheduled_at', 'duration_minutes', 'location', 'type', 'status', 'notes', 'meeting_link'];
            $update = [];
            $params = [];
            foreach ($fields as $f) {
                if (isset($payload[$f])) {
                    $update[] = "$f = ?";
                    $params[] = $payload[$f];
                }
            }
            if (!$update) {
                Res::error('Nada para actualizar', 400);
            }
            $update[] = 'updated_at = NOW()';
            $params[] = $id;
            $st = $pdo->prepare('UPDATE ' . T('interviews') . ' SET ' . implode(', ', $update) . ' WHERE id = ?');
            $ok = $st->execute($params);
            if ($ok) {
                Res::success('Entrevista actualizada');
            } else {
                Res::error('Error al actualizar', 500);
            }
            break;
        }
        case 'DELETE': {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                Res::error('ID requerido', 400);
            }
            $st = $pdo->prepare('DELETE FROM ' . T('interviews') . ' WHERE id = ?');
            $ok = $st->execute([$id]);
            if ($ok) {
                Res::success('Entrevista eliminada');
            } else {
                Res::error('Error al eliminar', 500);
            }
            break;
        }
        default:
            Res::error('MÃƒÂ©todo no permitido', 405);
    }
} catch (\Throwable $e) {
    Res::exception($e);
}

