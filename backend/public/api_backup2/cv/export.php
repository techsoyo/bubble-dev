<?php

/**
 * GET /api/cv/export/{candidate_id}
 * Requiere AUTH si REQUIRE_AUTH_FOR_CONFIRM=true.
 * Controlado por flag CV_ALLOW_EXPORT_JSON=true.
 */

declare(strict_types=1);
require_once $BOOT;

use Utils\Auth;
use Utils\Cors;
use Utils\Log;
use Utils\RequestId;

$__start = microtime(true);

if (class_exists('Utils\\RequestId')) {
    RequestId::init();
}
if (class_exists('Utils\\Cors')) {
    Cors::enforce(['GET', 'OPTIONS']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    jsonResponse(405, ['success' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'MÃ©todo no permitido']]);
}

$allow = (getenv('CV_ALLOW_EXPORT_JSON') === 'true') || (($_ENV['CV_ALLOW_EXPORT_JSON'] ?? '') === 'true');
if (!$allow) {
    jsonResponse(403, ['success' => false, 'error' => ['code' => 'EXPORT_DISABLED', 'message' => 'ExportaciÃ³n deshabilitada']]);
}

if (class_exists('Utils\\Auth')) {
    Auth::enforceConfirmAuth();
}

$pdo = getDbConnection();

// Extraer candidate_id de la URL (router simple)
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (!preg_match('#/api/cv/export/(\d+)#', $uri, $m)) {
    jsonResponse(400, ['success' => false, 'error' => ['code' => 'INVALID_ID', 'message' => 'ID invÃ¡lido']]);
}
$id = (int)$m[1];

try {
    $stmt = $pdo->prepare('SELECT id,name as nombre,email,phone as telefono,location as ubicacion_actual,date_of_birth as fecha_nacimiento,linkedin_url as linkedin,portfolio_url as portfolio,created_at,updated_at FROM bt_candidates WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonResponse(404, ['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'No encontrado']]);
    }

    // Hijos
    $exp = $pdo->prepare('SELECT company as empresa, position as puesto, start_date as fecha_inicio, end_date as fecha_fin, current as actual, description as descripcion, location as ubicacion FROM bt_candidate_experiences WHERE candidate_id=? ORDER BY start_date DESC');
    $exp->execute([$id]);
    $edu = $pdo->prepare('SELECT institution_name as institucion, degree_title as titulo, start_date as fecha_inicio, end_date as fecha_fin, description as descripcion FROM bt_candidate_education WHERE candidate_id=? ORDER BY start_date DESC');
    $edu->execute([$id]);
    $proj = $pdo->prepare('SELECT nombre, descripcion, tecnologias FROM bt_candidate_projects WHERE candidate_id=?');
    $proj->execute([$id]);

    $data = $row + [
      'puestos_anteriores' => $exp->fetchAll(PDO::FETCH_ASSOC),
      'educacion' => $edu->fetchAll(PDO::FETCH_ASSOC),
      'proyectos' => array_map(function ($p) {
          $p['tecnologias'] = $p['tecnologias'] ? json_decode($p['tecnologias'], true) : [];
          return $p;
      }, $proj->fetchAll(PDO::FETCH_ASSOC)),
      'exported_at' => gmdate('c')
    ];

    if (class_exists('Utils\\Log')) {
        Log::json('info', ['event' => 'cv_admin', 'action' => 'export', 'candidate_id' => $id, 'duration_ms' => (int)round((microtime(true) - $__start) * 1000)]);
    }
    jsonResponse(200, ['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    if (class_exists('Utils\\Log')) {
        Log::json('error', ['event' => 'cv_admin', 'action' => 'export', 'error' => substr($e->getMessage(), 0, 120)]);
    }
    jsonResponse(500, ['success' => false, 'error' => ['code' => 'EXPORT_ERROR', 'message' => 'Error exportando']]);
}
