<?php

require_once __DIR__ . '/../bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automáticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php

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
        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido', 'data' => null]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}
