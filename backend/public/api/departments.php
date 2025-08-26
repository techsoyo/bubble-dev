<?php declare(strict_types=1);
require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// cookie HttpOnly obligatoria

// En producciÃƒÂ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
require_once __DIR__ . '/../../config/database.php';

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
                $id = (int)$_GET['id'];
                $st = $pdo->prepare('SELECT * FROM ' . T('departments') . ' WHERE id = ?');
                $st->execute([$id]);
                $dept = $st->fetch(PDO::FETCH_ASSOC);
                if (!$dept) {
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'message' => 'Departamento no encontrado', 'data' => null]);
                    exit;
                }
                $catSt = $pdo->prepare('SELECT * FROM ' . T('department_categories') . ' WHERE department_id = ? ORDER BY id ASC');
                $catSt->execute([$id]);
                $dept['categories'] = $catSt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['ok' => true, 'message' => 'OK', 'data' => $dept]);
            } else {
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 100;
                $offset = ($page - 1) * $limit;
                $st = $pdo->prepare('SELECT * FROM ' . T('departments') . ' ORDER BY id ASC LIMIT ? OFFSET ?');
                $st->bindValue(1, $limit, PDO::PARAM_INT);
                $st->bindValue(2, $offset, PDO::PARAM_INT);
                $st->execute();
                $departments = $st->fetchAll(PDO::FETCH_ASSOC);
                foreach ($departments as &$dept) {
                    $catSt = $pdo->prepare('SELECT * FROM ' . T('department_categories') . ' WHERE department_id = ? ORDER BY id ASC');
                    $catSt->execute([$dept['id']]);
                    $dept['categories'] = $catSt->fetchAll(PDO::FETCH_ASSOC);
                }
                echo json_encode(['ok' => true, 'message' => 'OK', 'data' => $departments, 'page' => $page, 'limit' => $limit]);
            }
            break;
        case 'POST':
            // En producciÃ³n, insertar en BD real
            if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
                http_response_code(501);
                echo json_encode(['error' => 'Department creation not implemented']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $department = [
                'id' => 1001, // ID fijo para desarrollo
                'name' => $input['name'] ?? 'Nuevo Departamento',
                'description' => $input['description'] ?? 'DescripciÃ³n del departamento',
                'active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ];
            echo json_encode(['ok' => true, 'message' => 'Departamento creado exitosamente (dev)', 'data' => $department]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'MÃƒÆ’Ã‚Â©todo no permitido', 'data' => null]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}
