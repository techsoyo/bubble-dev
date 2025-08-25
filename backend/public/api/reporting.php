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

/**
 * Reporting para dashboard con 4 mÃƒÆ’Ã‚Â©tricas.
 * Rutas:
 *  - GET /api/reporting/candidates-by-department
 *  - GET /api/reporting/applications-by-job
 *  - GET /api/reporting/candidates-without-recruiter
 *  - GET /api/reporting/cv-validated-percentage
 */

declare(strict_types=1);

/* ========= Helpers ========= */
function jsend($ok, $message, $data = null, $code = 200): void
{
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function pdo(): PDO
{
    static $pdo;
    if ($pdo) {
        return $pdo;
    }
    $host = getenv('DB_HOST');
    $db = getenv('DB_NAME');
    $usr  = getenv('DB_USER');
    $pwd = getenv('DB_PASSWORD');
    $dsn  = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $usr, $pwd, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}
function T(string $raw): string
{
    return (getenv('DB_TABLE_PREFIX') ?: '') . $raw;
}
function dates(): array
{
    // Permite ?from=YYYY-MM-DD&to=YYYY-MM-DD o ?created_at[from]=...&created_at[to]=...
    $from = $_GET['from'] ?? ($_GET['created_at']['from'] ?? null);
    $to   = $_GET['to']   ?? ($_GET['created_at']['to']   ?? null);
    // Normaliza a lÃƒÆ’Ã‚Â­mites del dÃƒÆ’Ã‚Â­a si vienen
    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from .= ' 00:00:00';
    }
    if ($to   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to   .= ' 23:59:59';
    }
    return [$from, $to];
}
function whereCreated(string $alias, ?string $from, ?string $to): array
{
    $w = [];
    $p = [];
    if ($from) {
        $w[] = "$alias.created_at >= ?";
        $p[] = $from;
    }
    if ($to) {
        $w[] = "$alias.created_at <= ?";
        $p[] = $to;
    }
    $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';
    return [$where, $p];
}

/* ========= KPI queries ========= */

/**
 * KPI 1: Total candidatos por departamento (usa el routing mÃƒÆ’Ã‚Â¡s reciente por candidato)
 * Devuelve: { labels: [depName...], series: [count...] }
 */
function kpi_candidates_by_department(PDO $db, ?string $from, ?string $to): array
{
    // Subconsulta para ÃƒÆ’Ã‚Âºltimo routing por candidato
    $sub = 'SELECT candidate_id, MAX(assigned_at) AS max_assigned
          FROM ' . T('candidate_routing') . '
          GROUP BY candidate_id';
    // Filtro por ventana (si se exige sobre assigned_at del routing actual)
    $where = [];
    $params = [];
    if ($from) {
        $where[] = 'r.assigned_at >= ?';
        $params[] = $from;
    }
    if ($to) {
        $where[] = 'r.assigned_at <= ?';
        $params[] = $to;
    }
    $W = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = 'SELECT d.name AS label, COUNT(*) AS value
          FROM ' . T('candidate_routing') . " r
          JOIN ($sub) last_r
              ON last_r.candidate_id = r.candidate_id
             AND last_r.max_assigned = r.assigned_at
          JOIN " . T('departments') . " d ON d.id = r.department_id
          $W
          GROUP BY d.name
          ORDER BY value DESC, d.name ASC";
    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
    return [
        'labels' => array_column($rows, 'label'),
        'series' => array_map('intval', array_column($rows, 'value')),
    ];
}

/**
 * KPI 2: Total aplicaciones por job
 * Devuelve: { labels: [jobTitleOrId...], series: [count...] }
 */ function kpi_applications_by_job(PDO $db, ?string $from, ?string $to): array
{
    [$W, $P] = whereCreated('a', $from, $to);

    $sql = 'SELECT COALESCE(j.title, a.job_id) AS label, COUNT(*) AS value
            FROM ' . T('applications') . ' a
       LEFT JOIN ' . T('jobs') . " j ON j.id = a.job_id
            $W
        GROUP BY label
        ORDER BY value DESC, label ASC";
    $st = $db->prepare($sql);
    $st->execute($P);
    $rows = $st->fetchAll();

    return [
        'labels' => array_map('strval', array_column($rows, 'label')),
        'series' => array_map('intval', array_column($rows, 'value')),
    ];
}

/**
 * KPI 3: % candidatos sin recruiter asignado (sobre el routing actual)
 * Devuelve: { labels: ["con recruiter","sin recruiter"], series: [%, %] }
 */
function kpi_candidates_without_recruiter(PDO $db, ?string $from, ?string $to): array
{
    $sub = 'SELECT candidate_id, MAX(assigned_at) AS max_assigned
          FROM ' . T('candidate_routing') . '
          GROUP BY candidate_id';
    $where = [];
    $params = [];
    if ($from) {
        $where[] = 'r.assigned_at >= ?';
        $params[] = $from;
    }
    if ($to) {
        $where[] = 'r.assigned_at <= ?';
        $params[] = $to;
    }
    $W = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT
            SUM(CASE WHEN r.recruiter_id IS NULL OR r.recruiter_id = '' THEN 1 ELSE 0 END) AS no_rec,
            COUNT(*) AS total
          FROM " . T('candidate_routing') . " r
          JOIN ($sub) last_r
              ON last_r.candidate_id = r.candidate_id
             AND last_r.max_assigned = r.assigned_at
          $W";
    $st = $db->prepare($sql);
    $st->execute($params);
    $row = $st->fetch() ?: ['no_rec' => 0, 'total' => 0];

    $total = (int)$row['total'];
    if ($total === 0) {
        return ['labels' => ['con recruiter', 'sin recruiter'], 'series' => [0, 0]];
    }
    $no = (int)$row['no_rec'];
    $yes = $total - $no;
    $pctNo  = round($no  * 100 / $total, 2);
    $pctYes = round($yes * 100 / $total, 2);
    return ['labels' => ['con recruiter', 'sin recruiter'], 'series' => [$pctYes, $pctNo]];
}

/**
 * KPI 4: % candidatos con CV "validado por IA"
 * Proxy: routing actual con source='ai' frente al total de candidatos con routing actual.
 * Devuelve: { labels: ["IA","No IA"], series: [%, %] }
 */
function kpi_cv_validated_percentage(PDO $db, ?string $from, ?string $to): array
{
    $sub = 'SELECT candidate_id, MAX(assigned_at) AS max_assigned
          FROM ' . T('candidate_routing') . '
          GROUP BY candidate_id';
    $where = [];
    $params = [];
    if ($from) {
        $where[] = 'r.assigned_at >= ?';
        $params[] = $from;
    }
    if ($to) {
        $where[] = 'r.assigned_at <= ?';
        $params[] = $to;
    }
    $W = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT
            SUM(CASE WHEN r.source = 'ai' THEN 1 ELSE 0 END) AS ai_cnt,
            COUNT(*) AS total
          FROM " . T('candidate_routing') . " r
          JOIN ($sub) last_r
              ON last_r.candidate_id = r.candidate_id
             AND last_r.max_assigned = r.assigned_at
          $W";
    $st = $db->prepare($sql);
    $st->execute($params);
    $row = $st->fetch() ?: ['ai_cnt' => 0, 'total' => 0];

    $total = (int)$row['total'];
    if ($total === 0) {
        return ['labels' => ['IA', 'No IA'], 'series' => [0, 0]];
    }
    $ai  = (int)$row['ai_cnt'];
    $no  = $total - $ai;
    $pctAi = round($ai * 100 / $total, 2);
    $pctNo = round($no * 100 / $total, 2);
    return ['labels' => ['IA', 'No IA'], 'series' => [$pctAi, $pctNo]];
}

/* ========= Router ========= */

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsend(false, 'MÃƒÆ’Ã‚Â©todo no permitido', null, 405);
    }

    $db = pdo();
    [$from, $to] = dates();

    // Determina acciÃƒÆ’Ã‚Â³n por path
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $segments = array_values(array_filter(explode('/', $path)));
    $action = strtolower($segments[count($segments) - 1] ?? '');

    $result = null;
    switch ($action) {
        case 'candidates-by-department':
            $result = kpi_candidates_by_department($db, $from, $to);
            break;
        case 'applications-by-job':
            $result = kpi_applications_by_job($db, $from, $to);
            break;
        case 'candidates-without-recruiter':
            $result = kpi_candidates_without_recruiter($db, $from, $to);
            break;
        case 'cv-validated-percentage':
            $result = kpi_cv_validated_percentage($db, $from, $to);
            break;
        default:
            jsend(false, 'Ruta no encontrada', ['path' => $path], 404);
    }
    // ValidaciÃƒÆ’Ã‚Â³n de formato de salida
    if (!is_array($result) || !isset($result['labels'], $result['series'])) {
        jsend(false, 'Formato de salida invÃƒÆ’Ã‚Â¡lido', $result, 500);
    }
    jsend(true, 'OK', $result, 200);
} catch (Throwable $e) {
    $code = ($e instanceof PDOException) ? 400 : 500;
    jsend(false, 'Error', ['error' => $e->getMessage()], $code);
}


