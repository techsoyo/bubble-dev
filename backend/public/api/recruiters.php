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

// cookie HttpOnly obligatoria

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
declare(strict_types=1);

// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../src/Middleware/SecurityMiddleware.php';

use Middleware\SecurityMiddleware as Sec;
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
            $id = $_GET['id'] ?? null;
            if ($id) {
                ['ok' => $ok, 'errors' => $errs] = Val::validate(['id' => $id], [
                    'id' => 'required|string:1,36|regex:/^rec-\w+$/'
                ]);
                if (!$ok) {
                    Res::error('ValidaciÃƒÆ’Ã‚Â³n fallida', 422, ['errors' => $errs]);
                }
                $st = $pdo->prepare('SELECT * FROM ' . T('staff_profiles') . ' WHERE id = ?');
                $st->execute([$id]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                if (!$r) {
                    Res::error('Reclutador no encontrado', 404);
                }
                $out = [
                    'id' => $r['id'],
                    'first_name' => $r['first_name'],
                    'last_name' => $r['last_name'],
                    'email' => $r['email'],
                    'phone' => $r['phone'],
                    'company' => $r['company_name'],
                    'department' => $r['department'],
                    'role' => $r['role'],
                    'avatar' => $r['avatar'] ?? null,
                    'status' => $r['status'],
                    'created' => $r['created_at'],
                ];
                Res::success('OK', $out);
            } else {
                $where = [];
                $params = [];
                if (isset($_GET['status'])) {
                    $where[] = 'status = ?';
                    $params[] = $_GET['status'];
                }
                if (isset($_GET['company'])) {
                    $where[] = 'company_name LIKE ?';
                    $params[] = '%' . $_GET['company'] . '%';
                }
                $W = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
                $st = $pdo->prepare('SELECT * FROM ' . T('staff_profiles') . " $W ORDER BY created_at DESC");
                $st->execute($params);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                $out = array_map(function ($r) {
                    return [
                        'id' => $r['id'],
                        'first_name' => $r['first_name'],
                        'last_name' => $r['last_name'],
                        'email' => $r['email'],
                        'phone' => $r['phone'],
                        'company' => $r['company_name'],
                        'department' => $r['department'],
                        'role' => $r['role'],
                        'avatar' => $r['avatar'] ?? null,
                        'status' => $r['status'],
                        'created' => $r['created_at'],
                    ];
                }, $rows);
                Res::success('OK', ['items' => $out]);
            }
            break;
        }
        case 'POST': {
            $payload = Request::json();
            ['ok' => $ok, 'errors' => $errs] = Val::validate($payload, [
                'first_name' => 'required|string:1,100',
                'last_name' => 'required|string:1,100',
                'email' => 'required|email',
                'phone' => 'string:0,30',
                'company' => 'required|string:1,255',
                'department' => 'required|string:1,100',
                'role' => 'required|string:1,50',
                'status' => 'required|string:1,20',
                'avatar' => 'string:0,255'
            ]);
            if (!$ok) {
                Res::error('ValidaciÃƒÆ’Ã‚Â³n fallida', 422, ['errors' => $errs]);
            }
            Sec::assertAdmin($authUser); // Solo admin puede crear reclutadores
            $id = 'rec-' . uniqid();
            $st = $pdo->prepare('INSERT INTO ' . T('staff_profiles') . ' (id, first_name, last_name, email, phone, company_name, department, role, status, avatar, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $ok = $st->execute([
                $id,
                trim($payload['first_name']),
                trim($payload['last_name']),
                strtolower(trim($payload['email'])),
                $payload['phone'] ?? '',
                trim($payload['company']),
                trim($payload['department']),
                trim($payload['role']),
                trim($payload['status']),
                $payload['avatar'] ?? null
            ]);
            if ($ok) {
                Res::success('Reclutador creado', ['id' => $id], 201);
            } else {
                Res::error('Error al crear reclutador', 500);
            }
            break;
        }
        case 'PUT': {
            $payload = Request::json();
            $id = $_GET['id'] ?? null;
            ['ok' => $ok, 'errors' => $errs] = Val::validate(['id' => $id], [
                'id' => 'required|string:1,36|regex:/^rec-\w+$/'
            ]);
            if (!$ok) {
                Res::error('ID invÃƒÆ’Ã‚Â¡lido', 422, ['errors' => $errs]);
            }
            Sec::assertAdmin($authUser); // Solo admin puede editar reclutadores
            $fields = ['first_name', 'last_name', 'email', 'phone', 'company', 'department', 'role', 'status', 'avatar'];
            $update = [];
            $params = [];
            foreach ($fields as $f) {
                if (isset($payload[$f])) {
                    $col = $f === 'company' ? 'company_name' : $f;
                    $update[] = "$col = ?";
                    $params[] = $payload[$f];
                }
            }
            if (!$update) {
                Res::error('Nada para actualizar', 400);
            }
            $update[] = 'updated_at = NOW()';
            $params[] = $id;
            $st = $pdo->prepare('UPDATE ' . T('staff_profiles') . ' SET ' . implode(', ', $update) . ' WHERE id = ?');
            $ok = $st->execute($params);
            if ($ok) {
                Res::success('Reclutador actualizado');
            } else {
                Res::error('Error al actualizar', 500);
            }
            break;
        }
        case 'DELETE': {
            $id = $_GET['id'] ?? null;
            ['ok' => $ok, 'errors' => $errs] = Val::validate(['id' => $id], [
                'id' => 'required|string:1,36|regex:/^rec-\w+$/'
            ]);
            if (!$ok) {
                Res::error('ID invÃƒÆ’Ã‚Â¡lido', 422, ['errors' => $errs]);
            }
            Sec::assertAdmin($authUser); // Solo admin puede eliminar reclutadores
            $st = $pdo->prepare('DELETE FROM ' . T('staff_profiles') . ' WHERE id = ?');
            $ok = $st->execute([$id]);
            if ($ok) {
                Res::success('Reclutador eliminado');
            } else {
                Res::error('Error al eliminar', 500);
            }
            break;
        }
        default:
            Res::error('MÃƒÆ’Ã‚Â©todo no permitido', 405);
    }
} catch (\Throwable $e) {
    Res::exception($e);
}


