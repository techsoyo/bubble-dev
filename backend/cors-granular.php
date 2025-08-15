<?php

/**
 * Configuraciones granulares de CORS por tipo de endpoint
 * 
 * Define configuraciones específicas de CORS para diferentes
 * categorías de endpoints basadas en patrones de URL.
 * 
 * @author Bubble of Talents Security Team
 * @version 1.0.0
 */

declare(strict_types=1);

/**
 * Configuraciones granulares de CORS por tipo de endpoint
 */
return [
  'auth' => [
    'patterns' => ['/auth/', '/login', '/logout', '/verify'],
    'allowed_origins' => [
      'http://localhost:3000',
      'http://localhost:3002',
      'http://127.0.0.1:3000',
      'http://127.0.0.1:3002'
    ],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization'],
    'max_age' => 300,
    'allow_credentials' => true,
    'security_level' => 'high',
    'description' => 'Endpoints de autenticación - Máxima seguridad'
  ],

  'api_data' => [
    'patterns' => ['/api/candidate', '/api/language', '/api/jobs', '/api/save-', '/api/endpoints/'],
    'allowed_origins' => [
      'http://localhost:3000',
      'http://localhost:3002',
      'http://127.0.0.1:3000',
      'http://127.0.0.1:3002'
    ],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => [
      'Content-Type',
      'Authorization',
      'X-Requested-With',
      'X-CSRF-Token'
    ],
    'max_age' => 600,
    'allow_credentials' => true,
    'security_level' => 'medium',
    'description' => 'APIs de datos - CRUD completo'
  ],

  'api_admin' => [
    'patterns' => ['/api/admin', '/admin/', '/dashboard/'],
    'allowed_origins' => [
      'http://localhost:3001',
      'http://127.0.0.1:3001'
    ],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_headers' => [
      'Content-Type',
      'Authorization',
      'X-Admin-Token',
      'X-CSRF-Token'
    ],
    'max_age' => 300,
    'allow_credentials' => true,
    'security_level' => 'high',
    'description' => 'APIs administrativas - Solo admin panel'
  ],

  'files' => [
    'patterns' => ['/uploads/', '/files/', '/documents/', '/images/'],
    'allowed_origins' => ['*'],
    'allowed_methods' => ['GET', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Range'],
    'max_age' => 3600,
    'allow_credentials' => false,
    'security_level' => 'low',
    'description' => 'Archivos estáticos - Acceso público'
  ],

  'ai' => [
    'patterns' => ['/api/chatbot', '/api/ai/', '/ai/'],
    'allowed_origins' => [
      'http://localhost:3000',
      'http://localhost:3002',
      'http://127.0.0.1:3000',
      'http://127.0.0.1:3002'
    ],
    'allowed_methods' => ['POST', 'OPTIONS'],
    'allowed_headers' => [
      'Content-Type',
      'Authorization',
      'X-AI-Session',
      'X-Conversation-ID'
    ],
    'max_age' => 300,
    'allow_credentials' => true,
    'security_level' => 'medium',
    'description' => 'Endpoints de IA - Solo POST'
  ],

  'public' => [
    'patterns' => ['/api/health', '/api/test', '/public/', '/status'],
    'allowed_origins' => ['*'],
    'allowed_methods' => ['GET', 'OPTIONS'],
    'allowed_headers' => ['Content-Type'],
    'max_age' => 1800,
    'allow_credentials' => false,
    'security_level' => 'low',
    'description' => 'Endpoints públicos - Solo lectura'
  ],

  'default' => [
    'patterns' => ['/*'],
    'allowed_origins' => [
      'http://localhost:3000',
      'http://127.0.0.1:3000'
    ],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization'],
    'max_age' => 600,
    'allow_credentials' => true,
    'security_level' => 'medium',
    'description' => 'Configuración por defecto'
  ]
];
