<?php
return [
  // === AdminController ===
  ['GET', '/api/admin/dashboard', 'AdminController@dashboard'],
  ['GET', '/api/admin/stats', 'AdminController@stats'],
  ['POST', '/api/admin/bulk-actions', 'AdminController@bulkActions'],

  // === AIController ===
  ['GET', '/api/ai/health', 'AIController@healthCheck'],
  ['POST', '/api/ai/parse-cv-file', 'AIController@parseCVFromFile'],
  ['POST', '/api/ai/parse-cv', 'AIController@parseCV'],
  ['POST', '/api/ai/analyze-pdf', 'AIController@analyzePdfDirect'],
  ['POST', '/api/ai/calculate-matching', 'AIController@calculateMatching'],
  ['POST', '/api/ai/chatbot', 'AIController@chatbot'],
  ['POST', '/api/ai/analyze-personality', 'AIController@analyzePersonality'],
  ['POST', '/api/ai/predict-performance', 'AIController@predictPerformance'],

  // === ApplicationController ===
  ['GET', '/api/applications', 'ApplicationController@index'],
  ['POST', '/api/applications', 'ApplicationController@store'],
  ['GET', '/api/applications/{id}', 'ApplicationController@show'],
  ['PUT', '/api/applications/{id}', 'ApplicationController@update'],
  ['DELETE', '/api/applications/{id}', 'ApplicationController@delete'],
  ['PATCH', '/api/applications/{id}/status', 'ApplicationController@updateStatus'],
  ['GET', '/api/applications/table-data', 'ApplicationController@tableData'],
  ['POST', '/api/applications/save-partial', 'ApplicationController@savePartial'],
  ['PATCH', '/api/applications/bulk', 'ApplicationController@bulkUpdate'],
  ['DELETE', '/api/applications/bulk', 'ApplicationController@bulkDelete'],

  // === AuthController ===
  ['POST', '/api/auth/register', 'AuthController@register'],
  ['GET', '/api/auth/me', 'AuthController@me'],
  ['POST', '/api/auth/logout', 'AuthController@logout'],
  ['POST', '/api/auth/forgot-password', 'AuthController@forgotPassword'],
  ['POST', '/api/auth/reset-password', 'AuthController@resetPassword'],
  ['POST', '/api/auth/change-password', 'AuthController@changePassword'],
  ['POST', '/api/auth/social-login', 'AuthController@socialLogin'],
  ['POST', '/api/auth/refresh', 'AuthController@refresh'],
  ['POST', '/api/auth/set-cookie', 'AuthController@setCookie'],
  ['POST', '/api/auth/remove-cookie', 'AuthController@removeCookie'],
  ['GET', '/api/auth/check-session', 'AuthController@checkSession'],
  ['GET', '/api/auth/verify-session', 'AuthController@verifySession'],
  ['POST', '/api/auth/verify-session', 'AuthController@verifySession'],
  ['POST', '/api/auth/candidate-login', 'AuthController@candidateLogin'],
  ['POST', '/api/auth/staff-login', 'AuthController@staffLogin'],

  // === CandidateController ===
  ['GET', '/api/candidates', 'CandidateController@getAll'],
  ['GET', '/api/candidates/{id}', 'CandidateController@getById'],
  ['POST', '/api/candidates', 'CandidateController@create'],
  ['PUT', '/api/candidates/{id}', 'CandidateController@update'],
  ['DELETE', '/api/candidates/{id}', 'CandidateController@delete'],
  ['POST', '/api/candidates/register', 'CandidateController@register'],
  ['GET', '/api/candidates/profile/{id}', 'CandidateController@profile'],
  ['POST', '/api/candidates/upload-cv', 'CandidateController@uploadCV'],
  ['PATCH', '/api/candidates/{id}/status', 'CandidateController@updateStatus'],
  ['GET', '/api/recruiters/assigned-candidates', 'CandidateController@assignedCandidates'],

  // === ChatbotController ===
  ['GET', '/api/chatbot/data', 'ChatbotController@getChatbotData'],
  ['GET', '/api/chatbot/{id}', 'ChatbotController@show'],
  ['GET', '/api/chatbot/node', 'ChatbotController@getNode'],
  ['POST', '/api/chatbot/node', 'ChatbotController@processInteraction'],
  ['POST', '/api/chatbot/interaction', 'ChatbotController@processInteraction'],
  ['GET', '/api/chatbot/analytics', 'ChatbotController@getAnalytics'],
  ['POST', '/api/chatbot/track', 'ChatbotController@trackAnalyticsEvent'],
  ['POST', '/api/chatbot/node/create', 'ChatbotController@createNode'],
  ['POST', '/api/chatbot/option/create', 'ChatbotController@createOption'],
  ['PUT', '/api/chatbot/node/{id}', 'ChatbotController@updateNode'],
  ['PUT', '/api/chatbot/option/{id}', 'ChatbotController@updateOption'],
  ['DELETE', '/api/chatbot/node/{id}', 'ChatbotController@deleteNode'],
  ['DELETE', '/api/chatbot/option/{id}', 'ChatbotController@deleteOption'],

  // === CORS catch-all (si usas un CorsController) ===
  ['OPTIONS', '/{any:.*}', 'CorsController@preflight'],

  // === CultureController ===
  ['GET', '/api/culture', 'CultureController@index'],
  ['POST', '/api/culture', 'CultureController@store'],
  ['GET', '/api/culture/{id}', 'CultureController@show'],
  ['PUT', '/api/culture/{id}', 'CultureController@update'],
  ['DELETE', '/api/culture/{id}', 'CultureController@delete'],

  // === CVController ===
  ['POST', '/api/cv/analyze-file', 'CVController@analyzeFile'],
  ['POST', '/api/cv/analyze-text', 'CVController@analyzeText'],
  ['POST', '/api/cv/extract-text', 'CVController@extractText'],
  ['POST', '/api/cv/process', 'CVController@process'],

  // === DepartmentController ===
  ['GET', '/api/departments', 'DepartmentController@index'],
  ['POST', '/api/departments', 'DepartmentController@store'],
  ['GET', '/api/departments/{id}', 'DepartmentController@show'],
  ['PUT', '/api/departments/{id}', 'DepartmentController@update'],
  ['DELETE', '/api/departments/{id}', 'DepartmentController@delete'],

  // === FileController ===
  ['GET', '/api/files/{path:.+}', 'FileController@serve'],
  ['DELETE', '/api/files/{path:.+}', 'FileController@delete'],

  // === HealthController & SystemController ===
  ['GET', '/api/health', 'HealthController@check'],
  ['GET', '/api/status', 'SystemController@status'],
  ['GET', '/api/version', 'SystemController@version'],

  // === InterviewController ===
  ['GET', '/api/interviews', 'InterviewController@index'],
  ['POST', '/api/interviews', 'InterviewController@store'],
  ['GET', '/api/interviews/{id}', 'InterviewController@show'],
  ['PUT', '/api/interviews/{id}', 'InterviewController@update'],
  ['DELETE', '/api/interviews/{id}', 'InterviewController@delete'],

  // === JobCategoryController ===
  ['GET', '/api/job-categories', 'JobCategoryController@index'],
  ['POST', '/api/job-categories', 'JobCategoryController@store'],
  ['GET', '/api/job-categories/{id}', 'JobCategoryController@show'],
  ['PUT', '/api/job-categories/{id}', 'JobCategoryController@update'],
  ['DELETE', '/api/job-categories/{id}', 'JobCategoryController@delete'],

  // === JobController ===
  ['GET', '/api/jobs', 'JobController@index'],
  ['POST', '/api/jobs', 'JobController@store'],
  ['GET', '/api/jobs/{id}', 'JobController@show'],
  ['PUT', '/api/jobs/{id}', 'JobController@update'],
  ['DELETE', '/api/jobs/{id}', 'JobController@delete'],
  ['GET', '/api/jobs/available', 'JobController@available'],
  ['POST', '/api/jobs/search', 'JobController@search'],
  ['GET', '/api/jobs/by-department/{id}', 'JobController@byDepartment'],

  // === LanguageController ===
  ['GET', '/api/language', 'LanguageController@getLanguage'],
  ['POST', '/api/language', 'LanguageController@setLanguage'],

  // === NewsController ===
  ['GET', '/api/news', 'NewsController@index'],
  ['POST', '/api/news', 'NewsController@store'],
  ['GET', '/api/news/{id}', 'NewsController@show'],
  ['PUT', '/api/news/{id}', 'NewsController@update'],
  ['DELETE', '/api/news/{id}', 'NewsController@delete'],

  // === NotificationController ===
  ['GET', '/api/notifications', 'NotificationController@index'],
  ['POST', '/api/notifications', 'NotificationController@store'],
  ['GET', '/api/notifications/{id}', 'NotificationController@show'],
  ['PUT', '/api/notifications/{id}', 'NotificationController@update'],
  ['PATCH', '/api/notifications/{id}/read', 'NotificationController@markAsRead'],
  ['DELETE', '/api/notifications/{id}', 'NotificationController@delete'],

  // === PDFController ===
  ['POST', '/api/pdf/parse', 'PDFController@parse'],

  // === SkillController ===
  ['GET', '/api/skills', 'SkillController@index'],
  ['POST', '/api/skills', 'SkillController@store'],
  ['GET', '/api/skills/{id}', 'SkillController@show'],
  ['PUT', '/api/skills/{id}', 'SkillController@update'],
  ['DELETE', '/api/skills/{id}', 'SkillController@delete'],
  ['POST', '/api/skills/extract', 'SkillController@extract'],

  // === RecruiterController ===
  ['GET', '/api/recruiters', 'RecruiterController@index'],
  ['POST', '/api/recruiters', 'RecruiterController@store'],
  ['GET', '/api/recruiters/{id}', 'RecruiterController@show'],
  ['PUT', '/api/recruiters/{id}', 'RecruiterController@update'],
  ['DELETE', '/api/recruiters/{id}', 'RecruiterController@delete'],

  // === TestController ===
  ['GET', '/api/test', 'TestController@test'],

  // === UploadController ===
  ['POST', '/api/upload', 'UploadController@upload'],

  // === UserController ===
  ['GET', '/api/users', 'UserController@index'],
  ['POST', '/api/users', 'UserController@store'],
  ['GET', '/api/users/{id}', 'UserController@show'],
  ['PUT', '/api/users/{id}', 'UserController@update'],
  ['DELETE', '/api/users/{id}', 'UserController@delete'],
];
