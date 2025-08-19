<?php

/**
 * POST /api/applications
 * Crea una postulación en bt_applications. Si el candidato no tiene routing vigente,
 * ejecuta asignación automática (skills -> department_category_id -> department_id -> recruiter_id)
 * y persiste en bt_candidate_routing antes de insertar la aplicación.
 *
 * Requiere .env cargado por bootstrap.php y variables:
 * DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_TABLE_PREFIX
 */

require_once __DIR__ . '/../bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automáticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php
function jsend($ok, $message, $data = null, $code = 200): void
{
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function body_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}
function uuid_like(?string $v): bool
{
    return is_string($v) && strlen($v) > 0 && strlen($v) <= 36;
}

/* ========= PDO & env ========= */
function pdo(): PDO
{
    static $pdo;
    if ($pdo) {
        return $pdo;
    }

    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $usr  = getenv('DB_USER');
    $pwd  = getenv('DB_PASS');
    $dsn  = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $usr, $pwd, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}
function T(string $raw): string
{
    // table name with prefix
    $p = getenv('DB_TABLE_PREFIX') ?: '';
    return $p . $raw;
}

/* ========= Dominio: consultas auxiliares ========= */
function candidateExists(PDO $db, string $candidateId): bool
{
    $st = $db->prepare('SELECT 1 FROM ' . T('candidates') . ' WHERE id = ? LIMIT 1');
    $st->execute([$candidateId]);
    return (bool)$st->fetchColumn();
}
function jobExists(PDO $db, string $jobId): bool
{
    $st = $db->prepare('SELECT 1 FROM ' . T('jobs') . ' WHERE id = ? LIMIT 1');
    $st->execute([$jobId]);
    return (bool)$st->fetchColumn();
}
function getLatestRouting(PDO $db, string $candidateId): ?array
{
    $sql = 'SELECT r.* FROM ' . T('candidate_routing') . ' r
          WHERE r.candidate_id = ?
          ORDER BY r.assigned_at DESC
          LIMIT 1';
    $st = $db->prepare($sql);
    $st->execute([$candidateId]);
    $row = $st->fetch();
    return $row ?: null;
}
function getCandidateSkills(PDO $db, string $candidateId): array
{
    // Si no tienes bt_candidate_skills, devuelve [].
    if (!tableExists($db, T('candidate_skills'))) {
        return [];
    }
    $st = $db->prepare('SELECT skill FROM ' . T('candidate_skills') . ' WHERE candidate_id = ? ORDER BY skill ASC');
    $st->execute([$candidateId]);
    return array_map(fn ($r) => $r['skill'], $st->fetchAll());
}
function tableExists(PDO $db, string $table): bool
{
    $st = $db->prepare('SHOW TABLES LIKE ?');
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}
function mapSkillsToDept(PDO $db, array $skills): ?array
{
    // 1) Mapping por tabla dedicada si existe
    if (tableExists($db, T('skill_department_map')) && !empty($skills)) {
        $in = implode(',', array_fill(0, count($skills), '?'));
        $sql = 'SELECT department_category_id, department_id
            FROM ' . T('skill_department_map') . "
            WHERE skill IN ($in)
            LIMIT 1";
        $st  = $db->prepare($sql);
        $st->execute(array_map('strval', $skills));
        $m = $st->fetch();
        if ($m) {
            return [
                'department_category_id' => (int)$m['department_category_id'],
                'department_id'          => (int)$m['department_id'],
                'source'                 => 'ai',
                'reason'                 => 'skill_department_map'
            ];
        }
    }

    // 2) Heurística de fallback mínima (ajusta a tu catálogo real)
    $skills_l = array_map(fn ($s) => mb_strtolower(trim($s)), $skills);
    $isBackend = array_intersect($skills_l, ['php', 'node.js', 'node', 'javascript', 'mysql', 'laravel', 'symfony', 'api']);
    $isFrontend = array_intersect($skills_l, ['react', 'vue', 'next.js', 'typescript', 'html', 'css', 'sass']);
    $isData    = array_intersect($skills_l, ['python', 'pandas', 'kubernetes', 'airflow', 'spark', 'ml', 'machine learning']);
    if (!empty($isBackend)) {
        return ['department_category_id' => 3, 'department_id' => 2, 'source' => 'ai', 'reason' => 'heuristic:backend'];
    }
    if (!empty($isFrontend)) {
        return ['department_category_id' => 4, 'department_id' => 2, 'source' => 'ai', 'reason' => 'heuristic:frontend'];
    }
    if (!empty($isData)) {
        return ['department_category_id' => 5, 'department_id' => 2, 'source' => 'ai', 'reason' => 'heuristic:data'];
    }

    return null; // sin mapping -> abortar con 422
}
function pickRecruiter(PDO $db, int $departmentId): ?string
{
    // La vista es sin prefijo: vw_recruiter_load
    $st = $db->prepare(
        'SELECT recruiter_id 
       FROM vw_recruiter_load 
      WHERE department_id = ? 
      ORDER BY active_candidates ASC 
      LIMIT 1'
    );
    $st->execute([$departmentId]);
    $r = $st->fetch();
    return $r['recruiter_id'] ?? null;
}
function createRouting(PDO $db, string $candidateId, int $departmentCategoryId, int $departmentId, ?string $recruiterId, string $source, string $reason): string
{
    $id = generateId();
    $sql = 'INSERT INTO ' . T('candidate_routing') . ' 
          (id, candidate_id, department_category_id, department_id, recruiter_id, source, reason, assigned_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())';
    $st  = $db->prepare($sql);
    $st->execute([$id, $candidateId, $departmentCategoryId, $departmentId, $recruiterId, $source, $reason]);
    return $id;
}
function createApplication(PDO $db, array $app): string
{
    $id = $app['id'] ?? generateId();
    $sql = 'INSERT INTO ' . T('applications') . ' 
          (id, job_id, candidate_id, status, source, created_at)
          VALUES (?, ?, ?, ?, ?, NOW())';
    $st  = $db->prepare($sql);
    $st->execute([
        $id,
        $app['job_id'],
        $app['candidate_id'],
        $app['status'] ?? 'applied',
        $app['source'] ?? 'website'
    ]);
    return $id;
}
function generateId(): string
{
    // ID compacto compatible con char(36)
    return bin2hex(random_bytes(8)) . '-' . bin2hex(random_bytes(8));
}

/* ========= Handler ========= */
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsend(false, 'Método no permitido', null, 405);
    }

    $db  = pdo();
    $in  = body_json();

    // Validaciones mínimas
    foreach (['job_id', 'candidate_id'] as $k) {
        if (!isset($in[$k]) || !uuid_like($in[$k])) {
            jsend(false, "Campo $k inválido o ausente", null, 422);
        }
    }
    if (!jobExists($db, $in['job_id'])) {
        jsend(false, 'job_id no existe', null, 422);
    }
    if (!candidateExists($db, $in['candidate_id'])) {
        jsend(false, 'candidate_id no existe', null, 422);
    }

    $candidateId = $in['candidate_id'];

    // Routing vigente?
    $routing = getLatestRouting($db, $candidateId);
    if (!$routing) {
        // Obtener skills del candidato
        $skills = getCandidateSkills($db, $candidateId);

        // Mapear skills a dept/cat
        $map = mapSkillsToDept($db, $skills);
        if (!$map) {
            jsend(false, 'No hay mapeo de skills→departamento. Define bt_skill_department_map o añade skills al candidato.', [
                'candidate_id' => $candidateId,
                'skills' => $skills,
            ], 422);
        }

        // Seleccionar recruiter opcional
        $recruiterId = pickRecruiter($db, (int)$map['department_id']);

        // Crear routing
        $routingId = createRouting(
            $db,
            $candidateId,
            (int)$map['department_category_id'],
            (int)$map['department_id'],
            $recruiterId,
            $map['source'],
            $map['reason']
        );

        // Refrescar routing para continuar
        $routing = getLatestRouting($db, $candidateId);
    }

    // Crear aplicación
    $appId = createApplication($db, $in);

    // (Opcional) Métricas en tiempo real: aquí podrías actualizar una tabla de acumulados o retornar datos para el dashboard.
    jsend(true, 'Aplicación creada', [
        'application_id' => $appId,
        'routing' => $routing
    ], 201);
} catch (Throwable $e) {
    $code = ($e instanceof PDOException) ? 400 : 500;
    jsend(false, 'Error', ['error' => $e->getMessage()], $code);
}
