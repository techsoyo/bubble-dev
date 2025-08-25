<?php

require_once __DIR__ . '/bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÂ¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÂ¡ticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/database.php';

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
                $st = $pdo->prepare('SELECT * FROM ' . T('news') . ' WHERE id = ?');
                $st->execute([$id]);
                $news = $st->fetch(PDO::FETCH_ASSOC);
                if (!$news) {
                    http_response_code(404);
                    Res::error('Noticia no encontrada', 404);
                }
                Res::success('OK', $news);
            } else {
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
                $offset = ($page - 1) * $limit;
                $st = $pdo->prepare('SELECT * FROM ' . T('news') . ' ORDER BY date_published DESC LIMIT ? OFFSET ?');
                $st->bindValue(1, $limit, PDO::PARAM_INT);
                $st->bindValue(2, $offset, PDO::PARAM_INT);
                $st->execute();
                $newsList = $st->fetchAll(PDO::FETCH_ASSOC);
                Res::success('OK', ['items' => $newsList, 'page' => $page, 'limit' => $limit]);
            }
            break;
        case 'POST': {
            $input = Request::json();
            ['ok' => $ok, 'errors' => $errs] = Val::validate($input, [
                'title' => 'required|string:1,255',
                'summary' => 'required|string:1,1000',
                'content' => 'required|string:1,10000',
                'image' => 'string:0,255',
                'slug' => 'string:0,255'
            ]);
            if (!$ok) {
                Res::error('ValidaciÃƒÂ³n fallida', 422, ['errors' => $errs]);
            }
            $id = 'news-' . uniqid();
            $slug = $input['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $input['title']));
            $st = $pdo->prepare('INSERT INTO ' . T('news') . ' (id, title, summary, content, image, date_published, slug) VALUES (?, ?, ?, ?, ?, NOW(), ?)');
            $st->execute([
                $id,
                $input['title'],
                $input['summary'],
                $input['content'],
                $input['image'] ?? null,
                $slug
            ]);
            Res::success('Noticia creada', ['id' => $id], 201);
            break;
        }
        case 'PUT': {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                Res::error('ID de noticia requerido', 400);
            }
            $input = Request::json();
            $fields = ['title', 'summary', 'content', 'image', 'slug'];
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
            $update[] = 'date_published = NOW()';
            $params[] = $id;
            $st = $pdo->prepare('UPDATE ' . T('news') . ' SET ' . implode(', ', $update) . ' WHERE id = ?');
            $ok = $st->execute($params);
            if ($ok) {
                Res::success('Noticia actualizada');
            } else {
                Res::error('Error al actualizar noticia', 500);
            }
            break;
        }
        case 'DELETE': {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                Res::error('ID de noticia requerido', 400);
            }
            $st = $pdo->prepare('DELETE FROM ' . T('news') . ' WHERE id = ?');
            $ok = $st->execute([$id]);
            if ($ok) {
                Res::success('Noticia eliminada');
            } else {
                Res::error('Error al eliminar noticia', 500);
            }
            break;
        }
        default:
            Res::error('MÃƒÂ©todo no permitido', 405);
    }
} catch (Throwable $e) {
    Res::exception($e);
}

