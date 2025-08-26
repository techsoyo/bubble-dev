<?php declare(strict_types=1);
namespace Router;

use AltoRouter;
use Utils\Request;
use Utils\RequestFactory;

class AppRouter
{
  private AltoRouter $router;
  private string $basePath;
  /** @var string[] Nombres de rutas protegidas (requieren auth) */
  private array $protectedRoutes = [];

  public function __construct(string $basePath = '')
  {
    $this->router = new AltoRouter();
    $this->basePath = $basePath;

    if ($basePath) {
      $this->router->setBasePath($basePath);
    }

    $this->setupRoutes();
  }

  /**
   * Verificar si estamos en ambiente de desarrollo
   */
  private function isDevelopmentEnvironment(): bool
  {
    $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
    return in_array(strtolower((string)$env), ['development', 'dev', 'local'], true);
  }

  /**
   * === REGISTRO DE TODAS LAS RUTAS ===
   * Nota: aÃƒÆ’Ã‚Â±ade aquÃƒÆ’Ã‚Â­ cualquier ruta nueva y, si debe requerir auth,
   * agrega su "name" al array $this->protectedRoutes[] justo debajo del map().
   */
  private function setupRoutes(): void
  {
    // === APLICACIONES ===
    $this->router->map('GET',    '/api/applications',                   'ApplicationController#index',         'applications.index');
    $this->router->map('POST',   '/api/applications',                   'ApplicationController#store',         'applications.store');
    $this->router->map('GET',    '/api/applications/[i:id]',            'ApplicationController#show',          'applications.show');
    $this->router->map('PUT',    '/api/applications/[i:id]',            'ApplicationController#update',        'applications.update');
    $this->router->map('DELETE', '/api/applications/[i:id]',            'ApplicationController#delete',        'applications.delete');
    $this->router->map('PATCH',  '/api/applications/[i:candidato_id]/status', 'ApplicationController#updateStatus', 'applications.status');
    $this->router->map('GET',    '/api/applications/table-data',        'ApplicationController#tableData',     'applications.table');
    $this->router->map('POST',   '/api/applications/save-partial',      'ApplicationController#savePartial',   'applications.partial');
    $this->router->map('PATCH',  '/api/applications',                   'ApplicationController#bulkUpdate',    'applications.bulk_update');
    $this->router->map('DELETE', '/api/applications',                   'ApplicationController#bulkDelete',    'applications.bulk_delete');

    // Protegidas (ejemplos habituales):
    $this->protectedRoutes[] = 'applications.index';
    $this->protectedRoutes[] = 'applications.show';
    $this->protectedRoutes[] = 'applications.update';
    $this->protectedRoutes[] = 'applications.delete';
    $this->protectedRoutes[] = 'applications.status';
    $this->protectedRoutes[] = 'applications.table';
    $this->protectedRoutes[] = 'applications.partial';
    $this->protectedRoutes[] = 'applications.bulk_update';
    $this->protectedRoutes[] = 'applications.bulk_delete';

    // === AUTENTICACIÃƒÆ’Ã¢â‚¬Å“N ===
    $this->router->map('POST',   '/api/auth/login',          'AuthController#login',          'auth.login');
    $this->router->map('POST',   '/api/auth/candidate-login', 'AuthController#candidateLogin', 'auth.candidate_login');
    $this->router->map('POST',   '/api/auth/staff-login',    'AuthController#staffLogin',     'auth.staff_login');
    $this->router->map('POST',   '/api/auth/social-login',   'AuthController#socialLogin',    'auth.social');
    $this->router->map('GET',    '/api/auth/me',             'AuthController#me',             'auth.me');
    $this->router->map('POST',   '/api/auth/refresh',        'AuthController#refresh',        'auth.refresh');
    $this->router->map('POST',   '/api/auth/set-cookie',     'AuthController#setCookie',      'auth.set_cookie');
    $this->router->map('POST',   '/api/auth/remove-cookie',  'AuthController#removeCookie',   'auth.remove_cookie');
    $this->router->map('GET',    '/api/auth/check-session',  'AuthController#checkSession',   'auth.check_session');
    $this->router->map('GET',    '/api/auth/verify-session', 'AuthController#verifySession',  'auth.verify_session');
    $this->router->map('POST',   '/api/auth/verify-session', 'AuthController#verifySession',  'auth.verify_session_post');
    // Legacy:
    $this->router->map('POST',   '/auth.php',                'AuthController#legacyLogin',    'auth.legacy');

    // Protegidas:
    $this->protectedRoutes[] = 'auth.me';
    $this->protectedRoutes[] = 'auth.set_cookie';
    $this->protectedRoutes[] = 'auth.remove_cookie';
    $this->protectedRoutes[] = 'auth.check_session';
    $this->protectedRoutes[] = 'auth.verify_session';
    $this->protectedRoutes[] = 'auth.verify_session_post';

    // === CANDIDATOS ===
    $this->router->map('GET',    '/api/candidates',              'CandidateController#index',       'candidates.index');
    $this->router->map('POST',   '/api/candidates',              'CandidateController#store',       'candidates.store');
    $this->router->map('POST',   '/api/candidates/register',     'CandidateController#register',    'candidates.register');
    $this->router->map('GET',    '/api/candidates/[i:id]',       'CandidateController#show',        'candidates.show');
    $this->router->map('PUT',    '/api/candidates/[i:id]',       'CandidateController#update',      'candidates.update');
    $this->router->map('DELETE', '/api/candidates/[i:id]',       'CandidateController#delete',      'candidates.delete');
    $this->router->map('POST',   '/api/candidates/upload-cv',    'CandidateController#uploadCV',    'candidates.upload_cv');
    $this->router->map('GET',    '/api/candidates/profile/[i:id]', 'CandidateController#profile',    'candidates.profile');
    $this->router->map('PATCH',  '/api/candidates/[i:id]/status', 'CandidateController#updateStatus', 'candidates.status');

    // Protegidas:
    $this->protectedRoutes[] = 'candidates.index';
    $this->protectedRoutes[] = 'candidates.show';
    $this->protectedRoutes[] = 'candidates.update';
    $this->protectedRoutes[] = 'candidates.delete';
    $this->protectedRoutes[] = 'candidates.upload_cv';
    $this->protectedRoutes[] = 'candidates.profile';
    $this->protectedRoutes[] = 'candidates.status';

    // === RECRUITERS ===
    $this->router->map('GET', '/api/recruiters/assigned-candidates', 'CandidateController#assignedCandidates', 'recruiters.assigned_candidates');
    $this->protectedRoutes[] = 'recruiters.assigned_candidates';

    // === TRABAJOS ===
    $this->router->map('GET',  '/api/jobs',                        'JobController#index',        'jobs.index');
    $this->router->map('POST', '/api/jobs',                        'JobController#store',        'jobs.store');
    $this->router->map('GET',  '/api/jobs/available',              'JobController#available',    'jobs.available');
    $this->router->map('POST', '/api/jobs/search',                 'JobController#search',       'jobs.search');
    $this->router->map('GET',  '/api/jobs/by-department/[i:department_id]', 'JobController#byDepartment', 'jobs.by_department');
    // Usamos comodÃƒÆ’Ã‚Â­n para ids tipo 'job-102'
    $this->router->map('GET',    '/api/jobs/[*:id]',               'JobController#show',         'jobs.show');
    $this->router->map('PUT',    '/api/jobs/[*:id]',               'JobController#update',       'jobs.update');
    $this->router->map('DELETE', '/api/jobs/[*:id]',               'JobController#delete',       'jobs.delete');

    // Protegidas tÃƒÆ’Ã‚Â­picas:
    $this->protectedRoutes[] = 'jobs.store';
    $this->protectedRoutes[] = 'jobs.update';
    $this->protectedRoutes[] = 'jobs.delete';

    // === CATEGORÃƒÆ’Ã‚ÂAS DE TRABAJO ===
    $this->router->map('GET',    '/api/job-categories',      'JobCategoryController#index',  'job_categories.index');
    $this->router->map('POST',   '/api/job-categories',      'JobCategoryController#store',  'job_categories.store');
    $this->router->map('GET',    '/api/job-categories/[i:id]', 'JobCategoryController#show',  'job_categories.show');
    $this->router->map('PUT',    '/api/job-categories/[i:id]', 'JobCategoryController#update', 'job_categories.update');
    $this->router->map('DELETE', '/api/job-categories/[i:id]', 'JobCategoryController#delete', 'job_categories.delete');
    $this->protectedRoutes[] = 'job_categories.store';
    $this->protectedRoutes[] = 'job_categories.update';
    $this->protectedRoutes[] = 'job_categories.delete';

    // === CHATBOT ===
    $this->router->map('GET',  '/api/chatbot/data',        'ChatbotController#getChatbotData',     'chatbot.data');
    $this->router->map('GET',  '/api/chatbot/node',        'ChatbotController#getNode',             'chatbot.node');
    $this->router->map('POST', '/api/chatbot/node',        'ChatbotController#getNode',             'chatbot.node_post');
    $this->router->map('POST', '/api/chatbot/interaction', 'ChatbotController#processInteraction',  'chatbot.interaction');
    $this->router->map('GET',  '/api/chatbot/analytics',   'ChatbotController#getAnalytics',        'chatbot.analytics');

    // === CV ===
    $this->router->map('POST', '/api/cv/analyze-file', 'CVController#analyzeFile',  'cv.analyze_file');
    $this->router->map('POST', '/api/cv/analyze-text', 'CVController#analyzeText',  'cv.analyze_text');
    $this->router->map('POST', '/api/cv/extract-text', 'CVController#extractText',  'cv.extract_text');
    $this->router->map('POST', '/api/cv/process',      'CVController#process',      'cv.process');

    // === INTELIGENCIA ARTIFICIAL ===
    $this->router->map('POST', '/api/ai/parse-cv-file',      'AIController#parseCVFromFile',   'ai.parse_cv_file');
    $this->router->map('POST', '/api/ai/parse-cv',           'AIController#parseCV',           'ai.parse_cv');
    $this->router->map('POST', '/api/ai/analyze-pdf',        'AIController#analyzePdfDirect',  'ai.analyze_pdf');
    $this->router->map('POST', '/api/ai/calculate-matching', 'AIController#calculateMatching', 'ai.calculate_matching');
    $this->router->map('POST', '/api/ai/chatbot',            'AIController#chatbot',           'ai.chatbot');
    $this->router->map('GET',  '/api/ai/health',             'AIController#healthCheck',       'ai.health');
    $this->router->map('POST', '/api/ai/analyze-personality', 'AIController#analyzePersonality', 'ai.analyze_personality');
    $this->router->map('POST', '/api/ai/predict-performance', 'AIController#predictPerformance', 'ai.predict_performance');

    // === IDIOMAS ===
    $this->router->map('GET',  '/api/language', 'LanguageController#getLanguage', 'language.get');
    $this->router->map('POST', '/api/language', 'LanguageController#setLanguage', 'language.set');

    // === PROCESAMIENTO DE PDFs ===
    $this->router->map('POST', '/api/pdf/parse', 'PDFController#parse', 'pdf.parse');

    // === USUARIOS ===
    $this->router->map('GET',    '/api/users',      'UserController#index', 'users.index');
    $this->router->map('POST',   '/api/users',      'UserController#store', 'users.store');
    $this->router->map('GET',    '/api/users/[i:id]', 'UserController#show', 'users.show');
    $this->router->map('PUT',    '/api/users/[i:id]', 'UserController#update', 'users.update');
    $this->router->map('DELETE', '/api/users/[i:id]', 'UserController#delete', 'users.delete');

    // Protegidas:
    $this->protectedRoutes[] = 'users.index';
    $this->protectedRoutes[] = 'users.store';
    $this->protectedRoutes[] = 'users.show';
    $this->protectedRoutes[] = 'users.update';
    $this->protectedRoutes[] = 'users.delete';

    // === NOTIFICACIONES ===
    $this->router->map('GET',    '/api/notifications',             'NotificationController#index',     'notifications.index');
    $this->router->map('POST',   '/api/notifications',             'NotificationController#store',     'notifications.store');
    $this->router->map('PATCH',  '/api/notifications/[i:id]/read', 'NotificationController#markAsRead', 'notifications.read');
    $this->router->map('DELETE', '/api/notifications/[i:id]',      'NotificationController#delete',    'notifications.delete');
    $this->protectedRoutes[] = 'notifications.index';
    $this->protectedRoutes[] = 'notifications.store';
    $this->protectedRoutes[] = 'notifications.read';
    $this->protectedRoutes[] = 'notifications.delete';

    // === ADMINISTRACIÃƒÆ’Ã¢â‚¬Å“N ===
    $this->router->map('GET',  '/api/admin/dashboard', 'AdminController#dashboard', 'admin.dashboard');
    $this->router->map('GET',  '/api/admin/stats',     'AdminController#stats',     'admin.stats');
    $this->router->map('POST', '/api/admin/bulk-actions', 'AdminController#bulkActions', 'admin.bulk_actions');
    $this->protectedRoutes[] = 'admin.dashboard';
    $this->protectedRoutes[] = 'admin.stats';
    $this->protectedRoutes[] = 'admin.bulk_actions';

    // === ARCHIVOS Y UPLOADS ===
    $this->router->map('POST',   '/api/upload',       'UploadController#upload', 'upload.file');
    $this->router->map('GET',    '/api/files/[*:path]', 'FileController#serve',   'files.serve');
    $this->router->map('DELETE', '/api/files/[*:path]', 'FileController#delete',  'files.delete');
    $this->protectedRoutes[] = 'files.serve';
    $this->protectedRoutes[] = 'files.delete';

    // === SISTEMA ===
    $this->router->map('GET', '/api/health',  'SystemController#health',  'system.health');
    $this->router->map('GET', '/api/status',  'SystemController#status',  'system.status');
    $this->router->map('GET', '/api/version', 'SystemController#version', 'system.version');

    // === DEPARTAMENTOS ===
    $this->router->map('GET',    '/api/departments',      'DepartmentController#index',  'departments.index');
    $this->router->map('POST',   '/api/departments',      'DepartmentController#store',  'departments.store');
    $this->router->map('GET',    '/api/departments/[i:id]', 'DepartmentController#show',  'departments.show');
    $this->router->map('PUT',    '/api/departments/[i:id]', 'DepartmentController#update', 'departments.update');
    $this->router->map('DELETE', '/api/departments/[i:id]', 'DepartmentController#delete', 'departments.delete');
    $this->protectedRoutes[] = 'departments.store';
    $this->protectedRoutes[] = 'departments.update';
    $this->protectedRoutes[] = 'departments.delete';

    // === SKILLS ===
    $this->router->map('GET',    '/api/skills',      'SkillController#index',  'skills.index');
    $this->router->map('POST',   '/api/skills',      'SkillController#store',  'skills.store');
    $this->router->map('GET',    '/api/skills/[i:id]', 'SkillController#show',  'skills.show');
    $this->router->map('PUT',    '/api/skills/[i:id]', 'SkillController#update', 'skills.update');
    $this->router->map('DELETE', '/api/skills/[i:id]', 'SkillController#delete', 'skills.delete');
    $this->protectedRoutes[] = 'skills.store';
    $this->protectedRoutes[] = 'skills.update';
    $this->protectedRoutes[] = 'skills.delete';

    // === NOTICIAS ===
    $this->router->map('GET',    '/api/news',      'NewsController#index',  'news.index');
    $this->router->map('POST',   '/api/news',      'NewsController#store',  'news.store');
    $this->router->map('GET',    '/api/news/[i:id]', 'NewsController#show',  'news.show');
    $this->router->map('PUT',    '/api/news/[i:id]', 'NewsController#update', 'news.update');
    $this->router->map('DELETE', '/api/news/[i:id]', 'NewsController#delete', 'news.delete');
    $this->protectedRoutes[] = 'news.store';
    $this->protectedRoutes[] = 'news.update';
    $this->protectedRoutes[] = 'news.delete';

    // === ENTREVISTAS ===
    $this->router->map('GET',    '/api/interviews',      'InterviewController#index',  'interviews.index');
    $this->router->map('POST',   '/api/interviews',      'InterviewController#store',  'interviews.store');
    $this->router->map('GET',    '/api/interviews/[i:id]', 'InterviewController#show',  'interviews.show');
    $this->router->map('PUT',    '/api/interviews/[i:id]', 'InterviewController#update', 'interviews.update');
    $this->router->map('DELETE', '/api/interviews/[i:id]', 'InterviewController#delete', 'interviews.delete');
    $this->protectedRoutes[] = 'interviews.store';
    $this->protectedRoutes[] = 'interviews.update';
    $this->protectedRoutes[] = 'interviews.delete';

    // === RUTAS DE TESTING Y DESARROLLO ===
    if ($this->isDevelopmentEnvironment()) {
      $this->router->map('GET', '/api/test', 'TestController#test', 'test.basic');
      $this->router->map('GET', '/test.php', 'TestController#legacyTest', 'test.legacy');
    }

    // === CORS PRELIGHT (catch-all) ===
    $this->router->map('OPTIONS', '/api/[**]', 'CorsController#preflight', 'cors.preflight');
    $this->router->map('OPTIONS', '/[**]',     'CorsController#preflight', 'cors.preflight_all');
  }

  /**
   * Despachar la solicitud
   */
  public function dispatch()
  {
    $requestUri    = $_SERVER['REQUEST_URI']    ?? '/';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $this->applyCorsHeaders();

    // Preflight
    if ($requestMethod === 'OPTIONS') {
      http_response_code(204);
      exit();
    }

    $match = $this->router->match($requestUri, $requestMethod);

    if ($match) {
      // Verificar protecciÃƒÆ’Ã‚Â³n
      if ($this->isProtectedRoute($match['name'])) {
        if (!$this->checkAuthentication()) {
          return $this->handleError(401, 'Unauthorized', 'No authentication token provided or token invalid');
        }
      }
      return $this->handleMatch($match);
    }

    return $this->handleNotFound($requestUri, $requestMethod);
  }

  /**
   * Ãƒâ€šÃ‚Â¿La ruta requiere auth?
   */
  private function isProtectedRoute(?string $routeName): bool
  {
    if (!$routeName) return false;
    return in_array($routeName, $this->protectedRoutes, true);
  }

  /**
   * VerificaciÃƒÆ’Ã‚Â³n simple de autenticaciÃƒÆ’Ã‚Â³n.
   * Sustituye este mÃƒÆ’Ã‚Â©todo con validaciÃƒÆ’Ã‚Â³n real de JWT/cookie segÃƒÆ’Ã‚Âºn tu proyecto.
   */
  private function checkAuthentication(): bool
  {
    // Authorization: Bearer <token>
    $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (stripos($authHeader, 'Bearer ') === 0) {
      $token = substr($authHeader, 7);
      // TODO: validar JWT real aquÃƒÆ’Ã‚Â­ (recomiendo firebase/php-jwt)
      return !empty($token);
    }

    // Fallback: sesiÃƒÆ’Ã‚Â³n PHP
    if (session_status() !== PHP_SESSION_ACTIVE) {
      @session_start();
    }
    return isset($_SESSION['user_id']);
  }

  /**
   * Manejar una ruta coincidente
   */
  private function handleMatch(array $match)
  {
    $target = $match['target'];
    $params = $match['params'] ?? [];

    if (strpos($target, '#') !== false) {
      [$controllerName, $method] = explode('#', $target, 2);
      try {
        return $this->callController($controllerName, $method, $params);
      } catch (\Throwable $e) {
        $this->log("500 Internal Server Error @ $controllerName#$method :: " . $e->getMessage());
        return $this->handleError(500, 'Internal Server Error', $e->getMessage());
      }
    }

    return $this->handleError(500, 'Invalid Route Configuration', "Invalid target: $target");
  }

  /**
   * Llamar al controlador especificado
   */
  private function callController(string $controllerName, string $method, array $params)
  {
    $controllerClass = "Controllers\\$controllerName";

    if (!class_exists($controllerClass)) {
      return $this->handleError(500, 'Controller Not Found', "Controller $controllerClass not found");
    }

    $controller = new $controllerClass();

    if (!method_exists($controller, $method)) {
      return $this->handleError(500, 'Method Not Found', "Method $method not found in $controllerClass");
    }

    $request = RequestFactory::fromGlobals();

    // Firma estÃƒÆ’Ã‚Â¡ndar: action(Request $req, array $params)
    return call_user_func_array([$controller, $method], [$request, $params]);
  }

  /**
   * 404 consistente
   */
  private function handleNotFound(string $uri, string $method)
  {
    $this->log("404 Not Found: $method $uri");
    return $this->handleError(404, 'Not Found', "Route $method $uri not found");
  }

  /**
   * Manejo de errores consistente
   */
  private function handleError(int $code, string $message, string $details = '')
  {
    http_response_code($code);

    $response = [
      'error'     => true,
      'code'      => $code,
      'message'   => $message,
      'timestamp' => date('c'),
      'path'      => $_SERVER['REQUEST_URI']    ?? '',
      'method'    => $_SERVER['REQUEST_METHOD'] ?? ''
    ];

    if ($details && ($this->isDevelopmentEnvironment() || (getenv('APP_DEBUG') === 'true'))) {
      $response['details'] = $details;
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return false;
  }

  /**
   * CORS headers
   */
  private function applyCorsHeaders(): void
  {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = [
      'http://localhost:3000',
      'http://localhost:3001',
      'http://localhost:3002',
      'http://127.0.0.1:3000',
      'http://127.0.0.1:3001',
      'http://127.0.0.1:3002'
    ];

    if ($origin && in_array($origin, $allowedOrigins, true)) {
      header("Access-Control-Allow-Origin: $origin");
    } else {
      // Opcional: permitir todo en dev
      if ($this->isDevelopmentEnvironment()) {
        header("Access-Control-Allow-Origin: *");
      }
    }

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
  }

  /**
   * Logging simple (solo en dev)
   */
  private function log(string $message): void
  {
    if (!$this->isDevelopmentEnvironment()) {
      return;
    }
    $logDir = __DIR__ . '/../../storage/logs';
    if (!is_dir($logDir)) {
      @mkdir($logDir, 0775, true);
    }
    $logFile = $logDir . '/router.log';
    @file_put_contents($logFile, '[' . date('c') . "] $message\n", FILE_APPEND);
  }

  /**
   * Rutas registradas (debug)
   */
  public function getRoutes()
  {
    return $this->router->getRoutes();
  }
}
