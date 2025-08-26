<?php declare(strict_types=1);



require_once __DIR__ . '/../bootstrap.php';
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

// Proteger solo mÃƒÂ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    // double-submit cookie
}

// En producciÃƒÂ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
/**
 * DELETE /api/cv/{candidate_id}
 * Requiere AUTH. Controlado por CV_ALLOW_DELETE_REQUEST=true.
 */

declare(strict_types=1);

use Utils\Auth;
use Utils\Cors;
use Utils\Log;
use Utils\RequestId;

$__start = microtime(true);
if (class_exists('Utils\\RequestId')) {
    RequestId::init();
}
if (class_exists('Utils\\Cors')) {
    Cors::enforce(['DELETE', 'OPTIONS']);
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'DELETE';
if ($method !== 'DELETE') {
    jsonResponse(405, ['success' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'MÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©todo no permitido']]);
}

$allow = (getenv('CV_ALLOW_DELETE_REQUEST') === 'true') || (($_ENV['CV_ALLOW_DELETE_REQUEST'] ?? '') === 'true');
if (!$allow) {
    jsonResponse(403, ['success' => false, 'error' => ['code' => 'DELETE_DISABLED', 'message' => 'Borrado deshabilitado']]);
}

if (class_exists('Utils\\Auth')) {
    Auth::enforceConfirmAuth();
}

$pdo = getDbConnection();
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (!preg_match('#/api/cv/(\d+)$#', $uri, $m)) {
    jsonResponse(400, ['success' => false, 'error' => ['code' => 'INVALID_ID', 'message' => 'ID invÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lido']]);
}
$id = (int)$m[1];
try {
    $pdo->beginTransaction();
    // Dependiendo de FKs con ON DELETE CASCADE solo borrar padre; sino eliminar manualmente.
    $delParent = $pdo->prepare('DELETE FROM bt_candidates WHERE id=?');
    $delParent->execute([$id]);
    $affected = $delParent->rowCount();
    $pdo->commit();
    if ($affected === 0) {
        jsonResponse(404, ['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'No encontrado']]);
    }
    if (class_exists('Utils\\Log')) {
        Log::json('info', ['event' => 'cv_admin', 'action' => 'delete', 'candidate_id' => $id, 'duration_ms' => (int)round((microtime(true) - $__start) * 1000)]);
    }
    jsonResponse(200, ['success' => true, 'data' => ['deleted' => true, 'candidate_id' => $id]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (class_exists('Utils\\Log')) {
        Log::json('error', ['event' => 'cv_admin', 'action' => 'delete', 'error' => substr($e->getMessage(), 0, 120)]);
    }
    jsonResponse(500, ['success' => false, 'error' => ['code' => 'DELETE_ERROR', 'message' => 'Error eliminando']]);
}

