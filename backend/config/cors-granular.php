<?php

/**
 * Configuración granular de CORS por tipo de endpoint
 * 
 * Define configuraciones específicas de CORS para diferentes tipos de endpoints
 * basándose en patrones de URL y funcionalidad.
 * 
 * @author Bubble of Talents Security Team
 * @version 1.0.0
 */

declare(strict_types=1);

/**
 * Configuraciones CORS granulares por tipo de endpoint
 */
return [
  // 🔐 Endpoints de autenticación - Más restrictivos
  'auth' => [
    'pattern' => '/auth/',
    'methods' => ['GET', 'POST', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization'],
    'credentials' => true,
    'max_age' => 300, // 5 minutos - más corto por seguridad
    'description' => 'Endpoints de autenticación y sesiones'
  ],

  // 📊 APIs de datos - Configuración estándar
  'api_data' => [
    'pattern' => '/api/(candidate-|jobs|language)',
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'credentials' => true,
    'max_age' => 600, // 10 minutos
    'description' => 'APIs de datos de candidatos y trabajos'
  ],

  // 🎯 APIs endpoints - Configuración específica para /api/endpoints/
  'api_endpoints' => [
    'pattern' => '/api/endpoints/',
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'credentials' => true,
    'max_age' => 600, // 10 minutos
    'description' => 'APIs específicas en directorio endpoints'
  ],

  // 🔧 APIs administrativas - Más restrictivos
  'api_admin' => [
    'pattern' => '/api/(admin|staff|manage)',
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-Admin-Token'],
    'credentials' => true,
    'max_age' => 180, // 3 minutos - muy corto para admin
    'description' => 'APIs administrativas y de gestión'
  ],

  // 📁 Endpoints de archivos - Solo lectura principalmente
  'files' => [
    'pattern' => '/(uploads|cv|files)/',
    'methods' => ['GET', 'POST', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization'],
    'credentials' => true,
    'max_age' => 3600, // 1 hora - archivos cambian menos
    'description' => 'Endpoints de gestión de archivos'
  ],

  // 🤖 APIs de IA y procesamiento - Permisivos para desarrollo
  'ai' => [
    'pattern' => '/(ai|chatbot|analyze|parse)/',
    'methods' => ['GET', 'POST', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-AI-Model'],
    'credentials' => false, // IA puede no necesitar credenciales
    'max_age' => 1800, // 30 minutos
    'description' => 'APIs de inteligencia artificial y análisis'
  ],

  // 🔍 APIs públicas - Menos restrictivos
  'public' => [
    'pattern' => '/(health|status|info|test)/',
    'methods' => ['GET', 'OPTIONS'],
    'headers' => ['Content-Type'],
    'credentials' => false,
    'max_age' => 7200, // 2 horas - info pública cambia poco
    'description' => 'APIs públicas y de estado'
  ],

  // 🎯 Configuración por defecto - Para endpoints no clasificados
  'default' => [
    'pattern' => '.*', // Coincide con todo
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-Token'],
    'credentials' => true,
    'max_age' => 600, // 10 minutos
    'description' => 'Configuración por defecto para endpoints no clasificados'
  ]
];
