<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automáticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php

// ...lógica propia del endpoint...
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Utils/ResponseHelper.php';
require_once __DIR__ . '/../../src/Utils/Validator.php';
require_once __DIR__ . '/../../src/Utils/Request.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use Utils\Request;
use Utils\ResponseHelper as Res;
use Utils\Validator as Val;

if (!function_exists('db')) {
    function db(): PDO
    {
        return getDbConnection();
    }
}
if (!function_exists('T')) {
    function T(string $n): string
    {
        return 'bt_' . $n;
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    $pdo = db();
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = $_GET['id'];
                $st = $pdo->prepare('SELECT * FROM ' . T('culture') . ' WHERE id = ?');
                $st->execute([$id]);
                $record = $st->fetch(PDO::FETCH_ASSOC);
                if (!$record) {
                    http_response_code(404);
                    Res::error('Registro no encontrado', 404);
                }
                Res::success('OK', $record);
            } else {
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
                $offset = ($page - 1) * $limit;
                $st = $pdo->prepare('SELECT * FROM ' . T('culture') . ' ORDER BY sort_order ASC LIMIT ? OFFSET ?');
                $st->bindValue(1, $limit, PDO::PARAM_INT);
                $st->bindValue(2, $offset, PDO::PARAM_INT);
                $st->execute();
                $records = $st->fetchAll(PDO::FETCH_ASSOC);
                Res::success('OK', ['items' => $records, 'page' => $page, 'limit' => $limit]);
            }
            break;
        case 'POST': {
                $input = Request::json();
                ['ok' => $ok, 'errors' => $errs] = Val::validate($input, [
                    'title' => 'required|string:1,255',
                    'description' => 'required|string:1,2000',
                    'image' => 'string:0,255',
                    'sort_order' => 'int:0,1000'
                ]);
                if (!$ok) {
                    Res::error('Validación fallida', 422, ['errors' => $errs]);
                }
                $st = $pdo->prepare('INSERT INTO ' . T('culture') . ' (title, description, image, sort_order) VALUES (?, ?, ?, ?)');
                $st->execute([
                    $input['title'],
                    $input['description'],
                    $input['image'] ?? null,
                    $input['sort_order'] ?? 0
                ]);
                Res::success('Registro creado', null, 201);
                break;
            }
        case 'PUT': {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    Res::error('ID requerido', 400);
                }
                $input = Request::json();
                $fields = ['title', 'description', 'image', 'sort_order'];
                $update = [];
                $params = [];
                foreach ($fields as $f) {
                    if (isset($input[$f])) {
                        $update[] = "$f = ?";
                        $params[] = $input[$f];
                    }
                }
                if (!$update) {
                    Res::error('Nada para actualizar', 400);
                }
                $params[] = $id;
                $st = $pdo->prepare('UPDATE ' . T('culture') . ' SET ' . implode(', ', $update) . ' WHERE id = ?');
                $ok = $st->execute($params);
                if ($ok) {
                    Res::success('Registro actualizado');
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
                $st = $pdo->prepare('DELETE FROM ' . T('culture') . ' WHERE id = ?');
                $ok = $st->execute([$id]);
                if ($ok) {
                    Res::success('Registro eliminado');
                } else {
                    Res::error('Error al eliminar', 500);
                }
                break;
            }
        default:
            Res::error('Método no permitido', 405);
    }
} catch (Throwable $e) {
    Res::exception($e);
}
