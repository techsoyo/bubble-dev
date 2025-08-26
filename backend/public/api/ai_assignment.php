<?php

declare(strict_types=1);

use Security\CsrfMiddleware;

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


use Utils\JWT;
use Utils\Request;
use Utils\ResponseHelper as Res;
use Utils\Validator as Val;

// helpers mÃƒÆ’Ã‚Â­nimos si tu bootstrap no los define
if (!function_exists('db')) {
    function db(): PDO
    {
        return $GLOBALS['pdo'];
    }
}
if (!function_exists('T')) {
    function T(string $name): string
    {
        return 'bt_' . $name;
    }
}

try {
    // exige token vÃƒÆ’Ã‚Â¡lido (recruiter/sistema)
    JWT::requireAuth();

    $payload = Request::json();

    // ValidaciÃƒÆ’Ã‚Â³n fuerte con tu Validator (sin Val::int inexistente)
    ['ok' => $ok, 'errors' => $errors] = Val::validate($payload, [
        'candidate_id' => 'required|string:1,36|regex:/^cnd-\d+$/',
        'skills'       => 'required|array',
    ]);
    if (!$ok) {
        Res::error('ValidaciÃ³n fallida', null, 422);
    }

    // Normaliza skills
    $skills = array_values(array_filter(array_map(function ($s) {
        return is_string($s) ? strtolower(trim($s)) : '';
    }, $payload['skills'])));

    if (!$skills) {
        Res::error('skills debe ser array no vacÃ­o', null, 422);
    }

    $pdo = db();
    $pdo->beginTransaction();

    // 1) Intento de mapeo por tabla (si existe): bt_skill_department_map
    //    Si no existe o no hay match, usa fallback.
    $categoryId = null;
    $departmentId = null;

    try {
        if (!empty($skills)) {
            $in = implode(',', array_fill(0, count($skills), '?'));
            $sql = "SELECT department_category_id, department_id
                FROM bt_skill_department_map
               WHERE skill IN ($in)
               GROUP BY department_category_id, department_id
               ORDER BY COUNT(*) DESC
               LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute($skills);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $categoryId  = (int)$row['department_category_id'];
                $departmentId = (int)$row['department_id'];
            }
        }
    } catch (\Throwable $e) {
        // si no existe la tabla o falla la query, seguimos con fallback
    }

    // Fallback simple (ajÃƒÆ’Ã‚Âºstalo a tu dominio)
    if (!$categoryId || !$departmentId) {
        // ejemplo de heurÃƒÆ’Ã‚Â­stica mÃƒÆ’Ã‚Â­nima
        $skillMap = [
            'javascript' => ['category_id' => 1, 'department_id' => 10],
            'php'        => ['category_id' => 1, 'department_id' => 11],
            'marketing'  => ['category_id' => 2, 'department_id' => 20],
        ];
        foreach ($skills as $s) {
            if (isset($skillMap[$s])) {
                $categoryId  = $skillMap[$s]['category_id'];
                $departmentId = $skillMap[$s]['department_id'];
                break;
            }
        }
        if (!$categoryId || !$departmentId) {
            $categoryId  = 99;
            $departmentId = 990;
        }
    }

    // 2) SelecciÃƒÆ’Ã‚Â³n automÃƒÆ’Ã‚Â¡tica de recruiter (si tienes vista/tabla de carga, ÃƒÆ’Ã‚Âºsala)
    $recruiterId = null;
    try {
        $st = $pdo->prepare('SELECT recruiter_id
                           FROM vw_recruiter_load
                          WHERE department_id = ?
                          ORDER BY active_candidates ASC
                          LIMIT 1');
        $st->execute([$departmentId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        $recruiterId = $r['recruiter_id'] ?? null;
    } catch (\Throwable $e) {
        // si no existe la vista, lo dejamos en null o pon un ID fijo si prefieres
        $recruiterId = null; // o 1
    }

    // 3) Persistir routing y actualizar candidate
    //    Nota: ajusta tipos si tu ID de candidato es UUID (char(36))

    $insertRouting = $pdo->prepare('
    INSERT INTO ' . T('candidate_routing') . " 
      (id, candidate_id, department_category_id, department_id, recruiter_id, source, reason, assigned_at, created_at)
    VALUES (UUID(), ?, ?, ?, ?, 'ai', 'ai_assignment', NOW(), NOW())
  ");
    $insertRouting->execute([
        (string)$payload['candidate_id'],
        $categoryId,
        $departmentId,
        $recruiterId
    ]);

    $upd = $pdo->prepare('UPDATE ' . T('candidates') . ' SET department_id = ? WHERE id = ?');
    $upd->execute([$departmentId, (string)$payload['candidate_id']]);

    $pdo->commit();

    Res::success('AsignaciÃƒÆ’Ã‚Â³n realizada', [
        'category_id'   => $categoryId,
        'department_id' => $departmentId,
        'recruiter_id'  => $recruiterId
    ], 201);
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Res::exception($e);
}

