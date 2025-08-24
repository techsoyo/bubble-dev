<?php

/**
 * Archivo centralizado de rutas para la aplicación Bubble of Talents
 * 
 * Este archivo contiene todas las rutas detectadas en la tabla de endpoints.
 * Al instanciar AppRouter, las rutas se configuran internamente en el método
 * setupRoutes() de la clase AppRouter.
 * 
 * @package Router
 * @version 1.0.0
 * @since 2025-08-24
 */

namespace Router;

use Router\AppRouter;

/**
 * NOTA IMPORTANTE:
 * 
 * La implementación actual de AppRouter no permite configurar rutas
 * externamente como se requiere. Para solucionar esto, se recomienda:
 * 
 * 1. Modificar AppRouter.php para que la configuración de rutas se realice
 *    a través de métodos públicos, permitiendo que este archivo las configure.
 * 
 * 2. O modificar setupRoutes() dentro de AppRouter para incluir todas las rutas
 *    de la tabla CSV proporcionada.
 * 
 * Para referencia, las rutas que deben estar configuradas en setupRoutes() son:
 * 
 * // 1. ENDPOINTS DE APLICACIONES
 * $this->router->map('GET',    '/api/applications',                     'ApplicationController#index',         'applications.index');
 * $this->router->map('POST',   '/api/applications',                     'ApplicationController#store',         'applications.store');
 * $this->router->map('PATCH',  '/api/applications/[i:candidato_id]/status', 'ApplicationController#updateStatus', 'applications.status');
 * $this->router->map('GET',    '/api/applications/table-data',          'ApplicationController#tableData',     'applications.table');
 * $this->router->map('POST',   '/api/applications/save-partial',        'ApplicationController#savePartial',   'applications.partial');
 * $this->router->map('PUT',    '/api/applications/[i:id]',              'ApplicationController#update',        'applications.update');
 * $this->router->map('DELETE', '/api/applications/[i:id]',              'ApplicationController#delete',        'applications.delete');
 * $this->router->map('PATCH',  '/api/applications',                     'ApplicationController#bulkUpdate',    'applications.bulk_update');
 * $this->router->map('DELETE', '/api/applications',                     'ApplicationController#bulkDelete',    'applications.bulk_delete');
 * 
 * // 2. ENDPOINTS DE AUTENTICACIÓN
 * $this->router->map('POST',   '/api/auth/social-login',                'AuthController#socialLogin',          'auth.social');
 * $this->router->map('POST',   '/api/auth/login',                       'AuthController#login',                'auth.login');
 * $this->router->map('GET',    '/api/auth/me',                          'AuthController#me',                   'auth.me');
 * $this->router->map('POST',   '/api/auth/refresh',                     'AuthController#refresh',              'auth.refresh');
 * $this->router->map('POST',   '/api/auth/set-cookie',                  'AuthController#setCookie',            'auth.set_cookie');
 * $this->router->map('POST',   '/api/auth/remove-cookie',               'AuthController#removeCookie',         'auth.remove_cookie');
 * $this->router->map('GET',    '/api/auth/check-session',               'AuthController#checkSession',         'auth.check_session');
 * $this->router->map('POST',   '/auth.php',                             'AuthController#legacyLogin',          'auth.legacy');
 * $this->router->map('GET',    '/api/auth/csrf-token.php',              'AuthController#csrfToken',            'auth.csrf_token');
 * $this->router->map('POST',   '/api/auth/validate-csrf.php',           'AuthController#validateCsrf',         'auth.validate_csrf');
 * 
 * // 3. ENDPOINTS DE CANDIDATOS
 * $this->router->map('POST',   '/api/candidates/register',              'CandidateController#register',        'candidates.register');
 * $this->router->map('GET',    '/api/candidates',                       'CandidateController#index',           'candidates.index');
 * $this->router->map('POST',   '/api/candidates',                       'CandidateController#store',           'candidates.store');
 * $this->router->map('GET',    '/api/candidates/[i:id]',                'CandidateController#show',            'candidates.show');
 * $this->router->map('PUT',    '/api/candidates/[i:id]',                'CandidateController#update',          'candidates.update');
 * $this->router->map('DELETE', '/api/candidates/[i:id]',                'CandidateController#delete',          'candidates.delete');
 * $this->router->map('POST',   '/api/save-candidate.php',               'CandidateController#saveCandidate',   'candidates.save_legacy');
 * $this->router->map('POST',   '/api/candidates/save_v2.php',           'CandidateController#saveV2',          'candidates.save_v2');
 * 
 * // 4. ENDPOINTS DE ANÁLISIS DE CV
 * $this->router->map('POST',   '/api/analyze_cv.php',                   'CvAnalysisController#analyze',        'cv.analyze_legacy');
 * $this->router->map('POST',   '/api/cv/parse.php',                     'CvAnalysisController#parse',          'cv.parse_legacy');
 * $this->router->map('POST',   '/api/parse-cv',                         'CvAnalysisController#parseModern',    'cv.parse');
 * 
 * // 5. ENDPOINTS DE TRABAJOS
 * $this->router->map('GET',    '/api/jobs.php',                         'JobController#index',                 'jobs.index_legacy');
 * $this->router->map('POST',   '/api/jobs.php',                         'JobController#store',                 'jobs.store_legacy');
 * $this->router->map('PUT',    '/api/jobs.php',                         'JobController#update',                'jobs.update_legacy');
 * $this->router->map('DELETE', '/api/jobs.php',                         'JobController#delete',                'jobs.delete_legacy');
 * $this->router->map('GET',    '/api/jobs',                             'JobController#index',                 'jobs.index');
 * $this->router->map('GET',    '/api/jobs/translate',                   'JobController#translate',             'jobs.translate');
 * $this->router->map('POST',   '/api/jobs/translate',                   'JobController#translatePost',         'jobs.translate_post');
 * 
 * // Continuar con el resto de endpoints del CSV...
 * 
 * // OPTIONS - CORS Preflight
 * $this->router->map('OPTIONS', '/{any:.*}',                            'SystemController#preflight',          'system.preflight');
 */

// Devolver la instancia del router configurada internamente
return new AppRouter();

// =====================================================
// 2. ENDPOINTS DE AUTENTICACIÓN
// =====================================================
$router->post('/api/auth/social-login', 'AuthController@socialLogin');
$router->post('/api/auth/login', 'AuthController@login');
$router->get('/api/auth/me', 'AuthController@me');
$router->post('/api/auth/refresh', 'AuthController@refresh');
$router->post('/api/auth/set-cookie', 'AuthController@setCookie');
$router->post('/api/auth/remove-cookie', 'AuthController@removeCookie');
$router->get('/api/auth/check-session', 'AuthController@checkSession');
$router->post('/auth.php', 'AuthController@handleAuth');
$router->get('/api/auth/csrf-token.php', 'AuthController@csrfToken');
$router->post('/api/auth/validate-csrf.php', 'AuthController@validateCsrf');

// =====================================================
// 3. ENDPOINTS DE CANDIDATOS
// =====================================================
$router->post('/api/candidates/register', 'CandidateController@register');
$router->get('/api/candidates', 'CandidateController@index');
$router->post('/api/candidates', 'CandidateController@store');
$router->get('/api/candidates/{id}', 'CandidateController@show');
$router->put('/api/candidates/{id}', 'CandidateController@update');
$router->delete('/api/candidates/{id}', 'CandidateController@delete');
$router->post('/api/save-candidate.php', 'CandidateController@saveCandidate');
$router->post('/api/candidates/save_v2.php', 'CandidateController@saveV2');

// =====================================================
// 4. ENDPOINTS DE ANÁLISIS DE CV
// =====================================================
$router->post('/api/analyze_cv.php', 'CvAnalysisController@analyze');
$router->post('/api/cv/parse.php', 'CvAnalysisController@parse');
$router->post('/api/parse-cv', 'CvAnalysisController@parseModern');

// =====================================================
// 5. ENDPOINTS DE TRABAJOS
// =====================================================
$router->get('/api/jobs.php', 'JobController@index');
$router->post('/api/jobs.php', 'JobController@store');
$router->put('/api/jobs.php', 'JobController@update');
$router->delete('/api/jobs.php', 'JobController@delete');
$router->get('/api/jobs', 'JobController@index');
$router->post('/api/jobs', 'JobController@store');
$router->put('/api/jobs/{id}', 'JobController@update');
$router->delete('/api/jobs/{id}', 'JobController@delete');
$router->get('/api/jobs/translate', 'JobController@translate');
$router->post('/api/jobs/translate', 'JobController@translatePost');

// =====================================================
// 6. ENDPOINTS DE DASHBOARD
// =====================================================
$router->get('/api/hr/dashboard-stats', 'DashboardController@hrStats');
$router->get('/api/recruiter/{recruiter_id}/dashboard-stats', 'DashboardController@recruiterStats');
$router->get('/api/me', 'UserController@me');

// =====================================================
// 7. ENDPOINTS DE NOTIFICACIONES
// =====================================================
$router->post('/api/notifications', 'NotificationController@store');
$router->get('/api/notifications/templates', 'NotificationController@templates');
$router->get('/api/get-notification-preferences.php', 'NotificationController@getPreferences');
$router->post('/api/save-notification-preferences.php', 'NotificationController@savePreferences');
$router->get('/api/candidate-notifications.php', 'NotificationController@candidateNotifications');

// =====================================================
// 8. ENDPOINTS DE CALENDARIO
// =====================================================
$router->get('/api/calendar/integrations', 'CalendarController@integrations');
$router->post('/api/calendar/connect', 'CalendarController@connect');
$router->post('/api/calendar/disconnect', 'CalendarController@disconnect');
$router->get('/api/calendar/events', 'CalendarController@events');
$router->post('/api/calendar/schedule-interview', 'CalendarController@scheduleInterview');
$router->get('/api/calendar/available-slots', 'CalendarController@availableSlots');

// =====================================================
// 9. ENDPOINTS DE ARCHIVOS
// =====================================================
$router->post('/api/files/upload-cv', 'FileController@uploadCv');

// =====================================================
// 10. ENDPOINTS DE HABILIDADES Y DATOS AUXILIARES
// =====================================================
$router->get('/api/skills.php', 'SkillController@index');
$router->post('/api/skills.php', 'SkillController@store');
$router->put('/api/skills.php', 'SkillController@update');
$router->delete('/api/skills.php', 'SkillController@delete');
$router->get('/api/skills', 'SkillController@index');
$router->get('/api/recruiters.php', 'RecruiterController@index');
$router->get('/api/recruiters', 'RecruiterController@index');
$router->get('/api/departments.php', 'DepartmentController@index');
$router->get('/api/departments', 'DepartmentController@index');

// =====================================================
// 11. ENDPOINTS ESPECÍFICOS DE DATOS
// =====================================================
$router->get('/api/candidate_experiences.php', 'CandidateExperienceController@index');
$router->get('/api/candidate-experiences.php', 'CandidateExperienceController@index');
$router->get('/api/candidate_skills.php', 'CandidateSkillController@index');
$router->get('/api/candidate-applications.php', 'CandidateApplicationController@index');
$router->get('/api/application_notes.php', 'ApplicationNoteController@index');
$router->get('/api/social_logins.php', 'SocialLoginController@index');
$router->get('/api/interviews.php', 'InterviewController@index');
$router->get('/api/job_benefits.php', 'JobBenefitController@index');
$router->get('/api/job_requirements.php', 'JobRequirementController@index');
$router->get('/api/job_skills.php', 'JobSkillController@index');

// =====================================================
// 12. ENDPOINTS DE CONTENIDO Y CULTURA
// =====================================================
$router->get('/api/culture.php', 'CultureController@index');
$router->get('/api/news.php', 'NewsController@index');

// =====================================================
// 13. ENDPOINTS DE CHATBOT E IA
// =====================================================
$router->post('/api/chatbot.php', 'ChatbotController@process');
$router->post('/chatbot_decision_tree.php', 'ChatbotController@decisionTree');
$router->post('/chatbot_analytics.php', 'ChatbotController@analytics');

// =====================================================
// 14. ENDPOINTS DE MONITOREO Y SISTEMA
// =====================================================
$router->post('/api/web-vitals', 'MonitoringController@webVitals');
$router->post('/api/csp-violation', 'SecurityController@cspViolation');
$router->post('/api/sync', 'SystemController@sync');
$router->get('/api/ping', 'SystemController@ping');
$router->get('/api/info', 'SystemController@info');

// =====================================================
// 15. ENDPOINTS DE VALIDACIÓN
// =====================================================
$router->get('/api/users/check-email', 'ValidationController@checkEmail');
$router->get('/api/users/check-username', 'ValidationController@checkUsername');

// =====================================================
// 16. ENDPOINTS DE ASIGNACIÓN Y GESTIÓN
// =====================================================
$router->post('/api/assign-candidate', 'AssignmentController@assignCandidate');

// =====================================================
// 17. ENDPOINTS DE DESARROLLO
// =====================================================
$router->post('/api/change-password.php', 'UserController@changePassword');

// =====================================================
// 18. ENDPOINTS DE SECUREAUTH
// =====================================================
$router->post('/auth/verify-session.php', 'SecureAuthController@verifySession');
$router->post('/auth/candidate-login.php', 'SecureAuthController@candidateLogin');
$router->post('/auth/staff-login-simple.php', 'SecureAuthController@staffLoginSimple');
$router->post('/auth/logout.php', 'SecureAuthController@logout');

// =====================================================
// 19. ENDPOINTS DE IDIOMAS
// =====================================================
$router->post('/language.php', 'LanguageController@process');
$router->get('/language.php', 'LanguageController@get');
$router->get('/api/language', 'LanguageController@getApi');

// =====================================================
// 20. OPTIONS - CORS Preflight
// =====================================================
$router->options('/{any:.*}', 'SystemController@preflight');

return $router;
